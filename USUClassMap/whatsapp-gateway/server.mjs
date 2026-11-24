import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import express from 'express';
import pino from 'pino';
import QRCode from 'qrcode';
import makeWASocket, {
  Browsers,
  DisconnectReason,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
} from '@whiskeysockets/baileys';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const PORT = Number(process.env.PORT || 3000);
const API_KEY = String(process.env.WHATSAPP_API_KEY || 'usuclassmap-waha-2026');
const SESSION_NAME = String(process.env.WHATSAPP_SESSION || 'default');
const SESSIONS_DIR = path.resolve(__dirname, process.env.SESSIONS_DIR || '.sessions');
const SENDER_DISPLAY = String(process.env.WHATSAPP_SENDER_PHONE_DISPLAY || '+62 812-8357-5656');
const ENGINE_NAME = 'BAILEYS-NOWEB-NATIVE';

const state = {
  status: 'STARTING',
  latestQr: null,
  me: null,
  lastError: null,
  updatedAt: new Date().toISOString(),
};

let sock = null;
let startPromise = null;
let reconnectTimer = null;

function setStatus(nextStatus, extra = {}) {
  state.status = nextStatus;
  if ('latestQr' in extra) state.latestQr = extra.latestQr;
  if ('me' in extra) state.me = extra.me;
  if ('lastError' in extra) state.lastError = extra.lastError;
  state.updatedAt = new Date().toISOString();
}

function sessionDirPath() {
  return path.join(SESSIONS_DIR, SESSION_NAME);
}

function extractMeFromUser(user) {
  if (!user || !user.id) return null;
  const raw = String(user.id);
  const digits = raw.split('@')[0].split(':')[0].replace(/[^\d]/g, '');
  if (!digits) return null;
  return {
    id: `${digits}@c.us`,
    pushName: user.name || user.verifiedName || 'Bot Pengirim USUClassMap',
  };
}

function toWhatsAppJid(chatIdOrPhone) {
  const str = String(chatIdOrPhone || '').trim();
  if (str.endsWith('@g.us') || str.endsWith('@s.whatsapp.net')) {
    return str;
  }
  const digits = str.split('@')[0].replace(/[^\d]/g, '');
  return `${digits}@s.whatsapp.net`;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function sessionPayload() {
  return {
    name: SESSION_NAME,
    status: state.status,
    me: state.me,
    engine: ENGINE_NAME,
    senderTarget: SENDER_DISPLAY,
    lastError: state.lastError,
    updatedAt: state.updatedAt,
  };
}

async function cleanupSocket() {
  if (reconnectTimer) {
    clearTimeout(reconnectTimer);
    reconnectTimer = null;
  }
  if (sock) {
    try {
      sock.ev.removeAllListeners();
      sock.end(undefined);
    } catch {
      // ignore socket teardown error
    }
    sock = null;
  }
}

async function startSession({ clearAuth = false } = {}) {
  if (startPromise) {
    return startPromise;
  }

  startPromise = (async () => {
    await cleanupSocket();

    const authDir = sessionDirPath();
    if (clearAuth && fs.existsSync(authDir)) {
      fs.rmSync(authDir, { recursive: true, force: true });
    }
    fs.mkdirSync(authDir, { recursive: true });

    setStatus('STARTING', { latestQr: null, me: null, lastError: null });

    const { state: authState, saveCreds } = await useMultiFileAuthState(authDir);
    let version;
    try {
      const latest = await fetchLatestBaileysVersion();
      version = latest.version;
    } catch {
      version = undefined;
    }

    const createSocket = typeof makeWASocket === 'function' ? makeWASocket : makeWASocket.default;
    const logger = pino({ level: 'silent' });

    const client = createSocket({
      version,
      auth: authState,
      logger,
      printQRInTerminal: false,
      browser: Browsers.ubuntu('Chrome'),
      syncFullHistory: false,
      markOnlineOnConnect: false,
    });

    sock = client;

    client.ev.on('creds.update', saveCreds);

    client.ev.on('connection.update', (update) => {
      if (sock !== client) return;

      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        setStatus('SCAN_QR_CODE', { latestQr: qr, me: null, lastError: null });
      }

      if (connection === 'open') {
        setStatus('WORKING', {
          latestQr: null,
          me: extractMeFromUser(client.user),
          lastError: null,
        });
      }

      if (connection === 'close') {
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const message = lastDisconnect?.error?.message || 'Koneksi terputus.';

        if (statusCode === DisconnectReason.loggedOut) {
          setStatus('STOPPED', {
            latestQr: null,
            me: null,
            lastError: 'Sesi WhatsApp logout dari HP. Klik Mulai Ulang Sesi untuk memunculkan QR baru.',
          });
          sock = null;
          return;
        }

        setStatus('STARTING', {
          latestQr: null,
          me: null,
          lastError: message,
        });

        reconnectTimer = setTimeout(() => {
          startSession().catch((err) => {
            setStatus('STOPPED', { lastError: err?.message || 'Gagal reconnect.' });
          });
        }, 2500);
      }
    });
  })()
    .catch((err) => {
      setStatus('STOPPED', {
        latestQr: null,
        me: null,
        lastError: err?.message || 'Gagal menginisialisasi Baileys socket.',
      });
    })
    .finally(() => {
      startPromise = null;
    });

  return startPromise;
}

