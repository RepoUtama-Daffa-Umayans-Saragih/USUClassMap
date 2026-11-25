<!--
  Komponen UI Chatbot "Tanya Ucup — Pemandu Cerdas Kampus USU"
  USU Explorer Theme (#154D32 -> #1E6B45, Gold #F5B82E, Warm Parchment #FAF6EC)
-->
<div
    x-data="usuCopilot()"
    @keydown.escape.window="closeChat()"
    class="font-sans antialiased text-[#14261C]"
>
    <!-- ========================================================================= -->
    <!-- 1. FLOATING MASCOT DOCK (MINIMIZED LAUNCHER + SPEECH BUBBLE TEASER)       -->
    <!-- ========================================================================= -->
    <div
        x-show="!isOpen"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 scale-90 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        :class="isOrderPage ? 'bottom-24 right-4 lg:bottom-5 lg:right-5' : 'bottom-4 right-4 sm:bottom-5 sm:right-5'"
        class="fixed z-[9999] flex items-end gap-2.5 max-w-[calc(100vw-2rem)]"
    >
        <!-- Speech Bubble Teaser Kontekstual (Desktop/Tablet) -->
        <div
            x-show="!teaserDismissed"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-x-2"
            x-transition:enter-end="opacity-100 translate-x-0"
            class="hidden sm:flex items-center gap-2 mb-2 px-3.5 py-2 rounded-2xl rounded-br-xs bg-[#FFFDF7] border border-[#F5B82E]/70 shadow-lg text-[#14261C] text-xs font-semibold max-w-[235px]"
        >
            <button
                type="button"
                @click="toggleChat()"
                class="text-left leading-snug hover:text-[#154D32] transition-colors cursor-pointer"
                x-text="teaserText"
            ></button>
            <button
                type="button"
                @click.stop="dismissTeaser()"
                aria-label="Tutup pesan sambutan Ucup"
                class="text-gray-400 hover:text-gray-700 text-xs font-bold leading-none px-1 py-0.5 rounded cursor-pointer shrink-0"
            >&times;</button>
        </div>

        <!-- Floating Ucup Mascot Launcher Button -->
        <button
            id="ucup-launcher-btn"
            @click="toggleChat()"
            type="button"
            class="group relative flex items-center gap-2.5 pl-14 pr-4 py-2.5 sm:py-3 rounded-full bg-gradient-to-r from-[#154D32] to-[#1E6B45] text-white border-2 border-[#F5B82E]/85 shadow-[0_14px_30px_-8px_rgba(21,77,50,0.55)] hover:shadow-[0_18px_36px_-6px_rgba(245,184,46,0.45)] hover:scale-[1.03] active:scale-95 transition-all duration-200 cursor-pointer"
            aria-label="Buka Chatbot Tanya Ucup"
        >
            <!-- Pop-Out Transparan Ucup Full-Body -->
            <div class="pointer-events-none absolute -top-6 -left-1.5 w-14 h-20 flex items-end justify-center drop-shadow-[0_6px_10px_rgba(0,0,0,0.3)]">
                <img
                    src="/images/mascot/ucup-stand.webp"
                    alt="Maskot Ucup USU"
                    width="56"
                    height="80"
                    loading="eager"
                    class="w-full h-auto max-h-20 object-contain select-none ucup-float group-hover:scale-105 transition-transform"
                >
            </div>

            <!-- Online Pulse Indicator -->
            <span class="relative flex h-2.5 w-2.5 shrink-0">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#FACC15] opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-400 border border-white"></span>
            </span>

            <!-- Label -->
            <div class="flex flex-col text-left pr-0.5">
                <span class="text-[13px] font-extrabold tracking-tight leading-none text-white">Tanya Ucup</span>
                <span class="text-[10px] text-[#FAF6EC]/85 font-medium leading-tight mt-0.5">Pemandu Kampus USU</span>
            </div>

            <svg class="w-3.5 h-3.5 text-[#FACC15] group-hover:translate-x-0.5 transition-transform shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. MOBILE BACKDROP (HANYA LAYAR < sm SAAT CHAT TERBUKA)                    -->
    <!-- ========================================================================= -->
    <div
        x-show="isOpen && !isDesktop"
        x-transition.opacity.duration.200ms
        @click="closeChat()"
        style="display: none;"
        class="fixed inset-0 z-[9998] bg-[#14261C]/40 backdrop-blur-[2px] sm:hidden"
    ></div>

    <!-- ========================================================================= -->
    <!-- 3. EXPANDED CHAT WINDOW (DESKTOP FLOATING CARD / MOBILE BOTTOM-SHEET)     -->
    <!-- ========================================================================= -->
    <div
        id="ucup-chat-drawer"
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 translate-y-6 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-6 scale-95"
        style="display: none;"
        :class="[
            isResizing ? 'transition-none select-none' : '',
            isDesktop
                ? 'bottom-5 right-5 rounded-3xl max-h-[82vh] border border-[#154D32]/20 shadow-[0_24px_60px_-12px_rgba(20,38,28,0.38)]'
                : 'inset-x-0 bottom-0 h-[88dvh] w-full rounded-t-3xl border-t border-[#F5B82E]/50 shadow-2xl'
        ]"
        :style="isDesktop ? { width: chatWidth + 'px', height: chatHeight + 'px' } : {}"
        class="fixed z-[9999] bg-[#FAF6EC] flex flex-col overflow-hidden"
    >
        <!-- RESIZE HANDLES (DESKTOP ONLY) -->
        <div x-show="isDesktop" class="hidden sm:block">
            <div
                @pointerdown="startResize('left', $event)"
                class="absolute left-0 top-4 bottom-4 w-2.5 -ml-1 cursor-ew-resize z-30 group"
                title="Tarik untuk mengubah lebar"
            >
                <div class="w-1 h-full mx-auto rounded-full group-hover:bg-[#F5B82E]/50 transition-colors"></div>
            </div>
            <div
                @pointerdown="startResize('top', $event)"
                class="absolute top-0 left-4 right-4 h-2.5 -mt-1 cursor-ns-resize z-30 group"
                title="Tarik untuk mengubah tinggi"
            >
                <div class="h-1 w-full my-auto rounded-full group-hover:bg-[#F5B82E]/50 transition-colors"></div>
            </div>
            <div
                @pointerdown="startResize('corner', $event)"
                @dblclick="resetSize()"
                class="absolute top-0 left-0 w-6 h-6 cursor-nwse-resize z-40 group flex items-start justify-start p-1.5"
                title="Tarik sudut untuk ubah ukuran (Klik 2x reset)"
            >
                <svg class="w-3 h-3 text-white/40 group-hover:text-[#FACC15] transition-colors" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M2 10L10 2M5 11L11 5"></path>
                </svg>
            </div>
        </div>

        <!-- Mobile Bottom-Sheet Drag Handle -->
        <div class="sm:hidden bg-[#154D32] pt-2 pb-0.5 flex justify-center shrink-0">
            <span class="w-10 h-1 rounded-full bg-white/30"></span>
        </div>

        <!-- A. HEADER EXPLORER USU POP-OUT -->
        <div class="relative px-4 py-3 bg-gradient-to-r from-[#154D32] via-[#1A5D3C] to-[#1E6B45] text-white flex items-center justify-between gap-2 shadow-sm overflow-hidden shrink-0">
            <!-- Topografi Contour SVG Pattern Tipis -->
            <svg aria-hidden="true" class="pointer-events-none absolute inset-0 w-full h-full opacity-[0.09]" viewBox="0 0 400 90" fill="none">
                <path d="M-20 75 C 60 20, 140 85, 220 40 C 295 0, 350 65, 420 20" stroke="#FAF6EC" stroke-width="1.5"/>
                <path d="M-10 50 C 70 5, 160 68, 245 22 C 310 -12, 365 48, 425 5" stroke="#FACC15" stroke-width="1.2"/>
                <circle cx="340" cy="45" r="26" stroke="#FAF6EC" stroke-width="1" stroke-dasharray="4 4"/>
            </svg>

            <div class="relative z-10 flex items-center gap-3 min-w-0">
                <!-- Pop-Out Avatar Frame Ucup -->
                <div class="relative w-11 h-11 rounded-2xl bg-[#FAF6EC] border-2 border-[#F5B82E] shadow-md flex items-center justify-center overflow-hidden shrink-0">
                    <img
                        src="/images/mascot/ucup-avatar.webp"
                        alt="Avatar Ucup"
                        width="44"
                        height="44"
                        loading="eager"
                        class="w-full h-full object-contain scale-105"
                    >
                    <span class="sr-only">Avatar Tanya Ucup</span>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <h3 class="font-extrabold text-sm text-white tracking-tight truncate">Tanya Ucup</h3>
                        <span
                            x-show="!isOffline"
                            class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-[#F5B82E] text-[#14261C] shrink-0"
                        >Online</span>
                        <span
                            x-show="isOffline"
                            style="display: none;"
                            class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-300 text-amber-950 shrink-0"
                        >Offline Mode</span>
                    </div>
                    <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                        <p class="text-[11px] text-[#FAF6EC]/85 font-medium truncate">Pemandu Cerdas Kampus USU</p>
                        <!-- GPS Live Status Pill -->
                        <button
                            type="button"
                            @click="requestGpsManually()"
                            :title="gpsActive ? ('Lokasi GPS aktif (±' + (gpsAccuracy || 25) + ' m)') : 'Klik untuk aktifkan deteksi GPS'"
                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold transition-colors cursor-pointer shrink-0"
                            :class="gpsActive ? 'bg-emerald-500/25 text-emerald-100 border border-emerald-300/40' : 'bg-[#F5B82E]/20 text-[#FACC15] border border-[#F5B82E]/50 hover:bg-[#F5B82E]/35'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="gpsActive ? 'bg-emerald-300' : (gpsDetecting ? 'bg-amber-300 animate-ping' : 'bg-[#FACC15]')"></span>
                            <span x-text="gpsActive ? 'GPS Aktif' : (gpsDetecting ? 'Mendeteksi...' : 'Aktifkan GPS')"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Action Buttons Header -->
            <div class="relative z-10 flex items-center gap-1 shrink-0">
                <!-- Expand / Restore Width (Desktop Only) -->
                <button
                    x-show="isDesktop"
                    @click="toggleExpand()"
                    :title="isExpanded ? 'Perkecil (395px)' : 'Perluas (480px)'"
                    type="button"
                    class="hidden sm:flex p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-xl transition-colors items-center justify-center cursor-pointer"
                >
                    <svg x-show="!isExpanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                    </svg>
                    <svg x-show="isExpanded" style="display: none;" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 4v4m0 0H5m4 0L4 3m11 1v4m0 0h4m-4 0l5-5M9 20v-4m0 0H5m4 0l-5 5m11-5v4m0-4h4m-4 0l5 5"></path>
                    </svg>
                </button>

                <!-- Reset Percakapan -->
                <button
                    @click="clearChat()"
                    type="button"
                    title="Reset Percakapan"
                    class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-xl transition-colors cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </button>

                <!-- Tutup -->
                <button
                    @click="closeChat()"
                    type="button"
                    aria-label="Tutup Jendela Chat"
                    title="Tutup (Esc)"
                    class="p-1.5 text-white/85 hover:text-white hover:bg-white/15 rounded-xl transition-colors cursor-pointer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- B. QUICK CHIPS STRIP (SAAT SUDAH ADA PERCAKAPAN) -->
        <div
            x-show="messages.length > 0"
            style="display: none;"
            class="px-3 py-2 bg-[#FFFDF7] border-b border-[#154D32]/10 flex items-center gap-1.5 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden whitespace-nowrap shrink-0"
        >
            <template x-for="(prompt, idx) in quickPrompts" :key="'chip-top-' + idx">
                <button
                    type="button"
                    @click="sendQuickPrompt(prompt)"
                    :disabled="isLoading"
                    class="text-[11px] font-semibold text-[#154D32] bg-[#FAF6EC] hover:bg-[#154D32] hover:text-white px-2.5 py-1 rounded-full border border-[#154D32]/20 transition-colors shadow-2xs shrink-0 disabled:opacity-50 cursor-pointer"
                    x-text="prompt"
                ></button>
            </template>
        </div>

        <!-- C. MESSAGES BODY & WELCOME HERO -->
        <div class="relative flex-1 min-h-0 flex flex-col">
            <div
                id="copilot-messages"
                @scroll="handleMessagesScroll($el)"
                class="flex-1 min-h-0 p-3.5 sm:p-4 overflow-y-auto space-y-3.5 bg-[#FAF6EC] text-[#14261C] text-xs custom-scrollbar"
            >
                <!-- WELCOME HERO STATE (KETIKA CHAT MASIH KOSONG) -->
                <div
                    x-show="messages.length === 0"
                    class="flex flex-col items-center text-center pt-2 pb-1 px-1 space-y-3.5"
                >
                    <div class="relative flex items-center justify-center">
                        <div class="absolute w-32 h-32 rounded-full bg-gradient-to-tr from-[#154D32]/15 via-[#F5B82E]/25 to-[#1E6B45]/10 blur-xs"></div>
                        <img
                            src="/images/mascot/ucup-stand.webp"
                            alt="Ucup Pemandu Kampus USU"
                            width="104"
                            height="218"
                            class="relative w-24 sm:w-28 h-auto max-h-44 object-contain drop-shadow-md select-none ucup-float"
                        >
                    </div>

                    <div class="bg-[#FFFDF7] border border-[#F5B82E]/50 rounded-2xl p-3.5 shadow-xs space-y-1.5 max-w-[96%]">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#154D32] text-[#FACC15] text-[10px] font-extrabold uppercase tracking-wider">
                            <i class="fa-solid fa-compass"></i>
                            <span>Siap Menjelajah Kampus USU</span>
                        </div>
                        <p class="text-xs sm:text-[13px] text-[#14261C] leading-relaxed">
                            Halo! Aku <strong class="text-[#154D32]">Ucup</strong>, siap bantu cari kelas, tebak lokasi gedung, rute jalan kaki, sampai fotokopi termurah di sekitar USU!
                        </p>
                    </div>

                    <div class="w-full space-y-1.5 text-left">
                        <div class="text-[10px] font-bold text-[#154D32]/75 uppercase tracking-wider px-1">
                            Coba tanyakan langsung ke Ucup:
                        </div>
                        <div class="grid grid-cols-1 gap-1.5">
                            <template x-for="(prompt, idx) in quickPrompts" :key="'welcome-chip-' + idx">
                                <button
                                    type="button"
                                    @click="sendQuickPrompt(prompt)"
                                    :disabled="isLoading"
                                    class="w-full flex items-center justify-between gap-2 px-3 py-2.5 bg-[#FFFDF7] hover:bg-[#154D32] text-[#14261C] hover:text-white border border-[#154D32]/15 hover:border-[#154D32] rounded-xl text-left text-xs font-semibold shadow-2xs transition-all group cursor-pointer"
                                >
                                    <span class="truncate" x-text="prompt"></span>
                                    <i class="fa-solid fa-arrow-right text-[10px] text-[#F5B82E] group-hover:translate-x-0.5 transition-transform shrink-0"></i>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- DAFTAR PESAN PERCAKAPAN -->
                <template x-for="msg in messages" :key="msg.id">
                    <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start items-start gap-2'">
                        <!-- Mini Avatar Ucup di Kiri -->
                        <template x-if="msg.role === 'assistant'">
                            <div class="w-7 h-7 rounded-full bg-[#FFFDF7] border border-[#F5B82E] shadow-2xs overflow-hidden flex items-center justify-center shrink-0 mt-0.5">
                                <img
                                    src="/images/mascot/ucup-avatar.webp"
                                    alt="Ucup"
                                    width="28"
                                    height="28"
                                    class="w-full h-full object-contain"
                                >
                            </div>
                        </template>

                        <!-- Bubble Chat -->
                        <div
                            :class="msg.role === 'user'
                                ? 'bg-gradient-to-br from-[#154D32] to-[#1E6B45] text-white rounded-2xl rounded-tr-xs px-3.5 py-2.5 max-w-[85%] shadow-xs font-medium'
                                : 'bg-[#FFFDF7] text-[#14261C] rounded-2xl rounded-tl-xs px-3.5 py-2.5 max-w-[88%] border border-[#154D32]/15 shadow-xs leading-relaxed'"
                        >
                            <!-- Formatted Content -->
                            <div
                                class="whitespace-pre-wrap break-words"
                                x-html="formatMessage(msg.content)"
                            ></div>

                            <!-- Honest Advisory Note Banner -->
                            <template x-if="msg.advisory_note">
                                <div class="mt-2.5 p-2.5 rounded-xl bg-amber-50 border border-[#F5B82E]/70 text-amber-950 text-[11px] leading-snug flex items-start gap-2">
                                    <span class="shrink-0 mt-0.5 text-amber-600"><i class="fa-solid fa-circle-info"></i></span>
                                    <span x-text="msg.advisory_note"></span>
                                </div>
                            </template>

                            <!-- Rich Cards: Fotokopi/ATK & Tebak Gedung Spasial -->
                            <template x-if="msg.cards && msg.cards.some(c => c.type === 'fotocopy_item' || c.type === 'spatial_building')">
                                <div class="mt-3 pt-2.5 border-t border-[#154D32]/10 space-y-2" data-copilot-cards>
                                    <template x-for="(card, cIdx) in msg.cards" :key="cIdx">
                                        <div>
                                            <!-- Kartu Produk / Jasa Fotokopi -->
                                            <template x-if="card.type === 'fotocopy_item'">
                                                <div class="p-2.5 bg-[#FAF6EC]/85 hover:bg-emerald-50/50 rounded-xl border border-[#154D32]/15 transition-all">
                                                    <div class="flex items-start gap-2.5">
                                                        <template x-if="card.data.image_url">
                                                            <img :src="card.data.image_url" :alt="card.data.name" class="w-12 h-12 rounded-lg object-cover border border-gray-200 shrink-0 bg-white">
                                                        </template>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="flex items-start justify-between gap-1.5">
                                                                <div class="font-bold text-[11px] text-[#14261C] leading-snug line-clamp-2" x-text="card.data.name"></div>
                                                                <span
                                                                    class="px-1.5 py-0.5 rounded text-[9px] font-extrabold shrink-0"
                                                                    :class="card.data.exact_attribute_match ? 'bg-emerald-100 text-[#154D32]' : 'bg-amber-100 text-amber-900'"
                                                                    x-text="card.data.exact_attribute_match ? card.data.category : 'Alternatif'"
                                                                ></span>
                                                            </div>
                                                            <div class="mt-1 flex items-center flex-wrap gap-1.5 text-[10px]">
                                                                <span class="font-black text-[#154D32]" x-text="card.data.formatted_price + '/' + card.data.unit"></span>
                                                                <span class="text-gray-300">•</span>
                                                                <span class="font-semibold text-gray-700 truncate" x-text="card.data.store?.name"></span>
                                                            </div>
                                                            <div class="mt-0.5 flex items-center flex-wrap gap-1.5 text-[10px] text-gray-600">
                                                                <span x-text="'~' + (card.data.store?.distance_meters ?? '-') + ' m (' + (card.data.store?.walk_minutes ?? '-') + ' mnt)'"></span>
                                                                <span class="text-gray-300">•</span>
                                                                <span
                                                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold"
                                                                    :class="(card.data.store?.operational_status?.is_open_now ?? card.data.store?.is_open_now) ? 'bg-emerald-100 text-[#154D32]' : 'bg-[#DC2626] text-white'"
                                                                    x-text="(card.data.store?.operational_status?.is_open_now ?? card.data.store?.is_open_now) ? 'Buka' : 'Tutup'"
                                                                ></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2 flex items-center gap-1.5">
                                                        <a
                                                            :href="card.data.actions?.order_url || ('/fotocopy/' + card.data.store?.id + '/order')"
                                                            class="flex-1 py-1.5 px-2 bg-[#154D32] hover:bg-[#1E6B45] text-white rounded-lg font-bold text-[10px] text-center transition-colors"
                                                        >
                                                            Pesan di Toko
                                                        </a>
                                                        <button
                                                            type="button"
                                                            @click="routeCardTarget(card)"
                                                            class="py-1.5 px-2.5 bg-[#FAF6EC] hover:bg-[#F5B82E]/25 border border-[#F5B82E] text-[#14261C] rounded-lg font-bold text-[10px] transition-colors cursor-pointer"
                                                        >
                                                            <i class="fa-solid fa-compass text-amber-600"></i> Rute GPS
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Kartu Kandidat Gedung Spasial -->
                                            <template x-if="card.type === 'spatial_building'">
                                                <div class="p-2.5 bg-[#FAF6EC]/85 hover:bg-emerald-50/50 rounded-xl border border-[#154D32]/15 transition-all space-y-1.5">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="min-w-0">
                                                            <div class="font-bold text-[11px] text-[#14261C] leading-snug" x-text="card.data.name + ' (' + card.data.code + ')'"></div>
                                                            <div class="text-[10px] text-gray-600" x-text="card.data.faculty"></div>
                                                        </div>
                                                        <div class="flex flex-col items-end gap-1 shrink-0">
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-[#F5B82E]/30 text-[#14261C] border border-[#F5B82E]" x-text="card.data.confidence_percent + '% cocok'"></span>
                                                            <template x-if="card.data.operational_status">
                                                                <span
                                                                    class="px-1.5 py-0.5 rounded text-[9px] font-bold"
                                                                    :class="card.data.operational_status.is_open_now ? 'bg-emerald-100 text-[#154D32]' : 'bg-[#DC2626] text-white'"
                                                                    x-text="card.data.operational_status.is_open_now ? 'Buka (07:00–16:00)' : 'Tutup'"
                                                                ></span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    <p class="text-[10px] text-gray-700 leading-relaxed" x-text="card.data.spatial_reason"></p>
                                                    <div class="pt-0.5 flex flex-wrap items-center gap-1.5">
                                                        <button
                                                            type="button"
                                                            @click="focusCardBuilding(card)"
                                                            class="flex-1 py-1.5 px-2 bg-[#154D32]/10 hover:bg-[#154D32] text-[#154D32] hover:text-white rounded-lg font-bold text-[10px] transition-colors cursor-pointer whitespace-nowrap"
                                                        >
                                                            <i class="fa-solid fa-location-dot"></i> Lihat di Peta
                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="routeCardTarget(card)"
                                                            class="py-1.5 px-2.5 bg-[#FAF6EC] hover:bg-[#F5B82E]/25 border border-[#F5B82E] text-[#14261C] rounded-lg font-bold text-[10px] transition-colors cursor-pointer whitespace-nowrap"
                                                        >
                                                            <i class="fa-solid fa-compass text-amber-600"></i> Rute GPS
                                                        </button>
                                                        <template x-if="getFibWalkUrl(card.data)">
                                                            <a
                                                                :href="getFibWalkUrl(card.data)"
                                                                class="w-full py-1.5 px-2.5 bg-amber-100/80 hover:bg-amber-200 border border-[#F5B82E] text-amber-950 rounded-lg font-bold text-[10px] text-center transition-colors flex items-center justify-center gap-1"
                                                            >
                                                                <i class="fa-solid fa-street-view text-amber-700"></i>
                                                                <span>Jalan 360° FIB</span>
                                                            </a>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Multi-Faculty Summary Cards -->
                            <template x-if="msg.facultySummary && msg.facultySummary.length > 0">
                                <div class="mt-3 pt-2.5 border-t border-[#154D32]/10 space-y-2">
                                    <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider flex items-center justify-between">
                                        <span>Distribusi Lokasi Kelas:</span>
                                        <span class="text-[9px] text-[#154D32] font-bold" x-text="msg.facultySummary.length + ' Fakultas/Gedung'"></span>
                                    </div>
                                    <template x-for="(fac, fIdx) in msg.facultySummary" :key="fIdx">
                                        <div class="p-2.5 bg-[#FAF6EC]/85 hover:bg-emerald-50/40 rounded-xl border border-[#154D32]/15 transition-all">
                                            <div class="flex items-center justify-between gap-1.5">
                                                <span class="font-bold text-[11px] text-[#14261C] truncate" x-text="fac.building_name"></span>
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-[#154D32]/10 text-[#154D32] shrink-0" x-text="fac.schedule_count + ' Jadwal'"></span>
                                            </div>
                                            <template x-if="fac.sample_courses && fac.sample_courses.length > 0">
                                                <div class="mt-1 text-[10px] text-gray-600 line-clamp-2">
                                                    <span class="font-medium text-gray-400">Contoh: </span>
                                                    <span x-text="fac.sample_courses.slice(0, 2).join(', ')"></span>
                                                </div>
                                            </template>
                                            <div class="mt-2 flex items-center gap-1.5">
                                                <button
                                                    x-show="fac.map_action"
                                                    @click="triggerMapAction(fac.map_action)"
                                                    type="button"
                                                    class="flex-1 py-1 px-2 bg-[#154D32]/10 hover:bg-[#154D32] text-[#154D32] hover:text-white rounded-lg font-bold text-[10px] transition-colors flex items-center justify-center gap-1 cursor-pointer"
                                                >
                                                    <span>Lihat di Peta</span>
                                                </button>
                                                <button
                                                    x-show="fac.follow_up_query"
                                                    @click="askFollowUp(fac.follow_up_query)"
                                                    type="button"
                                                    class="py-1 px-2 bg-white hover:bg-gray-100 border border-gray-200 text-gray-700 rounded-lg font-semibold text-[10px] transition-colors flex items-center justify-center gap-1 cursor-pointer"
                                                    title="Tanya detail jadwal ini"
                                                >
                                                    <span>Detail</span>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Interactive Schedules List -->
                            <template x-if="msg.schedules && msg.schedules.length > 0">
                                <div class="mt-3 pt-2.5 border-t border-[#154D32]/10 space-y-2">
                                    <div class="flex items-center justify-between text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                        <span class="flex items-center gap-1">
                                            <span>Daftar Jadwal Kelas</span>
                                            <span class="text-[9px] text-[#154D32] font-bold" x-text="'(' + msg.schedules.length + ' kelas)'"></span>
                                        </span>
                                        <button
                                            @click="handleQuickReply({ sidebar_search: msg.searchKeyword || '' })"
                                            type="button"
                                            class="text-[9px] text-[#154D32] hover:underline font-bold flex items-center gap-0.5 cursor-pointer"
                                        >
                                            <span>Sidebar Peta</span>
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </button>
                                    </div>

                                    <div class="space-y-1.5">
                                        <template x-for="(sched, sIdx) in (msg.showAllSchedules ? msg.schedules : msg.schedules.slice(0, 4))" :key="sIdx">
                                            <div class="p-2.5 bg-[#FAF6EC]/85 hover:bg-emerald-50/40 rounded-xl border border-[#154D32]/15 transition-all text-[11px] space-y-1">
                                                <div class="flex items-start justify-between gap-1.5">
                                                    <div class="font-bold text-[#14261C] leading-snug line-clamp-1" x-text="sched.course_name"></div>
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-[#154D32]/10 text-[#154D32] shrink-0" x-text="sched.course_code"></span>
                                                </div>

                                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-gray-600">
                                                    <span class="font-semibold text-[#154D32]" x-text="sched.day + ', ' + sched.time"></span>
                                                    <span class="text-gray-300">•</span>
                                                    <span x-text="'Kelas ' + sched.class_group"></span>
                                                    <template x-if="sched.sks">
                                                        <span class="text-gray-400" x-text="'(' + sched.sks + ' SKS)'"></span>
                                                    </template>
                                                </div>

                                                <div class="text-[10px] text-gray-600 truncate">
                                                    <span class="text-gray-400">Ruang: </span>
                                                    <span class="font-semibold text-[#14261C]" x-text="sched.room_name"></span>
                                                    <span x-text="' (' + (sched.building_code || sched.building_name) + ')'"></span>
                                                </div>

                                                <template x-if="sched.lecturer && sched.lecturer !== '-'">
                                                    <div class="text-[9px] text-gray-500 truncate">
                                                        <span>Dosen: </span>
                                                        <span class="text-[#14261C]/80" x-text="sched.lecturer"></span>
                                                    </div>
                                                </template>

                                                <template x-if="sched.map_action">
                                                    <div class="pt-1 flex items-center gap-1.5">
                                                        <button
                                                            @click="triggerMapAction(sched.map_action)"
                                                            type="button"
                                                            class="flex-1 py-1 px-2 bg-[#154D32]/10 hover:bg-[#154D32] text-[#154D32] hover:text-white rounded-lg font-bold text-[10px] transition-colors cursor-pointer"
                                                        >
                                                            <i class="fa-solid fa-location-dot"></i> Lihat di Peta
                                                        </button>
                                                        <button
                                                            @click="routeScheduleBuilding(sched)"
                                                            type="button"
                                                            class="py-1 px-2.5 bg-[#FAF6EC] hover:bg-[#F5B82E]/25 border border-[#F5B82E] text-[#14261C] rounded-lg font-bold text-[10px] transition-colors cursor-pointer"
                                                        >
                                                            <i class="fa-solid fa-route text-amber-600"></i> Rute ke Kelas
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>

                                    <template x-if="msg.schedules.length > 4">
                                        <button
                                            @click="msg.showAllSchedules = !msg.showAllSchedules"
                                            type="button"
                                            class="w-full py-1.5 px-2 bg-[#E8F5EE] hover:bg-emerald-100 border border-[#154D32]/20 text-[#154D32] rounded-lg font-bold text-[10px] transition-all flex items-center justify-center gap-1 cursor-pointer"
                                        >
                                            <span x-text="msg.showAllSchedules ? 'Tutup sebagian jadwal' : 'Lihat selengkapnya (+' + (msg.schedules.length - 4) + ' jadwal lainnya)'"></span>
                                            <svg
                                                class="w-3 h-3 transition-transform duration-200"
                                                :class="{ 'rotate-180': msg.showAllSchedules }"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            <!-- Quick Replies Chips -->
                            <template x-if="msg.quickReplies && msg.quickReplies.length > 0">
                                <div class="mt-2.5 pt-2 border-t border-[#154D32]/10 flex flex-wrap gap-1.5">
                                    <template x-for="(qr, qrIdx) in msg.quickReplies" :key="qrIdx">
                                        <button
                                            x-show="!qr.sidebar_search || ((msg.schedules && msg.schedules.length > 0) || (msg.facultySummary && msg.facultySummary.length > 0))"
                                            @click="handleQuickReply(qr)"
                                            type="button"
                                            class="px-2.5 py-1 text-[10px] font-bold rounded-full transition-all flex items-center gap-1 border shadow-2xs cursor-pointer active:scale-95"
                                            :class="qr.sidebar_search
                                                ? 'bg-[#E8F5EE] text-[#154D32] border-[#154D32]/30 hover:bg-emerald-100'
                                                : 'bg-white text-[#14261C] border-gray-200 hover:border-[#154D32] hover:text-[#154D32]'"
                                        >
                                            <span x-show="qr.sidebar_search" class="text-[#154D32]"><i class="fa-solid fa-magnifying-glass"></i></span>
                                            <span x-text="qr.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            <!-- Single Map Action Fallback -->
                            <template x-if="msg.mapAction && (!msg.facultySummary || msg.facultySummary.length <= 1) && (!msg.cards || !msg.cards.some(c => c.type === 'fotocopy_item' || c.type === 'spatial_building' || c.type === 'schedule'))">
                                <div class="mt-2.5 pt-2 border-t border-[#154D32]/10">
                                    <button
                                        @click="triggerMapAction(msg.mapAction)"
                                        type="button"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 bg-[#E8F5EE] hover:bg-[#154D32] text-[#154D32] hover:text-white border border-[#154D32]/25 rounded-xl font-bold text-[11px] transition-all shadow-xs group cursor-pointer"
                                    >
                                        <div class="flex items-center gap-1.5 truncate">
                                            <i class="fa-solid fa-location-dot shrink-0"></i>
                                            <span class="truncate" x-text="'Lihat di Peta: ' + (msg.mapAction.building_name || 'Lokasi Kampus')"></span>
                                        </div>
                                        <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <!-- Timestamp -->
                            <div
                                :class="msg.role === 'user' ? 'text-[#FAF6EC]/80 text-right' : 'text-gray-400 text-left'"
                                class="text-[9px] mt-1"
                                x-text="msg.time"
                            ></div>
                        </div>
                    </div>
                </template>

                <!-- ANIMATED EXPLORER THINKING STATE (KOMPAS EMAS BERPUTAR) -->
                <div
                    x-show="isLoading"
                    style="display: none;"
                    class="flex items-center gap-2"
                >
                    <div class="w-7 h-7 rounded-full bg-[#154D32] border border-[#F5B82E] flex items-center justify-center shrink-0 shadow-2xs">
                        <svg class="w-4 h-4 text-[#FACC15] ucup-compass-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9"></circle>
                            <polygon points="14.5 9.5 13 13 9.5 14.5 11 11 14.5 9.5" fill="currentColor"></polygon>
                        </svg>
                    </div>
                    <div class="bg-[#FFFDF7] border border-[#F5B82E]/60 text-[#14261C] rounded-2xl rounded-tl-xs px-3.5 py-2 shadow-2xs flex items-center gap-2">
                        <span class="text-[11px] font-semibold text-[#154D32]" x-text="thinkingText"></span>
                        <span class="flex gap-1 items-center">
                            <span class="w-1.5 h-1.5 bg-[#F5B82E] rounded-full animate-bounce" style="animation-delay: 0ms;"></span>
                            <span class="w-1.5 h-1.5 bg-[#154D32] rounded-full animate-bounce" style="animation-delay: 150ms;"></span>
                            <span class="w-1.5 h-1.5 bg-[#F5B82E] rounded-full animate-bounce" style="animation-delay: 300ms;"></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tombol Pintas Scroll ke Bawah -->
            <button
                type="button"
                x-show="showScrollToBottom"
                x-transition.opacity.duration.150ms
                style="display: none;"
                @click="scrollToBottom()"
                title="Scroll ke pesan terbaru"
                class="absolute bottom-3 right-4 z-20 inline-flex items-center gap-1 px-2.5 py-1.5 rounded-full bg-[#154D32] hover:bg-[#1E6B45] text-white border border-[#F5B82E]/80 text-[10px] font-bold shadow-md cursor-pointer"
            >
                <i class="fa-solid fa-arrow-down text-[#FACC15]"></i>
                <span>Terbaru</span>
            </button>
        </div>

        <!-- D. FOOTER INPUT AREA (AUTO-EXPAND TEXTAREA + SAFE AREA) -->
        <div class="px-3 pt-2.5 pb-3 ucup-safe-bottom bg-[#FFFDF7] border-t border-[#154D32]/15 shrink-0">
            <form @submit.prevent="sendMessage()" class="flex items-end gap-2">
                <textarea
                    x-ref="chatInput"
                    rows="1"
                    x-model="inputMessage"
                    @keydown="handleInputKeydown($event)"
                    @input="autoResizeInput($el)"
                    :disabled="isLoading"
                    placeholder="Tanya Ucup soal kelas, gedung, rute, atau print murah..."
                    maxlength="500"
                    style="height: 38px;"
                    class="flex-1 px-3.5 py-2 text-xs text-[#14261C] bg-[#FAF6EC] border border-[#154D32]/20 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[#154D32]/25 focus:border-[#154D32] disabled:bg-gray-100 disabled:cursor-not-allowed resize-none leading-relaxed overflow-y-auto transition-colors"
                ></textarea>
                <button
                    type="submit"
                    :disabled="isLoading || !inputMessage.trim()"
                    class="h-10 w-10 rounded-2xl bg-gradient-to-br from-[#154D32] to-[#1E6B45] hover:from-[#1E6B45] hover:to-[#154D32] border border-[#F5B82E] text-[#FACC15] shadow-sm transition-all disabled:opacity-45 disabled:cursor-not-allowed flex items-center justify-center shrink-0 cursor-pointer"
                    aria-label="Kirim Pesan"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </form>

            <div class="flex items-center justify-between text-[10px] text-gray-500 mt-1.5 px-1">
                <span class="flex items-center gap-1 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#154D32]"></span>
                    <span>Data 100% Terverifikasi Kampus USU</span>
                </span>
                <span>Enter kirim • Shift+Enter baris baru</span>
            </div>
        </div>
    </div>
</div>
