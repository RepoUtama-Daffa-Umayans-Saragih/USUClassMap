<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pembuktian Deep Scan Panorama 360P — USUClassMap</title>

    <!-- Tailwind CSS CDN (Standalone Admin Proof View) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#10A760',
                            600: '#0D8A4E',
                            700: '#087A3F',
                        }
                    }
                }
            }
        };
    </script>

    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Pannellum 360 Viewer CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css">
    <script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        #foto360p-viewer {
            width: 100%;
            height: 480px;
            min-height: 460px;
            background: #0b0f19;
            border-radius: 1rem;
            overflow: hidden;
        }
        .pnlm-container { border-radius: 1rem; }
        .foto360p-hotspot {
            width: 24px;
            height: 24px;
            border-radius: 9999px;
            background: rgba(16, 167, 96, 0.92);
            border: 3px solid #ffffff;
            box-shadow: 0 0 0 6px rgba(16, 167, 96, 0.35);
            cursor: pointer;
            animation: pulse360 1.8s infinite;
        }
        @keyframes pulse360 {
            0%, 100% { box-shadow: 0 0 0 4px rgba(16, 167, 96, 0.45); transform: scale(1); }
            50% { box-shadow: 0 0 0 11px rgba(16, 167, 96, 0.08); transform: scale(1.1); }
        }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen antialiased">

    <!-- Payload JSON & Endpoint Map (Aman via json_encode tanpa inline JS kompleks) -->
    @php
        $foto360pRoutes = [
            'verify_base' => url('/admin/cek-foto/360p'),
            'export_md'   => route('admin.cek-foto.360p.export', ['format' => 'md']),
            'export_csv'  => route('admin.cek-foto.360p.export', ['format' => 'csv']),
            'export_json' => route('admin.cek-foto.360p.export', ['format' => 'json']),
        ];
    @endphp
    <script type="application/json" id="foto360p-data">{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script type="application/json" id="foto360p-routes">{!! json_encode($foto360pRoutes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    <div x-data="photo360pInspector()" x-init="init()" class="min-h-screen flex flex-col">

        <!-- Header Bar -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
            <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-500 text-white flex items-center justify-center font-extrabold shadow-sm shrink-0">
                        360°
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-base sm:text-lg font-extrabold text-slate-900">Pembuktian Deep Scan Panorama 360P</h1>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Terisolasi (/admin/cek-foto/360p)
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Sumber: <code class="bg-slate-100 px-1 rounded">public/images/foto-360p/</code> • Engine:
                            <span class="font-medium text-slate-700" x-text="scanEngine"></span>
                        </p>
                    </div>
                </div>

                <!-- Action & Export Links -->
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <a href="{{ route('admin.cek-foto') }}" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition-colors">
                        Cek-Foto Lama
                    </a>
                    <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition-colors">
                        Dashboard Admin
                    </a>
                    <a href="{{ route('admin.cek-foto.360p.export', ['format' => 'md']) }}" class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 font-bold transition-colors">
                        Export .MD
                    </a>
                    <a href="{{ route('admin.cek-foto.360p.export', ['format' => 'csv']) }}" class="px-3 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-800 font-bold transition-colors">
                        Export .CSV
                    </a>
                    <a href="{{ route('admin.cek-foto.360p.export', ['format' => 'json']) }}" class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold transition-colors">
                        Export .JSON
                    </a>
                </div>
            </div>
        </header>

        <!-- Toast Feedback -->
        <template x-if="toastMsg">
            <div class="fixed bottom-5 right-5 z-50 px-4 py-3 rounded-xl shadow-xl border text-xs font-bold flex items-center gap-2"
                 :class="toastError ? 'bg-rose-600 border-rose-700 text-white' : 'bg-slate-900 border-slate-700 text-white'">
                <span x-text="toastMsg"></span>
            </div>
        </template>

        <!-- Main Container -->
        <main class="flex-1 max-w-[1440px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-5">

            <!-- Summary Stat Cards -->
            <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white rounded-2xl border border-slate-200 p-3.5 shadow-2xs">
                    <div class="text-[11px] font-semibold text-slate-500">Total Foto</div>
                    <div class="text-2xl font-extrabold text-slate-900 mt-0.5" x-text="computedStats.total"></div>
                </div>
                <div class="bg-white rounded-2xl border border-emerald-200 p-3.5 shadow-2xs">
                    <div class="text-[11px] font-semibold text-emerald-700">Terdeteksi Ruangan</div>
                    <div class="text-2xl font-extrabold text-emerald-700 mt-0.5" x-text="computedStats.detected"></div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-3.5 shadow-2xs">
                    <div class="text-[11px] font-semibold text-slate-500">Tanpa Tulisan (no_text)</div>
                    <div class="text-2xl font-extrabold text-slate-700 mt-0.5" x-text="computedStats.no_text"></div>
                </div>
                <div class="bg-white rounded-2xl border border-amber-200 p-3.5 shadow-2xs">
                    <div class="text-[11px] font-semibold text-amber-700">Buram (blurry)</div>
                    <div class="text-2xl font-extrabold text-amber-700 mt-0.5" x-text="computedStats.blurry"></div>
                </div>
                <div class="bg-white rounded-2xl border border-blue-200 p-3.5 shadow-2xs">
                    <div class="text-[11px] font-semibold text-blue-700">Ruangan Unik</div>
                    <div class="text-2xl font-extrabold text-blue-700 mt-0.5" x-text="computedStats.unique_rooms"></div>
                </div>
                <div class="bg-white rounded-2xl border border-indigo-200 p-3.5 shadow-2xs">
                    <div class="text-[11px] font-semibold text-indigo-700">Terverifikasi Admin</div>
                    <div class="text-2xl font-extrabold text-indigo-700 mt-0.5" x-text="computedStats.verified"></div>
                </div>
            </section>

            <!-- Split-View Inspector -->
            <section class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

                <!-- LEFT COLUMN: Filter + Search + Cards List (1..112) -->
                <aside class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden flex flex-col max-h-[860px]">
                    <div class="p-4 border-b border-slate-200 bg-slate-50 space-y-3 shrink-0">
                        <!-- Filter Tabs -->
                        <div class="flex flex-wrap gap-1.5 text-xs">
                            <button type="button" @click="setFilter('all')"
                                class="px-2.5 py-1 rounded-lg font-bold transition-colors cursor-pointer"
                                :class="statusFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100'"
                                x-text="'Semua (' + computedStats.total + ')'">
                            </button>
                            <button type="button" @click="setFilter('detected')"
                                class="px-2.5 py-1 rounded-lg font-bold transition-colors cursor-pointer"
                                :class="statusFilter === 'detected' ? 'bg-brand-500 text-white' : 'bg-white border border-emerald-200 text-emerald-800 hover:bg-emerald-50'"
                                x-text="'Terdeteksi (' + computedStats.detected + ')'">
                            </button>
                            <button type="button" @click="setFilter('undetected')"
                                class="px-2.5 py-1 rounded-lg font-bold transition-colors cursor-pointer"
                                :class="statusFilter === 'undetected' ? 'bg-amber-600 text-white' : 'bg-white border border-amber-200 text-amber-800 hover:bg-amber-50'"
                                x-text="'Tidak Terdeteksi / Buram (' + (computedStats.no_text + computedStats.blurry) + ')'">
                            </button>
                            <button type="button" @click="setFilter('verified')"
                                class="px-2.5 py-1 rounded-lg font-bold transition-colors cursor-pointer"
                                :class="statusFilter === 'verified' ? 'bg-indigo-600 text-white' : 'bg-white border border-indigo-200 text-indigo-800 hover:bg-indigo-50'"
                                x-text="'Terverifikasi (' + computedStats.verified + ')'">
                            </button>
                        </div>

                        <!-- Search Box -->
                        <div class="relative">
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="Cari nomor (#1..#112), nama ruangan, teks papan, atau file asli..."
                                class="w-full pl-9 pr-8 py-2 text-xs rounded-xl border border-slate-300 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs font-bold cursor-pointer">✕</button>
                        </div>
                    </div>

                    <!-- Scrollable Cards List -->
                    <div class="flex-1 overflow-y-auto divide-y divide-slate-100 p-2 space-y-2">
                        <template x-for="item in filteredItems" :key="item.id">
                            <div
                                :data-photo-card="item.id"
                                @click="selectPhoto(item.id)"
                                class="p-3 rounded-xl border transition-all cursor-pointer"
                                :class="selectedId === item.id
                                    ? 'bg-emerald-50/70 border-brand-500 ring-1 ring-brand-500/40'
                                    : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                            >
                                <div class="flex items-start gap-3">
                                    <!-- Large Photo Number + Thumbnail -->
                                    <div class="shrink-0 flex flex-col items-center gap-1">
                                        <span class="text-sm font-black px-2 py-0.5 rounded-lg"
                                              :class="selectedId === item.id ? 'bg-brand-500 text-white' : 'bg-slate-900 text-white'"
                                              x-text="'#' + item.id"></span>
                                        <img :src="item.image_url" :alt="item.filename" loading="lazy" class="w-20 h-10 object-cover rounded-lg border border-slate-200 bg-slate-100" />
                                    </div>

                                    <!-- Metadata + Detected Room Pills -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-mono text-xs font-bold text-slate-900" x-text="item.filename"></span>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <template x-if="item.verified_by_admin">
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800">✓ Valid</span>
                                                </template>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                                      :class="statusBadgeClass(item.status)"
                                                      x-text="statusLabel(item.status)"></span>
                                            </div>
                                        </div>

                                        <div class="text-[10px] font-mono text-slate-400 truncate mt-0.5" x-text="item.original_filename + ' • ' + item.width + 'x' + item.height"></div>

                                        <p class="text-[11px] text-slate-600 line-clamp-2 mt-1" x-text="item.scene_summary || 'Tanpa catatan.'"></p>

                                        <!-- Detected Room Pills inside card -->
                                        <template x-if="item.detected_rooms && item.detected_rooms.length > 0">
                                            <div class="flex flex-wrap gap-1.5 mt-2">
                                                <template x-for="(room, rIdx) in item.detected_rooms" :key="rIdx">
                                                    <button
                                                        type="button"
                                                        data-room-pill
                                                        @click.stop="selectAndLookAtRoom(item.id, room)"
                                                        class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-100 hover:bg-brand-500 text-emerald-900 hover:text-white border border-emerald-300 transition-colors cursor-pointer"
                                                        x-text="room.room_name + ' (' + room.yaw + '°, ' + room.pitch + '°)'"
                                                    ></button>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="filteredItems.length === 0">
                            <div class="p-8 text-center text-xs text-slate-500">
                                Tidak ada foto yang cocok dengan filter / kata kunci saat ini.
                            </div>
                        </template>
                    </div>
                </aside>

                <!-- RIGHT COLUMN: Interactive 360° / Flat Viewer + Evidence + Admin Verification -->
                <div class="lg:col-span-7 space-y-4">
                    <template x-if="selectedItem">
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">

                            <!-- Viewer Toolbar -->
                            <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-lg bg-slate-900 text-white text-xs font-black" x-text="'#' + selectedItem.id"></span>
                                    <div>
                                        <div class="text-sm font-extrabold text-slate-900" x-text="selectedItem.filename"></div>
                                        <div class="text-[11px] font-mono text-slate-500" x-text="selectedItem.original_filename + ' (' + selectedItem.size_kb + ' KB)'"></div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <!-- Live Yaw/Pitch Indicator -->
                                    <span class="px-2.5 py-1 rounded-lg bg-slate-900 text-emerald-300 font-mono text-[11px]"
                                          x-text="'P: ' + livePitch.toFixed(1) + '° | Y: ' + liveYaw.toFixed(1) + '°'"></span>

                                    <!-- Toggle 360 vs Flat -->
                                    <div class="inline-flex rounded-lg border border-slate-300 overflow-hidden bg-white">
                                        <button type="button" @click="setViewerMode('360')"
                                                class="px-2.5 py-1 font-bold transition-colors cursor-pointer"
                                                :class="viewerMode === '360' ? 'bg-brand-500 text-white' : 'text-slate-700 hover:bg-slate-100'">
                                            Mode 360°
                                        </button>
                                        <button type="button" @click="setViewerMode('flat')"
                                                class="px-2.5 py-1 font-bold transition-colors cursor-pointer"
                                                :class="viewerMode === 'flat' ? 'bg-brand-500 text-white' : 'text-slate-700 hover:bg-slate-100'">
                                            Mode Datar (Flat)
                                        </button>
                                    </div>

                                    <!-- Prev / Next Navigation -->
                                    <button type="button" @click="prevPhoto()" class="px-2.5 py-1 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 font-bold text-slate-700 cursor-pointer">
                                        ← Sebelumnya
                                    </button>
                                    <button type="button" @click="nextPhoto()" class="px-2.5 py-1 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 font-bold text-slate-700 cursor-pointer">
                                        Berikutnya →
                                    </button>
                                </div>
                            </div>

                            <!-- Interactive Viewer Area -->
                            <div class="p-4 space-y-4">
                                <!-- 360 Pannellum Mode -->
                                <div x-show="viewerMode === '360'" class="relative">
                                    <div id="foto360p-viewer"></div>
                                    <template x-if="selectedItem.projection && !selectedItem.projection.is_panorama">
                                        <div class="mt-2 px-3 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-[11px] text-amber-800">
                                            Catatan: Rasio gambar ini bukan panorama 360° penuh (<span x-text="selectedItem.width + '×' + selectedItem.height"></span>). Gunakan <strong>Mode Datar (Flat)</strong> bila ingin melihat seluruh frame tanpa potongan.
                                        </div>
                                    </template>
                                </div>

                                <!-- Flat Panorama / Normal Image Mode with Evidence Markers -->
                                <div x-show="viewerMode === 'flat'" id="flat-view-container" class="overflow-x-auto rounded-xl border border-slate-200 bg-slate-950 p-2" style="display: none;">
                                    <div class="relative inline-block min-w-full">
                                        <img
                                            :src="selectedItem.image_url"
                                            :alt="selectedItem.filename"
                                            @click="handleFlatImageClick($event)"
                                            class="w-full h-auto max-h-[460px] object-contain mx-auto cursor-crosshair select-none"
                                        />
                                        <template x-for="(room, idx) in (selectedItem.detected_rooms || [])" :key="idx">
                                            <button
                                                type="button"
                                                @click.stop="lookAtRoom(room)"
                                                :style="'left:' + ((room.x_pct || 0.5) * 100) + '%; top:' + ((room.y_pct || 0.5) * 100) + '%; transform: translate(-50%, -50%);'"
                                                class="absolute px-2 py-0.5 rounded-full text-[10px] font-extrabold border-2 shadow-lg transition-transform cursor-pointer whitespace-nowrap"
                                                :class="activeRoomName === room.room_name ? 'bg-amber-400 text-slate-950 border-white scale-115 z-20' : 'bg-brand-500 text-white border-white z-10'"
                                                x-text="room.room_name"
                                            ></button>
                                        </template>
                                    </div>
                                    <div class="text-[11px] text-slate-300 mt-1.5 px-1">
                                        Klik posisi mana saja pada gambar datar untuk mengisi koordinat draft sudut kamera (yaw, pitch).
                                    </div>
                                </div>

                                <!-- Clickable Room Evidence Pills (Auto lookAt in Pannellum) -->
                                <div class="bg-slate-50 rounded-xl border border-slate-200 p-3.5 space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">Bukti Tulisan Ruangan Terdeteksi</h3>
                                        <span class="text-[11px] text-slate-500">Klik pill untuk memutar kamera tepat ke papan tulisan</span>
                                    </div>

                                    <template x-if="selectedItem.detected_rooms && selectedItem.detected_rooms.length > 0">
                                        <div class="space-y-2">
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="(room, rIdx) in selectedItem.detected_rooms" :key="rIdx">
                                                    <button
                                                        type="button"
                                                        data-room-pill
                                                        @click="lookAtRoom(room)"
                                                        class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all cursor-pointer flex items-center gap-1.5"
                                                        :class="activeRoomName === room.room_name ? 'bg-brand-500 text-white border-brand-600 shadow-sm' : 'bg-white hover:bg-emerald-50 text-emerald-900 border-emerald-300'"
                                                    >
                                                        <span x-text="room.room_name"></span>
                                                        <span class="font-mono text-[10px] opacity-80" x-text="'(' + room.yaw + '°, ' + room.pitch + '°)'"></span>
                                                    </button>
                                                </template>
                                            </div>

                                            <div class="divide-y divide-slate-200 bg-white rounded-xl border border-slate-200">
                                                <template x-for="(room, rIdx) in selectedItem.detected_rooms" :key="'det-' + rIdx">
                                                    <div class="p-2.5 text-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5">
                                                        <div>
                                                            <span class="font-extrabold text-slate-900" x-text="room.room_name"></span>
                                                            <span class="ml-1.5 px-1.5 py-0.5 rounded bg-slate-100 font-mono text-[10px] text-slate-700" x-text="'Teks: ' + room.raw_text"></span>
                                                            <span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-800 text-[10px] font-semibold" x-text="room.category"></span>
                                                            <div class="text-[11px] text-slate-500 mt-0.5" x-text="room.evidence_note || 'Papan nama ruangan terlihat pada sudut kamera ini.'"></div>
                                                        </div>
                                                        <button type="button" @click="lookAtRoom(room)" class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-brand-500 text-emerald-800 hover:text-white text-[11px] font-bold shrink-0 cursor-pointer">
                                                            Arahkan Kamera
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="!selectedItem.detected_rooms || selectedItem.detected_rooms.length === 0">
                                        <div class="text-xs text-slate-600 bg-white rounded-xl border border-dashed border-slate-300 p-3">
                                            AI tidak menemukan tulisan ruangan yang lolos verifikasi zoom pada foto ini (<span class="font-mono font-bold" x-text="selectedItem.status"></span>).
                                        </div>
                                    </template>
                                </div>

                                <!-- Admin Verification & Correction Form -->
                                <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                                        <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">Panel Verifikasi & Koreksi Admin</h3>
                                        <label class="inline-flex items-center gap-2 text-xs font-bold text-indigo-800 bg-indigo-50 px-3 py-1 rounded-lg border border-indigo-200 cursor-pointer">
                                            <input type="checkbox" x-model="form.verified_by_admin" class="rounded text-indigo-600" />
                                            <span>Valid / Terverifikasi Admin</span>
                                        </label>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <div>
                                            <label class="block font-bold text-slate-700 mb-1">Status Foto</label>
                                            <select x-model="form.status" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-xs font-semibold">
                                                <option value="detected">detected (Ada Tulisan Ruangan)</option>
                                                <option value="no_text">no_text (Tidak Ada Tulisan)</option>
                                                <option value="blurry">blurry (Tulisan Buram / Tidak Terbaca)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block font-bold text-slate-700 mb-1">Catatan Verifikasi Admin</label>
                                            <input type="text" x-model="form.admin_note" placeholder="Catatan opsional koreksi/lokasi..." class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs" />
                                        </div>
                                    </div>

                                    <!-- Editable Room Rows -->
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-xs font-bold text-slate-700">Daftar Ruangan di Foto Ini</span>
                                            <div class="flex items-center gap-2">
                                                <button type="button" @click="captureCameraAngle()" class="px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 text-[11px] font-bold cursor-pointer">
                                                    📍 Ambil Sudut Kamera Aktif
                                                </button>
                                                <button type="button" @click="addRoomRow()" class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-emerald-800 text-[11px] font-bold cursor-pointer">
                                                    + Tambah Ruangan
                                                </button>
                                            </div>
                                        </div>

                                        <template x-for="(r, idx) in form.rooms" :key="'form-room-' + idx">
                                            <div @click="activeEditRowIndex = idx" class="p-2.5 rounded-xl border text-xs grid grid-cols-1 sm:grid-cols-12 gap-2 items-center"
                                                 :class="activeEditRowIndex === idx ? 'border-brand-500 bg-emerald-50/30' : 'border-slate-200 bg-slate-50'">
                                                <div class="sm:col-span-3">
                                                    <input type="text" x-model="r.room_name" placeholder="Nama ruang" class="w-full px-2 py-1.5 rounded border border-slate-300 bg-white text-xs font-bold" />
                                                </div>
                                                <div class="sm:col-span-3">
                                                    <input type="text" x-model="r.raw_text" placeholder="Teks asli papan" class="w-full px-2 py-1.5 rounded border border-slate-300 bg-white text-xs font-mono" />
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <select x-model="r.category" class="w-full px-2 py-1.5 rounded border border-slate-300 bg-white text-xs">
                                                        <option value="ruang_kuliah">ruang_kuliah</option>
                                                        <option value="laboratorium">laboratorium</option>
                                                        <option value="ruang_dosen">ruang_dosen</option>
                                                        <option value="administrasi">administrasi</option>
                                                        <option value="fasilitas">fasilitas</option>
                                                        <option value="papan_fakultas">papan_fakultas</option>
                                                        <option value="lainnya">lainnya</option>
                                                    </select>
                                                </div>
                                                <div class="sm:col-span-1">
                                                    <input type="number" step="0.1" x-model.number="r.yaw" placeholder="Yaw" class="w-full px-1.5 py-1.5 rounded border border-slate-300 bg-white text-xs font-mono" />
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <input type="number" step="0.1" x-model.number="r.pitch" placeholder="Pitch" class="w-full px-1.5 py-1.5 rounded border border-slate-300 bg-white text-xs font-mono" />
                                                </div>
                                                <div class="sm:col-span-1 text-right">
                                                    <button type="button" @click.stop="removeRoomRow(idx)" class="px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs cursor-pointer">Hapus</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="flex justify-end pt-2 border-t border-slate-100">
                                        <button type="button" @click="saveVerification()" :disabled="isSaving"
                                                class="px-5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold shadow-xs transition-all disabled:opacity-60 cursor-pointer">
                                            <span x-text="isSaving ? 'Menyimpan...' : 'Simpan Verifikasi & Sinkronkan Markdown'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </section>
        </main>
    </div>

    <script>
        window.__foto360pViewer = null;

        window.photo360pInspector = function () {
            let boot = { items: [], scan_engine: '-' };
            let routes = { verify_base: '/admin/cek-foto/360p' };

            try {
                const rawData = document.getElementById('foto360p-data')?.textContent;
                if (rawData) boot = JSON.parse(rawData);
            } catch (e) {}

            try {
                const rawRoutes = document.getElementById('foto360p-routes')?.textContent;
                if (rawRoutes) routes = JSON.parse(rawRoutes);
            } catch (e) {}

            return {
                items: Array.isArray(boot.items) ? boot.items : [],
                scanEngine: boot.scan_engine || '-',
                statusFilter: 'all',
                searchQuery: '',
                selectedId: null,
                viewerMode: '360',
                livePitch: 0,
                liveYaw: 0,
                activeRoomName: '',
                activeEditRowIndex: 0,
                isSaving: false,
                toastMsg: '',
                toastError: false,
                _angleTimer: null,
                form: {
                    verified_by_admin: false,
                    admin_note: '',
                    status: 'no_text',
                    rooms: []
                },

                get filteredItems() {
                    const q = (this.searchQuery || '').trim().toLowerCase();
                    return this.items.filter((item) => {
                        if (this.statusFilter === 'detected' && item.status !== 'detected') return false;
                        if (this.statusFilter === 'undetected' && item.status === 'detected') return false;
                        if (this.statusFilter === 'verified' && !item.verified_by_admin) return false;

                        if (!q) return true;
                        const idStr = String(item.id || '');
                        const numQ = q.replace('#', '');
                        if (idStr === numQ || ('#' + idStr) === q) return true;

                        const haystack = [
                            item.filename,
                            item.original_filename,
                            item.scene_summary,
                            ...(item.detected_rooms || []).map(r => `${r.room_name} ${r.raw_text} ${r.evidence_note}`)
                        ].join(' ').toLowerCase();

                        return haystack.includes(q);
                    });
                },

                get selectedItem() {
                    return this.items.find(i => Number(i.id) === Number(this.selectedId)) || null;
                },

                get computedStats() {
                    let detected = 0, no_text = 0, blurry = 0, verified = 0;
                    const uniqueSet = new Set();

                    this.items.forEach((item) => {
                        if (item.status === 'detected') detected++;
                        else if (item.status === 'blurry') blurry++;
                        else no_text++;

                        if (item.verified_by_admin) verified++;
                        (item.detected_rooms || []).forEach((r) => {
                            const k = String(r.room_name || '').trim().toUpperCase();
                            if (k) uniqueSet.add(k);
                        });
                    });

                    return {
                        total: this.items.length,
                        detected,
                        no_text,
                        blurry,
                        verified,
                        unique_rooms: uniqueSet.size
                    };
                },

                init() {
                    if (this.items.length > 0) {
                        const firstDetected = this.items.find(i => i.status === 'detected');
                        const initialId = firstDetected ? firstDetected.id : this.items[0].id;
                        this.selectPhoto(initialId);
                    }
                },

                setFilter(next) {
                    this.statusFilter = next;
                    const visible = this.filteredItems;
                    if (visible.length > 0 && !visible.some(i => Number(i.id) === Number(this.selectedId))) {
                        this.selectPhoto(visible[0].id);
                    }
                },

                statusLabel(status) {
                    if (status === 'detected') return 'Terdeteksi';
                    if (status === 'blurry') return 'Buram';
                    if (status === 'pending') return 'Pending';
                    return 'Tanpa Tulisan';
                },

                statusBadgeClass(status) {
                    if (status === 'detected') return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
                    if (status === 'blurry') return 'bg-amber-100 text-amber-800 border border-amber-200';
                    return 'bg-slate-100 text-slate-700 border border-slate-200';
                },

                selectPhoto(id) {
                    const found = this.items.find(i => Number(i.id) === Number(id));
                    if (!found) return;

                    this.selectedId = found.id;
                    this.activeRoomName = '';
                    this.activeEditRowIndex = 0;

                    this.form = {
                        verified_by_admin: !!found.verified_by_admin,
                        admin_note: found.admin_note || '',
                        status: found.status || 'no_text',
                        rooms: (found.detected_rooms || []).map(r => ({
                            room_name: r.room_name || '',
                            raw_text: r.raw_text || '',
                            category: r.category || 'ruang_kuliah',
                            yaw: Number(r.yaw || 0),
                            pitch: Number(r.pitch || 0),
                            evidence_note: r.evidence_note || '',
                            source: r.source || 'ai'
                        }))
                    };

                    const isPano = found.projection ? (found.projection.is_panorama !== false) : true;
                    this.viewerMode = isPano ? '360' : 'flat';

                    const firstRoom = (found.detected_rooms && found.detected_rooms.length > 0) ? found.detected_rooms[0] : null;
                    this.$nextTick(() => {
                        if (this.viewerMode === '360') {
                            this.mountPannellum(found, firstRoom ? firstRoom.yaw : 0, firstRoom ? firstRoom.pitch : 0);
                        } else {
                            this.destroyViewer();
                        }
                    });
                },

                selectAndLookAtRoom(photoId, room) {
                    if (Number(this.selectedId) !== Number(photoId)) {
                        this.selectPhoto(photoId);
                    }
                    setTimeout(() => this.lookAtRoom(room), 300);
                },

                setViewerMode(mode) {
                    this.viewerMode = mode === 'flat' ? 'flat' : '360';
                    const item = this.selectedItem;
                    if (!item) return;

                    if (this.viewerMode === '360') {
                        this.$nextTick(() => {
                            const firstRoom = (item.detected_rooms && item.detected_rooms.length > 0) ? item.detected_rooms[0] : null;
                            this.mountPannellum(item, firstRoom ? firstRoom.yaw : this.liveYaw, firstRoom ? firstRoom.pitch : this.livePitch);
                        });
                    } else {
                        this.destroyViewer();
                    }
                },

                destroyViewer() {
                    if (this._angleTimer) {
                        clearInterval(this._angleTimer);
                        this._angleTimer = null;
                    }
                    if (window.__foto360pViewer) {
                        try {
                            window.__foto360pViewer.destroy();
                        } catch (e) {}
                        window.__foto360pViewer = null;
                    }
                    const container = document.getElementById('foto360p-viewer');
                    if (container) container.innerHTML = '';
                },

                mountPannellum(item, initYaw = 0, initPitch = 0) {
                    this.destroyViewer();
                    const container = document.getElementById('foto360p-viewer');
                    if (!container || typeof window.pannellum === 'undefined') return;

                    const proj = item.projection || { haov: 360, vaov: 60 };
                    const haov = Number(proj.haov || 360);
                    const vaov = Number(proj.vaov || 60);
                    const hfov = Math.min(90, Math.max(45, (vaov * 1.6) || 90));

                    const hotSpots = (item.detected_rooms || []).map((r) => ({
                        pitch: Number(r.pitch || 0),
                        yaw: Number(r.yaw || 0),
                        type: 'info',
                        text: `${r.room_name} — ${r.raw_text}`,
                        cssClass: 'foto360p-hotspot'
                    }));

                    try {
                        window.__foto360pViewer = window.pannellum.viewer('foto360p-viewer', {
                            type: 'equirectangular',
                            panorama: item.image_url,
                            haov: haov,
                            vaov: vaov,
                            vOffset: 0,
                            autoLoad: true,
                            compass: true,
                            showControls: true,
                            hfov: hfov,
                            pitch: Number(initPitch || 0),
                            yaw: Number(initYaw || 0),
                            hotSpots: hotSpots
                        });

                        this._angleTimer = setInterval(() => {
                            if (window.__foto360pViewer && typeof window.__foto360pViewer.getYaw === 'function') {
                                try {
                                    this.liveYaw = Number(window.__foto360pViewer.getYaw() || 0);
                                    this.livePitch = Number(window.__foto360pViewer.getPitch() || 0);
                                } catch (e) {}
                            }
                        }, 200);
                    } catch (e) {
                        console.warn('[foto360p] Pannellum init error:', e);
                    }
                },

                lookAtRoom(room) {
                    if (!room) return;
                    this.activeRoomName = room.room_name || '';
                    const targetYaw = Number(room.yaw || 0);
                    const targetPitch = Number(room.pitch || 0);
                    this.liveYaw = targetYaw;
                    this.livePitch = targetPitch;

                    if (this.viewerMode === '360' && window.__foto360pViewer && typeof window.__foto360pViewer.lookAt === 'function') {
                        try {
                            window.__foto360pViewer.lookAt(targetPitch, targetYaw, 50, 900);
                        } catch (e) {
                            console.warn('[foto360p] lookAt error:', e);
                        }
                    } else if (this.viewerMode === 'flat') {
                        const flatBox = document.getElementById('flat-view-container');
                        if (flatBox && typeof room.x_pct === 'number') {
                            const scrollX = Math.max(0, (room.x_pct * flatBox.scrollWidth) - (flatBox.clientWidth / 2));
                            flatBox.scrollTo({ left: scrollX, behavior: 'smooth' });
                        }
                    }
                },

                handleFlatImageClick(event) {
                    const item = this.selectedItem;
                    if (!item) return;
                    const rect = event.target.getBoundingClientRect();
                    if (!rect.width || !rect.height) return;

                    const xPct = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
                    const yPct = Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height));
                    const proj = item.projection || { haov: 360, vaov: 60 };
                    const haov = Number(proj.haov || 360);
                    const vaov = Number(proj.vaov || 60);

                    const yaw = Number(((xPct * haov) - (haov / 2)).toFixed(1));
                    const pitch = Number((((0.5 - yPct) * vaov)).toFixed(1));
                    this.liveYaw = yaw;
                    this.livePitch = pitch;

                    if (!this.form.rooms.length) {
                        this.addRoomRow();
                    }
                    const rowIdx = Math.min(this.activeEditRowIndex, this.form.rooms.length - 1);
                    this.form.rooms[rowIdx].yaw = yaw;
                    this.form.rooms[rowIdx].pitch = pitch;
                },

                prevPhoto() {
                    const list = this.filteredItems;
                    if (!list.length) return;
                    const idx = list.findIndex(i => Number(i.id) === Number(this.selectedId));
                    const prevIdx = idx > 0 ? idx - 1 : list.length - 1;
                    this.selectPhoto(list[prevIdx].id);
                },

                nextPhoto() {
                    const list = this.filteredItems;
                    if (!list.length) return;
                    const idx = list.findIndex(i => Number(i.id) === Number(this.selectedId));
                    const nextIdx = (idx >= 0 && idx + 1 < list.length) ? idx + 1 : 0;
                    this.selectPhoto(list[nextIdx].id);
                },

                captureCameraAngle() {
                    let yaw = this.liveYaw;
                    let pitch = this.livePitch;
                    if (window.__foto360pViewer && typeof window.__foto360pViewer.getYaw === 'function') {
                        try {
                            yaw = Number(window.__foto360pViewer.getYaw() || 0);
                            pitch = Number(window.__foto360pViewer.getPitch() || 0);
                        } catch (e) {}
                    }
                    if (!this.form.rooms.length) {
                        this.addRoomRow();
                    }
                    const idx = Math.min(this.activeEditRowIndex, this.form.rooms.length - 1);
                    this.form.rooms[idx].yaw = Number(yaw.toFixed(1));
                    this.form.rooms[idx].pitch = Number(pitch.toFixed(1));
                    this.showToast(`Sudut kamera (${yaw.toFixed(1)}°, ${pitch.toFixed(1)}°) disalin ke baris ruangan #${idx + 1}.`);
                },

                addRoomRow() {
                    this.form.rooms.push({
                        room_name: '',
                        raw_text: '',
                        category: 'ruang_kuliah',
                        yaw: Number(this.liveYaw.toFixed(1)),
                        pitch: Number(this.livePitch.toFixed(1)),
                        evidence_note: 'Ditambahkan oleh admin',
                        source: 'admin'
                    });
                    this.activeEditRowIndex = this.form.rooms.length - 1;
                    this.form.status = 'detected';
                },

                removeRoomRow(idx) {
                    this.form.rooms.splice(idx, 1);
                    if (this.activeEditRowIndex >= this.form.rooms.length) {
                        this.activeEditRowIndex = Math.max(0, this.form.rooms.length - 1);
                    }
                    if (this.form.rooms.length === 0 && this.form.status === 'detected') {
                        this.form.status = 'no_text';
                    }
                },

                async saveVerification() {
                    if (!this.selectedItem || this.isSaving) return;
                    this.isSaving = true;

                    const cleanedRooms = this.form.rooms
                        .filter(r => String(r.room_name || '').trim() !== '')
                        .map(r => ({
                            room_name: String(r.room_name).trim(),
                            raw_text: String(r.raw_text || r.room_name).trim(),
                            category: r.category || 'ruang_kuliah',
                            yaw: Number(r.yaw || 0),
                            pitch: Number(r.pitch || 0),
                            evidence_note: r.evidence_note || '',
                            source: r.source || 'admin'
                        }));

                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const url = `${routes.verify_base}/${this.selectedItem.id}/verify`;

                    try {
                        const resp = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf
                            },
                            body: JSON.stringify({
                                verified_by_admin: !!this.form.verified_by_admin,
                                admin_note: this.form.admin_note || '',
                                status: cleanedRooms.length > 0 ? 'detected' : this.form.status,
                                rooms: cleanedRooms
                            })
                        });

                        const json = await resp.json();
                        if (!resp.ok || json.status !== 'success') {
                            throw new Error(json.message || 'Gagal menyimpan verifikasi.');
                        }

                        const targetIdx = this.items.findIndex(i => Number(i.id) === Number(this.selectedItem.id));
                        if (targetIdx !== -1 && json.item) {
                            this.items.splice(targetIdx, 1, json.item);
                            this.selectPhoto(json.item.id);
                        }
                        this.showToast(`Foto #${this.selectedItem.id} berhasil disimpan & disinkronkan ke Markdown.`);
                    } catch (err) {
                        console.error('[foto360p] Verify save error:', err);
                        this.showToast(err.message || 'Gagal menyimpan perubahan.', true);
                    } finally {
                        this.isSaving = false;
                    }
                },

                showToast(msg, isError = false) {
                    this.toastMsg = msg;
                    this.toastError = isError;
                    setTimeout(() => {
                        this.toastMsg = '';
                    }, 3500);
                }
            };
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
