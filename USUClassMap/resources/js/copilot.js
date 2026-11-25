/**
 * Tanya Ucup — Pemandu Cerdas Kampus USU (Alpine.js Controller)
 * Mengelola state UI responsif, deteksi GPS real-time, teaser kontekstual,
 * riwayat multi-turn, kartu interaktif (Fotokopi, Spasial + FIB 360°, Jadwal), & fallback offline.
 */

window.usuCopilot = function () {
    const initialPath = typeof window !== 'undefined' ? window.location.pathname : '/';
    const isFotocopyPath = /^\/fotocopy/i.test(initialPath);
    const isOrderPath = /^\/fotocopy\/[^/]+\/order/i.test(initialPath);

    let initialTeaserDismissed = false;
    try {
        initialTeaserDismissed = sessionStorage.getItem('usu_ucup_teaser_dismissed') === '1';
    } catch (e) {}

    return {
        // State UI
        isOpen: false,
        isLoading: false,
        isOffline: false,
        inputMessage: '',
        thinkingText: 'Ucup lagi nyari info kampus USU...',
        showScrollToBottom: false,

        // Context Halaman & Teaser Mascot
        currentPath: initialPath,
        isOrderPage: isOrderPath,
        teaserDismissed: initialTeaserDismissed,

        // GPS Live Status
        gpsActive: false,
        gpsDetecting: false,
        gpsAccuracy: null,

        // State Dimensi & Resizing (Desktop >= 640px)
        chatWidth: 395,
        chatHeight: 590,
        isResizing: false,
        isExpanded: false,
        isDesktop: typeof window !== 'undefined' ? window.innerWidth >= 640 : true,
        preExpandWidth: 395,
        preExpandHeight: 590,

        // 4 Quick Action Chips Kontekstual
        quickPrompts: isFotocopyPath
            ? [
                'Cari pulpen pink murah terdekat',
                'Print A4 termurah yang buka sekarang',
                'Kelas saya di depan FIB di samping fakultas B',
                'Jadwal kuliah hari ini',
            ]
            : [
                'Kelas saya di depan FIB di samping fakultas B',
                'Jadwal kuliah hari ini',
                'Cari pulpen pink murah terdekat',
                'Print A4 termurah yang buka sekarang',
            ],

        // Daftar Pesan Chat (kosong -> menampilkan Welcome Hero Ucup)
        messages: [],

        /**
         * Pesan speech bubble teaser sesuai halaman aktif.
         */
        get teaserText() {
            if (/^\/map/i.test(this.currentPath)) {
                return 'Bingung cari kelas atau rute? Tanya Ucup!';
            }
            if (/^\/fotocopy/i.test(this.currentPath)) {
                return 'Cari print / ATK murah terdekat? Klik Ucup!';
            }
            return 'Halo! Aku Ucup, pemandu kampus USU';
        },

        /**
         * Inisialisasi komponen: muat riwayat, ukuran tersimpan, dan pemanasan GPS pasif.
         */
        init() {
            this.loadHistory();
            this.loadSavedSize();
            this.syncGpsStateFromCache();
            this.warmGpsFix(false);

            window.addEventListener('resize', () => {
                this.isDesktop = window.innerWidth >= 640;
                if (this.isDesktop) {
                    const maxWidth = Math.min(720, window.innerWidth - 32);
                    const maxHeight = Math.min(780, Math.round(window.innerHeight * 0.82));
                    if (this.chatWidth > maxWidth) this.chatWidth = maxWidth;
                    if (this.chatHeight > maxHeight) this.chatHeight = maxHeight;
                }
            });

            window.addEventListener('open-copilot', (e) => {
                this.isOpen = true;
                this.warmGpsFix(false);
                if (e.detail && e.detail.prompt) {
                    this.inputMessage = e.detail.prompt;
                    this.sendMessage();
                } else {
                    this.$nextTick(() => this.scrollToBottom());
                }
            });
        },

        /**
         * Tutup speech bubble teaser di sesi aktif.
         */
        dismissTeaser() {
            this.teaserDismissed = true;
            try {
                sessionStorage.setItem('usu_ucup_teaser_dismissed', '1');
            } catch (e) {}
        },

        /**
         * Baca GPS terbaru (< 5 menit) dari map engine atau sessionStorage.
         */
        getCachedGpsFix() {
            try {
                const liveFix = window.USUClassMap?.getLastGpsFix?.();
                if (liveFix && Number.isFinite(Number(liveFix.lat)) && Number.isFinite(Number(liveFix.lng))) {
                    const age = Date.now() - Number(liveFix.timestamp || Date.now());
                    if (age < 300000) return liveFix;
                }
                for (const key of ['usu_last_gps', 'usu_last_gps_fix']) {
                    const raw = sessionStorage.getItem(key);
                    if (!raw) continue;
                    const parsed = JSON.parse(raw);
                    if (parsed && Number.isFinite(Number(parsed.lat)) && Number.isFinite(Number(parsed.lng))) {
                        const age = Date.now() - Number(parsed.timestamp || 0);
                        if (age < 300000) return parsed;
                    }
                }
            } catch (e) {}
            return null;
        },

        syncGpsStateFromCache() {
            const cached = this.getCachedGpsFix();
            if (cached) {
                this.gpsActive = true;
                this.gpsAccuracy = Number.isFinite(Number(cached.accuracy)) ? Math.round(Number(cached.accuracy)) : null;
                return true;
            }
            return false;
        },

        warmGpsFix(forceFresh = false) {
            if (!forceFresh && this.syncGpsStateFromCache()) return;
            if (!window.isSecureContext || !navigator.geolocation) return;

            this.gpsDetecting = true;

            const saveFix = (pos) => {
                this.gpsDetecting = false;
                const lat = Number(pos?.coords?.latitude);
                const lng = Number(pos?.coords?.longitude);
                const acc = Math.round(Number(pos?.coords?.accuracy || 30));
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                this.gpsActive = true;
                this.gpsAccuracy = acc;

                try {
                    const payload = JSON.stringify({
                        lat,
                        lng,
                        accuracy: acc,
                        timestamp: Date.now(),
                    });
                    sessionStorage.setItem('usu_last_gps', payload);
                    sessionStorage.setItem('usu_last_gps_fix', payload);
                } catch (e) {}
            };

            const onFail = () => {
                this.gpsDetecting = false;
            };

            navigator.geolocation.getCurrentPosition(
                saveFix,
                () => {
                    navigator.geolocation.getCurrentPosition(
                        saveFix,
                        onFail,
                        { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
                    );
                },
                { enableHighAccuracy: true, timeout: 6000, maximumAge: forceFresh ? 0 : 30000 }
            );
        },

        requestGpsManually() {
            this.warmGpsFix(true);
        },

        /**
         * Buka atau tutup jendela chat Tanya Ucup.
         */
        toggleChat() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.syncGpsStateFromCache();
                this.warmGpsFix(false);
                this.$nextTick(() => {
                    this.scrollToBottom();
                    if (this.isDesktop && this.$refs.chatInput) {
                        this.$refs.chatInput.focus();
                    }
                });
            }
        },

        closeChat() {
            if (this.isOpen) {
                this.isOpen = false;
            }
        },

        /**
         * Memuat preferensi ukuran chat desktop dari localStorage.
         */
        loadSavedSize() {
            try {
                const saved = localStorage.getItem('usuclassmap_copilot_size');
                if (saved) {
                    const parsed = JSON.parse(saved);
                    const maxWidth = Math.min(720, window.innerWidth - 32);
                    const maxHeight = Math.min(780, Math.round(window.innerHeight * 0.82));
                    if (parsed.width) {
                        this.chatWidth = Math.min(maxWidth, Math.max(360, parseInt(parsed.width, 10)));
                    }
                    if (parsed.height) {
                        this.chatHeight = Math.min(maxHeight, Math.max(420, parseInt(parsed.height, 10)));
                    }
                    this.isExpanded = !!parsed.isExpanded;
                }
            } catch (e) {}
        },

        /**
         * Menyimpan ukuran chat ke localStorage.
         */
        saveSize() {
            try {
                localStorage.setItem('usuclassmap_copilot_size', JSON.stringify({
                    width: this.chatWidth,
                    height: this.chatHeight,
                    isExpanded: this.isExpanded,
                }));
            } catch (e) {
                console.warn('[Tanya Ucup] Gagal menyimpan ukuran chat:', e);
            }
        },

        /**
         * Mulai geser ukuran jendela chat di desktop.
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
                const maxWidth = Math.min(720, window.innerWidth - 32);
                const maxHeight = Math.min(780, Math.round(window.innerHeight * 0.82));

                if (type === 'left' || type === 'corner') {
                    const deltaX = startX - e.clientX;
                    this.chatWidth = Math.round(Math.min(maxWidth, Math.max(360, startW + deltaX)));
                }
                if (type === 'top' || type === 'corner') {
                    const deltaY = startY - e.clientY;
                    this.chatHeight = Math.round(Math.min(maxHeight, Math.max(420, startH + deltaY)));
                }
                if (this.isExpanded && (this.chatWidth !== 480)) {
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
         * Toggle ukuran cepat desktop (395px <-> 480px).
         */
        toggleExpand() {
            if (!this.isDesktop) return;
            const maxW = Math.min(720, window.innerWidth - 32);
            const maxH = Math.min(780, Math.round(window.innerHeight * 0.82));

            if (!this.isExpanded) {
                this.preExpandWidth = this.chatWidth;
                this.preExpandHeight = this.chatHeight;
                this.chatWidth = Math.min(480, maxW);
                this.chatHeight = Math.min(680, maxH);
                this.isExpanded = true;
            } else {
                this.chatWidth = Math.min(this.preExpandWidth || 395, maxW);
                this.chatHeight = Math.min(this.preExpandHeight || 590, maxH);
                this.isExpanded = false;
            }
            this.saveSize();
            this.$nextTick(() => this.scrollToBottom());
        },

        /**
         * Reset ukuran desktop ke default (395x590px).
         */
        resetSize() {
            if (!this.isDesktop) return;
            this.chatWidth = 395;
            this.chatHeight = Math.min(590, Math.round(window.innerHeight * 0.82));
            this.isExpanded = false;
            this.saveSize();
            this.$nextTick(() => this.scrollToBottom());
        },

        /**
         * Hitung label status berpikir kontekstual dari query pengguna.
         */
        computeThinkingLabel(query) {
            const q = String(query || '').toLowerCase();
            if (/(pulpen|pena|pensil|print|cetak|fotocopy|fotokopi|jilid|kertas|hvs|atk|scan|laminat|toko|murah|tipec|tipex|tipe-x|tip-x|stipo|correction|stabilo|highlighter|usupress|usu\s*press|penerbit)/i.test(q)) {
                return 'Ucup lagi ngecek seluruh mitra fotokopi USU...';
            }
            if (/(depan|seberang|sebrang|samping|sebelah|belakang|antara|ingat|rute|arah|jalan|gedung|fakultas|fib|feb|fmipa|fasilkom|gkb|fisip|peta|dimana)/i.test(q)) {
                return 'Ucup lagi baca peta & posisi gedung...';
            }
            if (/(jadwal|kuliah|kelas|dosen|sks|ruang|senin|selasa|rabu|kamis|jumat|sabtu|hari ini)/i.test(q)) {
                return 'Ucup lagi buka jadwal kampus...';
            }
            return 'Ucup lagi nyari info kampus USU...';
        },

        /**
         * Kirim prompt dari chip saran cepat.
         */
        sendQuickPrompt(promptText) {
            this.inputMessage = promptText;
            this.sendMessage();
        },

        /**
         * Handler input textarea: Enter kirim, Shift+Enter baris baru.
         */
        handleInputKeydown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                this.sendMessage();
            }
        },

        autoResizeInput(el) {
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = Math.min(84, Math.max(38, el.scrollHeight)) + 'px';
        },

        handleMessagesScroll(el) {
            if (!el) return;
            const distFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
            this.showScrollToBottom = distFromBottom > 120;
        },

        /**
         * Format jam pesan saat ini (misal: "08:35").
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
                    const parsed = JSON.parse(saved);
                    if (Array.isArray(parsed)) {
                        this.messages = parsed;
                        return;
                    }
                }
            } catch (e) {}
            this.messages = [];
        },

        /**
         * Menyimpan riwayat chat ke sessionStorage.
         */
        saveHistory() {
            try {
                const trimmed = this.messages.slice(-20);
                sessionStorage.setItem('usuclassmap_copilot_messages', JSON.stringify(trimmed));
            } catch (e) {
                console.warn('[Tanya Ucup] Gagal menyimpan riwayat chat:', e);
            }
        },

        /**
         * Bersihkan riwayat chat agar kembali menampilkan Welcome Hero Ucup.
         */
        clearChat() {
            try {
                sessionStorage.removeItem('usuclassmap_copilot_messages');
            } catch (e) {}
            this.messages = [];
            this.isOffline = false;
            this.showScrollToBottom = false;
        },

        /**
         * Fungsi Utama: Kirim Pesan ke Backend Laravel (/copilot/chat).
         */
        async sendMessage() {
            const rawText = this.inputMessage.trim();
            if (!rawText || this.isLoading) return;

            this.messages.push({
                id: 'user-' + Date.now(),
                role: 'user',
                content: rawText,
                time: this.getCurrentTime(),
            });
            this.inputMessage = '';
            if (this.$refs.chatInput) {
                this.$refs.chatInput.style.height = '38px';
            }

            this.thinkingText = this.computeThinkingLabel(rawText);
            this.isLoading = true;
            this.$nextTick(() => this.scrollToBottom());

            // Siapkan riwayat percakapan untuk payload API (maksimal 6 pesan terakhir, slice aman 1200 char)
            const apiHistory = this.messages
                .slice(-7, -1)
                .map((m) => ({
                    role: m.role,
                    content: String(m.content || '').slice(0, 1200),
                }));

            let gpsLat = null;
            let gpsLng = null;
            try {
                const cached = this.getCachedGpsFix();
                if (cached) {
                    gpsLat = Number(cached.lat);
                    gpsLng = Number(cached.lng);
                    this.gpsActive = true;
                    this.gpsAccuracy = Number.isFinite(Number(cached.accuracy)) ? Math.round(Number(cached.accuracy)) : null;
                }
            } catch (e) {}

            try {
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

                    const actionRegex = /\[MAP_ACTION:\s*(\{.*?\})\]/s;
                    const match = replyText.match(actionRegex);
                    if (match) {
                        try {
                            mapAction = JSON.parse(match[1]);
                        } catch (err) {
                            console.warn('[Tanya Ucup] Gagal parse JSON dari tag MAP_ACTION:', err);
                        }
                        replyText = replyText.replace(actionRegex, '').trim();
                    }

                    this.messages.push({
                        id: 'bot-' + Date.now(),
                        role: 'assistant',
                        content: replyText,
                        time: this.getCurrentTime(),
                        intent,
                        advisory_note: advisoryNote,
                        cards,
                        actions,
                        mapAction,
                        facultySummary,
                        quickReplies,
                        mapActions,
                        schedules,
                        showAllSchedules: false,
                        searchKeyword: rawText,
                    });

                    this.isOffline = false;

                    if (mapAction && mapAction.type !== 'start_route' && window.innerWidth >= 1024 && window.USUClassMap) {
                        this.triggerMapAction(mapAction, false);
                    }
                } else {
                    throw new Error(response.data?.message || 'Gagal memproses respon AI');
                }
            } catch (error) {
                console.warn('[Tanya Ucup] Terjadi kesalahan koneksi backend. Beralih ke Mesin Offline:', error);
                this.isOffline = true;

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
         * URL Jalan Virtual 360° FIB bila gedung thuộc zona FIB.
         */
        getFibWalkUrl(buildingData) {
            if (!buildingData) return null;
            const code = String(buildingData.code || '').toUpperCase().trim();
            const fac = String(buildingData.faculty || '').toUpperCase().trim();
            if (code === 'FIB') return '/virtual-walk/fib?scene=495';
            if (code === 'FIB-O') return '/virtual-walk/fib?scene=430';
            if (code === 'FIB-A' || code === 'A FIB') return '/virtual-walk/fib?scene=436';
            if (code === 'FIB-B') return '/virtual-walk/fib?scene=439';
            if (code === 'FIB-F') return '/virtual-walk/fib?scene=482';
            if (code === 'FIB-H') return '/virtual-walk/fib?scene=456';
            if (code === 'FIB-I') return '/virtual-walk/fib?scene=484';
            if (code === 'FIB-K') return '/virtual-walk/fib?scene=474';
            if (code === 'FIB-M') return '/virtual-walk/fib?scene=489';
            if (code.startsWith('FIB') || fac === 'FIB') return '/virtual-walk/fib?scene=495';
            return null;
        },

        /**
         * Menghubungkan Aksi Chatbot dengan Peta (window.USUClassMap).
         */
        triggerMapAction(action, autoCloseMobile = true) {
            if (!action) return;

            if (action.type === 'start_route' && action.target_key) {
                if (window.USUClassMap && typeof window.USUClassMap.startRouteTo === 'function') {
                    window.USUClassMap.startRouteTo(action.target_key);
                    if (autoCloseMobile && window.innerWidth < 640) {
                        this.isOpen = false;
                    }
                } else {
                    window.location.href = '/map?route_to=' + encodeURIComponent(action.target_key);
                }
                return;
            }

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

                if (autoCloseMobile && window.innerWidth < 640) {
                    this.isOpen = false;
                }
            } else if (action.building_id) {
                window.location.href = '/map?focus_building=' + encodeURIComponent(action.building_id);
            }
        },

        /**
         * Arahkan navigasi rute ke gedung kelas dari kartu jadwal.
         */
        routeScheduleBuilding(sched) {
            const buildingId = sched?.map_action?.building_id || sched?.building_id || null;
            if (buildingId) {
                const targetKey = 'building:' + buildingId;
                if (window.USUClassMap && typeof window.USUClassMap.startRouteTo === 'function') {
                    window.USUClassMap.startRouteTo(targetKey);
                    if (window.innerWidth < 640) {
                        this.isOpen = false;
                    }
                } else {
                    window.location.href = '/map?route_to=' + encodeURIComponent(targetKey);
                }
                return;
            }
            if (sched?.map_action) {
                this.triggerMapAction(sched.map_action, true);
            }
        },

        /**
         * Fokuskan kartu gedung (dari Smart Spatial Search).
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
                if (window.innerWidth < 640) {
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
                if (window.innerWidth < 640) {
                    this.isOpen = false;
                }
            } else if (routeUrl) {
                window.location.href = routeUrl;
            }
        },

        /**
         * Penanganan aksi chip quick reply.
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
                if (window.innerWidth < 640) {
                    this.isOpen = false;
                }
            } else if (item.query) {
                this.inputMessage = item.query;
                this.sendMessage();
            }
        },

        askFollowUp(query) {
            if (!query) return;
            this.inputMessage = query;
            this.sendMessage();
        },

        /**
         * Mesin Pencarian Lokal / Offline (Regex Pattern Matching).
         */
        prosesOffline(query) {
            const cleanQuery = query.toLowerCase().trim();

            if (/(hapus|delete|tambah|insert|buat|update|ubah|ganti|drop|kprs|nilai)/i.test(cleanQuery)) {
                return {
                    text: '⚠️ **Mode Read-Only Aktif:**\nMaaf, Ucup adalah pemandu & asisten pencarian informasi dengan izin baca saja. Perubahan atau penghapusan jadwal tidak bisa dilakukan lewat sini.',
                    mapAction: null,
                };
            }

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

            if (/(dosen|rudy sofyan|rusdi|pengajar)/i.test(cleanQuery)) {
                return {
                    text: '👨‍🏫 **Informasi Dosen (Mode Offline):**\nUntuk melihat daftar lengkap jadwal mengajar dosen tersebut, kamu bisa mengetikkan nama beliau pada kolom pencarian di panel kiri peta.',
                    mapAction: null,
                };
            }

            if (/(jadwal saya|kelasku|kuliahku)/i.test(cleanQuery)) {
                return {
                    text: '📅 **Jadwal Kuliahmu:**\nPastikan kamu sudah login ke akun USUClassMap ya. Jadwalmu akan otomatis ditandai pin oranye dengan highlight emas pada peta!',
                    mapAction: null,
                };
            }

            return {
                text: '*(Mode Offline Aktif)*\nKoneksi ke server lagi terputus nih, tapi kamu tetap bisa cari gedung dan jadwal kuliah langsung lewat kolom pencarian di peta atau halaman fotokopi.',
                mapAction: null,
            };
        },

        /**
         * Parser teks markdown (tebal, miring, list, dan tabel) menjadi HTML aman.
         */
        formatMessage(text) {
            if (!text) return '';

            let safe = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            safe = safe.replace(/\*(.*?)\*/g, '<em>$1</em>');

            const tablePlaceholders = [];
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

                let tableHtml = '<div class="overflow-x-auto my-2.5 border border-emerald-200/80 rounded-xl custom-scrollbar shadow-2xs">' +
                    '<table class="min-w-full text-left text-[11px] border-collapse bg-white">' +
                    '<thead class="bg-[#E8F5EE] text-[#154D32] font-bold border-b border-emerald-200/70"><tr>' +
                    headers.map(h => `<th class="px-3 py-1.5 whitespace-nowrap">${h}</th>`).join('') +
                    '</tr></thead>' +
                    '<tbody class="divide-y divide-gray-100">';

                bodyRows.forEach(row => {
                    const cells = parseRow(row);
                    tableHtml += '<tr class="hover:bg-[#FAF6EC]/70 transition-colors">';
                    cells.forEach(c => {
                        tableHtml += `<td class="px-3 py-1.5 whitespace-nowrap text-[#14261C]">${c}</td>`;
                    });
                    tableHtml += '</tr>';
                });

                tableHtml += '</tbody></table></div>';

                const placeholder = `@@MD_TABLE_${tablePlaceholders.length}@@`;
                tablePlaceholders.push(tableHtml);
                return placeholder;
            });

            safe = safe.replace(/^[•\-\*]\s+(.*)$/gm, '<li class="ml-3.5 list-disc">$1</li>');
            safe = safe.replace(/\n/g, '<br>');

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
                this.showScrollToBottom = false;
            }
        },
    };
};

if (typeof window !== 'undefined') {
    if (window.Alpine) {
        window.Alpine.data('usuCopilot', window.usuCopilot);
    } else {
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('usuCopilot', window.usuCopilot);
        });
    }
}
