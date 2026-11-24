<!-- 
  Komponen UI Chatbot "Rexa Copilot" (USUClassMap)
  Fase 3: Floating Drawer interaktif dengan Tailwind CSS v4 & Alpine.js
-->
<div 
    x-data="usuCopilot()" 
    class="fixed bottom-5 right-5 z-[9999] font-sans antialiased"
>
    <!-- ========================================================================= -->
    <!-- 1. FLOATING LAUNCHER BUTTON (MINIMIZED STATE)                             -->
    <!-- ========================================================================= -->
    <div x-show="!isOpen" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 scale-75 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0">
        <button 
            @click="toggleChat()" 
            type="button"
            class="group relative flex items-center gap-3 px-4 py-3.5 bg-gradient-to-r from-[#10A760] to-[#0D8A4E] text-white rounded-full shadow-2xl hover:shadow-[#10A760]/40 hover:scale-105 active:scale-95 transition-all duration-200 border-2 border-white/20"
            aria-label="Buka Chatbot Rexa Copilot"
        >
            <!-- Avatar Bot & Live Dot -->
            <div class="relative w-9 h-9 rounded-full bg-white/20 flex items-center justify-center backdrop-blur-xs">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                </svg>
                <!-- Online Pulse Indicator -->
                <span class="absolute -top-0.5 -right-0.5 flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-300 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-400 border-2 border-white"></span>
                </span>
            </div>

            <!-- Label Tombol -->
            <div class="flex flex-col text-left pr-1">
                <span class="text-[13px] font-extrabold tracking-tight leading-none text-white">Tanya Rexa</span>
                <span class="text-[10px] text-emerald-100 font-medium leading-tight mt-0.5">Asisten Jadwal & Peta</span>
            </div>

            <!-- Hint Arrow -->
            <svg class="w-4 h-4 text-emerald-200 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. EXPANDED CHAT DRAWER (WINDOW CHAT UTAMA)                                -->
    <!-- ========================================================================= -->
    <div 
        x-show="isOpen" 
        x-transition:enter="transition ease-out duration-300 transform" 
        x-transition:enter-start="opacity-0 translate-y-8 scale-95" 
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200 transform" 
        x-transition:leave-start="opacity-100 translate-y-0 scale-100" 
        x-transition:leave-end="opacity-0 translate-y-8 scale-95"
        :class="{ 'transition-none select-none': isResizing }"
        :style="isDesktop ? { width: chatWidth + 'px', height: chatHeight + 'px' } : {}"
        class="relative w-[92vw] md:w-auto h-[590px] md:h-auto max-h-[85vh] md:max-h-[calc(100vh-80px)] bg-white rounded-2xl shadow-2xl border border-gray-200/90 flex flex-col overflow-hidden"
    >
        <!-- RESIZE HANDLES (DESKTOP ONLY) -->
        <div x-show="isDesktop" class="hidden md:block">
            <!-- Left Edge Handle -->
            <div 
                @pointerdown="startResize('left', $event)"
                class="absolute left-0 top-3 bottom-3 w-2.5 -ml-1 cursor-ew-resize z-30 group"
                title="Tarik untuk mengubah lebar"
            >
                <div class="w-1 h-full mx-auto rounded-full group-hover:bg-[#10A760]/40 transition-colors"></div>
            </div>

            <!-- Top Edge Handle -->
            <div 
                @pointerdown="startResize('top', $event)"
                class="absolute top-0 left-3 right-3 h-2.5 -mt-1 cursor-ns-resize z-30 group"
                title="Tarik untuk mengubah tinggi"
            >
                <div class="h-1 w-full my-auto rounded-full group-hover:bg-[#10A760]/40 transition-colors"></div>
            </div>

            <!-- Top-Left Corner Handle -->
            <div 
                @pointerdown="startResize('corner', $event)"
                @dblclick="resetSize()"
                class="absolute top-0 left-0 w-6 h-6 cursor-nwse-resize z-40 group flex items-start justify-start p-1.5"
                title="Tarik sudut untuk mengubah ukuran (Klik 2x untuk reset)"
            >
                <svg class="w-3 h-3 text-white/50 group-hover:text-white transition-colors" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M2 10L10 2M5 11L11 5"></path>
                </svg>
            </div>
        </div>

        <!-- A. HEADER CHATBOT -->
        <div class="px-4 py-3.5 bg-gradient-to-r from-[#10A760] via-[#0E9656] to-[#0D8A4E] text-white flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-3">
                <div class="relative w-9 h-9 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center border border-white/25 shadow-xs">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-300 border-2 border-[#10A760]"></span>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h3 class="font-extrabold text-sm text-white tracking-tight">Rexa Copilot</h3>
                        <!-- Mode Badge -->
                        <span 
                            x-show="!isOffline" 
                            class="text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-white/20 text-emerald-100 border border-white/20"
                        >AI Claude</span>
                        <span 
                            x-show="isOffline" 
                            class="text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-amber-400 text-amber-950"
                        >Offline Mode</span>
                    </div>
                    <p class="text-[11px] text-emerald-100/90 font-medium">Asisten Spasial USUClassMap</p>
                </div>
            </div>

            <!-- Tombol Aksi Header -->
            <div class="flex items-center gap-1">
                <!-- Toggle Ukuran Perbesar / Normal (Desktop) -->
                <button 
                    x-show="isDesktop"
                    @click="toggleExpand()" 
                    :title="isExpanded ? 'Kembalikan Ukuran Normal' : 'Perbesar Jendela Chat'"
                    class="hidden md:flex p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition-colors items-center justify-center cursor-pointer"
                    type="button"
                >
                    <!-- Icon Expand -->
                    <svg x-show="!isExpanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                    </svg>
                    <!-- Icon Restore -->
                    <svg x-show="isExpanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 4v4m0 0H5m4 0L4 3m11 1v4m0 0h4m-4 0l5-5M9 20v-4m0 0H5m4 0l-5 5m11-5v4m0-4h4m-4 0l5 5"></path>
                    </svg>
                </button>
                <!-- Hapus Riwayat Percakapan -->
                <button 
                    @click="clearChat()" 
                    title="Bersihkan Percakapan"
                    class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
                <!-- Minimize Button -->
                <button 
                    @click="toggleChat()" 
                    title="Tutup Chat"
                    class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- B. QUICK CHIP PROMPTS (SARAN CEPAT) -->
        <div class="px-3 py-2 bg-gray-50/90 border-b border-gray-150 flex items-center gap-1.5 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden whitespace-nowrap shrink-0 after:content-[''] after:shrink-0 after:w-3">
            <template x-for="(prompt, idx) in quickPrompts" :key="idx">
                <button 
                    @click="sendQuickPrompt(prompt)" 
                    :disabled="isLoading"
                    class="text-[11px] font-semibold text-gray-700 bg-white hover:bg-[#10A760] hover:text-white px-2.5 py-1 rounded-full border border-gray-200 transition-colors shadow-2xs shrink-0 disabled:opacity-50 cursor-pointer"
                    x-text="prompt"
                ></button>
            </template>
        </div>

        <!-- C. MESSAGES CONTAINER -->
        <div 
            id="copilot-messages" 
            class="flex-1 min-h-0 p-4 overflow-y-auto space-y-3.5 bg-[#FAFBFB] text-gray-800 text-xs custom-scrollbar"
        >
            <template x-for="msg in messages" :key="msg.id">
                <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start items-start gap-2'">
                    <!-- Bot Avatar on Left -->
                    <template x-if="msg.role === 'assistant'">
                        <div class="w-6 h-6 rounded-full bg-[#10A760] text-white flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                    </template>

                    <!-- Bubble Chat -->
                    <div 
                        :class="msg.role === 'user' 
                            ? 'bg-[#10A760] text-white rounded-2xl rounded-tr-xs px-3.5 py-2.5 max-w-[85%] shadow-xs font-medium' 
                            : 'bg-white text-gray-800 rounded-2xl rounded-tl-xs px-3.5 py-2.5 max-w-[88%] border border-gray-200/90 shadow-2xs leading-relaxed'"
                    >
                        <!-- Formatted Content -->
                        <div 
                            class="whitespace-pre-wrap break-words" 
                            x-html="formatMessage(msg.content)"
                        ></div>

                        <!-- Honest Advisory Note Banner (jika atribut langka/tidak tersedia persis) -->
                        <template x-if="msg.advisory_note">
                            <div class="mt-2.5 p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-[11px] leading-snug flex items-start gap-2">
                                <span class="shrink-0 mt-0.5">ℹ️</span>
                                <span x-text="msg.advisory_note"></span>
                            </div>
                        </template>

                        <!-- Rich Smart Search Cards (Fotokopi/ATK & Tebak Gedung Spasial) -->
                        <template x-if="msg.cards && msg.cards.some(c => c.type === 'fotocopy_item' || c.type === 'spatial_building')">
                            <div class="mt-3 pt-2.5 border-t border-gray-150 space-y-2" data-copilot-cards>
                                <template x-for="(card, cIdx) in msg.cards" :key="cIdx">
                                    <div>
                                        <!-- Kartu Produk / Jasa Fotokopi -->
                                        <template x-if="card.type === 'fotocopy_item'">
                                            <div class="p-2.5 bg-gray-50/90 hover:bg-emerald-50/40 rounded-xl border border-gray-200/90 transition-all">
                                                <div class="flex items-start gap-2.5">
                                                    <template x-if="card.data.image_url">
                                                        <img :src="card.data.image_url" :alt="card.data.name" class="w-12 h-12 rounded-lg object-cover border border-gray-200 shrink-0 bg-white">
                                                    </template>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-start justify-between gap-1.5">
                                                            <div class="font-bold text-[11px] text-gray-900 leading-snug line-clamp-2" x-text="card.data.name"></div>
                                                            <span
                                                                class="px-1.5 py-0.5 rounded text-[9px] font-extrabold shrink-0"
                                                                :class="card.data.exact_attribute_match ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                                                x-text="card.data.exact_attribute_match ? card.data.category : 'Alternatif'"
                                                            ></span>
                                                        </div>
                                                        <div class="mt-1 flex items-center flex-wrap gap-1.5 text-[10px]">
                                                            <span class="font-black text-[#10A760]" x-text="card.data.formatted_price + '/' + card.data.unit"></span>
                                                            <span class="text-gray-300">•</span>
                                                            <span class="font-semibold text-gray-700 truncate" x-text="card.data.store?.name"></span>
                                                        </div>
                                                        <div class="mt-0.5 flex items-center flex-wrap gap-1.5 text-[10px] text-gray-500">
                                                            <span x-text="'~' + (card.data.store?.distance_meters ?? '-') + ' m (' + (card.data.store?.walk_minutes ?? '-') + ' mnt)'"></span>
                                                            <span class="text-gray-300">•</span>
                                                            <span :class="card.data.store?.is_open_now ? 'text-emerald-700 font-bold' : 'text-rose-600 font-semibold'" x-text="card.data.store?.is_open_now ? 'Buka' : 'Tutup'"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-2 flex items-center gap-1.5">
                                                    <a
                                                        :href="card.data.actions?.order_url || ('/fotocopy/' + card.data.store?.id + '/order')"
                                                        class="flex-1 py-1.5 px-2 bg-[#10A760] hover:bg-[#0D8A4E] text-white rounded-lg font-bold text-[10px] text-center transition-colors"
                                                    >
                                                        Pesan di Toko
                                                    </a>
                                                    <button
                                                        type="button"
                                                        @click="routeCardTarget(card)"
                                                        class="py-1.5 px-2.5 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 rounded-lg font-bold text-[10px] transition-colors cursor-pointer"
                                                    >
                                                        🧭 Rute GPS
                                                    </button>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Kartu Kandidat Gedung Spasial -->
                                        <template x-if="card.type === 'spatial_building'">
                                            <div class="p-2.5 bg-gray-50/90 hover:bg-emerald-50/40 rounded-xl border border-gray-200/90 transition-all">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <div class="font-bold text-[11px] text-gray-900 leading-snug" x-text="card.data.name + ' (' + card.data.code + ')'"></div>
                                                        <div class="text-[10px] text-gray-500" x-text="card.data.faculty"></div>
                                                    </div>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 shrink-0" x-text="card.data.confidence_percent + '% cocok'"></span>
                                                </div>
                                                <p class="mt-1.5 text-[10px] text-gray-700 leading-relaxed" x-text="card.data.spatial_reason"></p>
                                                <div class="mt-2 flex items-center gap-1.5">
                                                    <button
                                                        type="button"
                                                        @click="focusCardBuilding(card)"
                                                        class="flex-1 py-1.5 px-2 bg-emerald-100/80 hover:bg-[#10A760] text-emerald-900 hover:text-white rounded-lg font-bold text-[10px] transition-colors cursor-pointer"
                                                    >
                                                        📍 Lihat di Peta
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="routeCardTarget(card)"
                                                        class="py-1.5 px-2.5 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 rounded-lg font-bold text-[10px] transition-colors cursor-pointer"
                                                    >
                                                        🧭 Rute GPS ke Sini
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Multi-Faculty Summary Cards (Pilihan Fakultas Interaktif) -->
                        <template x-if="msg.facultySummary && msg.facultySummary.length > 0">
                            <div class="mt-3 pt-2.5 border-t border-gray-150 space-y-2">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider flex items-center justify-between">
                                    <span>Distribusi Lokasi Kelas:</span>
                                    <span class="text-[9px] text-[#10A760] font-semibold" x-text="msg.facultySummary.length + ' Fakultas/Gedung'"></span>
                                </div>
                                <template x-for="(fac, fIdx) in msg.facultySummary" :key="fIdx">
                                    <div class="p-2.5 bg-gray-50/90 hover:bg-emerald-50/50 rounded-xl border border-gray-200/80 transition-all">
                                        <div class="flex items-center justify-between gap-1.5">
                                            <span class="font-bold text-[11px] text-gray-800 truncate" x-text="fac.building_name"></span>
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-[#10A760]/10 text-[#10A760] shrink-0" x-text="fac.schedule_count + ' Jadwal'"></span>
                                        </div>
                                        <!-- Sample courses -->
                                        <template x-if="fac.sample_courses && fac.sample_courses.length > 0">
                                            <div class="mt-1 text-[10px] text-gray-500 line-clamp-2">
                                                <span class="font-medium text-gray-400">Contoh: </span>
                                                <span x-text="fac.sample_courses.slice(0, 2).join(', ')"></span>
                                            </div>
                                        </template>
                                        <!-- Card Action Buttons -->
                                        <div class="mt-2 flex items-center gap-1.5">
                                            <button 
                                                x-show="fac.map_action"
                                                @click="triggerMapAction(fac.map_action)" 
                                                type="button"
                                                class="flex-1 py-1 px-2 bg-emerald-100/70 hover:bg-[#10A760] text-emerald-800 hover:text-white rounded-lg font-bold text-[10px] transition-colors flex items-center justify-center gap-1 cursor-pointer"
                                            >
                                                <span>📍 Buka di Peta</span>
                                            </button>
                                            <button 
                                                x-show="fac.follow_up_query"
                                                @click="askFollowUp(fac.follow_up_query)" 
                                                type="button"
                                                class="py-1 px-2 bg-white hover:bg-gray-100 border border-gray-200 text-gray-700 rounded-lg font-semibold text-[10px] transition-colors flex items-center justify-center gap-1 cursor-pointer"
                                                title="Tanya detail jadwal ini"
                                            >
                                                <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                                </svg>
                                                <span>Detail</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Interactive Schedules List (Daftar Jadwal Interaktif) -->
                        <template x-if="msg.schedules && msg.schedules.length > 0">
                            <div class="mt-3 pt-2.5 border-t border-gray-150 space-y-2">
                                <div class="flex items-center justify-between text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                    <span class="flex items-center gap-1">
                                        <span>Daftar Jadwal Kelas</span>
                                        <span class="text-[9px] text-[#10A760] font-semibold" x-text="'(' + msg.schedules.length + ' kelas)'"></span>
                                    </span>
                                    <!-- Quick Link to Sidebar Map Search -->
                                    <button 
                                        @click="handleQuickReply({ sidebar_search: msg.searchKeyword || '' })" 
                                        type="button"
                                        class="text-[9px] text-emerald-700 hover:text-emerald-900 font-semibold flex items-center gap-0.5 cursor-pointer underline"
                                        title="Tampilkan semua jadwal di sidebar peta"
                                    >
                                        <span>Lihat di Sidebar</span>
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Schedule Cards (Limit 4 unless showAllSchedules is true) -->
                                <div class="space-y-1.5">
                                    <template x-for="(sched, sIdx) in (msg.showAllSchedules ? msg.schedules : msg.schedules.slice(0, 4))" :key="sIdx">
                                        <div class="p-2 bg-gray-50/90 hover:bg-emerald-50/40 rounded-xl border border-gray-200/80 transition-all text-[11px]">
                                            <div class="flex items-start justify-between gap-1.5">
                                                <div class="font-bold text-gray-800 leading-snug line-clamp-1" x-text="sched.course_name"></div>
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-emerald-100/80 text-emerald-800 shrink-0" x-text="sched.course_code"></span>
                                            </div>

                                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-gray-600">
                                                <span class="font-medium text-emerald-700" x-text="sched.day + ', ' + sched.time"></span>
                                                <span class="text-gray-300">•</span>
                                                <span class="text-gray-500" x-text="'Kelas ' + sched.class_group"></span>
                                                <template x-if="sched.sks">
                                                    <span class="text-gray-400" x-text="'(' + sched.sks + ' SKS)'"></span>
                                                </template>
                                            </div>

                                            <div class="mt-0.5 flex items-center justify-between gap-1 text-[10px] text-gray-500">
                                                <div class="truncate">
                                                    <span class="text-gray-400">Ruang: </span>
                                                    <span class="font-medium text-gray-700" x-text="sched.room_name"></span>
                                                    <span x-text="' (' + (sched.building_code || sched.building_name) + ')'"></span>
                                                </div>
                                                <template x-if="sched.map_action">
                                                    <button 
                                                        @click="triggerMapAction(sched.map_action)" 
                                                        type="button"
                                                        class="px-2 py-0.5 bg-emerald-100 hover:bg-[#10A760] text-emerald-800 hover:text-white rounded font-bold text-[9px] transition-colors shrink-0 flex items-center gap-0.5 cursor-pointer"
                                                        title="Fokuskan ke ruangan ini di peta"
                                                    >
                                                        <span>📍 Lihat di Peta</span>
                                                    </button>
                                                </template>
                                            </div>

                                            <template x-if="sched.lecturer && sched.lecturer !== '-'">
                                                <div class="mt-0.5 text-[9px] text-gray-400 truncate">
                                                    <span>Dosen: </span>
                                                    <span class="text-gray-600" x-text="sched.lecturer"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                <!-- Toggle View More Button if schedules > 4 -->
                                <template x-if="msg.schedules.length > 4">
                                    <button 
                                        @click="msg.showAllSchedules = !msg.showAllSchedules" 
                                        type="button"
                                        class="w-full py-1.5 px-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 rounded-lg font-bold text-[10px] transition-all flex items-center justify-center gap-1 cursor-pointer active:scale-98"
                                    >
                                        <span x-text="msg.showAllSchedules ? 'Tutup sebagian jadwal' : 'Lihat selengkapnya (+' + (msg.schedules.length - 4) + ' jadwal lainnya)'"></span>
                                        <svg 
                                            class="w-3 h-3 text-emerald-700 transition-transform duration-200" 
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

                        <!-- Quick Replies Chips (Opsi Lanjutan Cepat) -->
                        <template x-if="msg.quickReplies && msg.quickReplies.length > 0">
                            <div class="mt-2.5 pt-2 border-t border-gray-150 flex flex-wrap gap-1.5">
                                <template x-for="(qr, qrIdx) in msg.quickReplies" :key="qrIdx">
                                    <button 
                                        x-show="!qr.sidebar_search || ((msg.schedules && msg.schedules.length > 0) || (msg.facultySummary && msg.facultySummary.length > 0))"
                                        @click="handleQuickReply(qr)" 
                                        type="button"
                                        class="px-2.5 py-1 text-[10px] font-bold rounded-full transition-all flex items-center gap-1 border shadow-2xs cursor-pointer active:scale-95"
                                        :class="qr.sidebar_search 
                                            ? 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100' 
                                            : 'bg-white text-gray-700 border-gray-200 hover:border-[#10A760] hover:text-[#10A760] hover:bg-gray-50'"
                                    >
                                        <span x-show="qr.sidebar_search" class="text-emerald-600">🔍</span>
                                        <span x-text="qr.label"></span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        <!-- Interactive Map Action Card (Jika ada instruksi koordinat peta tunggal) -->
                        <template x-if="msg.mapAction && (!msg.facultySummary || msg.facultySummary.length <= 1) && (!msg.cards || !msg.cards.some(c => c.type === 'fotocopy_item' || c.type === 'spatial_building'))">
                            <div class="mt-2.5 pt-2 border-t border-gray-150">
                                <button 
                                    @click="triggerMapAction(msg.mapAction)" 
                                    type="button"
                                    class="w-full flex items-center justify-between gap-2 px-3 py-2 bg-emerald-50 hover:bg-emerald-100/90 border border-emerald-300 text-emerald-900 rounded-xl font-bold text-[11px] transition-all shadow-xs group cursor-pointer"
                                >
                                    <div class="flex items-center gap-1.5 truncate">
                                        <span class="text-emerald-600 group-hover:scale-110 transition-transform">📍</span>
                                        <span class="truncate" x-text="'Lihat di Peta: ' + (msg.mapAction.building_name || 'Lokasi Kampus')"></span>
                                    </div>
                                    <svg class="w-3.5 h-3.5 text-emerald-700 group-hover:translate-x-0.5 transition-transform shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                    </svg>
                                </button>
                            </div>
                        </template>

                        <!-- Timestamp -->
                        <div 
                            :class="msg.role === 'user' ? 'text-emerald-100 text-right' : 'text-gray-400 text-left'" 
                            class="text-[9px] mt-1" 
                            x-text="msg.time"
                        ></div>
                    </div>
                </div>
            </template>

            <!-- Loading Indicator (Thinking Pulse) -->
            <div x-show="isLoading" class="flex items-center gap-2" style="display: none;">
                <div class="w-6 h-6 rounded-full bg-[#10A760] text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                </div>
                <div class="bg-white border border-gray-200 text-gray-500 rounded-2xl rounded-tl-xs px-3.5 py-2 shadow-2xs flex items-center gap-1.5">
                    <span class="text-[11px] font-medium text-gray-600">Rexa sedang mencari data</span>
                    <span class="flex gap-1 items-center">
                        <span class="w-1.5 h-1.5 bg-[#10A760] rounded-full animate-bounce" style="animation-delay: 0ms;"></span>
                        <span class="w-1.5 h-1.5 bg-[#10A760] rounded-full animate-bounce" style="animation-delay: 150ms;"></span>
                        <span class="w-1.5 h-1.5 bg-[#10A760] rounded-full animate-bounce" style="animation-delay: 300ms;"></span>
                    </span>
                </div>
            </div>
        </div>

        <!-- D. FOOTER INPUT AREA -->
        <div class="p-3 bg-white border-t border-gray-200 shrink-0">
            <form @submit.prevent="sendMessage()" class="flex items-center gap-2">
                <input 
                    type="text" 
                    x-model="inputMessage" 
                    :disabled="isLoading"
                    placeholder="Tanya jadwal, patokan gedung, atau cari ATK/fotokopi..." 
                    class="flex-1 px-3.5 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#10A760]/30 focus:border-[#10A760] disabled:bg-gray-100 disabled:cursor-not-allowed transition-all"
                    maxlength="500"
                />
                <button 
                    type="submit" 
                    :disabled="isLoading || !inputMessage.trim()" 
                    class="px-3.5 py-2.5 bg-[#10A760] hover:bg-[#0D8A4E] text-white rounded-xl shadow-xs transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center shrink-0"
                    aria-label="Kirim Pesan"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </form>

            <div class="flex items-center justify-between text-[10px] text-gray-400 mt-2 px-1">
                <span class="flex items-center gap-1 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#10A760]"></span>
                    100% Read-Only Mode
                </span>
                <span class="text-gray-400">Tekan Enter untuk kirim</span>
            </div>
        </div>
    </div>
</div>