const app = express();
app.use(express.json({ limit: '50mb' }));

function requireApiKey(req, res, next) {
  const provided = String(req.header('X-Api-Key') || '');
  if (API_KEY !== '' && provided !== API_KEY) {
    return res.status(401).json({ error: 'Unauthorized: invalid X-Api-Key header.' });
  }
  return next();
}

app.get('/', (_req, res) => {
  res.redirect('/dashboard');
});

app.get('/dashboard/status', (_req, res) => {
  res.json(sessionPayload());
});

app.get('/dashboard/qr.png', async (_req, res) => {
  if (!state.latestQr) {
    return res.status(404).json({ error: 'QR belum tersedia pada status ' + state.status });
  }
  try {
    const pngBuffer = await QRCode.toBuffer(state.latestQr, {
      type: 'png',
      width: 340,
      margin: 2,
      color: { dark: '#0f172a', light: '#ffffff' },
    });
    res.set('Cache-Control', 'no-store');
    res.type('image/png').send(pngBuffer);
  } catch (err) {
    res.status(500).json({ error: err?.message || 'Gagal merender QR PNG.' });
  }
});

app.post('/dashboard/restart', async (req, res) => {
  const clearAuth = Boolean(req.query.fresh === '1' || state.status === 'STOPPED');
  await startSession({ clearAuth });
  res.json(sessionPayload());
});

