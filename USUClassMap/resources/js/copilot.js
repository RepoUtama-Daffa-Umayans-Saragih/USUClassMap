/**
 * Rexa Copilot — Logic Controller & State Management (Alpine.js)
 * Fase 3: Integrasi Frontend, Parsing [MAP_ACTION], dan Mesin Fallback Offline.
 */

window.usuCopilot = function () {
    return {
        // State UI
        isOpen: false,
        isLoading: false,
        isOffline: false,
        inputMessage: '',

        // State Dimensi & Resizing (Desktop)
        chatWidth: 400,
        chatHeight: 560,
        isResizing: false,
        isExpanded: false,
        isDesktop: typeof window !== 'undefined' ? window.innerWidth >= 768 : true,
        preExpandWidth: 400,
        preExpandHeight: 560,

        // Saran Cepat (Quick Chips)
        quickPrompts: [
            'Cari pulpen pink murah terdekat',
            'Kelas saya di depan FIB samping FMIPA',
            'Jadwal Pancasila hari Senin',
            'Dimana Gedung Fasilkom-TI?',
        ],

        // Daftar Pesan Chat
        messages: [],

        /**
         * Inisialisasi komponen: Muat riwayat dari sessionStorage atau tampilkan sambutan awal.
         */
        init() {
            this.loadHistory();
            this.loadSavedSize();

            // Dengarkan event resize window untuk update breakpoint dan clamp dimensi
            window.addEventListener('resize', () => {
                this.isDesktop = window.innerWidth >= 768;
                if (this.isDesktop) {
                    const maxWidth = Math.min(760, window.innerWidth - 32);
                    const maxHeight = Math.min(820, window.innerHeight - 96);
                    if (this.chatWidth > maxWidth) this.chatWidth = maxWidth;
                    if (this.chatHeight > maxHeight) this.chatHeight = maxHeight;
                }
            });

            // Dengarkan event eksternal jika ada tombol lain di halaman yang ingin membuka chatbot
            window.addEventListener('open-copilot', (e) => {
                this.isOpen = true;
                if (e.detail && e.detail.prompt) {
                    this.inputMessage = e.detail.prompt;
                    this.sendMessage();
                } else {
                    this.$nextTick(() => this.scrollToBottom());
                }
            });
        },

        /**
         * Buka atau perkecil jendela chat.
         */
        toggleChat() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        /**
         * Memuat preferensi ukuran chat dari localStorage
         */
        loadSavedSize() {
            try {
                const saved = localStorage.getItem('usuclassmap_copilot_size');
                if (saved) {
                    const parsed = JSON.parse(saved);
                    const maxWidth = Math.min(760, window.innerWidth - 32);
                    const maxHeight = Math.min(820, window.innerHeight - 96);
                    if (parsed.width) {
                        this.chatWidth = Math.min(maxWidth, Math.max(320, parseInt(parsed.width, 10)));
                    }
                    if (parsed.height) {
                        this.chatHeight = Math.min(maxHeight, Math.max(380, parseInt(parsed.height, 10)));
                    }
                    this.isExpanded = !!parsed.isExpanded;
                }
            } catch (e) {
                // fallback to default
            }
        },

        /**
         * Menyimpan ukuran chat ke localStorage
         */
        saveSize() {
            try {
                localStorage.setItem('usuclassmap_copilot_size', JSON.stringify({
                    width: this.chatWidth,
                    height: this.chatHeight,
                    isExpanded: this.isExpanded,
                }));
            } catch (e) {
                console.warn('[Rexa Copilot] Gagal menyimpan ukuran chat:', e);
            }
        },

        /**
         * Mulai geser ukuran jendela chat (Left, Top, atau Corner)
         */
        startResize(type, event) {
            if (!this.isDesktop) return;
            event.preventDefault();
            event.stopPropagation();

            this.isResizing = true;
            const startX = event.clientX;
            const startY = event.clientY;
            const startW = this.chatWidth;
            const startH = this.chatHeight;

            document.body.classList.add('select-none');

            const onPointerMove = (e) => {
                if (!this.isResizing) return;

                const maxWidth = Math.min(760, window.innerWidth - 32);
                const maxHeight = Math.min(820, window.innerHeight - 96);

                if (type === 'left' || type === 'corner') {
                    const deltaX = startX - e.clientX;
                    this.chatWidth = Math.round(Math.min(maxWidth, Math.max(320, startW + deltaX)));
                }

                if (type === 'top' || type === 'corner') {
                    const deltaY = startY - e.clientY;
                    this.chatHeight = Math.round(Math.min(maxHeight, Math.max(380, startH + deltaY)));
                }

                if (this.isExpanded && (this.chatWidth !== 560 || this.chatHeight !== 680)) {
                    this.isExpanded = false;
                }
            };

            const onPointerUp = () => {
                this.isResizing = false;
                document.body.classList.remove('select-none');
                window.removeEventListener('pointermove', onPointerMove);
                window.removeEventListener('pointerup', onPointerUp);
                this.saveSize();
            };

            window.addEventListener('pointermove', onPointerMove, { passive: true });
            window.addEventListener('pointerup', onPointerUp, { once: true });
        },

        /**
         * Toggle ukuran cepat antara normal dan mode lebar
         */
        toggleExpand() {
            if (!this.isDesktop) return;
            const maxW = Math.min(760, window.innerWidth - 32);
            const maxH = Math.min(820, window.innerHeight - 96);

            if (!this.isExpanded) {
                this.preExpandWidth = this.chatWidth;
                this.preExpandHeight = this.chatHeight;
                this.chatWidth = Math.min(560, maxW);
                this.chatHeight = Math.min(680, maxH);
                this.isExpanded = true;
            } else {
                this.chatWidth = Math.min(this.preExpandWidth || 400, maxW);
                this.chatHeight = Math.min(this.preExpandHeight || 560, maxH);
                this.isExpanded = false;
            }
            this.saveSize();
            this.$nextTick(() => this.scrollToBottom());
        },

        /**
         * Reset ukuran ke default (400x560px)
         */
        resetSize() {
            if (!this.isDesktop) return;
            this.chatWidth = 400;
            this.chatHeight = 560;
            this.isExpanded = false;
            this.saveSize();
            this.$nextTick(() => this.scrollToBottom());
        },

        /**
         * Kirim prompt dari chip saran cepat.
         */
        sendQuickPrompt(promptText) {
            this.inputMessage = promptText;
            this.sendMessage();
        },

        /**
         * Format jam pesan saat ini (misal: "14:05").
         */
        getCurrentTime() {
            const now = new Date();
            return now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        },

        /**
         * Memuat riwayat chat dari sessionStorage.
         */
        loadHistory() {
            try {
                const saved = sessionStorage.getItem('usuclassmap_copilot_messages');
                if (saved) {
                    this.messages = JSON.parse(saved);
                }
            } catch (e) {
                this.messages = [];
            }

            // Jika belum ada riwayat, tambahkan pesan sambutan default dari Rexa
            if (this.messages.length === 0) {
                this.messages.push({
                    id: 'welcome-' + Date.now(),
                    role: 'assistant',
                    content: 'Halo! Saya **Rexa Kampus**, asisten AI cerdas USUClassMap. Ada jadwal kuliah, ruangan, atau lokasi gedung di kampus USU yang ingin kamu cari hari ini?',
                    time: this.getCurrentTime(),
                    mapAction: null,
                });
            }
        },

        /**
         * Menyimpan riwayat chat ke sessionStorage.
         */
        saveHistory() {
            try {
                // Simpan maksimal 20 pesan terakhir agar efisien
                const trimmed = this.messages.slice(-20);
                sessionStorage.setItem('usuclassmap_copilot_messages', JSON.stringify(trimmed));
            } catch (e) {
                console.warn('[Rexa Copilot] Gagal menyimpan riwayat chat:', e);
            }
        },

        /**
         * Bersihkan riwayat chat dan kembalikan ke pesan sambutan awal.
         */
        clearChat() {
            sessionStorage.removeItem('usuclassmap_copilot_messages');
            this.messages = [
                {
                    id: 'welcome-' + Date.now(),
                    role: 'assistant',
                    content: 'Riwayat percakapan telah dibersihkan. Silakan tanyakan hal baru seputar gedung dan jadwal kuliah USU!',
                    time: this.getCurrentTime(),
                    mapAction: null,
                },
            ];
            this.isOffline = false;
        },

        /**
         * Fungsi Utama: Kirim Pesan ke Backend Laravel (/copilot/chat)
         */
        async sendMessage() {
            const rawText = this.inputMessage.trim();
            if (!rawText || this.isLoading) return;

            // 1. Tambahkan pesan user ke daftar chat
            const userMsg = {
                id: 'user-' + Date.now(),
                role: 'user',
                content: rawText,
                time: this.getCurrentTime(),
            };
            this.messages.push(userMsg);
            this.inputMessage = '';
            this.isLoading = true;
            this.$nextTick(() => this.scrollToBottom());

            // 2. Siapkan riwayat percakapan untuk payload API (maksimal 6 pesan terakhir)
            const apiHistory = this.messages
                .slice(-7, -1)
                .map((m) => ({
                    role: m.role,
                    content: String(m.content || '').slice(0, 1200),
                }));

            // Ambil koordinat GPS terakhir jika tersedia dari peta atau sessionStorage
            let gpsLat = null;
            let gpsLng = null;
            try {
                const liveFix = window.USUClassMap?.getLastGpsFix?.();
                if (liveFix && Number.isFinite(Number(liveFix.lat)) && Number.isFinite(Number(liveFix.lng))) {
                    gpsLat = Number(liveFix.lat);
                    gpsLng = Number(liveFix.lng);
                } else {
                    const cachedFixRaw = sessionStorage.getItem('usu_last_gps_fix');
                    if (cachedFixRaw) {
                        const cachedFix = JSON.parse(cachedFixRaw);
                        if (cachedFix && Number.isFinite(Number(cachedFix.lat)) && Number.isFinite(Number(cachedFix.lng))) {
                            gpsLat = Number(cachedFix.lat);
                            gpsLng = Number(cachedFix.lng);
                        }
                    }
                }
            } catch (e) {}

            try {
                // 3. Panggil Endpoint Backend Laravel Grounded Copilot
                const response = await window.axios.post('/copilot/chat', {
                    message: rawText,
                    history: apiHistory,
                    user_lat: gpsLat,
                    user_lng: gpsLng,
                    current_page: typeof window !== 'undefined' ? window.location.pathname : '/',
                });

                if (response.data && response.data.status === 'success') {
                    let replyText = response.data.message || response.data.reply || '';
                    let mapAction = response.data.mapAction || response.data.map_action || null;
                    const facultySummary = response.data.facultySummary || response.data.faculty_summary || [];
                    const quickReplies = response.data.quickReplies || response.data.quick_replies || [];
                    const mapActions = response.data.mapActions || response.data.map_actions || [];
                    const schedules = response.data.schedules || [];
                    const cards = response.data.cards || [];
                    const actions = response.data.actions || [];
                    const advisoryNote = response.data.advisory_note || null;
                    const intent = response.data.intent || 'general_campus_qa';

                    // Parsing fallback jika tag [MAP_ACTION: {...}] masih tersisa di dalam teks
                    const actionRegex = /\[MAP_ACTION:\s*(\{.*?\})\]/s;
                    const match = replyText.match(actionRegex);
                    if (match) {
                        try {
                            mapAction = JSON.parse(match[1]);
                        } catch (err) {
                            console.warn('[Rexa Copilot] Gagal parse JSON dari tag MAP_ACTION:', err);
                        }
                        replyText = replyText.replace(actionRegex, '').trim();
                    }

                    // Tambahkan balasan AI ke daftar pesan
                    this.messages.push({
                        id: 'bot-' + Date.now(),
                        role: 'assistant',
                        content: replyText,
                        time: this.getCurrentTime(),
                        intent: intent,
                        advisory_note: advisoryNote,
                        cards: cards,
                        actions: actions,
                        mapAction: mapAction,
                        facultySummary: facultySummary,
                        quickReplies: quickReplies,
                        mapActions: mapActions,
                        schedules: schedules,
                        showAllSchedules: false,
                        searchKeyword: rawText,
                    });

                    this.isOffline = false;

                    // Jika ada aksi focus peta (bukan fotocopy auto-redirect), picu peta otomatis di layar lebar
                    if (mapAction && mapAction.type !== 'start_route' && window.innerWidth >= 1024 && window.USUClassMap) {
                        this.triggerMapAction(mapAction, false);
                    }
                } else {
                    throw new Error(response.data?.message || 'Gagal memproses respon AI');
                }
            } catch (error) {
                console.warn('[Rexa Copilot] Terjadi kesalahan koneksi backend. Beralih ke Mesin Offline:', error);
                this.isOffline = true;

                // 4. Eksekusi Engine Offline (Regex Fallback) jika server AI tidak merespon
                const fallbackResponse = this.prosesOffline(rawText);

                this.messages.push({
                    id: 'bot-fallback-' + Date.now(),
                    role: 'assistant',
                    content: fallbackResponse.text,
                    time: this.getCurrentTime(),
                    mapAction: fallbackResponse.mapAction,
                });

                if (fallbackResponse.mapAction && window.innerWidth >= 1024) {
                    this.triggerMapAction(fallbackResponse.mapAction, false);
                }
            } finally {
                this.isLoading = false;
                this.saveHistory();
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        /**
         * Menghubungkan Aksi Chatbot dengan Peta Leaflet (window.USUClassMap)
         *
         * @param {Object} action Objek aksi koordinat peta
         * @param {Boolean} autoCloseMobile Apakah drawer ditutup di layar mobile
         */
        triggerMapAction(action, autoCloseMobile = true) {
            if (!action) return;

            if (action.type === 'start_route' && action.target_key) {
                if (window.USUClassMap && typeof window.USUClassMap.startRouteTo === 'function') {
                    window.USUClassMap.startRouteTo(action.target_key);
                    if (autoCloseMobile && window.innerWidth < 768) {
                        this.isOpen = false;
                    }
                } else {
                    window.location.href = '/map?route_to=' + encodeURIComponent(action.target_key);
                }
                return;
            }

            // Periksa ketersediaan API global USUClassMap (dari map.js)
            if (window.USUClassMap && typeof window.USUClassMap.focusBuildingWithTarget === 'function') {
                window.USUClassMap.focusBuildingWithTarget(
                    action.building_id || null,
                    action.building_name || null,
                    action.lat ? parseFloat(action.lat) : null,
                    action.lng ? parseFloat(action.lng) : null,
                    action.room_id || null,
                    action.room_name || null,
                    action.schedule_id || null,
                    action.course_code || null,
                    action.course_name || null
                );

                // Pada perangkat mobile, tutup widget agar peta terlihat leluasa
                if (autoCloseMobile && window.innerWidth < 768) {
                    this.isOpen = false;
                }
            } else if (action.building_id) {
                window.location.href = '/map?focus_building=' + encodeURIComponent(action.building_id);
            }
        },

        /**
         * Fokuskan kartu gedung (dari Smart Spatial Search) secara in-page di /map atau redirect ke /map?focus_building=...
         */
        focusCardBuilding(card) {
            const d = card?.data;
            if (!d) return;
            if (window.USUClassMap && typeof window.USUClassMap.focusBuildingWithTarget === 'function') {
                window.USUClassMap.focusBuildingWithTarget(
                    d.building_id || null,
                    d.name || null,
                    d.latitude ? parseFloat(d.latitude) : null,
                    d.longitude ? parseFloat(d.longitude) : null,
                    null, null, null, null, null
                );
                if (window.innerWidth < 768) {
                    this.isOpen = false;
                }
            } else if (d.actions?.focus_url) {
                window.location.href = d.actions.focus_url;
            }
        },

        /**
         * Mulai navigasi rute GPS in-app dari kartu hasil Smart Search (fotokopi atau gedung).
         */
        routeCardTarget(card) {
            const d = card?.data;
            const targetKey = d?.actions?.route_key;
            const routeUrl = d?.actions?.route_url;
            if (targetKey && window.USUClassMap && typeof window.USUClassMap.startRouteTo === 'function') {
                window.USUClassMap.startRouteTo(targetKey);
                if (window.innerWidth < 768) {
                    this.isOpen = false;
                }
            } else if (routeUrl) {
                window.location.href = routeUrl;
            }
        },

        /**
         * Penanganan aksi chip quick reply (query chat lanjutan atau filter sidebar peta)
         */
        handleQuickReply(item) {
            if (!item) return;

            if (item.sidebar_search) {
                if (window.USUClassMap && typeof window.USUClassMap.applyRecentSearch === 'function') {
                    window.USUClassMap.applyRecentSearch(item.sidebar_search);
                } else {
                    const searchInput = document.getElementById('map-search-input') || document.getElementById('search-input');
                    if (searchInput) {
                        searchInput.value = item.sidebar_search;
                        searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
                if (window.innerWidth < 768) {
                    this.isOpen = false;
                }
            } else if (item.query) {
                this.inputMessage = item.query;
                this.sendMessage();
            }
        },

        /**
         * Kirim query tindak lanjut dari kartu fakultas ke chat
         */
        askFollowUp(query) {
            if (!query) return;
            this.inputMessage = query;
            this.sendMessage();
        },

        /**
         * Mesin Pencarian Lokal / Offline (Regex Pattern Matching)
         * Digunakan sebagai pertahanan terakhir jika koneksi AWS Bedrock terputus atau timeout.
         *
         * @param {string} query Pertanyaan user
         * @return {Object} { text: string, mapAction: ?Object }
         */
        prosesOffline(query) {
            const cleanQuery = query.toLowerCase().trim();

            // 1. Guardrail Check (100% Read-Only)
            if (/(hapus|delete|tambah|insert|buat|update|ubah|ganti|drop|kprs|nilai)/i.test(cleanQuery)) {
                return {
                    text: '⚠️ **Mode Read-Only Aktif:**\nMaaf, Rexa adalah asisten pencarian informasi dengan izin baca saja. Segala bentuk perubahan, penambahan, atau penghapusan jadwal tidak dapat dilakukan melalui asisten ini.',
                    mapAction: null,
                };
            }

            // 2. Deteksi Gedung FASILKOM-TI
            if (/(fasilkom|ilmu komputer|teknologi informasi|fikti)/i.test(cleanQuery)) {
                return {
                    text: '📍 **Gedung Fasilkom-TI USU**\nFakultas Ilmu Komputer dan Teknologi Informasi berlokasi di area tengah kampus USU (dekat Jl. Alumni). Gedung ini menaungi ruang kuliah seperti C-102, C-103, D-104, dan D-106.',
                    mapAction: {
                        type: 'focus_building',
                        building_name: 'Gedung FASILKOM-TI',
                        lat: 3.565251,
                        lng: 98.657388,
                    },
                };
            }

            // 3. Deteksi Gedung FIB (Fakultas Ilmu Budaya)
            if (/(fib|ilmu budaya|sastra|gedung fib)/i.test(cleanQuery)) {
                return {
                    text: '📍 **Gedung FIB (Fakultas Ilmu Budaya) USU**\nGedung ini digunakan untuk perkuliahan rumpun bahasa, budaya, serta kelas MKWU (seperti Ruang H 104, H 105, dll).',
                    mapAction: {
                        type: 'focus_building',
                        building_name: 'Gedung FIB',
                        lat: 3.562145,
                        lng: 98.659124,
                    },
                };
            }

            // 4. Deteksi Pertanyaan Dosen
            if (/(dosen|rudy sofyan|rusdi|pengajar)/i.test(cleanQuery)) {
                return {
                    text: '👨‍🏫 **Informasi Dosen (Mode Offline):**\nUntuk melihat daftar lengkap jadwal mengajar dosen yang bersangkutan, Anda dapat mengetikkan nama beliau pada kolom pencarian di panel kiri peta.',
                    mapAction: null,
                };
            }

            // 5. Deteksi Jadwal Pribadi
            if (/(jadwal saya|kelasku|kuliahku)/i.test(cleanQuery)) {
                return {
                    text: '📅 **Jadwal Kuliah Anda:**\nPastikan Anda sudah login ke akun USUClassMap. Jadwal Anda akan otomatis ditandai dengan **highlight emas** pada daftar ruangan di peta.',
                    mapAction: null,
                };
            }

            // 6. Jawaban Default Offline
            return {
                text: '*(Mode Offline Aktif)*\nKoneksi ke otak AI AWS Bedrock sedang mengalami kendala atau offline. Namun, Anda tetap dapat menelusuri gedung dan jadwal kuliah secara langsung menggunakan fitur pencarian di sidebar kiri atau mengeklik pin pada peta.',
                mapAction: null,
            };
        },

        /**
         * Parser teks markdown (tebal, miring, list, dan tabel) menjadi HTML yang aman dan responsif.
         */
        formatMessage(text) {
            if (!text) return '';

            // 1. Escape tag HTML mentah untuk keamanan (XSS protection)
            let safe = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            // 2. Bold **teks**
            safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // 3. Italic *teks*
            safe = safe.replace(/\*(.*?)\*/g, '<em>$1</em>');

            // 4. Parser Tabel Markdown (| kolom | kolom |)
            const tablePlaceholders = [];
            // Regex mendeteksi blok tabel markdown (header baris, pemisah baris, dan baris data)
            const sepTokens = ['-', ':', '\\s', '\\|'].join('');
            const tableRegex = new RegExp('((?:^[ \\t]*\\|.+?\\|[ \\t]*\\r?\\n)(?:^[ \\t]*\\|[' + sepTokens + ']+?\\|[ \\t]*\\r?\\n)(?:^[ \\t]*\\|.+?\\|[ \\t]*(?:\\r?\\n|$))+)', 'gm');

            safe = safe.replace(tableRegex, (match) => {
                const lines = match.trim().split(/\r?\n/).filter(line => line.trim().length > 0);
                if (lines.length < 2) return match;

                const parseRow = (line) => {
                    let trimmed = line.trim();
                    if (trimmed.startsWith('|')) trimmed = trimmed.substring(1);
                    if (trimmed.endsWith('|')) trimmed = trimmed.substring(0, trimmed.length - 1);
                    return trimmed.split('|').map(cell => cell.trim());
                };

                const headers = parseRow(lines[0]);
                const bodyRows = lines.slice(2);

                let tableHtml = '<div class="overflow-x-auto my-2.5 border border-gray-200 rounded-xl custom-scrollbar shadow-2xs">' +
                    '<table class="min-w-full text-left text-[11px] border-collapse bg-white">' +
                    '<thead class="bg-emerald-50 text-emerald-950 font-bold border-b border-gray-200"><tr>' +
                    headers.map(h => `<th class="px-3 py-1.5 whitespace-nowrap">${h}</th>`).join('') +
                    '</tr></thead>' +
                    '<tbody class="divide-y divide-gray-150">';

                bodyRows.forEach(row => {
                    const cells = parseRow(row);
                    tableHtml += '<tr class="hover:bg-emerald-50/40 transition-colors">';
                    cells.forEach(c => {
                        tableHtml += `<td class="px-3 py-1.5 whitespace-nowrap text-gray-700">${c}</td>`;
                    });
                    tableHtml += '</tr>';
                });

                tableHtml += '</tbody></table></div>';

                const placeholder = `@@MD_TABLE_${tablePlaceholders.length}@@`;
                tablePlaceholders.push(tableHtml);
                return placeholder;
            });

            // 5. Bullet points (- item atau * item)
            safe = safe.replace(/^[•\-\*]\s+(.*)$/gm, '<li class="ml-3.5 list-disc">$1</li>');

            // 6. Newlines to <br>
            safe = safe.replace(/\n/g, '<br>');

            // 7. Kembalikan tabel HTML yang telah diparsing rapi
            tablePlaceholders.forEach((html, idx) => {
                safe = safe.replace(`@@MD_TABLE_${idx}@@`, html);
            });

            return safe;
        },

        /**
         * Scroll otomatis ke pesan terbawah.
         */
        scrollToBottom() {
            const container = document.getElementById('copilot-messages');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },
    };
};

// Registrasi otomatis ke Alpine.js (kompatibel sebelum & sesudah Alpine.start)
if (typeof window !== 'undefined') {
    if (window.Alpine) {
        window.Alpine.data('usuCopilot', window.usuCopilot);
    } else {
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('usuCopilot', window.usuCopilot);
        });
    }
}
