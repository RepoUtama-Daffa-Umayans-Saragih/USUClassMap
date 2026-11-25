<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $isAdminMode ? 'Kalibrasi Jalan Virtual 360° FIB — Admin' : 'FIB 360° Virtual Walk — USUClassMap' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css" />

    <style>
        [x-cloak] { display: none !important; }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #090d16;
            color: #f8fafc;
        }
        #fib-pano-viewer {
            width: 100%;
            height: 100%;
            background: #090d16;
        }
        #fib-fallback-surface {
            position: absolute;
            inset: 0;
            background-repeat: repeat-x;
            will-change: background-position, background-size;
        }
        /* Hide pannellum's built-in intrusive overlays cleanly */
        .pnlm-error-msg, .pnlm-load-box p {
            color: #e2e8f0;
        }
        .pnlm-compass, .pnlm-controls-container, .pnlm-load-box {
            display: none !important;
        }
        .pnlm-hotspot-base.fib-walk-hotspot {
            cursor: pointer;
            background: transparent !important;
            border: none !important;
            width: auto !important;
            height: auto !important;
            margin: 0 !important;
        }

        /* Overlapping Zoom-Morph Crossfade Animations */
        @keyframes fibMorphOut {
            0% { transform: scale(1); filter: blur(0px); opacity: 1; }
            100% { transform: scale(1.32); filter: blur(2px); opacity: 0; }
        }
        @keyframes fibMorphIn {
            0% { transform: scale(0.92); opacity: 0.72; }
            100% { transform: scale(1); opacity: 1; }
        }
        #fib-morph-overlay {
            position: absolute;
            inset: 0;
            z-index: 22;
            pointer-events: none;
            background-repeat: repeat-x;
            transform-origin: center center;
            will-change: transform, filter, opacity;
        }
        #fib-morph-overlay.is-morphing-out {
            animation: fibMorphOut 420ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        #fib-viewport-layer.is-morphing-in {
            animation: fibMorphIn 420ms cubic-bezier(0.22, 1, 0.36, 1);
        }

        /* Click-to-walk subtle floor ripple */
        @keyframes fibClickRipple {
            0% { transform: translate(-50%, -50%) perspective(240px) rotateX(58deg) scale(0.45); opacity: 0.95; }
            100% { transform: translate(-50%, -50%) perspective(240px) rotateX(58deg) scale(1.85); opacity: 0; }
        }
        .fib-click-ripple {
            position: fixed;
            width: 68px;
            height: 68px;
            border-radius: 9999px;
            border: 3px solid rgba(52, 211, 153, 0.95);
            background: rgba(16, 167, 96, 0.25);
            pointer-events: none;
            z-index: 28;
            animation: fibClickRipple 520ms ease-out forwards;
        }

        /* Street View Spatial Hotspots */
        @keyframes fibFloorGlow {
            0%, 100% { box-shadow: 0 10px 24px rgba(0, 0, 0, 0.45), 0 0 16px rgba(16, 167, 96, 0.45); }
            50% { box-shadow: 0 12px 28px rgba(0, 0, 0, 0.55), 0 0 28px rgba(52, 211, 153, 0.8); }
        }
        @keyframes fibGuideGlow {
            0%, 100% { box-shadow: 0 10px 26px rgba(0, 0, 0, 0.55), 0 0 22px rgba(245, 158, 11, 0.78); }
            50% { box-shadow: 0 14px 30px rgba(0, 0, 0, 0.65), 0 0 34px rgba(251, 191, 36, 0.98); }
        }

        .fib-spatial-hotspot {
            position: relative;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transform: translate(-50%, -50%);
            background: transparent;
            border: none;
            padding: 6px;
            cursor: pointer;
            user-select: none;
            transition: transform 0.18s ease, z-index 0.1s;
        }
        .fib-spatial-hotspot:hover {
            z-index: 60 !important;
        }

        /* Floating Tooltip (appears cleanly on hover or guided step) */
        .fib-hotspot-tooltip {
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translate(-50%, 6px);
            padding: 5px 11px;
            border-radius: 9999px;
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #f8fafc;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            max-width: 240px;
            overflow: hidden;
            text-overflow: ellipsis;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.16s ease, transform 0.16s ease;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.45);
        }
        .fib-spatial-hotspot:hover .fib-hotspot-tooltip,
        .fib-spatial-hotspot.is-guided-next .fib-hotspot-tooltip {
            opacity: 1;
            transform: translate(-50%, 0);
        }
        .fib-spatial-hotspot.is-guided-next .fib-hotspot-tooltip {
            background: #f59e0b;
            color: #0f172a;
            border-color: #fde68a;
        }

        /* Perspective Street View Ground Chevron (walk) */
        .fib-ground-chevron {
            width: 72px;
            height: 72px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: perspective(260px) rotateX(58deg);
            background: radial-gradient(circle at 50% 45%, rgba(16, 167, 96, 0.9) 0%, rgba(16, 167, 96, 0.42) 66%, rgba(255, 255, 255, 0.12) 100%);
            border: 2.5px solid rgba(255, 255, 255, 0.92);
            color: #ffffff;
            backdrop-filter: blur(4px);
            transition: transform 0.2s cubic-bezier(0.22, 1, 0.36, 1), background 0.2s ease, border-color 0.2s ease;
            animation: fibFloorGlow 2.4s infinite;
        }
        .fib-spatial-hotspot:hover .fib-ground-chevron {
            transform: perspective(260px) rotateX(52deg) scale(1.16);
            background: radial-gradient(circle at 50% 45%, rgba(16, 185, 129, 0.98) 0%, rgba(16, 167, 96, 0.58) 70%, rgba(255, 255, 255, 0.22) 100%);
            border-color: #ecfdf5;
        }
        .fib-spatial-hotspot.is-guided-next .fib-ground-chevron {
            background: radial-gradient(circle at 50% 45%, rgba(251, 191, 36, 0.98) 0%, rgba(245, 158, 11, 0.58) 72%, rgba(254, 243, 199, 0.28) 100%);
            border-color: #fef3c7;
            color: #0f172a;
            animation: fibGuideGlow 1.35s infinite;
        }

        /* Compact Classroom Door Pin (room) */
        .fib-room-pin-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            transition: transform 0.18s ease;
        }
        .fib-spatial-hotspot:hover .fib-room-pin-wrap {
            transform: scale(1.1);
        }
        .fib-room-pin {
            width: 38px;
            height: 38px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(14, 116, 144, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.9);
            color: #ffffff;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
        }
        .fib-spatial-hotspot.is-guided-next .fib-room-pin {
            background: #f59e0b;
            color: #0f172a;
            border-color: #fef3c7;
        }
        .fib-pin-badge {
            padding: 1px 7px;
            border-radius: 9999px;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: #e2e8f0;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        /* Compact Stairs Pin (stairs_up / stairs_down) */
        .fib-stairs-pin {
            width: 42px;
            height: 42px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(217, 119, 6, 0.92);
            border: 2px solid rgba(255, 255, 255, 0.9);
            color: #ffffff;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
        }
        .fib-spatial-hotspot:hover .fib-stairs-pin {
            transform: scale(1.12);
        }
        .fib-spatial-hotspot.is-guided-next .fib-stairs-pin {
            background: #fbbf24;
            color: #0f172a;
            border-color: #fef3c7;
            animation: fibGuideGlow 1.35s infinite;
        }
        .fib-crosshair-layer {
            z-index: 25;
        }
        .fib-compass-needle {
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-bottom: 9px solid #34d399;
            margin-top: -5px;
        }

        .custom-dark-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-dark-scroll::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.4); }
        .custom-dark-scroll::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.45); border-radius: 9999px; }
    </style>
</head>
<body class="bg-slate-950 text-white font-sans antialiased">

<div
    x-data="fibVirtualWalkApp()"
    class="relative w-screen h-screen flex overflow-hidden select-none"