app.get('/dashboard', (_req, res) => {
  const safeSender = escapeHtml(SENDER_DISPLAY);
  const safeSession = escapeHtml(SESSION_NAME);
  const safeStatus = escapeHtml(state.status);
  const html = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>USUClassMap WAHA Gateway Dashboard</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f1f5f9; color: #0f172a; }
    .wrap { max-width: 780px; margin: 36px auto; padding: 0 16px; }
    .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 6px 24px rgba(15, 23, 42, .06); }
    .head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px; }
    .title { margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; }
    .sub { margin: 4px 0 0; font-size: 13px; color: #475569; }
    .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .badge-warn { background: #fef3c7; color: #92400e; }
    .badge-ok { background: #dcfce7; color: #166534; }
    .badge-err { background: #fee2e2; color: #991b1b; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; align-items: start; }
    .qr-box { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 310px; border: 2px dashed #cbd5e1; border-radius: 14px; padding: 16px; background: #f8fafc; text-align: center; }
    .qr-box img { max-width: 260px; width: 100%; height: auto; border-radius: 8px; background: #fff; padding: 8px; border: 1px solid #e2e8f0; }
    .info-list { margin: 0; padding: 0; list-style: none; font-size: 13px; }
    .info-list li { padding: 8px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; gap: 8px; }
    .info-list span { color: #64748b; }
    .steps { margin: 16px 0 0; padding-left: 18px; font-size: 13px; color: #334155; line-height: 1.6; }
    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .btn { border: 0; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
    .btn-primary { background: #0f766e; color: #fff; }
    .btn-secondary { background: #e2e8f0; color: #0f172a; }
    .note { margin-top: 12px; font-size: 12px; color: #64748b; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="head">
        <div>
          <h1 class="title">USUClassMap WhatsApp Gateway (WAHA-Compatible)</h1>
          <p class="sub">Bot Pengirim Laptop: <strong>${safeSender}</strong> &middot; Session: <code>${safeSession}</code> &middot; Engine: <code>${ENGINE_NAME}</code></p>
        </div>
        <span id="statusBadge" class="badge badge-warn">${safeStatus}</span>
      </div>

      <div class="grid">
        <div class="qr-box" id="qrContainer">
          <p style="font-size:13px;color:#475569;">Memuat status sesi WhatsApp...</p>
        </div>

        <div>
          <ul class="info-list">
            <li><span>Nomor Bot Target</span><strong>${safeSender}</strong></li>
            <li><span>Status Sesi</span><strong id="statusText">${safeStatus}</strong></li>
            <li><span>Nomor Terhubung</span><strong id="connectedPhone">-</strong></li>
            <li><span>Pembaruan Terakhir</span><strong id="updatedAt">-</strong></li>
          </ul>

          <ol class="steps">
            <li>Buka aplikasi <strong>WhatsApp</strong> di HP pengirim <strong>${safeSender}</strong>.</li>
            <li>Masuk ke menu <strong>Perangkat Tertaut (Linked Devices)</strong> &rarr; <strong>Tautkan Perangkat</strong>.</li>
            <li>Scan QR Code di samping. Setelah status menjadi <code>WORKING</code>, seluruh order terbayar akan mengirim ringkasan, semua file PDF, dan Invoice PDF secara otomatis.</li>
          </ol>

          <div class="actions">
            <button type="button" class="btn btn-primary" id="btnRestart">Mulai Ulang Sesi / Refresh QR</button>
            <button type="button" class="btn btn-secondary" id="btnFresh">Reset Pairing (Scan Baru)</button>
          </div>
          <p class="note" id="errorNote"></p>
        </div>
      </div>
    </div>
  </div>
  <script>
    (function () {
      const badgeEl = document.getElementById('statusBadge');
      const statusTextEl = document.getElementById('statusText');
      const phoneEl = document.getElementById('connectedPhone');
      const updatedEl = document.getElementById('updatedAt');
      const qrContainer = document.getElementById('qrContainer');
      const errorNote = document.getElementById('errorNote');

      function applyStatus(data) {
        const st = String(data.status || 'UNKNOWN');
        statusTextEl.textContent = st;
        badgeEl.textContent = st;
        badgeEl.className = 'badge ' + (st === 'WORKING' ? 'badge-ok' : (st === 'SCAN_QR_CODE' || st === 'STARTING' ? 'badge-warn' : 'badge-err'));
        phoneEl.textContent = (data.me && data.me.id) ? data.me.id : 'Belum tertaut';
        updatedEl.textContent = data.updatedAt ? new Date(data.updatedAt).toLocaleTimeString('id-ID') : '-';
        errorNote.textContent = data.lastError ? ('Info terakhir: ' + data.lastError) : '';

        if (st === 'WORKING') {
          qrContainer.innerHTML = '<div style="padding:16px;"><div style="font-size:36px;">&#9989;</div><h2 style="margin:8px 0 4px;font-size:17px;color:#166534;">WhatsApp Terhubung (WORKING)</h2><p style="margin:0;font-size:13px;color:#475569;">Gateway siap mengirim teks &amp; file PDF otomatis.</p></div>';
        } else if (st === 'SCAN_QR_CODE') {
          const ts = Date.now();
          qrContainer.innerHTML = '<img src="/dashboard/qr.png?t=' + ts + '" alt="Scan QR WhatsApp Bot USUClassMap" /><p style="margin:10px 0 0;font-size:12px;color:#475569;">QR otomatis diperbarui setiap 4 detik.</p>';
        } else if (st === 'STARTING') {
          qrContainer.innerHTML = '<p style="font-size:13px;color:#92400e;">Menginisialisasi handshake WhatsApp WebSocket...</p>';
        } else {
          qrContainer.innerHTML = '<p style="font-size:13px;color:#991b1b;">Sesi berhenti (' + st + '). Klik tombol Mulai Ulang Sesi.</p>';
        }
      }

      async function pollStatus() {
        try {
          const resp = await fetch('/dashboard/status', { cache: 'no-store' });
          if (resp.ok) {
            applyStatus(await resp.json());
          }
        } catch (err) {
          errorNote.textContent = 'Gagal membaca status lokal: ' + err.message;
        }
      }

      async function restartSession(fresh) {
        qrContainer.innerHTML = '<p style="font-size:13px;color:#475569;">Memulai ulang sesi...</p>';
        await fetch('/dashboard/restart' + (fresh ? '?fresh=1' : ''), { method: 'POST' });
        setTimeout(pollStatus, 1500);
      }

      document.getElementById('btnRestart').addEventListener('click', () => restartSession(false));
      document.getElementById('btnFresh').addEventListener('click', () => restartSession(true));

      pollStatus();
      setInterval(pollStatus, 4000);
    })();
  </script>
</body>
</html>`;
  res.type('text/html; charset=utf-8').send(html);
});

app.get('/api/sessions', requireApiKey, (_req, res) => {
  res.json([sessionPayload()]);
});

app.get('/api/sessions/:session', requireApiKey, (req, res) => {
  if (req.params.session !== SESSION_NAME) {
    return res.status(404).json({ error: `Session "${req.params.session}" not found.` });
  }
  return res.json(sessionPayload());
});

app.post('/api/sessions/start', requireApiKey, async (req, res) => {
  const requested = String(req.body?.name || SESSION_NAME);
  if (requested !== SESSION_NAME) {
    return res.status(404).json({ error: `Session "${requested}" not supported; expected "${SESSION_NAME}".` });
  }
  await startSession({ clearAuth: state.status === 'STOPPED' });
  return res.json(sessionPayload());
});

app.get('/api/:session/auth/qr', requireApiKey, async (req, res) => {
  if (req.params.session !== SESSION_NAME) {
    return res.status(404).json({ error: `Session "${req.params.session}" not found.` });
  }
  if (state.status !== 'SCAN_QR_CODE' || !state.latestQr) {
    return res.status(404).json({ error: `QR not available; current status is ${state.status}` });
  }
  if (req.query.format === 'raw') {
    return res.json({ value: state.latestQr });
  }
  const pngBuffer = await QRCode.toBuffer(state.latestQr, {
    type: 'png',
    width: 360,
    margin: 2,
  });
  res.set('Cache-Control', 'no-store');
  return res.type('image/png').send(pngBuffer);
});

app.post('/api/sendText', requireApiKey, async (req, res) => {
  const { session, chatId, text } = req.body || {};
  if (session && session !== SESSION_NAME) {
    return res.status(404).json({ error: `Session "${session}" not found.` });
  }
  if (state.status !== 'WORKING' || !sock) {
    return res.status(422).json({ error: `Session status is ${state.status}` });
  }
  if (!chatId || !text) {
    return res.status(422).json({ error: 'chatId and text are required.' });
  }
  try {
    const jid = toWhatsAppJid(chatId);
    const sent = await sock.sendMessage(jid, { text: String(text) });
    return res.json({
      id: sent?.key?.id || null,
      key: sent?.key || null,
    });
  } catch (err) {
    return res.status(500).json({ error: err?.message || 'Failed to send text via Baileys.' });
  }
});

app.post('/api/sendFile', requireApiKey, async (req, res) => {
  const { session, chatId, file, caption } = req.body || {};
  if (session && session !== SESSION_NAME) {
    return res.status(404).json({ error: `Session "${session}" not found.` });
  }
  if (state.status !== 'WORKING' || !sock) {
    return res.status(422).json({ error: `Session status is ${state.status}` });
  }
  if (!chatId || !file?.data || !file?.filename) {
    return res.status(422).json({ error: 'chatId, file.filename, and base64 file.data are required.' });
  }
  try {
    const jid = toWhatsAppJid(chatId);
    const buffer = Buffer.from(String(file.data), 'base64');
    const sent = await sock.sendMessage(jid, {
      document: buffer,
      mimetype: file.mimetype || 'application/pdf',
      fileName: String(file.filename),
      caption: caption ? String(caption) : '',
    });
    return res.json({
      id: sent?.key?.id || null,
      key: sent?.key || null,
    });
  } catch (err) {
    return res.status(500).json({ error: err?.message || 'Failed to send document via Baileys.' });
  }
});

app.listen(PORT, '127.0.0.1', () => {
  console.log(`[USUClassMap WA Gateway] listening on http://127.0.0.1:${PORT} (sender ${SENDER_DISPLAY}, session ${SESSION_NAME})`);
  startSession().catch((err) => {
    console.error('[USUClassMap WA Gateway] initial session error:', err);
  });
});
