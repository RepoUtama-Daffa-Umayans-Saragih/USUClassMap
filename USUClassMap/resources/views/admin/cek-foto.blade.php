<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>360° Photo Inspector — USUClassMap</title>

    <!-- Tailwind CSS CDN (Self-contained, no Vite required for testing) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#22c55e',
                            600: '#10A760',
                            700: '#0d8a4f',
                            800: '#166534',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Pannellum CDN CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css">

    <style>
        body { font-family: 'Inter', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        #pannellum-viewer {
            width: 100%;
            height: 100%;
            min-height: 480px;
            background-color: #0b0f19;
        }
        .pnlm-container {
            border-radius: 1rem;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col antialiased">

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand-600 flex items-center justify-center text-white shadow-sm shadow-brand-600/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-slate-900 leading-none">360° Photo Inspector</h1>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            Public Testing
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Pratinjau panorama internal USUClassMap &bull; <code class="text-slate-600">public/storage/foto360</code></p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Beranda</span>
                </a>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-900 text-white">
                    <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span>
                    {{ count($photos) }} File Terdeteksi
                </span>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 flex flex-col">

        @if(count($photos) === 0)
        <!-- Empty State -->
        <div class="flex-1 bg-white rounded-2xl border-2 border-dashed border-slate-300 p-12 flex flex-col items-center justify-center text-center my-auto shadow-sm">
            <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-slate-900">Belum Ada Foto 360 Ditemukan</h2>
            <p class="text-sm text-slate-500 mt-2 max-w-md">
                Tidak ada berkas gambar <code class="font-mono text-xs bg-slate-100 px-1 py-0.5 rounded text-rose-600 font-semibold">.jpg</code> di folder penyimpanan.
            </p>
            <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-slate-200 text-left text-xs text-slate-600 max-w-lg w-full space-y-2 font-mono">
                <p class="font-semibold text-slate-800 font-sans">Langkah Cepat:</p>
                <p>1. Buat symbolic link (jika belum):<br><span class="text-emerald-700">php artisan storage:link</span></p>
                <p>2. Salin file foto ke direktori:<br><span class="text-emerald-700">storage/app/public/foto360/424.jpg</span></p>
            </div>
            <button onclick="window.location.reload()" class="mt-6 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-medium text-xs transition-colors shadow-sm">
                Segarkan Halaman
            </button>
        </div>
        @else
        <!-- Split-Screen Dashboard Layout -->
        <div class="flex flex-col lg:flex-row gap-6 flex-1 min-h-[640px]">

            <!-- ================= SIDEBAR KIRI (30%) ================= -->
            <aside class="w-full lg:w-[30%] flex flex-col bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <!-- Sidebar Header -->
                <div class="p-4 border-b border-slate-200 bg-slate-50/75 flex-shrink-0">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Daftar Berkas 360</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">
                            {{ count($photos) }} item
                        </span>
                    </div>
                    <!-- Search Filter Input -->
                    <div class="relative">
                        <input
                            type="text"
                            id="search-box"
                            placeholder="Cari nama file foto..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-white"
                            oninput="filterPhotos(this.value)"
                        >
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>

                <!-- Scrollable Item List -->
                <div id="photo-list" class="flex-1 overflow-y-auto divide-y divide-slate-100 max-h-[650px] lg:max-h-none">
                    @foreach($photos as $index => $photo)
                    <button
                        type="button"
                        id="photo-btn-{{ $index }}"
                        data-index="{{ $index }}"
                        data-filename="{{ strtolower($photo['filename']) }}"
                        onclick="selectPhoto({{ $index }})"
                        class="photo-item w-full text-left p-3.5 flex items-center justify-between gap-3 transition-all duration-150 hover:bg-slate-50 focus:outline-none group border-l-4 border-transparent"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <!-- Thumbnail / 360 Icon -->
                            <div class="photo-icon w-9 h-9 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center flex-shrink-0 group-hover:bg-brand-50 group-hover:text-brand-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="photo-name text-xs font-semibold text-slate-800 truncate group-hover:text-brand-700 transition-colors">
                                    {{ $photo['filename'] }}
                                </p>
                                <p class="text-[11px] text-slate-400 font-mono mt-0.5">
                                    {{ $photo['size'] }}
                                </p>
                            </div>
                        </div>

                        <!-- Active Radio Pill -->
                        <div class="photo-pill w-2 h-2 rounded-full bg-transparent flex-shrink-0 transition-all"></div>
                    </button>
                    @endforeach
                </div>
            </aside>

            <!-- ================= AREA KANAN UTAMA (70%) ================= -->
            <section class="w-full lg:w-[70%] flex flex-col gap-6">

                <!-- 1. Viewer 360 Container (Atas) -->
                <div class="relative bg-slate-950 rounded-2xl shadow-sm border border-slate-800 overflow-hidden flex flex-col h-[500px] flex-shrink-0">
                    
                    <!-- Floating Top Info Overlay -->
                    <div class="absolute top-4 left-4 z-20 flex items-center gap-2 bg-slate-900/80 backdrop-blur-md px-3.5 py-1.5 rounded-xl border border-white/10 text-white shadow-lg">
                        <span class="w-2.5 h-2.5 rounded-full bg-brand-500 animate-ping"></span>
                        <span id="viewer-title" class="text-xs font-mono font-medium text-slate-200 truncate max-w-[280px]">
                            Memuat foto...
                        </span>
                    </div>

                    <!-- Floating Realtime Compass / Pitch & Yaw Angle Helper -->
                    <div class="absolute top-4 right-4 z-20 hidden sm:flex items-center gap-2 bg-slate-900/85 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/10 text-[11px] font-mono text-slate-200 shadow-lg">
                        <span class="text-slate-400">Posisi:</span>
                        <span id="live-angle-display" class="text-brand-400 font-semibold">P: 0.0° | Y: 0.0°</span>
                    </div>

                    <!-- Floating Control Helper -->
                    <div class="absolute bottom-4 right-4 z-20 hidden sm:flex items-center gap-2 bg-slate-900/80 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/10 text-[11px] text-slate-300">
                        <span>💡 Drag mouse atau swipe untuk rotasi 360°</span>
                    </div>

                    <!-- Loading Spinner Overlay -->
                    <div id="viewer-loader" class="absolute inset-0 z-30 bg-slate-950/90 flex flex-col items-center justify-center transition-opacity duration-300">
                        <div class="w-10 h-10 border-3 border-brand-500/20 border-t-brand-500 rounded-full animate-spin mb-3"></div>
                        <p class="text-xs font-medium text-slate-300 tracking-wide">Merender Panorama 360°...</p>
                    </div>

                    <!-- Pannellum Canvas Target -->
                    <div id="pannellum-viewer" class="flex-1 w-full h-full"></div>
                </div>

                <!-- 2. Dual Panel: Detail Properti & Control Panel Arah Hadap (Bawah) -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                    <!-- PANEL A: Detail Properti Berkas -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-emerald-50 text-brand-600 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-sm font-bold text-slate-900">Detail Properti Berkas</h3>
                                </div>

                                <button 
                                    id="btn-copy-url"
                                    type="button"
                                    onclick="copyActiveUrl()" 
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <span id="copy-btn-text">Salin URL</span>
                                </button>
                            </div>

                            <!-- Details Grid -->
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Nama File</span>
                                    <span id="prop-filename" class="font-bold font-mono text-slate-900 break-all">-</span>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Ukuran</span>
                                    <span id="prop-size" class="font-bold text-slate-900">-</span>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Modifikasi</span>
                                    <span id="prop-modified" class="font-semibold text-slate-800 truncate block">-</span>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Path Relatif</span>
                                    <span id="prop-path" class="font-mono text-slate-700 truncate block">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Public URL -->
                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Public URL</span>
                                <a id="prop-url-link" href="#" target="_blank" class="text-xs font-mono text-brand-600 hover:text-brand-700 hover:underline truncate block">
                                    -
                                </a>
                            </div>
                            <a id="btn-open-tab" href="#" target="_blank" class="flex-shrink-0 inline-flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-slate-900 bg-slate-50 hover:bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-lg transition-colors">
                                <span>Buka Tab</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- PANEL B: Control Panel "Set Default View" (Pitch & Yaw) -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
                        <div>
                            <!-- Header Panel B -->
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-sm font-bold text-slate-900">Pengaturan Arah Awal (Default View)</h3>
                                </div>

                                <span class="text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                                    Kalibrasi
                                </span>
                            </div>

                            <p class="text-xs text-slate-500 mb-3">
                                Putar panorama ke arah target (misal papan tulis), lalu klik <strong>"Ambil Posisi Saat Ini"</strong>.
                            </p>

                            <!-- Inputs: Pitch & Yaw -->
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <!-- Pitch Input -->
                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200/80">
                                    <div class="flex items-center justify-between mb-1">
                                        <label for="input-pitch" class="text-xs font-bold text-slate-700 flex items-center gap-1">
                                            <span>Pitch</span>
                                            <span class="text-[10px] font-normal text-slate-400 font-sans">(Vertikal)</span>
                                        </label>
                                        <span class="text-[10px] font-mono text-slate-400">-90° s/d +90°</span>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="number"
                                            id="input-pitch"
                                            step="0.5"
                                            min="-90"
                                            max="90"
                                            value="0.0"
                                            class="w-full pl-3 pr-7 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-white"
                                        >
                                        <span class="absolute right-2.5 top-1.5 text-xs font-mono text-slate-400">°</span>
                                    </div>
                                </div>

                                <!-- Yaw Input -->
                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200/80">
                                    <div class="flex items-center justify-between mb-1">
                                        <label for="input-yaw" class="text-xs font-bold text-slate-700 flex items-center gap-1">
                                            <span>Yaw</span>
                                            <span class="text-[10px] font-normal text-slate-400 font-sans">(Horizontal)</span>
                                        </label>
                                        <span class="text-[10px] font-mono text-slate-400">-180° s/d +180°</span>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="number"
                                            id="input-yaw"
                                            step="0.5"
                                            min="-180"
                                            max="180"
                                            value="0.0"
                                            class="w-full pl-3 pr-7 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-white"
                                        >
                                        <span class="absolute right-2.5 top-1.5 text-xs font-mono text-slate-400">°</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Preset Angle Shortcuts -->
                            <div class="flex items-center gap-1.5 mb-3 overflow-x-auto pb-1 text-[11px]">
                                <span class="text-slate-400 text-[10px] font-semibold uppercase tracking-wider mr-0.5">Preset:</span>
                                <button type="button" onclick="setPresetAngle(0, 0)" class="px-2 py-0.5 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-600 font-mono transition-colors cursor-pointer">
                                    Depan (0°)
                                </button>
                                <button type="button" onclick="setPresetAngle(0, 90)" class="px-2 py-0.5 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-600 font-mono transition-colors cursor-pointer">
                                    Kanan (90°)
                                </button>
                                <button type="button" onclick="setPresetAngle(0, 180)" class="px-2 py-0.5 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-600 font-mono transition-colors cursor-pointer">
                                    Belakang (180°)
                                </button>
                                <button type="button" onclick="setPresetAngle(0, -90)" class="px-2 py-0.5 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-600 font-mono transition-colors cursor-pointer">
                                    Kiri (-90°)
                                </button>
                            </div>
                        </div>

                        <!-- Action Buttons Group -->
                        <div class="pt-3 border-t border-slate-100 flex flex-wrap sm:flex-nowrap items-center gap-2">
                            <!-- Button: Ambil Posisi Saat Ini -->
                            <button
                                type="button"
                                onclick="captureCurrentPosition()"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 active:scale-95 rounded-xl transition-all shadow-2xs cursor-pointer"
                                title="Ambil sudut Pitch dan Yaw dari posisi kamera aktif"
                            >
                                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <span>Ambil Posisi Ini</span>
                            </button>

                            <!-- Button: Terapkan (Test) -->
                            <button
                                type="button"
                                onclick="applyTestView()"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 active:scale-95 rounded-xl transition-all shadow-2xs border border-indigo-200 cursor-pointer"
                                title="Render ulang panorama dengan sudut pandang awal di atas"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span>Terapkan (Test)</span>
                            </button>

                            <!-- Button: Simpan ke Apache Jena Fuseki -->
                            <button
                                type="button"
                                id="btn-save-fuseki"
                                onclick="saveCoordinates()"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 active:scale-95 rounded-xl transition-all shadow-sm cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                                title="Simpan koordinat sudut hadap awal ke Apache Jena Fuseki via SPARQL"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                </svg>
                                <span>Simpan</span>
                            </button>
                        </div>
                    </div>

                </div>

            </section>
        </div>
        @endif

    </main>

    <!-- Pannellum CDN Script -->
    <script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

    <!-- Interactive Client-side Script -->
    <script>
        // Inject backend JSON data into JavaScript
        const PHOTOS = @json($photos ?? []);

        let pannellumViewerInstance = null;
        let currentIndex = -1;
        let angleUpdateInterval = null;

        /**
         * Inisialisasi atau render ulang viewer Pannellum dengan target Pitch dan Yaw
         */
        function renderPannellum(photoUrl, pitch = 0, yaw = 0) {
            const loader = document.getElementById('viewer-loader');
            if (loader) {
                loader.style.opacity = '1';
                loader.style.pointerEvents = 'auto';
            }

            // Hentikan tracking sudut lama jika ada
            if (angleUpdateInterval) {
                clearInterval(angleUpdateInterval);
                angleUpdateInterval = null;
            }

            // 1. Destroy instance sebelumnya untuk mencegah WebGL memory leak / glitch
            if (pannellumViewerInstance) {
                try {
                    pannellumViewerInstance.destroy();
                } catch (e) {
                    console.warn('Pannellum destroy error:', e);
                }
                pannellumViewerInstance = null;

                const container = document.getElementById('pannellum-viewer');
                if (container) container.innerHTML = '';
            }

            // 2. Buat instance Pannellum baru dengan parameter pitch & yaw awal
            pannellumViewerInstance = pannellum.viewer('pannellum-viewer', {
                type: 'equirectangular',
                panorama: photoUrl,
                autoLoad: true,
                compass: true,
                showControls: true,
                showFullscreenCtrl: true,
                showZoomCtrl: true,
                hfov: 100,
                pitch: parseFloat(pitch) || 0,
                yaw: parseFloat(yaw) || 0,
                mouseZoom: true,
                keyboardZoom: true,
            });

            // 3. Callback saat gambar selesai dimuat
            pannellumViewerInstance.on('load', function() {
                if (loader) {
                    loader.style.opacity = '0';
                    loader.style.pointerEvents = 'none';
                }

                // Mulai tracking sudut kamera secara real-time untuk indicator di pojok viewer
                startAngleTracking();
            });

            pannellumViewerInstance.on('error', function(err) {
                console.error('Pannellum error:', err);
                if (loader) {
                    loader.innerHTML = `
                        <div class="text-rose-500 text-xs font-semibold mb-1">Gagal Memuat Foto Panorama</div>
                        <div class="text-slate-400 text-[11px]">Pastikan file foto ada di folder storage/app/public/foto360.</div>
                    `;
                }
            });
        }

        /**
         * Update badge sudut kamera secara real-time saat user memutar foto
         */
        function startAngleTracking() {
            const display = document.getElementById('live-angle-display');
            if (!display) return;

            angleUpdateInterval = setInterval(() => {
                if (pannellumViewerInstance && typeof pannellumViewerInstance.getPitch === 'function') {
                    try {
                        const p = pannellumViewerInstance.getPitch();
                        const y = pannellumViewerInstance.getYaw();
                        if (typeof p === 'number' && typeof y === 'number') {
                            display.textContent = `P: ${p.toFixed(1)}° | Y: ${y.toFixed(1)}°`;
                        }
                    } catch (e) {}
                }
            }, 150);
        }

        /**
         * Pilih foto dari daftar sidebar
         */
        function selectPhoto(index) {
            if (!PHOTOS || PHOTOS.length === 0 || !PHOTOS[index]) return;
            if (index === currentIndex && pannellumViewerInstance) return;

            currentIndex = index;
            const photo = PHOTOS[index];

            // 1. Update active state di sidebar
            document.querySelectorAll('.photo-item').forEach((item, i) => {
                const isSelected = (i === index);
                const icon = item.querySelector('.photo-icon');
                const name = item.querySelector('.photo-name');
                const pill = item.querySelector('.photo-pill');

                if (isSelected) {
                    item.classList.add('bg-brand-50/70', 'border-brand-600');
                    item.classList.remove('border-transparent');
                    icon.classList.add('bg-brand-600', 'text-white');
                    icon.classList.remove('bg-slate-100', 'text-slate-500');
                    name.classList.add('text-brand-700');
                    pill.classList.add('bg-brand-600', 'w-2.5', 'h-2.5');
                    pill.classList.remove('bg-transparent', 'w-2', 'h-2');
                } else {
                    item.classList.remove('bg-brand-50/70', 'border-brand-600');
                    item.classList.add('border-transparent');
                    icon.classList.remove('bg-brand-600', 'text-white');
                    icon.classList.add('bg-slate-100', 'text-slate-500');
                    name.classList.remove('text-brand-700');
                    pill.classList.remove('bg-brand-600', 'w-2.5', 'h-2.5');
                    pill.classList.add('bg-transparent', 'w-2', 'h-2');
                }
            });

            // 2. Ambil nilai Pitch & Yaw tersimpan dari Fuseki (atau default 0.0)
            const initialPitch = (typeof photo.pitch === 'number') ? photo.pitch : 0.0;
            const initialYaw = (typeof photo.yaw === 'number') ? photo.yaw : 0.0;

            document.getElementById('input-pitch').value = initialPitch.toFixed(1);
            document.getElementById('input-yaw').value = initialYaw.toFixed(1);

            // 3. Render viewer dengan nilai Pitch & Yaw awal dari data Fuseki
            renderPannellum(photo.url, initialPitch, initialYaw);

            // 4. Update data kartu properti
            document.getElementById('viewer-title').textContent = photo.filename;
            document.getElementById('prop-filename').textContent = photo.filename;
            document.getElementById('prop-size').textContent = photo.size;
            document.getElementById('prop-modified').textContent = photo.last_modified;
            document.getElementById('prop-path').textContent = photo.path;

            const urlLink = document.getElementById('prop-url-link');
            urlLink.textContent = photo.url;
            urlLink.href = photo.url;

            const openTabBtn = document.getElementById('btn-open-tab');
            if (openTabBtn) openTabBtn.href = photo.url;
        }

        /**
         * AKSI 1: Ambil Posisi Saat Ini
         * Mengambil nilai pitch dan yaw dari kamera aktif lalu memasukkannya ke input field
         */
        function captureCurrentPosition() {
            if (!pannellumViewerInstance || typeof pannellumViewerInstance.getPitch !== 'function') {
                alert('Pannellum viewer belum siap atau belum dimuat.');
                return;
            }

            const rawPitch = pannellumViewerInstance.getPitch();
            const rawYaw = pannellumViewerInstance.getYaw();

            const formattedPitch = (Math.round(rawPitch * 10) / 10).toFixed(1);
            const formattedYaw = (Math.round(rawYaw * 10) / 10).toFixed(1);

            const pitchInput = document.getElementById('input-pitch');
            const yawInput = document.getElementById('input-yaw');

            pitchInput.value = formattedPitch;
            yawInput.value = formattedYaw;

            // Flash visual feedback pada input field
            [pitchInput, yawInput].forEach(input => {
                input.classList.add('ring-2', 'ring-brand-500', 'bg-brand-50');
                setTimeout(() => {
                    input.classList.remove('ring-2', 'ring-brand-500', 'bg-brand-50');
                }, 600);
            });
        }

        /**
         * AKSI 2: Terapkan (Test)
         * Destroy Pannellum yang sedang berjalan, lalu render ulang dengan nilai Pitch & Yaw dari input
         */
        function applyTestView() {
            if (currentIndex < 0 || !PHOTOS[currentIndex]) {
                alert('Silakan pilih salah satu foto terlebih dahulu.');
                return;
            }

            const pitchVal = parseFloat(document.getElementById('input-pitch').value) || 0;
            const yawVal = parseFloat(document.getElementById('input-yaw').value) || 0;

            const photo = PHOTOS[currentIndex];
            renderPannellum(photo.url, pitchVal, yawVal);
        }

        /**
         * Set nilai preset sudut pandang cepat
         */
        function setPresetAngle(pitch, yaw) {
            document.getElementById('input-pitch').value = parseFloat(pitch).toFixed(1);
            document.getElementById('input-yaw').value = parseFloat(yaw).toFixed(1);
            applyTestView();
        }

        /**
         * AKSI 3: Simpan Koordinat ke Apache Jena Fuseki via AJAX POST (SPARQL Update)
         */
        async function saveCoordinates() {
            if (currentIndex < 0 || !PHOTOS[currentIndex]) {
                alert('Silakan pilih salah satu foto terlebih dahulu.');
                return;
            }

            const photo = PHOTOS[currentIndex];
            const filename = photo.filename;
            const pitchVal = parseFloat(document.getElementById('input-pitch').value) || 0.0;
            const yawVal = parseFloat(document.getElementById('input-yaw').value) || 0.0;

            const saveBtn = document.getElementById('btn-save-fuseki');
            const originalBtnHtml = saveBtn.innerHTML;

            // Tampilkan status loading pada tombol
            saveBtn.disabled = true;
            saveBtn.innerHTML = `
                <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span>Menyimpan...</span>
            `;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const response = await fetch("{{ route('admin.cek-foto.saveCalibration') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: JSON.stringify({
                        filename: filename,
                        pitch: pitchVal,
                        yaw: yawVal
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    // Perbarui cache data lokal foto
                    photo.pitch = pitchVal;
                    photo.yaw = yawVal;

                    saveBtn.classList.remove('bg-brand-600', 'hover:bg-brand-700');
                    saveBtn.classList.add('bg-emerald-700');
                    saveBtn.innerHTML = `
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Tersimpan!</span>
                    `;

                    setTimeout(() => {
                        saveBtn.innerHTML = originalBtnHtml;
                        saveBtn.disabled = false;
                        saveBtn.classList.remove('bg-emerald-700');
                        saveBtn.classList.add('bg-brand-600', 'hover:bg-brand-700');
                    }, 2000);

                } else {
                    alert('Gagal menyimpan ke Fuseki: ' + (result.message || 'Terjadi kesalahan sistem.'));
                    saveBtn.innerHTML = originalBtnHtml;
                    saveBtn.disabled = false;
                }
            } catch (err) {
                console.error('Fetch error:', err);
                alert('Gagal menghubungi backend: ' + err.message);
                saveBtn.innerHTML = originalBtnHtml;
                saveBtn.disabled = false;
            }
        }

        /**
         * Filter instan daftar file foto
         */
        function filterPhotos(keyword) {
            const query = (keyword || '').toLowerCase().trim();
            document.querySelectorAll('.photo-item').forEach(item => {
                const name = item.getAttribute('data-filename') || '';
                item.style.display = name.includes(query) ? 'flex' : 'none';
            });
        }

        /**
         * Salin URL aktif ke clipboard
         */
        function copyActiveUrl() {
            if (currentIndex < 0 || !PHOTOS[currentIndex]) return;
            const url = PHOTOS[currentIndex].url;
            navigator.clipboard.writeText(url).then(() => {
                const btnText = document.getElementById('copy-btn-text');
                const originalText = btnText.textContent;
                btnText.textContent = 'Tersalin!';
                setTimeout(() => {
                    btnText.textContent = originalText;
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }

        // Auto-muat foto indeks ke-0 saat halaman selesai dimuat
        document.addEventListener('DOMContentLoaded', () => {
            if (PHOTOS && PHOTOS.length > 0) {
                selectPhoto(0);
            }
        });
    </script>
</body>
</html>