>
    <!-- LEFT / MAIN 360 VIEWPORT AREA -->
    <div
        id="fib-viewport-layer"
        class="relative flex-1 h-full overflow-hidden bg-slate-950"
        :class="isMorphingIn ? 'is-morphing-in' : ''"
        @pointerdown="onViewportPointerDown($event)"
        @pointerup="onViewportPointerUp($event)"
    >

        <!-- 1. Single-Instance Pannellum WebGL Container -->
        <div
            id="fib-pano-viewer"
            x-show="!useFallback"
            class="w-full h-full"
        ></div>

        <!-- 2. Seamless Infinite 360° Cylindrical Fallback Engine -->
        <div
            id="fib-fallback-viewer"
            x-show="useFallback"
            x-cloak
            @pointerdown="startFallbackDrag($event)"
            @pointermove="moveFallbackDrag($event)"
            @pointerup="endFallbackDrag()"
            @pointercancel="endFallbackDrag()"
            @wheel.prevent="onFallbackWheel($event)"
            class="relative w-full h-full overflow-hidden bg-slate-950 cursor-grab active:cursor-grabbing"
            style="display: none; touch-action: none;"
        >
            <div id="fib-fallback-surface" :style="fallbackSurfaceStyle()"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black/35 via-transparent to-black/50 pointer-events-none"></div>

            <!-- Real-Time Projected 3D Spatial Hotspots Layer -->
            <div id="fib-fallback-hotspots" class="absolute inset-0 z-10 pointer-events-none overflow-hidden">
                <template x-for="proj in projectedFallbackHotspots" :key="'fb-hs-' + proj.hs.target_id">
                    <button
                        type="button"
                        data-walk-hotspot
                        :data-target-id="proj.hs.target_id"
                        :data-kind="proj.hs.kind"
                        @pointerdown.stop
                        @pointerup.stop
                        @click.stop="walkTo(proj.hs.target_id, proj.hs)"
                        class="pointer-events-auto fib-spatial-hotspot"
                        :class="[proj.hs.kind === 'walk' ? 'is-walk-hotspot' : '', isGuidedNext(proj.hs.target_id) ? 'is-guided-next' : '']"
                        :style="proj.style"
                    >
                        <div class="fib-hotspot-tooltip" x-text="proj.hs.label"></div>

                        <template x-if="proj.hs.kind === 'walk'">
                            <div class="fib-ground-chevron" x-html="chevronSvg()"></div>
                        </template>

                        <template x-if="proj.hs.kind === 'room'">
                            <div class="fib-room-pin-wrap">
                                <div class="fib-room-pin" x-html="roomDoorSvg()"></div>
                                <span class="fib-pin-badge" x-text="shortPinLabel(proj.hs)"></span>
                            </div>
                        </template>

                        <template x-if="proj.hs.kind === 'stairs_up' || proj.hs.kind === 'stairs_down'">
                            <div class="fib-room-pin-wrap">
                                <div class="fib-stairs-pin" x-html="stairsSvg(proj.hs.kind)"></div>
                                <span class="fib-pin-badge" x-text="shortPinLabel(proj.hs)"></span>
                            </div>
                        </template>
                    </button>
                </template>
            </div>
        </div>

        <!-- 3. Overlapping Zoom-Morph Crossfade Layer (Zero black frame during step transition) -->
        <div
            x-show="morphOverlayActive"
            x-cloak
            style="display: none;"
            class="absolute inset-0 z-20 pointer-events-none overflow-hidden"
        >
            <div
                id="fib-morph-overlay"
                :class="morphOverlayAnimating ? 'is-morphing-out' : ''"
                :style="morphOverlayStyle"
            ></div>
        </div>

        <!-- Click-to-Walk Floor Ripple Ring -->
        <template x-if="clickRipple">
            <div
                class="fib-click-ripple"
                :style="'left:' + clickRipple.x + 'px; top:' + clickRipple.y + 'px;'"
            ></div>
        </template>

        <!-- Admin Center Reticle + Live Yaw/Pitch Crosshair (Works in BOTH WebGL & Infinite Fallback) -->
        @if($isAdminMode)
        <div class="pointer-events-none fixed sm:absolute inset-0 fib-crosshair-layer flex items-center justify-center">
            <div class="relative flex items-center justify-center">
                <div class="w-8 h-8 rounded-full border-2 border-amber-400/90 shadow-lg flex items-center justify-center bg-black/20">
                    <div class="w-1.5 h-1.5 rounded-full bg-amber-300"></div>
                </div>
                <div class="absolute top-10 left-1/2 -translate-x-1/2 px-2.5 py-0.5 rounded-md bg-slate-900/90 border border-amber-400/50 text-[10px] font-mono text-amber-200 whitespace-nowrap shadow">
                    Yaw: <span x-text="liveYaw.toFixed(1)"></span>° | Pitch: <span x-text="livePitch.toFixed(1)"></span>°
                </div>
            </div>
        </div>
        @endif

        <!-- TOP HUD BAR -->
        <div data-hud class="absolute top-3 inset-x-3 z-30 flex flex-wrap items-start justify-between gap-2 pointer-events-none">
            <!-- Left: Back Button + Location Breadcrumb -->
            <div class="pointer-events-auto bg-slate-900/85 backdrop-blur-md border border-white/15 rounded-2xl p-2 sm:px-3.5 sm:py-2.5 shadow-xl max-w-lg flex items-center gap-2.5">
                <a
                    href="{{ $isAdminMode ? route('admin.cek-foto') : route('map') }}"
                    class="w-9 h-9 rounded-xl bg-white/10 hover:bg-[#10A760] text-white flex items-center justify-center transition-colors shrink-0"
                    title="{{ $isAdminMode ? 'Kembali ke Cek Foto' : 'Kembali ke Peta Kampus' }}"
                >
                    <i class="fa-solid fa-arrow-left text-sm"></i>
                </a>

                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 text-[10px] uppercase tracking-wider font-bold text-emerald-300">
                        <span>FIB USU</span>
                        <span class="text-white/40">•</span>
                        <span x-text="currentScene?.zone || 'FIB'"></span>
                        <span class="text-white/40">•</span>
                        <span x-text="'Lt. ' + (currentScene?.floor || 1)"></span>
                        @if($isAdminMode)
                            <span class="ml-1 px-1.5 py-0.2 rounded bg-amber-400 text-slate-950 font-black">ADMIN KALIBRASI</span>
                        @endif
                    </div>
                    <h1 id="fib-scene-title" class="text-xs sm:text-sm font-extrabold text-white truncate">
                        <span x-text="currentScene?.label || 'Memuat...'"></span>
                        <span class="text-xs font-mono text-emerald-300 ml-1" x-text="'#' + currentSceneId"></span>
                    </h1>
                </div>
            </div>

            <!-- Right: Quick Teleport, Path Finder, Auto-Walk, Mini Compass, Admin Drawer -->
            <div class="pointer-events-auto flex flex-wrap items-center gap-2">
                <!-- Quick Teleport Toggle -->
                <button
                    type="button"
                    id="fib-teleport-toggle-btn"
                    @click="teleportOpen = !teleportOpen; pathPanelOpen = false"
                    class="px-3 py-2 rounded-xl bg-slate-900/85 hover:bg-slate-800 backdrop-blur-md border border-white/15 text-xs font-bold text-white shadow-xl flex items-center gap-1.5 transition-colors cursor-pointer"
                >
                    <i class="fa-solid fa-map-location-dot text-emerald-400"></i>
                    <span class="hidden sm:inline">Peta Titik (67)</span>
                </button>

                <!-- Mini Path Finder Toggle -->
                <button
                    type="button"
                    id="fib-pathfinder-toggle-btn"
                    @click="pathPanelOpen = !pathPanelOpen; teleportOpen = false"
                    class="px-3 py-2 rounded-xl backdrop-blur-md border text-xs font-bold shadow-xl flex items-center gap-1.5 transition-colors cursor-pointer"
                    :class="guideTargetId ? 'bg-amber-500 text-slate-950 border-amber-300' : 'bg-slate-900/85 hover:bg-slate-800 text-white border-white/15'"
                >
                    <i class="fa-solid fa-route" :class="guideTargetId ? 'text-slate-950' : 'text-amber-400'"></i>
                    <span class="hidden sm:inline" x-text="guideTargetId ? ('Rute: ' + Math.max(0, guidedPath.length - 1) + ' langkah') : 'Arahkan ke Ruangan'"></span>
                </button>

                <!-- Continuous Auto-Walk Toggle -->
                <button
                    type="button"
                    id="fib-autowalk-btn"
                    @click="toggleAutoWalk()"
                    class="px-3 py-2 rounded-xl backdrop-blur-md border text-xs font-extrabold shadow-xl flex items-center gap-1.5 transition-all cursor-pointer"
                    :class="autoWalking
                        ? 'bg-rose-500 hover:bg-rose-400 text-white border-rose-300 animate-pulse'
                        : (guideTargetId && currentSceneId !== Number(guideTargetId)
                            ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950 border-emerald-200'
                            : 'bg-slate-900/85 hover:bg-[#10A760] text-white border-white/15')"
                >
                    <i class="fa-solid" :class="autoWalking ? 'fa-pause' : 'fa-play'"></i>
                    <span x-text="autoWalking ? '⏸ Berhenti' : (guideTargetId && currentSceneId !== Number(guideTargetId) ? '▶ Jalan Otomatis ke Tujuan' : '▶ Jalan Terus')"></span>
                </button>

                <!-- Mini Compass (click to reset view) -->
                <button
                    type="button"
                    id="fib-mini-compass"
                    @click="resetCameraToDefault()"
                    class="w-9 h-9 rounded-xl bg-slate-900/85 hover:bg-slate-800 backdrop-blur-md border border-white/15 flex items-center justify-center shadow-xl cursor-pointer relative"
                    title="Kompas Arah (Klik untuk reset kamera)"
                >
                    <span class="sr-only">Kompas</span>
                    <div
                        class="w-6 h-6 rounded-full border border-white/25 flex items-center justify-center transition-transform duration-100"
                        :style="'transform: rotate(' + (-liveYaw).toFixed(1) + 'deg)'"
                    >
                        <div class="fib-compass-needle"></div>
                    </div>
                </button>

                @if($isAdminMode)
                <button
                    type="button"
                    @click="adminSidebarOpen = !adminSidebarOpen"
                    class="px-3 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-xl flex items-center gap-1.5 cursor-pointer"
                >
                    <i class="fa-solid fa-sliders"></i>
                    <span>Kalibrasi</span>
                </button>
                @endif
            </div>
        </div>

        <!-- ACTIVE GUIDED PATH BANNER (Mini Path Finder) -->
        <template x-if="guideTargetId && currentSceneId !== Number(guideTargetId) && guidedPath.length >= 2">
            <div data-hud class="absolute top-20 left-3 z-30 bg-amber-500/95 text-slate-950 px-3.5 py-2 rounded-xl shadow-xl border border-amber-200 flex items-center gap-3 max-w-md">
                <div class="w-7 h-7 rounded-lg bg-slate-950/15 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-shoe-prints"></i>
                </div>
                <div class="text-xs min-w-0 flex-1">
                    <div class="font-black truncate" x-text="'Menuju: ' + (scenes[String(guideTargetId)]?.label || '')"></div>
                    <div class="text-[11px] font-semibold" x-text="'Ikuti panah emas — ' + (guidedPath.length - 1) + ' langkah lagi'"></div>
                </div>
                <button
                    type="button"
                    @click="stepGuidedNext()"
                    class="px-2.5 py-1 rounded-lg bg-slate-950 text-amber-300 text-[11px] font-bold hover:bg-slate-900 cursor-pointer shrink-0"
                >
                    Langkah Berikutnya
                </button>
                <button
                    type="button"
                    @click="clearGuidedPath()"
                    class="text-slate-900/70 hover:text-slate-950 cursor-pointer"
                    title="Batalkan Panduan"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </template>

        <template x-if="guideTargetId && currentSceneId === Number(guideTargetId)">
            <div data-hud class="absolute top-20 left-3 z-30 bg-emerald-600/95 text-white px-3.5 py-2 rounded-xl shadow-xl border border-emerald-300 flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-emerald-200"></i>
                <span class="text-xs font-bold" x-text="'Anda telah tiba di ' + (currentScene?.label || 'tujuan') + '!'"></span>
                <button type="button" @click="clearGuidedPath()" class="ml-2 text-xs underline cursor-pointer">Tutup</button>
            </div>
        </template>

        <!-- Auto-Walk Status Notice Pill -->
        <template x-if="autoWalkNotice">
            <div data-hud class="pointer-events-none absolute bottom-16 left-1/2 -translate-x-1/2 z-30 px-4 py-1.5 rounded-full bg-slate-900/90 border border-emerald-400/40 text-xs font-bold text-emerald-200 shadow-xl backdrop-blur-md">
                <span x-text="autoWalkNotice"></span>
            </div>
        </template>

        <!-- QUICK TELEPORT POPOVER -->
        <div
            data-hud
            x-show="teleportOpen"
            x-cloak
            style="display: none;"
            class="absolute top-16 right-3 z-40 w-[92vw] sm:w-96 max-h-[70vh] bg-slate-900/95 backdrop-blur-xl border border-white/20 rounded-2xl shadow-2xl flex flex-col overflow-hidden"
        >
            <div class="p-3 border-b border-white/10 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold text-white">Lompat Cepat ke Titik / Ruangan (67 Foto)</span>
                    <button type="button" @click="teleportOpen = false" class="text-slate-400 hover:text-white cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <input
                    id="fib-teleport-search"
                    type="text"
                    x-model="teleportQuery"
                    placeholder="Ketik ruangan/gedung (H-208, F-203, O.102, 465)..."
                    class="w-full px-3 py-1.5 rounded-lg bg-slate-800 border border-white/15 text-xs text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#10A760]"
                />
                <div class="flex gap-1.5 overflow-x-auto pb-1 custom-dark-scroll">
                    <button
                        type="button"
                        @click="selectedZone = ''"
                        class="px-2.5 py-1 rounded-full text-[10px] font-bold whitespace-nowrap cursor-pointer"
                        :class="selectedZone === '' ? 'bg-[#10A760] text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                    >Semua</button>
                    <template x-for="z in graphZones" :key="z">
                        <button
                            type="button"
                            @click="selectedZone = z"
                            class="px-2.5 py-1 rounded-full text-[10px] font-bold whitespace-nowrap cursor-pointer"
                            :class="selectedZone === z ? 'bg-[#10A760] text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                            x-text="z"
                        ></button>
                    </template>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-dark-scroll">
                <template x-for="sc in filteredTeleportScenes" :key="sc.id">
                    <button
                        type="button"
                        @click="jumpTo(sc.id); teleportOpen = false"
                        class="w-full text-left px-3 py-2 rounded-xl flex items-center justify-between gap-2 transition-colors cursor-pointer"
                        :class="sc.id === currentSceneId ? 'bg-[#10A760]/30 border border-emerald-400/50' : 'hover:bg-white/10'"
                    >
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-white truncate" x-text="sc.label"></div>
                            <div class="text-[10px] text-slate-400" x-text="sc.zone + ' • Lantai ' + sc.floor + ' • ' + sc.hotspots.length + ' arah'"></div>
                        </div>
                        <span class="px-2 py-0.5 rounded bg-slate-800 text-emerald-300 font-mono text-[10px] font-bold shrink-0" x-text="'#' + sc.id"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- MINI PATH FINDER POPOVER -->
        <div
            data-hud
            x-show="pathPanelOpen"
            x-cloak
            style="display: none;"
            class="absolute top-16 right-3 z-40 w-[92vw] sm:w-96 max-h-[65vh] bg-slate-900/95 backdrop-blur-xl border border-amber-400/30 rounded-2xl shadow-2xl flex flex-col overflow-hidden"
        >
            <div class="p-3 border-b border-white/10 flex items-center justify-between">
                <span class="text-xs font-extrabold text-amber-300"><i class="fa-solid fa-route mr-1"></i> Arahkan Jalan ke Ruangan...</span>
                <button type="button" @click="pathPanelOpen = false" class="text-slate-400 hover:text-white cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-3 space-y-2 border-b border-white/10">
                <input
                    id="fib-path-search"
                    type="text"
                    x-model="pathSearchQuery"
                    placeholder="Cari ruangan tujuan (H-208, F-203, K-302)..."
                    class="w-full px-3 py-1.5 rounded-lg bg-slate-800 border border-white/15 text-xs text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-400"
                />
            </div>
            <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-dark-scroll">
                <template x-for="sc in filteredPathTargets" :key="'pt-' + sc.id">
                    <button
                        type="button"
                        @click="setPathTarget(sc.id); pathPanelOpen = false"
                        class="w-full text-left px-3 py-2 rounded-xl hover:bg-amber-500/20 flex items-center justify-between gap-2 transition-colors cursor-pointer"
                    >
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-white truncate" x-text="sc.label"></div>
                            <div class="text-[10px] text-slate-400" x-text="sc.zone + ' • Lantai ' + sc.floor"></div>
                        </div>
                        <span class="text-[10px] font-bold text-amber-300 shrink-0" x-text="'#' + sc.id"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- ULTRA-CLEAN BOTTOM HUD (NO DUPLICATE HOTSPOT BUTTONS) -->
        <div data-hud class="absolute bottom-3 inset-x-3 z-30 flex items-end justify-between gap-2 pointer-events-none">
            <!-- Bottom-Left: Compact Step Back + Multi-Floor Switcher (Gedung F/H/K) -->
            <div class="pointer-events-auto flex flex-wrap items-center gap-2">
                <template x-if="historyStack.length > 0">
                    <button
                        type="button"
                        id="fib-step-back-btn"
                        @click="goBack()"
                        class="px-3 py-1.5 rounded-xl bg-slate-900/85 hover:bg-slate-800 backdrop-blur-md border border-white/15 text-slate-100 text-xs font-bold shadow-xl flex items-center gap-1.5 cursor-pointer transition-colors"
                        title="Kembali ke titik sebelumnya (S / ↓)"
                    >
                        <i class="fa-solid fa-rotate-left text-emerald-400"></i>
                        <span>Langkah Sebelumnya</span>
                    </button>
                </template>

                <template x-if="availableFloorsInZone.length > 1">
                    <div class="bg-slate-900/85 backdrop-blur-md border border-white/15 rounded-xl p-1 flex items-center gap-1 shadow-xl">
                        <template x-for="fl in availableFloorsInZone" :key="fl">
                            <button
                                type="button"
                                @click="switchFloorInCurrentZone(fl)"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                                :class="Number(currentScene?.floor) === Number(fl) ? 'bg-[#10A760] text-white' : 'text-slate-300 hover:bg-white/10'"
                                x-text="'Lt. ' + fl"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Bottom-Right: Zoom +/- & Brief Navigation Hint -->
            <div class="pointer-events-auto flex items-center gap-2">
                <div class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/75 backdrop-blur-md border border-white/15 text-[11px] text-slate-300 shadow-xl">
                    <span>Drag: Lihat 360°</span>
                    <span class="text-white/30">•</span>
                    <span>W/↑: Maju</span>
                    <span class="text-white/30">•</span>
                    <span>S/↓: Mundur</span>
                </div>
                <div class="flex items-center bg-slate-900/85 backdrop-blur-md border border-white/15 rounded-xl overflow-hidden shadow-xl">
                    <button
                        type="button"
                        id="fib-zoom-in-btn"
                        @click="adjustZoom(-12)"
                        class="w-8 h-8 flex items-center justify-center text-sm font-bold text-white hover:bg-white/10 cursor-pointer"
                        title="Perbesar (Zoom In)"
                    >+</button>
                    <div class="w-px h-4 bg-white/15"></div>
                    <button
                        type="button"
                        id="fib-zoom-out-btn"
                        @click="adjustZoom(12)"
                        class="w-8 h-8 flex items-center justify-center text-sm font-bold text-white hover:bg-white/10 cursor-pointer"
                        title="Perkecil (Zoom Out)"
                    >−</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ADMIN CALIBRATION & GRAPH EDITOR PANEL -->
    @if($isAdminMode)
    <aside
        x-show="adminSidebarOpen"
        class="w-80 sm:w-96 h-full bg-slate-900 border-l border-white/15 flex flex-col z-40 shrink-0 shadow-2xl"
    >
        <div class="p-4 border-b border-white/10 bg-slate-950/60 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-amber-300 flex items-center gap-2">
                    <i class="fa-solid fa-crosshairs"></i>
                    <span>Kalibrasi Panah Jalan FIB</span>
                </h2>
                <p class="text-[11px] text-slate-400 mt-0.5">Arahkan kamera lalu klik <strong>Set ke Tengah Kamera</strong></p>
            </div>
            <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono text-xs font-bold" x-text="'#' + currentSceneId"></span>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-5 custom-dark-scroll">
            <!-- Scene Metadata Form -->
            <div class="bg-slate-800/70 rounded-2xl p-3.5 border border-white/10 space-y-2.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-300">Identitas Titik Saat Ini</div>
                <div>
                    <label class="text-[11px] text-slate-400">Nama / Label Lokasi</label>
                    <input type="text" x-model="editForm.label" class="w-full mt-1 px-2.5 py-1.5 rounded-lg bg-slate-900 border border-white/15 text-xs text-white" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[11px] text-slate-400">Zona</label>
                        <input type="text" x-model="editForm.zone" class="w-full mt-1 px-2.5 py-1.5 rounded-lg bg-slate-900 border border-white/15 text-xs text-white" />
                    </div>
                    <div>
                        <label class="text-[11px] text-slate-400">Lantai & Tipe</label>
                        <div class="flex gap-1 mt-1">
                            <input type="number" min="1" max="5" x-model.number="editForm.floor" class="w-14 px-2 py-1.5 rounded-lg bg-slate-900 border border-white/15 text-xs text-white" />
                            <select x-model="editForm.type" class="flex-1 px-2 py-1.5 rounded-lg bg-slate-900 border border-white/15 text-xs text-white">
                                <option value="corridor">Lorong</option>
                                <option value="room">Ruangan</option>
                                <option value="stairs">Tangga</option>
                                <option value="junction">Persimpangan</option>
                                <option value="outdoor">Outdoor</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-2 pt-1">
                    <div class="text-[11px] font-mono text-slate-300">
                        Default: Yaw <span class="text-emerald-300" x-text="editForm.default_yaw"></span>° | Pitch <span class="text-emerald-300" x-text="editForm.default_pitch"></span>°
                    </div>
                    <button
                        type="button"
                        @click="setDefaultViewToCamera()"
                        class="px-2.5 py-1 rounded-lg bg-slate-700 hover:bg-slate-600 text-amber-200 text-[11px] font-bold cursor-pointer"
                    >
                        Set Awal ke Kamera
                    </button>
                </div>
            </div>

            <!-- Outgoing Hotspots Calibration List -->
            <div class="space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-300">Panah Keluar (<span x-text="editForm.hotspots.length"></span>)</span>
                    <button
                        type="button"
                        @click="saveCurrentScene()"
                        :disabled="isSaving"
                        class="px-3 py-1.5 rounded-xl bg-[#10A760] hover:bg-[#0D8A4E] text-white text-xs font-extrabold shadow cursor-pointer disabled:opacity-60"
                    >
                        <i class="fa-solid fa-floppy-disk mr-1"></i>
                        <span x-text="isSaving ? 'Menyimpan...' : 'Simpan Kalibrasi'"></span>
                    </button>
                </div>

                <template x-for="(hs, idx) in editForm.hotspots" :key="'ed-' + hs.target_id">
                    <div class="bg-slate-800/80 rounded-xl p-3 border border-white/10 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-white truncate" x-text="'#' + hs.target_id + ' • ' + (scenes[String(hs.target_id)]?.label || hs.label)"></span>
                            <select x-model="hs.kind" class="px-2 py-0.5 rounded bg-slate-900 border border-white/15 text-[10px] text-slate-200">
                                <option value="walk">Jalan</option>
                                <option value="room">Masuk Kelas</option>
                                <option value="stairs_up">Naik Tangga</option>
                                <option value="stairs_down">Turun Tangga</option>
                            </select>
                        </div>
                        <input type="text" x-model="hs.label" class="w-full px-2.5 py-1 rounded-lg bg-slate-900 border border-white/15 text-xs text-white" />
                        <div class="grid grid-cols-2 gap-2 text-[11px] font-mono">
                            <label class="flex items-center gap-1 bg-slate-900 px-2 py-1 rounded">
                                <span class="text-slate-400">Yaw:</span>
                                <input type="number" step="0.1" x-model.number="hs.yaw" class="w-full bg-transparent text-emerald-300 focus:outline-none" />
                            </label>
                            <label class="flex items-center gap-1 bg-slate-900 px-2 py-1 rounded">
                                <span class="text-slate-400">Pitch:</span>
                                <input type="number" step="0.1" x-model.number="hs.pitch" class="w-full bg-transparent text-emerald-300 focus:outline-none" />
                            </label>
                        </div>
                        <div class="flex items-center gap-1.5 pt-0.5">
                            <button
                                type="button"
                                @click="setHotspotToCamera(idx)"
                                class="flex-1 py-1.5 px-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-[11px] font-extrabold cursor-pointer"
                            >
                                <i class="fa-solid fa-crosshairs mr-1"></i> Set ke Tengah Kamera
                            </button>
                            <button
                                type="button"
                                @click="walkTo(hs.target_id, hs)"
                                class="py-1.5 px-2.5 rounded-lg bg-emerald-600/30 hover:bg-emerald-600 text-emerald-200 hover:text-white text-[11px] font-bold cursor-pointer"
                                title="Uji Jalan ke Tujuan"
                            >
                                Uji
                            </button>
                            <button
                                type="button"
                                @click="removeLink(hs.target_id)"
                                class="py-1.5 px-2 rounded-lg bg-rose-600/30 hover:bg-rose-600 text-rose-200 hover:text-white text-[11px] font-bold cursor-pointer"
                                title="Hapus Koneksi Dua Arah"
                            >
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Add New Bidirectional Link Form -->
            <div class="bg-slate-800/70 rounded-2xl p-3.5 border border-emerald-500/30 space-y-2.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-300">+ Tambah Jalur ke Foto Lain</div>
                <select x-model="newLink.to_id" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900 border border-white/15 text-xs text-white">
                    <option value="">-- Pilih Foto Tujuan (428–495) --</option>
                    <template x-for="sc in allScenesList" :key="'opt-' + sc.id">
                        <option :value="sc.id" x-show="sc.id !== currentSceneId" x-text="'#' + sc.id + ' — ' + sc.label + ' (' + sc.zone + ')'"></option>
                    </template>
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select x-model="newLink.kind" class="px-2.5 py-1.5 rounded-lg bg-slate-900 border border-white/15 text-xs text-white">
                        <option value="walk">Panah Jalan</option>
                        <option value="room">Masuk Ruangan</option>
                        <option value="stairs_up">Naik Tangga</option>
                        <option value="stairs_down">Turun Tangga</option>
                    </select>
                    <button
                        type="button"
                        @click="addLink()"
                        :disabled="!newLink.to_id || isSaving"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold cursor-pointer disabled:opacity-50"
                    >
                        Tambah Jalur
                    </button>
                </div>
            </div>
        </div>

        <!-- Admin Save Toast Status -->
        <template x-if="statusNotice">
            <div class="p-3 bg-emerald-950/90 border-t border-emerald-500/40 text-xs text-emerald-200 font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-400"></i>
                <span x-text="statusNotice"></span>
            </div>
        </template>
    </aside>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>
