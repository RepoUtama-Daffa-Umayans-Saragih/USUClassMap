$ErrorActionPreference = 'Stop'
$gatewayDir = Resolve-Path (Join-Path $PSScriptRoot '..\whatsapp-gateway')
$logDir = Resolve-Path (Join-Path $PSScriptRoot '..\storage\logs')
$logFile = Join-Path $logDir 'whatsapp-gateway.log'

$existing = Get-NetTCPConnection -LocalPort 3000 -State Listen -ErrorAction SilentlyContinue
if ($existing) {
    Write-Output "WhatsApp Gateway sudah aktif di port 3000 (PID=$($existing.OwningProcess)). Dashboard: http://localhost:3000/dashboard"
    exit 0
}

$cmd = "cmd /c `"cd /d `"$gatewayDir`" && set PORT=3000&& set WHATSAPP_API_KEY=usuclassmap-waha-2026&& set WHATSAPP_SESSION=default&& node server.mjs >> `"$logFile`" 2>&1`""
$res = Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{ CommandLine = $cmd }
if ($res.ReturnValue -ne 0) {
    Write-Error "Gagal menjalankan WhatsApp Gateway (WMI code $($res.ReturnValue))."
    exit 1
}

for ($i = 0; $i -lt 15; $i++) {
    Start-Sleep -Seconds 1
    try {
        $r = Invoke-WebRequest -Uri "http://localhost:3000/dashboard/status" -UseBasicParsing -TimeoutSec 3
        if ($r.StatusCode -eq 200) {
            Write-Output "WhatsApp Gateway aktif di http://localhost:3000 (Dashboard: http://localhost:3000/dashboard). Status: $($r.Content)"
            exit 0
        }
    } catch {}
}

Write-Warning "Proses dimulai (PID=$($res.ProcessId)), cek log di $logFile"