<script id="fib-walk-boot" type="application/json">@json(['isAdminMode' => $isAdminMode, 'initialSceneId' => $initialSceneId, 'graph' => $graph])</script>
<script>
    window.fibVirtualWalkApp = function () {
        let boot = { isAdminMode: false, initialSceneId: 495, graph: { scenes: {}, zones: [] } };
        try {
            const raw = document.getElementById('fib-walk-boot')?.textContent;
            if (raw) boot = JSON.parse(raw);
        } catch (e) {}

        let panoViewer = null;
        const preloadedCache = new Map();
        const MAX_PRELOAD_CACHE = 12;
        let activeKeydownHandler = null;
        let angleSyncTimer = null;
        let inertiaRaf = null;
        let morphFinishTimer = null;
        let stepLockTimer = null;
        let autoWalkTimer = null;
        let autoWalkNoticeTimer = null;
        let rippleTimer = null;

        function wrapYaw(deg) {
            const w = ((((Number(deg) || 0) + 180) % 360) + 360) % 360;
            return w - 180;
        }

        function angularDiff(a, b) {
            return Math.abs(wrapYaw(Number(a) - Number(b)));
        }

        function detectWebGLSupport() {
            try {
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl2') || canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (!gl) return false;
                const maxTex = Number(gl.getParameter(gl.MAX_TEXTURE_SIZE) || 0);
                const ok = maxTex >= 4096;
                try {
                    const ext = gl.getExtension('WEBGL_lose_context');
                    if (ext) ext.loseContext();
                } catch (e) {}
                return ok;
            } catch (e) {
                return false;
            }
        }

        function chevronSvgMarkup() {
            return '<svg class="w-8 h-8 drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.9" d="M6 15l6-6 6 6"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" stroke-opacity="0.7" d="M8 19l4-4 4 4"/></svg>';
        }

        function roomDoorSvgMarkup() {
            return '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 16V4a1 1 0 00-1-1H6a1 1 0 00-1 1v16m8 0h2m-2 0H4m9-8h7m-3-3l3 3-3 3"/></svg>';
        }

        function stairsSvgMarkup(kind) {
            if (kind === 'stairs_down') {
                return '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 6h4v4h4v4h4v4h4M17 10l-5 5m0 0v-4m0 4h4"/></svg>';
            }
            return '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 18h4v-4h4v-4h4V6h4M7 10l5-5m0 0v4m0-4H8"/></svg>';
        }

        function extractShortCode(hs) {
            const label = String(hs?.label || '');
            const m = label.match(/(O\.\d+(?:\s*&\s*O\.\d+)?|[ABFHIKM]-\d+(?:[–-]\d+|\s*&\s*[ABFHIKM]-\d+)?)/i);
            if (m) return m[1];
            if (hs?.kind === 'stairs_up' || hs?.kind === 'stairs_down') {
                const fl = label.match(/Lantai\s*(\d+)/i);
                return fl ? ('Lt. ' + fl[1]) : 'Tangga';
            }
            return label.slice(0, 14);
        }

        return {
            isAdminMode: !!boot.isAdminMode,
            scenes: boot.graph?.scenes || {},
            graphZones: boot.graph?.zones || [],
            currentSceneId: Number(boot.initialSceneId || 495),
            lastSceneId: null,
            historyStack: [],
            useFallback: false,

            // Fallback Seamless Infinite 360° Engine State
            fallbackYaw: 0,
            fallbackPitch: 0,
            fallbackHfov: 100,
            vpWidth: typeof window !== 'undefined' ? window.innerWidth : 1366,
            vpHeight: typeof window !== 'undefined' ? window.innerHeight : 768,
            isDraggingFallback: false,
            dragStartX: 0,
            dragStartY: 0,
            velYaw: 0,
            velPitch: 0,

            // Pointer Click-to-Walk State
            vpDownX: 0,
            vpDownY: 0,
            vpDownIgnore: true,
            clickRipple: null,

            // Morph Transition & Auto-Walk State
            isStepping: false,
            morphOverlayActive: false,
            morphOverlayAnimating: false,
            morphOverlayStyle: '',
            isMorphingIn: false,
            autoWalking: false,
            autoWalkNotice: '',
            autoWalkVisited: [],

            // HUD Popovers & Path Finder
            teleportOpen: false,
            teleportQuery: '',
            selectedZone: '',
            pathPanelOpen: false,
            pathSearchQuery: '',
            guideTargetId: null,
            guidedPath: [],

            // Admin Calibration State
            adminSidebarOpen: !!boot.isAdminMode,
            liveYaw: 0,
            livePitch: 0,
            isSaving: false,
            statusNotice: '',
            newLink: { to_id: '', kind: 'walk' },
            editForm: {
                label: '',
                zone: '',
                floor: 1,
                type: 'corridor',
                default_yaw: 0,
                default_pitch: 0,
                hotspots: []
            },

            get currentScene() {
                return this.scenes[String(this.currentSceneId)] || null;
            },

            get currentHotspots() {
                return this.currentScene?.hotspots || [];
            },

            get allScenesList() {
                return Object.values(this.scenes).sort((a, b) => Number(a.id) - Number(b.id));
            },

            get availableFloorsInZone() {
                const zone = this.currentScene?.zone;
                if (!zone) return [];
                const floors = new Set();
                this.allScenesList.forEach(s => {
                    if (s.zone === zone) floors.add(Number(s.floor || 1));
                });
                return Array.from(floors).sort((a, b) => a - b);
            },

            get filteredTeleportScenes() {
                const q = this.teleportQuery.trim().toLowerCase();
                return this.allScenesList.filter(sc => {
                    if (this.selectedZone && sc.zone !== this.selectedZone) return false;
                    if (!q) return true;
                    return String(sc.id).includes(q)
                        || (sc.label || '').toLowerCase().includes(q)
                        || (sc.zone || '').toLowerCase().includes(q);
                });
            },

            get filteredPathTargets() {
                const q = this.pathSearchQuery.trim().toLowerCase();
                return this.allScenesList.filter(sc => {
                    if (sc.id === this.currentSceneId) return false;
                    if (!q) return true;
                    return String(sc.id).includes(q)
                        || (sc.label || '').toLowerCase().includes(q)
                        || (sc.zone || '').toLowerCase().includes(q);
                });
            },

            get projectedFallbackHotspots() {
                if (!this.useFallback) return [];
                const vw = Math.max(320, this.vpWidth);
                const vh = Math.max(240, this.vpHeight);
                const hfov = this.fallbackHfov;
                const stripW = vw * (360 / hfov);
                const stripH = Math.max(vh * 1.28, stripW * 0.5);
                const rawBgY = (vh - stripH) / 2 + (this.fallbackPitch / 180) * stripH;
                const bgY = Math.max(vh - stripH, Math.min(0, rawBgY));

                const items = [];
                this.currentHotspots.forEach(hs => {
                    const dYaw = wrapYaw(Number(hs.yaw || 0) - this.fallbackYaw);
                    if (Math.abs(dYaw) > (hfov * 0.5) + 12) return;

                    const xPct = 50 + (dYaw / hfov) * 100;
                    const pitchFrac = (90 - Number(hs.pitch ?? -18)) / 180;
                    const pxY = bgY + (pitchFrac * stripH);
                    const yPct = Math.max(12, Math.min(88, (pxY / vh) * 100));

                    items.push({
                        hs,
                        style: `position:absolute; left:${xPct.toFixed(2)}%; top:${yPct.toFixed(2)}%; z-index:${hs.kind === 'walk' ? 22 : 25};`
                    });
                });
                return items;
            },

            chevronSvg() { return chevronSvgMarkup(); },
            roomDoorSvg() { return roomDoorSvgMarkup(); },
            stairsSvg(kind) { return stairsSvgMarkup(kind); },
            shortPinLabel(hs) { return extractShortCode(hs); },

            init() {
                const initSc = this.currentScene;
                this.fallbackYaw = Number(initSc?.default_yaw || 0);
                this.fallbackPitch = Number(initSc?.default_pitch || 0);
                this.liveYaw = wrapYaw(this.fallbackYaw);
                this.livePitch = this.fallbackPitch;

                this.syncEditForm();
                this.measureViewport();
                window.addEventListener('resize', () => this.measureViewport());

                if (!detectWebGLSupport() || typeof window.pannellum === 'undefined') {
                    this.useFallback = true;
                } else {
                    this.initSingleMultiScenePannellum();
                }

                // Live compass & admin yaw/pitch sync timer (lightweight 140ms)
                if (angleSyncTimer) clearInterval(angleSyncTimer);
                angleSyncTimer = setInterval(() => this.updateLiveAngles(), 140);

                this.bindKeyboardNavigation();
                this.preloadCorridor(this.currentSceneId);
            },

            measureViewport() {
                const el = document.getElementById('fib-viewport-layer');
                if (!el) return;
                const rect = el.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0) {
                    this.vpWidth = rect.width;
                    this.vpHeight = rect.height;
                }
            },

            initSingleMultiScenePannellum() {
                const self = this;
                const sceneConfigs = {};
                this.allScenesList.forEach(sc => {
                    sceneConfigs[String(sc.id)] = {
                        title: sc.label,
                        type: 'equirectangular',
                        panorama: sc.pano_url || sc.image_url,
                        haov: 360,
                        vaov: 180,
                        pitch: Number(sc.default_pitch || 0),
                        yaw: Number(sc.default_yaw || 0),
                        hfov: 100,
                        minHfov: 48,
                        maxHfov: 118,
                        hotSpots: self.buildPannellumHotspots(sc)
                    };
                });

                try {
                    panoViewer = window.pannellum.viewer('fib-pano-viewer', {
                        default: {
                            firstScene: String(this.currentSceneId),
                            sceneFadeDuration: 420,
                            autoLoad: true,
                            showControls: false,
                            showZoomCtrl: false,
                            compass: false,
                            friction: 0.15,
                            keyboardZoom: false,
                            mouseZoom: true
                        },
                        scenes: sceneConfigs
                    });

                    panoViewer.on('scenechange', (newSceneId) => {
                        const numId = Number(newSceneId);
                        if (Number.isFinite(numId) && self.scenes[String(numId)]) {
                            self.currentSceneId = numId;
                            self.syncEditForm();
                            self.recomputeGuidedPath();
                            self.applyGuidedBadgesToDom();
                            self.updateLiveAngles();
                            self.preloadCorridor(numId);
                        }
                    });

                    panoViewer.on('load', () => {
                        self.applyGuidedBadgesToDom();
                        self.updateLiveAngles();
                        self.preloadCorridor(self.currentSceneId);
                    });

                    panoViewer.on('error', () => {
                        self.activateFallbackMode();
                    });

                    this.$nextTick(() => {
                        const container = document.getElementById('fib-pano-viewer');
                        const canvas = container ? container.querySelector('canvas') : null;
                        if (canvas) {
                            canvas.addEventListener('webglcontextlost', (ev) => {
                                ev.preventDefault();
                                self.activateFallbackMode();
                            });
                        }
                    });
                } catch (err) {
                    console.warn('[FIB Walk] WebGL init error, activating infinite loop fallback:', err);
                    this.activateFallbackMode();
                }
            },

            activateFallbackMode() {
                if (panoViewer) {
                    try {
                        this.fallbackYaw = Number(panoViewer.getYaw?.() ?? this.fallbackYaw);
                        this.fallbackPitch = Math.max(-45, Math.min(45, Number(panoViewer.getPitch?.() ?? this.fallbackPitch)));
                        this.fallbackHfov = Math.max(52, Math.min(115, Number(panoViewer.getHfov?.() ?? 100)));
                    } catch (e) {}
                    if (typeof panoViewer.destroy === 'function') {
                        try { panoViewer.destroy(); } catch (e) {}
                    }
                }
                panoViewer = null;
                this.useFallback = true;
                this.measureViewport();
                this.updateLiveAngles();
            },

            buildPannellumHotspots(sc) {
                const self = this;
                return (sc.hotspots || []).map(hs => ({
                    id: `hs-${sc.id}-${hs.target_id}`,
                    pitch: Number(hs.pitch ?? (hs.kind === 'room' ? -2 : -20)),
                    yaw: Number(hs.yaw ?? 0),
                    type: 'custom',
                    cssClass: 'fib-walk-hotspot',
                    createTooltipFunc: (div, args) => self.renderHotspotDom(div, args.hotspot),
                    createTooltipArgs: { hotspot: hs },
                    clickHandlerFunc: (evt, args) => {
                        if (evt && typeof evt.stopPropagation === 'function') evt.stopPropagation();
                        self.walkTo(args.hotspot.target_id, args.hotspot, true);
                    },
                    clickHandlerArgs: { hotspot: hs }
                }));
            },

            renderHotspotDom(div, hs) {
                div.innerHTML = '';
                const kind = hs.kind || 'walk';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'fib-spatial-hotspot' + (kind === 'walk' ? ' is-walk-hotspot' : '');
                btn.setAttribute('data-walk-hotspot', '');
                btn.setAttribute('data-hotspot-target', String(hs.target_id));
                btn.setAttribute('data-target-id', String(hs.target_id));
                btn.setAttribute('data-kind', kind);
                if (this.isGuidedNext(hs.target_id)) {
                    btn.classList.add('is-guided-next');
                }

                const tip = document.createElement('div');
                tip.className = 'fib-hotspot-tooltip';
                tip.textContent = hs.label || ('Titik #' + hs.target_id);
                btn.appendChild(tip);

                if (kind === 'walk') {
                    const chev = document.createElement('div');
                    chev.className = 'fib-ground-chevron';
                    chev.innerHTML = chevronSvgMarkup();
                    btn.appendChild(chev);
                } else if (kind === 'room') {
                    const wrap = document.createElement('div');
                    wrap.className = 'fib-room-pin-wrap';
                    const pin = document.createElement('div');
                    pin.className = 'fib-room-pin';
                    pin.innerHTML = roomDoorSvgMarkup();
                    const badge = document.createElement('span');
                    badge.className = 'fib-pin-badge';
                    badge.textContent = extractShortCode(hs);
                    wrap.appendChild(pin);
                    wrap.appendChild(badge);
                    btn.appendChild(wrap);
                } else {
                    const wrap = document.createElement('div');
                    wrap.className = 'fib-room-pin-wrap';
                    const pin = document.createElement('div');
                    pin.className = 'fib-stairs-pin';
                    pin.innerHTML = stairsSvgMarkup(kind);
                    const badge = document.createElement('span');
                    badge.className = 'fib-pin-badge';
                    badge.textContent = extractShortCode(hs);
                    wrap.appendChild(pin);
                    wrap.appendChild(badge);
                    btn.appendChild(wrap);
                }

                div.appendChild(btn);
            },

            currentCameraYaw() {
                if (!this.useFallback && panoViewer && typeof panoViewer.getYaw === 'function') {
                    return Number(panoViewer.getYaw() || 0);
                }
                return Number(this.fallbackYaw || 0);
            },

            currentCameraPitch() {
                if (!this.useFallback && panoViewer && typeof panoViewer.getPitch === 'function') {
                    return Number(panoViewer.getPitch() || 0);
                }
                return Number(this.fallbackPitch || 0);
            },

            currentCameraHfov() {
                if (!this.useFallback && panoViewer && typeof panoViewer.getHfov === 'function') {
                    return Number(panoViewer.getHfov() || 100);
                }
                return Number(this.fallbackHfov || 100);
            },

            computeBgStyle(imageUrl, yaw, pitch, hfov) {
                const vw = Math.max(320, this.vpWidth);
                const vh = Math.max(240, this.vpHeight);
                const safeHfov = Math.max(48, Math.min(118, Number(hfov) || 100));
                const stripW = vw * (360 / safeHfov);
                const stripH = Math.max(vh * 1.28, stripW * 0.5);
                const u = (wrapYaw(yaw) + 180) / 360;
                const bgX = (vw / 2) - (u * stripW);
                const rawBgY = (vh - stripH) / 2 + ((Number(pitch) || 0) / 180) * stripH;
                const bgY = Math.max(vh - stripH, Math.min(0, rawBgY));
                return `background-image:url('${imageUrl}'); background-size:${stripW.toFixed(1)}px ${stripH.toFixed(1)}px; background-position:${bgX.toFixed(1)}px ${bgY.toFixed(1)}px;`;
            },

            fallbackSurfaceStyle() {
                const sc = this.currentScene;
                if (!sc) return '';
                const url = sc.pano_url || sc.image_url || '';
                return this.computeBgStyle(url, this.fallbackYaw, this.fallbackPitch, this.fallbackHfov);
            },

            walkTo(targetId, hotspot = null, pushHistory = true, options = {}) {
                const numTarget = Number(targetId);
                const targetScene = this.scenes[String(numTarget)];
                if (!targetScene || numTarget === this.currentSceneId) return;
                if (this.isStepping && !options.force) return;

                if (!options.fromAutoWalk && this.autoWalking) {
                    this.stopAutoWalk();
                }

                this.isStepping = true;
                if (stepLockTimer) clearTimeout(stepLockTimer);
                stepLockTimer = setTimeout(() => { this.isStepping = false; }, 650);

                if (inertiaRaf) {
                    cancelAnimationFrame(inertiaRaf);
                    inertiaRaf = null;
                }

                const originId = this.currentSceneId;
                const oldScene = this.currentScene;
                const oldPanoUrl = oldScene?.pano_url || oldScene?.image_url || '';
                const oldPitch = this.currentCameraPitch();
                const oldHfov = this.currentCameraHfov();

                this.lastSceneId = originId;
                if (pushHistory && originId !== numTarget) {
                    this.historyStack.push(originId);
                    if (this.historyStack.length > 40) this.historyStack.shift();
                }

                // Compute arrival heading preserving forward corridor continuity
                let tPitch = Number(targetScene.default_pitch || 0);
                let tYaw = Number(targetScene.default_yaw || 0);
                if (hotspot && hotspot.target_yaw !== undefined) {
                    tYaw = Number(hotspot.target_yaw);
                    if (hotspot.target_pitch !== undefined) tPitch = Number(hotspot.target_pitch);
                } else if (!pushHistory) {
                    const incoming = (targetScene.hotspots || []).find(h => Number(h.target_id) === Number(originId));
                    if (incoming) tYaw = wrapYaw(Number(incoming.yaw) + 180);
                }

                const stepAimYaw = hotspot ? Number(hotspot.yaw) : this.currentCameraYaw();
                const stepAimPitch = hotspot ? Number(hotspot.pitch) : oldPitch;

                const executeMorphTransition = () => {
                    // Snapshot current panorama into #fib-morph-overlay before swapping scene underneath
                    if (oldPanoUrl) {
                        this.morphOverlayStyle = this.computeBgStyle(oldPanoUrl, this.currentCameraYaw(), this.currentCameraPitch(), this.currentCameraHfov());
                        this.morphOverlayAnimating = false;
                        this.morphOverlayActive = true;
                        requestAnimationFrame(() => {
                            this.morphOverlayAnimating = true;
                        });
                    }

                    this.isMorphingIn = false;
                    requestAnimationFrame(() => {
                        this.isMorphingIn = true;
                    });

                    if (morphFinishTimer) clearTimeout(morphFinishTimer);
                    morphFinishTimer = setTimeout(() => {
                        this.morphOverlayActive = false;
                        this.morphOverlayAnimating = false;
                        this.isMorphingIn = false;
                        this.isStepping = false;
                    }, 430);

                    if (this.useFallback || !panoViewer) {
                        this.currentSceneId = numTarget;
                        this.fallbackYaw = tYaw;
                        this.fallbackPitch = Math.max(-45, Math.min(45, tPitch));
                        this.syncEditForm();
                        this.recomputeGuidedPath();
                        this.updateLiveAngles();
                        this.preloadCorridor(numTarget);
                    } else {
                        try {
                            panoViewer.loadScene(String(numTarget), tPitch, tYaw, oldHfov);
                        } catch (err) {
                            this.currentSceneId = numTarget;
                            this.syncEditForm();
                            this.recomputeGuidedPath();
                        }
                    }

                    if (this.guideTargetId && numTarget === Number(this.guideTargetId) && this.autoWalking) {
                        this.stopAutoWalk(`Tiba di ${targetScene.label}!`);
                    }
                };

                // Stage 1: Smooth short camera alignment toward clicked arrow (~165ms)
                if (hotspot && !options.skipAim) {
                    if (!this.useFallback && panoViewer && typeof panoViewer.lookAt === 'function') {
                        panoViewer.lookAt(stepAimPitch * 0.4, stepAimYaw, Math.max(58, oldHfov - 10), 170);
                        setTimeout(executeMorphTransition, 155);
                    } else {
                        this.animateFallbackYawToward(stepAimYaw, 155, executeMorphTransition);
                    }
                } else {
                    executeMorphTransition();
                }
            },

            animateFallbackYawToward(targetYaw, durationMs, done) {
                const startYaw = this.fallbackYaw;
                const diff = wrapYaw(targetYaw - startYaw);
                if (Math.abs(diff) < 3) {
                    done();
                    return;
                }
                const t0 = performance.now();
                const tick = (now) => {
                    const p = Math.min(1, (now - t0) / durationMs);
                    const ease = 1 - Math.pow(1 - p, 2);
                    this.fallbackYaw = startYaw + diff * ease;
                    this.updateLiveAngles();
                    if (p < 1) {
                        requestAnimationFrame(tick);
                    } else {
                        done();
                    }
                };
                requestAnimationFrame(tick);
            },

            jumpTo(targetId) {
                this.stopAutoWalk();
                this.walkTo(targetId, null, true, { skipAim: true });
            },

            goBack() {
                if (this.historyStack.length === 0) return;
                this.stopAutoWalk();
                const prevId = this.historyStack.pop();
                this.walkTo(prevId, null, false, { skipAim: true });
            },

            switchFloorInCurrentZone(targetFloor) {
                const zone = this.currentScene?.zone;
                if (!zone) return;
                const match = this.allScenesList.find(sc => sc.zone === zone && Number(sc.floor) === Number(targetFloor));
                if (match) this.jumpTo(match.id);
            },

            resetCameraToDefault() {
                const sc = this.currentScene;
                if (!sc) return;
                const yaw = Number(sc.default_yaw || 0);
                const pitch = Number(sc.default_pitch || 0);
                if (!this.useFallback && panoViewer && typeof panoViewer.lookAt === 'function') {
                    panoViewer.lookAt(pitch, yaw, 100, 260);
                } else {
                    this.fallbackYaw = yaw;
                    this.fallbackPitch = pitch;
                    this.fallbackHfov = 100;
                }
                this.updateLiveAngles();
            },

            adjustZoom(delta) {
                if (!this.useFallback && panoViewer && typeof panoViewer.getHfov === 'function') {
                    const next = Math.max(48, Math.min(118, panoViewer.getHfov() + delta));
                    panoViewer.setHfov(next, 160);
                } else {
                    this.fallbackHfov = Math.max(52, Math.min(115, this.fallbackHfov + delta));
                }
            },

            // =====================================================================
            // Continuous Auto-Walk (Guided BFS or Free Corridor Walk)
            // =====================================================================
            toggleAutoWalk() {
                if (this.autoWalking) {
                    this.stopAutoWalk();
                    return;
                }
                this.autoWalking = true;
                this.autoWalkVisited = [this.currentSceneId];
                this.showAutoWalkNotice(
                    this.guideTargetId ? 'Auto-Walk menuju tujuan aktif...' : 'Auto-Walk koridor aktif...'
                );
                this.runAutoWalkTick();
                if (autoWalkTimer) clearInterval(autoWalkTimer);
                autoWalkTimer = setInterval(() => this.runAutoWalkTick(), 1800);
            },

            stopAutoWalk(notice = '') {
                this.autoWalking = false;
                if (autoWalkTimer) {
                    clearInterval(autoWalkTimer);
                    autoWalkTimer = null;
                }
                if (notice) this.showAutoWalkNotice(notice);
            },

            showAutoWalkNotice(msg) {
                this.autoWalkNotice = msg;
                if (autoWalkNoticeTimer) clearTimeout(autoWalkNoticeTimer);
                autoWalkNoticeTimer = setTimeout(() => {
                    this.autoWalkNotice = '';
                }, 2600);
            },

            runAutoWalkTick() {
                if (!this.autoWalking || this.isStepping) return;

                if (this.guideTargetId) {
                    if (this.currentSceneId === Number(this.guideTargetId)) {
                        this.stopAutoWalk(`Tiba di ${this.currentScene?.label || 'tujuan'}!`);
                        return;
                    }
                    if (this.guidedPath.length >= 2) {
                        const nextId = Number(this.guidedPath[1]);
                        const hs = this.currentHotspots.find(h => Number(h.target_id) === nextId) || null;
                        this.autoWalkVisited.push(nextId);
                        this.walkTo(nextId, hs, true, { fromAutoWalk: true });
                        return;
                    }
                }

                // Free corridor auto-walk: prefer walk/stairs forward, avoid going backward or looping
                const camYaw = this.currentCameraYaw();
                const candidates = this.currentHotspots.filter(h =>
                    h.kind !== 'room'
                    && Number(h.target_id) !== Number(this.lastSceneId)
                    && !this.autoWalkVisited.includes(Number(h.target_id))
                );

                if (candidates.length === 0) {
                    this.stopAutoWalk('Auto-Walk selesai di ujung / persimpangan.');
                    return;
                }

                let best = candidates[0];
                let bestDiff = angularDiff(best.yaw, camYaw);
                candidates.forEach(h => {
                    const diff = angularDiff(h.yaw, camYaw);
                    if (diff < bestDiff) {
                        bestDiff = diff;
                        best = h;
                    }
                });

                this.autoWalkVisited.push(Number(best.target_id));
                this.walkTo(best.target_id, best, true, { fromAutoWalk: true });
            },

            // =====================================================================
            // Path Finder Helpers
            // =====================================================================
            setPathTarget(targetId) {
                this.guideTargetId = Number(targetId);
                this.recomputeGuidedPath();
                this.applyGuidedBadgesToDom();
            },

            clearGuidedPath() {
                this.stopAutoWalk();
                this.guideTargetId = null;
                this.guidedPath = [];
                this.applyGuidedBadgesToDom();
            },

            stepGuidedNext() {
                if (this.guidedPath.length < 2) return;
                const nextId = this.guidedPath[1];
                const hs = this.currentHotspots.find(h => Number(h.target_id) === Number(nextId)) || null;
                this.walkTo(nextId, hs, true);
            },

            isGuidedNext(targetId) {
                return this.guidedPath.length >= 2 && Number(this.guidedPath[1]) === Number(targetId);
            },

            recomputeGuidedPath() {
                if (!this.guideTargetId) {
                    this.guidedPath = [];
                    return;
                }
                const start = Number(this.currentSceneId);
                const goal = Number(this.guideTargetId);
                if (start === goal) {
                    this.guidedPath = [start];
                    return;
                }

                const queue = [[start]];
                const visited = new Set([start]);
                while (queue.length > 0) {
                    const path = queue.shift();
                    const curr = path[path.length - 1];
                    const sc = this.scenes[String(curr)];
                    for (const hs of (sc?.hotspots || [])) {
                        const nxt = Number(hs.target_id);
                        if (visited.has(nxt) || !this.scenes[String(nxt)]) continue;
                        const nextPath = [...path, nxt];
                        if (nxt === goal) {
                            this.guidedPath = nextPath;
                            return;
                        }
                        visited.add(nxt);
                        queue.push(nextPath);
                    }
                }
                this.guidedPath = [];
            },

            applyGuidedBadgesToDom() {
                const nextId = this.guidedPath.length >= 2 ? String(this.guidedPath[1]) : null;
                document.querySelectorAll('.fib-spatial-hotspot').forEach(el => {
                    if (nextId && el.getAttribute('data-hotspot-target') === nextId) {
                        el.classList.add('is-guided-next');
                    } else {
                        el.classList.remove('is-guided-next');
                    }
                });
            },

            // =====================================================================
            // 0ms Corridor 2-Hop Preloader (LRU Bounded to 12 4K Panos)
            // =====================================================================
            cachePreloadScene(sc) {
                const url = sc?.pano_url || sc?.image_url;
                if (!url) return;
                if (preloadedCache.has(url)) {
                    const existing = preloadedCache.get(url);
                    preloadedCache.delete(url);
                    preloadedCache.set(url, existing);
                    return;
                }
                if (preloadedCache.size >= MAX_PRELOAD_CACHE) {
                    const oldestKey = preloadedCache.keys().next().value;
                    preloadedCache.delete(oldestKey);
                }
                const img = new Image();
                img.decoding = 'async';
                img.src = url;
                if (typeof img.decode === 'function') {
                    img.decode().catch(() => {});
                }
                preloadedCache.set(url, img);
            },

            preloadCorridor(sceneId) {
                const sc = this.scenes[String(sceneId)];
                if (!sc) return;
                this.cachePreloadScene(sc);

                const neighbors = (sc.hotspots || []).slice(0, 6);
                neighbors.forEach(hs => {
                    const n1 = this.scenes[String(hs.target_id)];
                    if (!n1) return;
                    this.cachePreloadScene(n1);

                    // 2-hop lookahead along primary continuation of each neighbor
                    const hop2 = (n1.hotspots || []).find(h2 => Number(h2.target_id) !== Number(sceneId));
                    if (hop2) {
                        const n2 = this.scenes[String(hop2.target_id)];
                        if (n2) this.cachePreloadScene(n2);
                    }
                });
            },

            // =====================================================================
            // Keyboard & Click-to-Walk Navigation
            // =====================================================================
            bindKeyboardNavigation() {
                if (activeKeydownHandler) {
                    window.removeEventListener('keydown', activeKeydownHandler);
                }
                activeKeydownHandler = (e) => {
                    const tag = (e.target?.tagName || '').toLowerCase();
                    if (['input', 'textarea', 'select'].includes(tag)) return;

                    if (e.key === 'w' || e.key === 'W' || e.key === 'ArrowUp') {
                        if (this.guidedPath.length >= 2) {
                            e.preventDefault();
                            this.stepGuidedNext();
                            return;
                        }
                        const hotspots = this.currentHotspots;
                        if (hotspots.length > 0) {
                            e.preventDefault();
                            const camYaw = this.currentCameraYaw();
                            let best = hotspots[0];
                            let bestDiff = 999;
                            hotspots.forEach(h => {
                                const diff = angularDiff(h.yaw, camYaw);
                                if (diff < bestDiff) {
                                    bestDiff = diff;
                                    best = h;
                                }
                            });
                            this.walkTo(best.target_id, best, true);
                        }
                    } else if (e.key === 's' || e.key === 'S' || e.key === 'ArrowDown') {
                        if (this.historyStack.length > 0) {
                            e.preventDefault();
                            this.goBack();
                        }
                    } else if (e.key === 'a' || e.key === 'A' || e.key === 'ArrowLeft') {
                        e.preventDefault();
                        if (!this.useFallback && panoViewer && typeof panoViewer.setYaw === 'function') {
                            panoViewer.setYaw(panoViewer.getYaw() - 22);
                        } else {
                            this.fallbackYaw -= 22;
                        }
                        this.updateLiveAngles();
                    } else if (e.key === 'd' || e.key === 'D' || e.key === 'ArrowRight') {
                        e.preventDefault();
                        if (!this.useFallback && panoViewer && typeof panoViewer.setYaw === 'function') {
                            panoViewer.setYaw(panoViewer.getYaw() + 22);
                        } else {
                            this.fallbackYaw += 22;
                        }
                        this.updateLiveAngles();
                    } else if (e.key === 'Escape') {
                        this.stopAutoWalk();
                        this.teleportOpen = false;
                        this.pathPanelOpen = false;
                    }
                };
                window.addEventListener('keydown', activeKeydownHandler);
            },

            onViewportPointerDown(e) {
                this.vpDownX = e.clientX;
                this.vpDownY = e.clientY;
                const hitUi = e.target?.closest?.('[data-hud], .fib-spatial-hotspot, button, a, input, select, aside');
                this.vpDownIgnore = !!hitUi;
            },

            onViewportPointerUp(e) {
                if (this.vpDownIgnore) return;
                const dist = Math.hypot(e.clientX - this.vpDownX, e.clientY - this.vpDownY);
                if (dist >= 6) return;

                const el = document.getElementById('fib-viewport-layer');
                if (!el) return;
                const rect = el.getBoundingClientRect();
                const relX = (e.clientX - rect.left) / Math.max(1, rect.width);
                const relY = (e.clientY - rect.top) / Math.max(1, rect.height);
                if (relY < 0.48) return;

                const hotspots = this.currentHotspots;
                if (hotspots.length === 0) return;

                const clickYaw = wrapYaw(this.currentCameraYaw() + (relX - 0.5) * this.currentCameraHfov());
                let best = null;
                let bestDiff = 999;
                hotspots.forEach(hs => {
                    const diff = angularDiff(hs.yaw, clickYaw);
                    if (diff < bestDiff) {
                        bestDiff = diff;
                        best = hs;
                    }
                });

                if (best && bestDiff <= 35) {
                    this.clickRipple = { x: e.clientX, y: e.clientY };
                    if (rippleTimer) clearTimeout(rippleTimer);
                    rippleTimer = setTimeout(() => { this.clickRipple = null; }, 520);
                    this.walkTo(best.target_id, best, true);
                }
            },

            // =====================================================================
            // Seamless Infinite 360° Drag & Inertia (Fallback Engine)
            // =====================================================================
            startFallbackDrag(e) {
                if (e.target?.closest?.('.fib-spatial-hotspot, [data-hud], button')) return;
                this.measureViewport();
                if (inertiaRaf) {
                    cancelAnimationFrame(inertiaRaf);
                    inertiaRaf = null;
                }
                this.isDraggingFallback = true;
                this.dragStartX = e.clientX;
                this.dragStartY = e.clientY;
                this.velYaw = 0;
                this.velPitch = 0;
            },

            moveFallbackDrag(e) {
                if (!this.isDraggingFallback) return;
                const dx = e.clientX - this.dragStartX;
                const dy = e.clientY - this.dragStartY;
                this.dragStartX = e.clientX;
                this.dragStartY = e.clientY;

                const degPerPxX = this.fallbackHfov / Math.max(320, this.vpWidth);
                const degPerPxY = (this.fallbackHfov * 0.72) / Math.max(240, this.vpHeight);

                const dYaw = -dx * degPerPxX * 0.96;
                const dPitch = dy * degPerPxY * 0.85;

                this.fallbackYaw += dYaw;
                this.fallbackPitch = Math.max(-45, Math.min(45, this.fallbackPitch + dPitch));
                this.velYaw = dYaw;
                this.velPitch = dPitch;
                this.updateLiveAngles();
            },

            endFallbackDrag() {
                if (!this.isDraggingFallback) return;
                this.isDraggingFallback = false;

                const stepInertia = () => {
                    if (Math.abs(this.velYaw) < 0.04 && Math.abs(this.velPitch) < 0.02) {
                        inertiaRaf = null;
                        return;
                    }
                    this.fallbackYaw += this.velYaw;
                    this.fallbackPitch = Math.max(-45, Math.min(45, this.fallbackPitch + this.velPitch));
                    this.velYaw *= 0.88;
                    this.velPitch *= 0.84;
                    this.updateLiveAngles();
                    inertiaRaf = requestAnimationFrame(stepInertia);
                };
                if (Math.abs(this.velYaw) > 0.08 || Math.abs(this.velPitch) > 0.05) {
                    inertiaRaf = requestAnimationFrame(stepInertia);
                }
            },

            onFallbackWheel(e) {
                const delta = e.deltaY > 0 ? 6 : -6;
                this.fallbackHfov = Math.max(52, Math.min(115, this.fallbackHfov + delta));
            },

            // =====================================================================
            // Admin Calibration Helpers (Synchronized WebGL + Infinite Loop Fallback)
            // =====================================================================
            syncEditForm() {
                const sc = this.currentScene;
                if (!sc) return;
                this.editForm = {
                    label: sc.label,
                    zone: sc.zone,
                    floor: Number(sc.floor || 1),
                    type: sc.type || 'corridor',
                    default_yaw: Number(sc.default_yaw || 0),
                    default_pitch: Number(sc.default_pitch || 0),
                    hotspots: JSON.parse(JSON.stringify(sc.hotspots || []))
                };
            },

            updateLiveAngles() {
                if (!this.useFallback && panoViewer && typeof panoViewer.getYaw === 'function') {
                    this.liveYaw = wrapYaw(Number(panoViewer.getYaw() || 0));
                    this.livePitch = Number(panoViewer.getPitch() || 0);
                } else {
                    this.liveYaw = wrapYaw(this.fallbackYaw);
                    this.livePitch = Number(this.fallbackPitch || 0);
                }
            },

            setDefaultViewToCamera() {
                this.updateLiveAngles();
                this.editForm.default_yaw = Number(this.liveYaw.toFixed(1));
                this.editForm.default_pitch = Number(this.livePitch.toFixed(1));
                this.statusNotice = `Arah awal disetel ke Yaw ${this.editForm.default_yaw}°, Pitch ${this.editForm.default_pitch}° (klik Simpan Kalibrasi).`;
            },

            setHotspotToCamera(idx) {
                this.updateLiveAngles();
                if (!this.editForm.hotspots[idx]) return;
                const nextYaw = Number(this.liveYaw.toFixed(1));
                const nextPitch = Number(this.livePitch.toFixed(1));
                this.editForm.hotspots[idx].yaw = nextYaw;
                this.editForm.hotspots[idx].pitch = nextPitch;

                if (this.scenes[String(this.currentSceneId)]) {
                    this.scenes[String(this.currentSceneId)].hotspots = JSON.parse(JSON.stringify(this.editForm.hotspots));
                    this.refreshPannellumHotspots();
                }
                this.statusNotice = `Panah ke #${this.editForm.hotspots[idx].target_id} dipindahkan ke tengah kamera (${nextYaw}°, ${nextPitch}°). Klik Simpan Kalibrasi.`;
            },

            refreshPannellumHotspots() {
                if (!panoViewer || typeof panoViewer.getConfig !== 'function') return;
                const scKey = String(this.currentSceneId);
                const sc = this.scenes[scKey];
                if (!sc) return;

                const cfg = panoViewer.getConfig();
                const oldIds = (cfg?.hotSpots || []).map(h => h.id).filter(Boolean);
                oldIds.forEach(hid => {
                    try { panoViewer.removeHotSpot(hid, scKey); } catch (e) {}
                });

                this.buildPannellumHotspots(sc).forEach(hs => {
                    try { panoViewer.addHotSpot(hs, scKey); } catch (e) {}
                });
            },

            async saveCurrentScene() {
                this.isSaving = true;
                this.statusNotice = '';
                try {
                    const resp = await window.axios.post(`/admin/cek-foto/fib-walk/scene/${this.currentSceneId}`, this.editForm);
                    if (resp.data?.scene) {
                        this.scenes[String(this.currentSceneId)] = resp.data.scene;
                        this.syncEditForm();
                        this.refreshPannellumHotspots();
                    }
                    this.statusNotice = resp.data?.message || 'Kalibrasi berhasil disimpan permanen.';
                } catch (err) {
                    this.statusNotice = err.response?.data?.message || 'Gagal menyimpan kalibrasi scene.';
                } finally {
                    this.isSaving = false;
                }
            },

            async addLink() {
                if (!this.newLink.to_id) return;
                this.isSaving = true;
                this.statusNotice = '';
                this.updateLiveAngles();
                try {
                    const resp = await window.axios.post('/admin/cek-foto/fib-walk/link', {
                        from_id: this.currentSceneId,
                        to_id: Number(this.newLink.to_id),
                        yaw: Number(this.liveYaw.toFixed(1)),
                        pitch: Number(this.livePitch.toFixed(1)),
                        kind: this.newLink.kind || 'walk'
                    });
                    if (resp.data?.from_scene) {
                        this.scenes[String(this.currentSceneId)] = resp.data.from_scene;
                    }
                    if (resp.data?.to_scene) {
                        this.scenes[String(resp.data.to_scene.id)] = resp.data.to_scene;
                    }
                    this.syncEditForm();
                    this.refreshPannellumHotspots();
                    this.newLink.to_id = '';
                    this.statusNotice = resp.data?.message || 'Jalur dua arah ditambahkan.';
                } catch (err) {
                    this.statusNotice = err.response?.data?.message || 'Gagal menambah jalur.';
                } finally {
                    this.isSaving = false;
                }
            },

            async removeLink(targetId) {
                this.isSaving = true;
                this.statusNotice = '';
                try {
                    const resp = await window.axios.delete('/admin/cek-foto/fib-walk/link', {
                        data: { from_id: this.currentSceneId, to_id: Number(targetId) }
                    });
                    if (resp.data?.from_scene) {
                        this.scenes[String(this.currentSceneId)] = resp.data.from_scene;
                    }
                    if (this.scenes[String(targetId)]) {
                        this.scenes[String(targetId)].hotspots = (this.scenes[String(targetId)].hotspots || [])
                            .filter(h => Number(h.target_id) !== Number(this.currentSceneId));
                    }
                    this.syncEditForm();
                    this.refreshPannellumHotspots();
                    this.statusNotice = resp.data?.message || 'Koneksi dihapus.';
                } catch (err) {
                    this.statusNotice = err.response?.data?.message || 'Gagal menghapus koneksi.';
                } finally {
                    this.isSaving = false;
                }
            }
        };
    };
</script>
</body>
</html>
