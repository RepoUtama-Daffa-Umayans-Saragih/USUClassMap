<x-layouts.app>
    <x-slot name="title">Pesan Layanan - {{ $place->name }}</x-slot>

    @include('features.fotocopy.partials.catalog-filter-script')
    @php
        $usuOrderConfig = [
            'placeName' => $place->name,
            'placeWa' => $place->whatsapp,
            'submitUrl' => route('fotocopy.order.store', $place->id),
            'csrfToken' => csrf_token(),
        ];
    @endphp
    <script>
        window.usuFotocopyCatalog = @json($catalogItems ?? []);
        window.usuPrintServices = @json($printServices ?? []);
        window.usuFinishingServices = @json($finishingServices ?? []);
        window.usuFotocopyOrderConfig = @json($usuOrderConfig);

        window.usuFotocopyOrderPage = function () {
            const cfg = window.usuFotocopyOrderConfig || {};
            const MAX_PDF_SLOTS = 10;
            const MAX_FILE_BYTES = 25 * 1024 * 1024;
            const MAX_TOTAL_BYTES = 80 * 1024 * 1024;

            return {
                ...window.usuCatalogFilter(window.usuFotocopyCatalog),
                printServices: Array.isArray(window.usuPrintServices) ? window.usuPrintServices : [],
                finishingServices: Array.isArray(window.usuFinishingServices) ? window.usuFinishingServices : [],

                // Group 1 (Cart-Bound Online Print & Hybrid Bundle with 1:1 file quota slots)
                printCartItems: [],
                highlightedCartKey: '',
                highlightTimer: null,
                pdfGlobalError: '',

                // Hybrid Choice Modal state
                hybridModalOpen: false,
                selectedHybridItem: null,

                // Physical-Only Offline Reminder Banner
                physicalNoticeText: '',

                // Group 2 & Group 3 Shopping Carts
                offlineCart: {},
                cart: {},

                customerName: '',
                customerWhatsapp: '',
                orderNotes: '',
                paymentMethod: 'qris',
                selectedBank: 'BCA',
                submitting: false,
                submitError: '',
                placeName: cfg.placeName || '',
                placeWa: cfg.placeWa || '',

                // In-page PDF preview modal state
                previewModalOpen: false,
                previewFile: {
                    name: '',
                    sizeLabel: '',
                    pageCount: 1,
                    url: '',
                    note: '',
                    isEncrypted: false
                },

                init() {
                    window.addEventListener('beforeunload', () => this.revokeAllPreviewUrls());
                },

                defaultPrintServiceId() {
                    if (this.printServices.length === 0) return '';
                    const bw = this.printServices.find(s => (s.name || '').toLowerCase().includes('hitam putih') && (s.name || '').toLowerCase().includes('a4'));
                    return String((bw || this.printServices[0]).id);
                },

                smartPrintForFinishing(finItem) {
                    if (this.printServices.length === 0) return '';
                    const fn = (finItem?.name || '').toLowerCase();
                    if (fn.includes('a3')) {
                        const a3 = this.printServices.find(s => (s.name || '').toLowerCase().includes('warna') && (s.name || '').toLowerCase().includes('a3'))
                            || this.printServices.find(s => (s.name || '').toLowerCase().includes('a3'));
                        if (a3) return String(a3.id);
                    }
                    if (fn.includes('laminat')) {
                        const warna = this.printServices.find(s => (s.name || '').toLowerCase().includes('warna') && (s.name || '').toLowerCase().includes('a4'));
                        if (warna) return String(warna.id);
                    }
                    return this.defaultPrintServiceId();
                },

                detectPaperSize(svcName) {
                    const n = (svcName || '').toLowerCase();
                    if (n.includes('a3')) return 'A3';
                    if (n.includes('f4') || n.includes('folio')) return 'F4';
                    if (n.includes('legal')) return 'Legal';
                    return 'A4';
                },

                createEmptySlot(paperSize = 'A4') {
                    return {
                        slotUid: 'slot-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7),
                        file: null,
                        previewUrl: '',
                        name: '',
                        sizeBytes: 0,
                        sizeLabel: '',
                        dragOver: false,
                        inspecting: false,
                        valid: false,
                        error: null,
                        warning: null,
                        pageCount: 1,
                        isEncrypted: false,
                        paperSize: paperSize || 'A4',
                        copies: 1,
                        note: ''
                    };
                },

                revokeSlotPreviewUrl(slot) {
                    if (slot && slot.previewUrl) {
                        try {
                            URL.revokeObjectURL(slot.previewUrl);
                        } catch (e) {}
                        slot.previewUrl = '';
                    }
                },

                revokeAllPreviewUrls() {
                    for (const menu of this.printCartItems) {
                        for (const slot of menu.slots) {
                            this.revokeSlotPreviewUrl(slot);
                        }
                    }
                },

                openSlotPreview(slot) {
                    if (!slot || !slot.previewUrl) return;
                    this.previewFile = {
                        name: slot.name || 'Dokumen.pdf',
                        sizeLabel: slot.sizeLabel || '',
                        pageCount: Number(slot.pageCount || 1),
                        url: slot.previewUrl,
                        note: slot.note || '',
                        isEncrypted: Boolean(slot.isEncrypted)
                    };
                    this.previewModalOpen = true;
                },

                closePdfPreview() {
                    this.previewModalOpen = false;
                },

                getTotalPdfSlotCount() {
                    return this.printCartItems.reduce((sum, menu) => sum + Number(menu.quota || 0), 0);
                },

                getTotalPdfBytes(excludeSlotUid = null) {
                    let total = 0;
                    for (const menu of this.printCartItems) {
                        for (const slot of menu.slots) {
                            if (slot.slotUid === excludeSlotUid) continue;
                            if (slot.file) {
                                total += Number(slot.sizeBytes || 0);
                            }
                        }
                    }
                    return total;
                },

                scrollToCartPanel() {
                    const el = document.getElementById('cart-panel');
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                },

                highlightAndRevealCart(menuKey) {
                    this.highlightedCartKey = menuKey;
                    if (this.highlightTimer) {
                        clearTimeout(this.highlightTimer);
                    }
                    this.highlightTimer = setTimeout(() => {
                        this.highlightedCartKey = '';
                    }, 1600);

                    this.$nextTick(() => {
                        const card = document.getElementById('cart-card-' + menuKey) || document.getElementById('cart-panel');
                        if (!card) return;
                        const rect = card.getBoundingClientRect();
                        if (rect.top < 72 || rect.bottom > window.innerHeight) {
                            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    });
                },

                addOrIncrementPrintMenu(item) {
                    this.pdfGlobalError = '';
                    if (this.getTotalPdfSlotCount() >= MAX_PDF_SLOTS) {
                        this.pdfGlobalError = 'Total kuota dokumen cetak maksimal ' + MAX_PDF_SLOTS + ' file PDF dalam satu transaksi.';
                        this.scrollToCartPanel();
                        return;
                    }

                    const key = 'print_' + item.id;
                    const existing = this.printCartItems.find(m => m.key === key);
                    if (existing) {
                        existing.warning = '';
                        existing.quota += 1;
                        existing.slots.push(this.createEmptySlot(existing.defaultPaperSize));
                        this.highlightAndRevealCart(key);
                        return;
                    }

                    const paperSize = this.detectPaperSize(item.name);
                    this.printCartItems.push({
                        key,
                        kind: 'digital_print',
                        title: item.name,
                        printServiceId: String(item.id),
                        finishingServiceId: '',
                        finishingName: '',
                        defaultPaperSize: paperSize,
                        quota: 1,
                        warning: '',
                        slots: [this.createEmptySlot(paperSize)]
                    });
                    this.highlightAndRevealCart(key);
                },

                handleCatalogItemClick(item) {
                    const behavior = item.order_behavior || (item.item_type === 'barang' ? 'physical_goods' : 'digital_print');

                    if (behavior === 'digital_print') {
                        this.addOrIncrementPrintMenu(item);
                        return;
                    }

                    if (behavior === 'hybrid_finishing') {
                        this.selectedHybridItem = item;
                        this.hybridModalOpen = true;
                        return;
                    }

                    if (behavior === 'physical_only') {
                        this.addOfflinePhysicalItem(item);
                        this.physicalNoticeText = 'Khusus Berkas Fisik (Offline): "' + item.name + '" memerlukan dokumen fisik asli. Anda tetap dapat memesan/membayar sekarang untuk mengunci antrean, lalu serahkan berkas fisik saat tiba di toko.';
                        return;
                    }

                    this.addItem(item);
                },

                selectHybridOffline() {
                    if (!this.selectedHybridItem) return;
                    const item = this.selectedHybridItem;
                    this.addOfflinePhysicalItem(item);
                    this.physicalNoticeText = 'Dipilih Opsi A (Offline Berkas Fisik) untuk "' + item.name + '": Untuk berkas asli yang sudah Anda print sendiri / KTP / Ijazah. Bawa berkas fisik langsung ke toko.';
                    this.hybridModalOpen = false;
                    this.selectedHybridItem = null;
                },

                selectHybridBundlePdf() {
                    if (!this.selectedHybridItem) return;
                    const item = this.selectedHybridItem;
                    this.pdfGlobalError = '';

                    if (this.getTotalPdfSlotCount() >= MAX_PDF_SLOTS) {
                        this.pdfGlobalError = 'Total kuota dokumen cetak maksimal ' + MAX_PDF_SLOTS + ' file PDF dalam satu transaksi.';
                        this.hybridModalOpen = false;
                        this.selectedHybridItem = null;
                        this.scrollToCartPanel();
                        return;
                    }

                    const key = 'bundle_' + item.id;
                    const existing = this.printCartItems.find(m => m.key === key);
                    if (existing) {
                        existing.warning = '';
                        existing.quota += 1;
                        existing.slots.push(this.createEmptySlot(existing.defaultPaperSize));
                    } else {
                        const paperSize = this.detectPaperSize(item.name);
                        this.printCartItems.push({
                            key,
                            kind: 'hybrid_bundle',
                            title: 'Bundel Print + ' + item.name,
                            printServiceId: this.smartPrintForFinishing(item),
                            finishingServiceId: String(item.id),
                            finishingName: item.name,
                            defaultPaperSize: paperSize,
                            quota: 1,
                            warning: '',
                            slots: [this.createEmptySlot(paperSize)]
                        });
                    }

                    this.hybridModalOpen = false;
                    this.selectedHybridItem = null;
                    this.highlightAndRevealCart(key);
                },

                incrementPrintMenuQuota(key) {
                    this.pdfGlobalError = '';
                    const menu = this.printCartItems.find(m => m.key === key);
                    if (!menu) return;

                    if (this.getTotalPdfSlotCount() >= MAX_PDF_SLOTS) {
                        this.pdfGlobalError = 'Total maksimal ' + MAX_PDF_SLOTS + ' slot/file PDF dalam satu pesanan.';
                        return;
                    }

                    menu.warning = '';
                    menu.quota += 1;
                    menu.slots.push(this.createEmptySlot(menu.defaultPaperSize));
                },

                decrementPrintMenuQuota(key) {
                    this.pdfGlobalError = '';
                    const idx = this.printCartItems.findIndex(m => m.key === key);
                    if (idx === -1) return;
                    const menu = this.printCartItems[idx];

                    if (menu.quota <= 1) {
                        this.printCartItems.splice(idx, 1);
                        return;
                    }

                    // Prioritize deleting the last empty slot first before removing a filled slot
                    let removeSlotIdx = -1;
                    for (let i = menu.slots.length - 1; i >= 0; i--) {
                        if (!menu.slots[i].file) {
                            removeSlotIdx = i;
                            break;
                        }
                    }
                    if (removeSlotIdx === -1) {
                        removeSlotIdx = menu.slots.length - 1;
                    }

                    this.revokeSlotPreviewUrl(menu.slots[removeSlotIdx]);
                    menu.slots.splice(removeSlotIdx, 1);
                    menu.quota = menu.slots.length;
                    menu.warning = '';
                },

                removePrintMenu(key) {
                    const target = this.printCartItems.find(m => m.key === key);
                    if (target) {
                        for (const slot of target.slots) {
                            this.revokeSlotPreviewUrl(slot);
                        }
                    }
                    this.printCartItems = this.printCartItems.filter(m => m.key !== key);
                    this.pdfGlobalError = '';
                },

                triggerSlotInput(slotUid) {
                    const input = document.getElementById('slot-file-' + slotUid);
                    if (input) {
                        input.click();
                    }
                },

                handleSlotFileChange(menuKey, slotIdx, event) {
                    const files = Array.from(event.target.files || []);
                    // Always reset value immediately so re-uploading the same filename triggers change
                    event.target.value = '';
                    if (files.length > 0) {
                        this.assignFilesToMenuSlots(menuKey, slotIdx, files);
                    }
                },

                handleSlotDrop(menuKey, slotIdx, event) {
                    const menu = this.printCartItems.find(m => m.key === menuKey);
                    if (menu && menu.slots[slotIdx]) {
                        menu.slots[slotIdx].dragOver = false;
                    }
                    const files = Array.from(event.dataTransfer?.files || []);
                    if (files.length > 0) {
                        this.assignFilesToMenuSlots(menuKey, slotIdx, files);
                    }
                },

                async waitForPdfInspector(maxWaitMs = 2500) {
                    const start = Date.now();
                    while (typeof window.usuInspectPdfFile !== 'function' && (Date.now() - start) < maxWaitMs) {
                        await new Promise(r => setTimeout(r, 80));
                    }
                    return typeof window.usuInspectPdfFile === 'function' ? window.usuInspectPdfFile : null;
                },

                async assignFilesToMenuSlots(menuKey, startSlotIdx, files) {
                    const menu = this.printCartItems.find(m => m.key === menuKey);
                    if (!menu || !menu.slots[startSlotIdx]) return;

                    this.pdfGlobalError = '';
                    menu.warning = '';

                    // Target slot startSlotIdx first (supports fill & replace), then remaining empty slots sequentially
                    const targetIndices = [startSlotIdx];
                    for (let i = startSlotIdx + 1; i < menu.slots.length; i++) {
                        if (!menu.slots[i].file) targetIndices.push(i);
                    }
                    for (let i = 0; i < startSlotIdx; i++) {
                        if (!menu.slots[i].file) targetIndices.push(i);
                    }

                    if (files.length > targetIndices.length) {
                        const skipped = files.length - targetIndices.length;
                        menu.warning = skipped + ' file tambahan dilewati karena kuota menu ini ' + menu.quota + ' file. Klik tombol + di header menu untuk menambah kuota.';
                    }

                    const inspector = await this.waitForPdfInspector();
                    const countToAssign = Math.min(files.length, targetIndices.length);

                    for (let k = 0; k < countToAssign; k++) {
                        const file = files[k];
                        const slot = menu.slots[targetIndices[k]];
                        if (!slot) continue;

                        // Revoke any previous preview blob before assigning new file; keep note/copies/paperSize intact
                        this.revokeSlotPreviewUrl(slot);
                        slot.file = file;
                        slot.name = file.name;
                        slot.sizeBytes = Number(file.size || 0);
                        slot.sizeLabel = this.formatFileSize(slot.sizeBytes);
                        slot.error = null;
                        slot.warning = null;

                        if (slot.sizeBytes <= 0) {
                            slot.inspecting = false;
                            slot.valid = false;
                            slot.error = 'File PDF berukuran 0 byte / kosong.';
                            continue;
                        }

                        if (slot.sizeBytes > MAX_FILE_BYTES) {
                            slot.inspecting = false;
                            slot.valid = false;
                            slot.error = 'Ukuran file melebihi batas maksimal 25 MB per file.';
                            continue;
                        }

                        if (this.getTotalPdfBytes(slot.slotUid) + slot.sizeBytes > MAX_TOTAL_BYTES) {
                            slot.inspecting = false;
                            slot.valid = false;
                            slot.error = 'Total ukuran seluruh file PDF melebihi batas maksimal 80 MB.';
                            this.pdfGlobalError = 'Total ukuran seluruh file PDF melebihi batas maksimal 80 MB.';
                            continue;
                        }

                        slot.inspecting = true;
                        slot.valid = false;

                        let result;
                        if (inspector) {
                            result = await inspector(file);
                        } else {
                            const isPdfExt = file.name.toLowerCase().endsWith('.pdf');
                            result = isPdfExt
                                ? { valid: true, pageCount: 1, isEncrypted: false, warning: 'Detektor PDF sedang dimuat; mohon cek jumlah halaman manual.' }
                                : { valid: false, error: 'Hanya file .pdf yang diperbolehkan.' };
                        }

                        slot.inspecting = false;
                        slot.valid = Boolean(result.valid);
                        slot.error = result.error || null;
                        slot.warning = result.warning || null;
                        slot.pageCount = Number(result.pageCount || 1);
                        slot.isEncrypted = Boolean(result.isEncrypted);
                        if (slot.valid && !slot.error && typeof URL.createObjectURL === 'function') {
                            slot.previewUrl = URL.createObjectURL(file);
                        }
                    }
                },

                clearSlot(menuKey, slotIdx) {
                    const menu = this.printCartItems.find(m => m.key === menuKey);
                    if (!menu || !menu.slots[slotIdx]) return;
                    const slot = menu.slots[slotIdx];
                    this.revokeSlotPreviewUrl(slot);
                    slot.file = null;
                    slot.name = '';
                    slot.sizeBytes = 0;
                    slot.sizeLabel = '';
                    slot.dragOver = false;
                    slot.inspecting = false;
                    slot.valid = false;
                    slot.error = null;
                    slot.warning = null;
                    slot.pageCount = 1;
                    slot.isEncrypted = false;
                    menu.warning = '';
                    this.pdfGlobalError = '';
                },

                addOfflinePhysicalItem(item) {
                    const key = 'off_' + item.id;
                    if (this.offlineCart[key]) {
                        this.offlineCart[key].qty += 1;
                    } else {
                        this.offlineCart[key] = {
                            key,
                            id: item.id,
                            name: item.name,
                            category: item.category || 'Offline Fisik',
                            price: item.price,
                            unit: item.unit,
                            behavior: item.order_behavior,
                            qty: 1
                        };
                    }
                },

                removeOfflineItem(key) {
                    if (this.offlineCart[key]) {
                        if (this.offlineCart[key].qty > 1) {
                            this.offlineCart[key].qty -= 1;
                        } else {
                            delete this.offlineCart[key];
                        }
                    }
                },

                incrementOfflineItem(key) {
                    if (this.offlineCart[key]) {
                        this.offlineCart[key].qty += 1;
                    }
                },

                catalogBehaviorBadgeClass(behavior) {
                    if (behavior === 'digital_print') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                    if (behavior === 'hybrid_finishing') return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
                    if (behavior === 'physical_only') return 'bg-amber-50 text-amber-800 border border-amber-200';
                    return 'bg-sky-50 text-sky-700 border border-sky-200';
                },

                catalogButtonClass(behavior) {
                    if (behavior === 'digital_print') return 'bg-[#10A760] hover:bg-[#0D8A4E] text-white';
                    if (behavior === 'hybrid_finishing') return 'bg-indigo-600 hover:bg-indigo-700 text-white';
                    if (behavior === 'physical_only') return 'bg-amber-600 hover:bg-amber-700 text-white';
                    return 'bg-[#10A760] hover:bg-[#0D8A4E] text-white';
                },

                formatFileSize(bytes) {
                    if (bytes >= 1048576) {
                        return (bytes / 1048576).toFixed(1) + ' MB';
                    }
                    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
                },

                getPrintService(serviceId) {
                    return this.printServices.find(s => String(s.id) === String(serviceId)) || null;
                },

                getFinishingService(serviceId) {
                    if (!serviceId) return null;
                    return this.finishingServices.find(s => String(s.id) === String(serviceId)) || null;
                },

                isPerSheetFinishing(svc) {
                    if (!svc) return false;
                    if (typeof svc.is_per_sheet_finishing === 'boolean') {
                        return svc.is_per_sheet_finishing;
                    }
                    const title = (svc.name || '').toLowerCase();
                    const u = (svc.unit || '').toLowerCase();
                    return title.includes('laminat') && ['lembar', 'pcs', 'lbr', ''].includes(u);
                },

                getSlotBreakdown(menu, slot) {
                    if (!slot || !slot.file || !slot.valid || slot.error) {
                        return { printQty: 0, printUnit: 0, printSubtotal: 0, finishQty: 0, finishUnit: 0, finishSubtotal: 0, finishName: '', totalSubtotal: 0 };
                    }
                    const printSvc = this.getPrintService(menu.printServiceId);
                    const finishSvc = this.getFinishingService(menu.finishingServiceId);
                    const pages = Math.max(1, Number(slot.pageCount || 1));
                    const copies = Math.max(1, Number(slot.copies || 1));
                    const printQty = pages * copies;
                    const printUnit = Number(printSvc?.price || 0);
                    const printSubtotal = printQty * printUnit;
                    const finishUnit = Number(finishSvc?.price || 0);
                    const finishQty = finishSvc ? (this.isPerSheetFinishing(finishSvc) ? (pages * copies) : copies) : 0;
                    const finishSubtotal = finishQty * finishUnit;

                    return {
                        printQty,
                        printUnit,
                        printSubtotal,
                        finishQty,
                        finishUnit,
                        finishSubtotal,
                        finishName: finishSvc ? finishSvc.name : '',
                        totalSubtotal: printSubtotal + finishSubtotal,
                    };
                },

                getSlotSubtotal(menu, slot) {
                    return this.getSlotBreakdown(menu, slot).totalSubtotal;
                },

                getMenuSubtotal(menu) {
                    return menu.slots.reduce((sum, slot) => sum + this.getSlotSubtotal(menu, slot), 0);
                },

                getMenuFilledCount(menu) {
                    return menu.slots.filter(s => s.file && s.valid && !s.error && !s.inspecting).length;
                },

                getIncompletePdfMenus() {
                    const list = [];
                    for (const menu of this.printCartItems) {
                        const filled = this.getMenuFilledCount(menu);
                        const hasInspecting = menu.slots.some(s => s.inspecting);
                        const hasError = menu.slots.some(s => Boolean(s.error));
                        if (filled !== menu.quota || hasInspecting || hasError) {
                            list.push({
                                key: menu.key,
                                title: menu.title,
                                filled,
                                quota: menu.quota,
                                missing: Math.max(0, menu.quota - filled),
                                hasInspecting,
                                hasError,
                            });
                        }
                    }
                    return list;
                },

                getTotalMissingPdfCount() {
                    return this.getIncompletePdfMenus().reduce((sum, m) => sum + m.missing, 0);
                },

                getPdfSubtotal() {
                    return this.printCartItems.reduce((sum, menu) => sum + this.getMenuSubtotal(menu), 0);
                },

                addItem(item) {
                    if (this.cart[item.id]) {
                        this.cart[item.id].qty += 1;
                    } else {
                        this.cart[item.id] = {
                            id: item.id,
                            name: item.name,
                            category: item.category || 'ATK',
                            price: item.price,
                            unit: item.unit,
                            qty: 1
                        };
                    }
                },

                removeItem(id) {
                    if (this.cart[id]) {
                        if (this.cart[id].qty > 1) {
                            this.cart[id].qty -= 1;
                        } else {
                            delete this.cart[id];
                        }
                    }
                },

                getOfflineItemCount() {
                    return Object.values(this.offlineCart).reduce((sum, item) => sum + item.qty, 0);
                },

                getCartItemCount() {
                    return Object.values(this.cart).reduce((sum, item) => sum + item.qty, 0);
                },

                getItemCount() {
                    return this.getTotalPdfSlotCount() + this.getOfflineItemCount() + this.getCartItemCount();
                },

                getOfflineSubtotal() {
                    return Object.values(this.offlineCart).reduce((sum, item) => sum + (item.price * item.qty), 0);
                },

                getCartSubtotal() {
                    return Object.values(this.cart).reduce((sum, item) => sum + (item.price * item.qty), 0);
                },

                getSubtotal() {
                    return this.getPdfSubtotal() + this.getOfflineSubtotal() + this.getCartSubtotal();
                },

                isCartEmpty() {
                    return this.printCartItems.length === 0
                        && Object.keys(this.offlineCart).length === 0
                        && Object.keys(this.cart).length === 0;
                },

                isCheckoutDisabled() {
                    return this.submitting || this.isCartEmpty() || this.getIncompletePdfMenus().length > 0;
                },

                formatRupiah(number) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(number || 0));
                },

                submitOrder() {
                    this.submitError = '';

                    if (this.isCartEmpty()) {
                        this.submitError = 'Keranjang pesanan masih kosong! Pilih layanan cetak, layanan offline, atau barang ATK terlebih dahulu.';
                        return;
                    }

                    const incomplete = this.getIncompletePdfMenus();
                    if (incomplete.length > 0) {
                        const names = incomplete.map(m => {
                            if (m.hasInspecting) return m.title + ' (sedang memproses PDF)';
                            if (m.hasError) return m.title + ' (ada file bermasalah)';
                            return m.title + ' (kurang ' + m.missing + ' file PDF)';
                        }).join('; ');
                        this.submitError = 'Lengkapi semua slot PDF sebelum melanjutkan: ' + names;
                        return;
                    }

                    if (!this.customerName.trim()) {
                        this.submitError = 'Silakan masukkan nama pemesan terlebih dahulu.';
                        return;
                    }

                    this.submitting = true;

                    let backendMethod = 'qris';
                    let paymentChannel = 'QRIS';
                    if (this.paymentMethod === 'cod') {
                        backendMethod = 'cash';
                        paymentChannel = 'CASHIER';
                    } else if (this.paymentMethod === 'transfer') {
                        backendMethod = 'bank_transfer';
                        paymentChannel = this.selectedBank || 'BCA';
                    }

                    const orderItems = [
                        ...Object.values(this.offlineCart).map(item => ({
                            service_id: item.id,
                            name: item.name,
                            price: item.price,
                            quantity: item.qty,
                            category: item.category,
                            fulfillment_mode: 'offline_physical'
                        })),
                        ...Object.values(this.cart).map(item => ({
                            service_id: item.id,
                            name: item.name,
                            price: item.price,
                            quantity: item.qty,
                            category: item.category || 'ATK',
                            fulfillment_mode: 'standard'
                        }))
                    ];

                    const flattenedFiles = [];
                    const pdfConfigs = [];

                    for (const menu of this.printCartItems) {
                        const printSvc = this.getPrintService(menu.printServiceId);
                        const finishSvc = this.getFinishingService(menu.finishingServiceId);

                        for (const slot of menu.slots) {
                            flattenedFiles.push({ file: slot.file, name: slot.name });
                            pdfConfigs.push({
                                page_count: Math.max(1, Number(slot.pageCount || 1)),
                                copies: Math.max(1, Number(slot.copies || 1)),
                                print_service_id: menu.printServiceId ? Number(menu.printServiceId) : null,
                                print_mode: printSvc ? printSvc.name : 'Print Hitam Putih A4',
                                paper_size: slot.paperSize || 'A4',
                                finishing_service_id: menu.finishingServiceId ? Number(menu.finishingServiceId) : null,
                                finishing_option: finishSvc ? finishSvc.name : null,
                                is_encrypted: Boolean(slot.isEncrypted),
                                note: (slot.note || '').trim()
                            });
                        }
                    }

                    const formData = new FormData();
                    formData.append('customer_name', this.customerName.trim());
                    if (this.customerWhatsapp.trim()) {
                        formData.append('customer_whatsapp', this.customerWhatsapp.trim());
                        formData.append('customer_phone', this.customerWhatsapp.trim());
                    }
                    if (this.orderNotes.trim()) {
                        formData.append('notes', this.orderNotes.trim());
                    }
                    formData.append('payment_method', backendMethod);
                    formData.append('payment_channel', paymentChannel);
                    formData.append('items', JSON.stringify(orderItems));
                    formData.append('pdf_configs', JSON.stringify(pdfConfigs));
                    formData.append('expected_pdf_count', String(flattenedFiles.length));

                    flattenedFiles.forEach((entry) => {
                        formData.append('pdf_files[]', entry.file, entry.name);
                    });

                    fetch(cfg.submitUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': cfg.csrfToken
                        },
                        body: formData
                    })
                    .then(async res => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success || !data.payment_url) {
                            throw new Error(data.message || 'Terjadi kesalahan saat memproses pesanan.');
                        }
                        window.location.href = data.payment_url;
                    })
                    .catch(err => {
                        console.error('Error submitting order:', err);
                        this.submitError = err.message || 'Gagal menghubungi server. Silakan coba lagi.';
                        this.submitting = false;
                    });
                }
            };
        };
    </script>

    @push('scripts')
        @vite(['resources/js/features/fotocopy-pdf-uploader.js'])
    @endpush

    <div
        class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 pb-24 lg:pb-8 space-y-6"
        x-data="usuFotocopyOrderPage()"
    >
        <!-- Breadcrumb -->
        <nav class="flex items-center text-xs text-gray-500 gap-2 mb-2 overflow-x-auto whitespace-nowrap pb-1">
            <a href="/" class="hover:text-[#10A760]">Beranda</a>
            <span>/</span>
            <a href="{{ route('fotocopy.index') }}" class="hover:text-[#10A760]">Fotocopy</a>
            <span>/</span>
            <a href="{{ route('fotocopy.show', $place->id) }}" class="hover:text-[#10A760]">{{ $place->name }}</a>
            <span>/</span>
            <span class="text-gray-800 font-semibold">Pemesanan</span>
        </nav>

        <!-- Header Info -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 sm:gap-4">
                @if($place->image_url)
                    <img src="{{ $place->image_url }}" alt="{{ $place->name }}" class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl object-cover shrink-0 shadow-xs border border-gray-100">
                @else
                    <div class="w-12 h-12 sm:w-14 sm:h-14 bg-[#E8F5EE] text-[#10A760] rounded-2xl flex items-center justify-center font-bold text-xl sm:text-2xl shrink-0">
                        <i class="fa-solid fa-print"></i>
                    </div>
                @endif
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-lg sm:text-2xl font-black text-gray-900 leading-tight">{{ $place->name }}</h1>
                        <span class="{{ $isOpen ? 'bg-[#10A760]' : 'bg-gray-400' }} text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                            {{ $isOpen ? 'Buka' : 'Tutup' }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 flex items-center gap-2 flex-wrap">
                        <span class="flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-[#10A760]"></i>
                            <span>{{ $place->location_name }}</span>
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1">
                            <i class="fa-regular fa-clock text-[#10A760]"></i>
                            <span>{{ $place->open_time }} - {{ $place->close_time }}</span>
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1 text-emerald-700 font-semibold">
                            <i class="fa-brands fa-whatsapp text-[#25D366]"></i>
                            <span>{{ \App\Support\WhatsAppPhoneNormalizer::formatDisplay($place->whatsapp ?: $place->phone) }}</span>
                        </span>
                    </p>
                </div>
            </div>

            <a
                href="{{ $place->getDirectionUrl() }}"
                class="inline-flex items-center gap-2 bg-[#D4A017] hover:bg-[#B8880F] text-white text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-xs w-full sm:w-auto justify-center"
            >
                <i class="fa-solid fa-route text-xs"></i>
                <span>Rute di Peta</span>
            </a>
        </div>

        <!-- Main Layout: Catalog Left (7 cols), Cart-Bound Upload & Checkout Right (5 cols) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- LEFT 7 COLS: PURE CATALOG -->
            <div class="lg:col-span-7 space-y-6">

                <!-- SECTION 1 (POSISI UTAMA): KATALOG LAYANAN CETAK, FINISHING & BARANG ATK -->
                <div class="space-y-4">
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-gray-900 flex items-center gap-2 mb-1">
                            <span class="w-1.5 h-5 bg-[#10A760] rounded-full"></span>
                            <span>Pilih Layanan Cetak, Finishing &amp; Barang ATK</span>
                        </h2>
                        <p class="text-xs text-gray-500">
                            Klik layanan Print/Cetak atau pilih opsi bundel Print + Finishing untuk menambahkannya langsung ke Keranjang beserta slot unggah PDF per menu (1:1).
                        </p>
                    </div>

                    @include('features.fotocopy.partials.catalog-filter-bar')

                    <!-- Banner Pengingat Layanan Berkas Fisik (Offline) -->
                    <div x-show="physicalNoticeText" x-cloak class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start justify-between gap-3 text-xs shadow-2xs">
                        <div class="flex items-start gap-2.5">
                            <i class="fa-solid fa-store text-amber-600 text-sm mt-0.5 shrink-0"></i>
                            <span class="font-medium leading-relaxed" x-text="physicalNoticeText"></span>
                        </div>
                        <button type="button" @click="physicalNoticeText = ''" class="text-amber-700 hover:text-amber-900 font-bold text-sm px-1 cursor-pointer">&times;</button>
                    </div>

                    <template x-if="catalogFiltered().length === 0">
                        <div class="bg-white rounded-2xl border border-dashed border-gray-200 p-8 text-center text-xs text-gray-500">
                            <i class="fa-solid fa-magnifying-glass text-2xl text-gray-300 mb-2 block"></i>
                            Tidak ada produk atau layanan yang sesuai dengan filter pencarian Anda.
                        </div>
                    </template>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" x-show="catalogFiltered().length > 0">
                        <template x-for="item in catalogFiltered()" :key="item.id">
                            <div class="bg-white rounded-2xl border border-gray-100 p-3.5 shadow-xs hover:border-[#10A760] transition-all flex flex-col justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="w-16 h-16 rounded-xl bg-gray-50 border border-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
                                        <template x-if="item.image_url && !item._imgError">
                                            <img
                                                :src="item.image_url"
                                                :alt="item.name"
                                                loading="lazy"
                                                x-on:error="item._imgError = true"
                                                class="w-full h-full object-contain p-1"
                                            >
                                        </template>
                                        <template x-if="!item.image_url || item._imgError">
                                            <div class="w-full h-full flex items-center justify-center">
                                                <i class="fa-solid text-lg" :class="item.item_type === 'barang' ? 'fa-box-open text-sky-500' : 'fa-print text-emerald-500'"></i>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-1 mb-1">
                                            <span
                                                class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                :class="catalogBehaviorBadgeClass(item.order_behavior)"
                                                x-text="item.behavior_badge || item.type_label"
                                            ></span>
                                            <template x-if="item.brand">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200" x-text="item.brand"></span>
                                            </template>
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600" x-text="item.pack_label"></span>
                                        </div>

                                        <h4 class="font-extrabold text-gray-900 text-xs sm:text-sm leading-snug line-clamp-2" x-text="item.name"></h4>
                                        <div class="text-[10px] text-emerald-700 font-semibold mt-0.5" x-show="item.sub_category" x-text="item.sub_category"></div>
                                        <p class="text-[11px] text-gray-500 line-clamp-2 mt-1" x-show="item.description" x-text="item.description"></p>
                                        <p class="text-[10px] text-gray-400 mt-1 truncate" x-show="metadataSummary(item.metadata)" x-text="metadataSummary(item.metadata)"></p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2.5 border-t border-gray-50 gap-2">
                                    <div>
                                        <div class="flex items-baseline gap-1">
                                            <span class="text-xs sm:text-sm font-black text-[#10A760]" x-text="item.formatted_price || rupiah(item.price)"></span>
                                            <span class="text-[10px] text-gray-400" x-text="'/ ' + item.unit"></span>
                                        </div>
                                        <template x-if="item.item_type === 'barang' && item.stock !== null">
                                            <span
                                                class="text-[10px] font-semibold block"
                                                :class="item.stock <= 5 ? 'text-red-600' : 'text-gray-400'"
                                                x-text="'Stok: ' + item.stock"
                                            ></span>
                                        </template>
                                    </div>

                                    <button
                                        type="button"
                                        @click="handleCatalogItemClick(item)"
                                        :class="catalogButtonClass(item.order_behavior)"
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer shrink-0"
                                    >
                                        <i class="fa-solid text-[10px]" :class="{
                                            'fa-file-pdf': item.order_behavior === 'digital_print',
                                            'fa-layer-group': item.order_behavior === 'hybrid_finishing',
                                            'fa-store': item.order_behavior === 'physical_only',
                                            'fa-plus': !item.order_behavior || item.order_behavior === 'physical_goods'
                                        }"></i>
                                        <span x-text="item.behavior_cta || 'Tambah'"></span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- RIGHT 5 COLS: GROUPED STICKY CART & CART-BOUND PDF UPLOAD -->
            <div class="lg:col-span-5" id="cart-panel">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 lg:sticky lg:top-20 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-cart-shopping text-[#10A760] text-lg"></i>
                            <div>
                                <h3 class="font-extrabold text-gray-900 text-sm sm:text-base leading-tight">Keranjang Pesanan</h3>
                                <p class="text-[10px] text-gray-400" x-show="printCartItems.length > 0" x-cloak>
                                    Unggah file PDF langsung di tiap kartu menu (maks 10 file / 80 MB)
                                </p>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-[#10A760] bg-green-50 px-2.5 py-0.5 rounded-full shrink-0" x-text="getItemCount() + ' Item'"></span>
                    </div>

                    <div x-show="pdfGlobalError" x-cloak class="p-2.5 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation shrink-0"></i>
                        <span x-text="pdfGlobalError"></span>
                    </div>

                    <!-- Grouped Cart Summary (Scrollable Comfortably) -->
                    <div class="space-y-4 max-h-[58vh] lg:max-h-[56vh] overflow-y-auto pr-1">
                        <template x-if="isCartEmpty()">
                            <div class="text-center py-8 text-gray-400 text-xs border border-dashed border-gray-200 rounded-xl">
                                <i class="fa-solid fa-cart-shopping text-2xl text-gray-300 mb-2 block"></i>
                                Keranjang masih kosong.<br>Pilih layanan atau barang ATK di sebelah kiri.
                            </div>
                        </template>

                        <!-- GROUP 1: DOKUMEN CETAK ONLINE & BUNDEL HYBRID (CART-BOUND 1:1 QUOTA SLOTS) -->
                        <div x-show="printCartItems.length > 0" x-cloak class="space-y-3">
                            <div class="text-[10px] font-black uppercase tracking-wider text-emerald-800 flex items-center justify-between gap-1.5">
                                <span class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    <span>Dokumen Cetak Online (Upload per Menu)</span>
                                </span>
                                <span class="text-[10px] font-bold text-gray-500" x-text="getTotalPdfSlotCount() + '/10 Slot'"></span>
                            </div>

                            <template x-for="menu in printCartItems" :key="menu.key">
                                <div
                                    :id="'cart-card-' + menu.key"
                                    :class="highlightedCartKey === menu.key
                                        ? 'ring-2 ring-[#10A760] border-[#10A760] bg-emerald-50/40 shadow-md'
                                        : (menu.kind === 'hybrid_bundle' ? 'border-indigo-200 bg-indigo-50/20' : 'border-emerald-200 bg-emerald-50/25')"
                                    class="rounded-2xl border p-3 sm:p-3.5 space-y-3 transition-all duration-300"
                                >
                                    <!-- Card Header: Title, Kuota File [-] N [+], Remove Card -->
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                                <span
                                                    :class="menu.kind === 'hybrid_bundle' ? 'bg-indigo-100 text-indigo-800 border-indigo-200' : 'bg-emerald-100 text-emerald-800 border-emerald-200'"
                                                    class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full border"
                                                    x-text="menu.kind === 'hybrid_bundle' ? 'Bundel Print + Finishing' : 'Cetak PDF'"
                                                ></span>

                                                <template x-if="getMenuFilledCount(menu) === menu.quota && !menu.slots.some(s => s.inspecting || s.error)">
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-600 text-white shadow-2xs">
                                                        <i class="fa-solid fa-check text-[9px]"></i>
                                                        <span x-text="menu.quota + '/' + menu.quota + ' File Lengkap'"></span>
                                                    </span>
                                                </template>

                                                <template x-if="getMenuFilledCount(menu) !== menu.quota || menu.slots.some(s => s.inspecting || s.error)">
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-300">
                                                        <i class="fa-solid fa-triangle-exclamation text-[9px]"></i>
                                                        <span x-text="'Terisi ' + getMenuFilledCount(menu) + '/' + menu.quota + ' File — Kurang ' + Math.max(0, menu.quota - getMenuFilledCount(menu)) + ' PDF lagi'"></span>
                                                    </span>
                                                </template>
                                            </div>
                                            <h4 class="font-extrabold text-gray-900 text-xs sm:text-sm leading-snug" x-text="menu.title"></h4>
                                        </div>

                                        <button
                                            type="button"
                                            @click="removePrintMenu(menu.key)"
                                            class="text-gray-400 hover:text-red-600 p-1 rounded-lg hover:bg-red-50 transition cursor-pointer shrink-0"
                                            title="Hapus menu dari keranjang"
                                        >
                                            <i class="fa-solid fa-xmark text-sm"></i>
                                        </button>
                                    </div>

                                    <!-- Kuota File Control & Menu Subtotal -->
                                    <div class="flex items-center justify-between gap-2 bg-white px-2.5 py-2 rounded-xl border border-gray-200/80">
                                        <div>
                                            <span class="text-[11px] font-bold text-gray-700 block">Kuota File PDF</span>
                                            <span class="text-[10px] text-gray-400" x-text="'Wajib isi ' + menu.quota + ' file berbeda'"></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="inline-flex items-center border border-gray-200 rounded-lg overflow-hidden bg-gray-50">
                                                <button
                                                    type="button"
                                                    @click="decrementPrintMenuQuota(menu.key)"
                                                    class="w-7 h-7 flex items-center justify-center font-bold text-gray-700 hover:bg-gray-200 cursor-pointer"
                                                    title="Kurangi kuota file"
                                                >-</button>
                                                <span class="w-8 text-center text-xs font-black text-gray-900" x-text="menu.quota"></span>
                                                <button
                                                    type="button"
                                                    @click="incrementPrintMenuQuota(menu.key)"
                                                    :disabled="getTotalPdfSlotCount() >= 10"
                                                    class="w-7 h-7 flex items-center justify-center font-bold text-white bg-[#10A760] hover:bg-[#0D8A4E] cursor-pointer disabled:opacity-40"
                                                    title="Tambah kuota file"
                                                >+</button>
                                            </div>
                                            <span class="text-xs font-black text-[#10A760] min-w-[4.5rem] text-right" x-text="formatRupiah(getMenuSubtotal(menu))"></span>
                                        </div>
                                    </div>

                                    <!-- Compact Print Service Selector (Always editable for Hybrid Bundle) -->
                                    <div x-show="menu.kind === 'hybrid_bundle'" class="bg-white p-2.5 rounded-xl border border-indigo-200/80 space-y-1">
                                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-indigo-800">
                                            Jenis Print Pasangan Bundel:
                                        </label>
                                        <select
                                            x-model="menu.printServiceId"
                                            class="w-full text-xs rounded-lg border-indigo-200 bg-indigo-50/30 focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                                        >
                                            <template x-for="svc in printServices" :key="svc.id">
                                                <option :value="String(svc.id)" x-text="svc.name + ' (' + formatRupiah(svc.price) + '/' + svc.unit + ')'"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div x-show="menu.warning" x-cloak class="p-2 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-800 font-semibold flex items-start gap-1.5">
                                        <i class="fa-solid fa-circle-info text-amber-600 mt-0.5 shrink-0"></i>
                                        <span x-text="menu.warning"></span>
                                    </div>

                                    <!-- 1:1 Slot Upload Containers Inside Card -->
                                    <div class="space-y-2.5">
                                        <template x-for="(slot, slotIdx) in menu.slots" :key="slot.slotUid">
                                            <div class="rounded-xl border bg-white p-2.5 space-y-2 transition-all"
                                                :class="slot.error ? 'border-red-300 bg-red-50/30' : (slot.file ? 'border-emerald-200' : 'border-dashed border-emerald-300')">

                                                <input
                                                    type="file"
                                                    :id="'slot-file-' + slot.slotUid"
                                                    multiple
                                                    accept=".pdf,application/pdf"
                                                    @change="handleSlotFileChange(menu.key, slotIdx, $event)"
                                                    class="hidden"
                                                >

                                                <!-- SLOT EMPTY STATE -->
                                                <template x-if="!slot.file">
                                                    <div
                                                        @click="triggerSlotInput(slot.slotUid)"
                                                        @dragover.prevent="slot.dragOver = true"
                                                        @dragleave.prevent="slot.dragOver = false"
                                                        @drop.prevent="handleSlotDrop(menu.key, slotIdx, $event)"
                                                        :class="slot.dragOver ? 'bg-emerald-50 border-[#10A760] ring-2 ring-[#10A760]/30' : 'hover:bg-emerald-50/40'"
                                                        class="rounded-lg p-3 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-1"
                                                    >
                                                        <div class="inline-flex items-center gap-1.5 text-xs font-extrabold text-[#0D8A4E]">
                                                            <i class="fa-solid fa-cloud-arrow-up"></i>
                                                            <span x-text="'+ Unggah File PDF (Slot ' + (slotIdx + 1) + '/' + menu.quota + ')'"></span>
                                                        </div>
                                                        <span class="text-[10px] text-gray-500">
                                                            Klik atau tarik &amp; lepas .pdf (maks 25 MB). Bisa pilih banyak PDF untuk mengisi slot kosong otomatis.
                                                        </span>
                                                    </div>
                                                </template>

                                                <!-- SLOT FILLED / INSPECTING STATE -->
                                                <template x-if="slot.file">
                                                    <div class="space-y-2.5">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <div class="min-w-0 flex-1">
                                                                <div class="flex items-center gap-1.5 mb-0.5">
                                                                    <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-emerald-600 text-white shrink-0" x-text="'File ' + (slotIdx + 1) + '/' + menu.quota"></span>
                                                                    <span class="font-bold text-gray-900 text-xs truncate" x-text="slot.name"></span>
                                                                </div>
                                                                <div class="flex flex-wrap items-center gap-1 text-[10px]">
                                                                    <span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 font-semibold" x-text="slot.sizeLabel"></span>
                                                                    <template x-if="slot.inspecting">
                                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 font-bold">
                                                                            <i class="fa-solid fa-spinner fa-spin"></i> Hitung hal...
                                                                        </span>
                                                                    </template>
                                                                    <template x-if="!slot.inspecting && slot.valid && !slot.isEncrypted">
                                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold">
                                                                            <i class="fa-solid fa-wand-magic-sparkles text-[9px]"></i>
                                                                            <span x-text="slot.pageCount + ' hal (pdf.js)'"></span>
                                                                        </span>
                                                                    </template>
                                                                    <template x-if="!slot.inspecting && slot.valid && slot.isEncrypted">
                                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold">
                                                                            <i class="fa-solid fa-lock text-[9px]"></i> Terkunci (isi halaman)
                                                                        </span>
                                                                    </template>
                                                                </div>
                                                            </div>

                                                            <div class="flex items-center gap-1 shrink-0">
                                                                <button
                                                                    type="button"
                                                                    x-show="slot.previewUrl && !slot.inspecting && slot.valid && !slot.error"
                                                                    @click="openSlotPreview(slot)"
                                                                    class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-md bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 cursor-pointer"
                                                                    title="Pratinjau isi halaman PDF sebelum bayar"
                                                                >
                                                                    <i class="fa-regular fa-eye"></i>
                                                                    <span>Preview</span>
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    @click="triggerSlotInput(slot.slotUid)"
                                                                    class="text-[10px] font-bold px-2 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 cursor-pointer"
                                                                    title="Ganti file PDF (catatan & rangkap tetap tersimpan)"
                                                                >
                                                                    Ganti
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    @click="clearSlot(menu.key, slotIdx)"
                                                                    class="text-[10px] font-bold px-2 py-1 rounded-md bg-red-50 hover:bg-red-100 text-red-600 cursor-pointer"
                                                                    title="Kosongkan slot ini"
                                                                >
                                                                    Kosongkan
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div x-show="slot.error" class="p-2 rounded-lg bg-red-100/80 border border-red-200 text-[11px] text-red-800 font-semibold" x-text="slot.error"></div>
                                                        <div x-show="slot.warning && !slot.error" class="p-2 rounded-lg bg-amber-50 border border-amber-200 text-[10px] text-amber-800" x-text="slot.warning"></div>

                                                        <!-- Configuration inside filled valid slot -->
                                                        <div x-show="!slot.inspecting && slot.valid && !slot.error" class="space-y-2 pt-1 border-t border-gray-100">
                                                            <div class="grid grid-cols-3 gap-2">
                                                                <div>
                                                                    <label class="block text-[10px] font-bold text-gray-600 mb-0.5">Kertas</label>
                                                                    <select x-model="slot.paperSize" class="w-full text-[11px] rounded-lg border-gray-200 py-1.5 px-2 focus:border-[#10A760] focus:ring-[#10A760]">
                                                                        <option value="A4">A4</option>
                                                                        <option value="F4">F4</option>
                                                                        <option value="A3">A3</option>
                                                                        <option value="Legal">Legal</option>
                                                                    </select>
                                                                </div>

                                                                <div>
                                                                    <label class="block text-[10px] font-bold text-gray-600 mb-0.5">Halaman</label>
                                                                    <input
                                                                        type="number"
                                                                        min="1"
                                                                        max="5000"
                                                                        x-model.number="slot.pageCount"
                                                                        :readonly="!slot.isEncrypted"
                                                                        :class="slot.isEncrypted ? 'bg-white border-amber-300' : 'bg-gray-100 border-gray-200 text-gray-700'"
                                                                        class="w-full text-[11px] font-bold rounded-lg py-1.5 px-2"
                                                                    >
                                                                </div>

                                                                <div>
                                                                    <label class="block text-[10px] font-bold text-gray-600 mb-0.5">Rangkap (Cop.)</label>
                                                                    <div class="inline-flex items-center border border-gray-200 rounded-lg overflow-hidden w-full bg-white">
                                                                        <button type="button" @click="if (slot.copies > 1) slot.copies--" class="px-2 py-1.5 text-gray-700 font-bold text-xs hover:bg-gray-100 cursor-pointer">-</button>
                                                                        <input type="number" min="1" max="500" x-model.number="slot.copies" class="w-full text-center text-[11px] font-extrabold border-none focus:ring-0 py-1.5 px-0">
                                                                        <button type="button" @click="slot.copies++" class="px-2 py-1.5 text-white bg-[#10A760] font-bold text-xs hover:bg-[#0D8A4E] cursor-pointer">+</button>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div>
                                                                <label class="block text-[10px] font-bold text-gray-700 mb-0.5">
                                                                    <i class="fa-regular fa-note-sticky text-[#10A760] mr-1"></i>Note Khusus File Ini (Dikirim ke WA Toko):
                                                                </label>
                                                                <textarea
                                                                    x-model="slot.note"
                                                                    maxlength="500"
                                                                    rows="2"
                                                                    placeholder="Misal: Hal. 1-2 warna, sisanya hitam putih..."
                                                                    class="w-full text-[11px] px-2.5 py-1.5 bg-slate-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-1 focus:ring-[#10A760] focus:border-[#10A760]"
                                                                ></textarea>
                                                            </div>

                                                            <div class="bg-emerald-50/70 rounded-lg border border-emerald-200/80 px-2.5 py-1.5 flex items-center justify-between gap-2 text-[11px]">
                                                                <div class="text-gray-600 leading-tight">
                                                                    <span x-text="getSlotBreakdown(menu, slot).printQty + 'x Print (' + formatRupiah(getSlotBreakdown(menu, slot).printSubtotal) + ')'"></span>
                                                                    <template x-if="getSlotBreakdown(menu, slot).finishQty > 0">
                                                                        <span class="text-indigo-700 font-bold block" x-text="'+ ' + getSlotBreakdown(menu, slot).finishQty + 'x ' + getSlotBreakdown(menu, slot).finishName + ' (' + formatRupiah(getSlotBreakdown(menu, slot).finishSubtotal) + ')'"></span>
                                                                    </template>
                                                                </div>
                                                                <span class="font-black text-[#10A760] shrink-0" x-text="formatRupiah(getSlotSubtotal(menu, slot))"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- GROUP 2: LAYANAN BERKAS FISIK (OFFLINE DI TOKO) -->
                        <div x-show="Object.keys(offlineCart).length > 0" x-cloak class="space-y-2">
                            <div class="text-[10px] font-black uppercase tracking-wider text-amber-800 flex items-center gap-1.5">
                                <i class="fa-solid fa-store"></i>
                                <span>Layanan Berkas Fisik (Offline di Toko)</span>
                            </div>
                            <template x-for="item in Object.values(offlineCart)" :key="item.key">
                                <div class="bg-amber-50/70 p-2.5 rounded-xl border border-amber-200 text-xs space-y-1.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-bold text-gray-900" x-text="item.name"></div>
                                            <span class="inline-block text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-300 mt-0.5">
                                                Wajib Bawa Berkas Fisik (Offline)
                                            </span>
                                            <div class="text-[11px] text-amber-800 font-bold mt-0.5" x-text="formatRupiah(item.price * item.qty)"></div>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <button type="button" @click="removeOfflineItem(item.key)" class="w-6 h-6 rounded-md bg-white border border-amber-300 text-gray-700 font-bold flex items-center justify-center hover:bg-gray-100 cursor-pointer">-</button>
                                            <span class="w-6 text-center font-bold text-gray-900" x-text="item.qty"></span>
                                            <button type="button" @click="incrementOfflineItem(item.key)" class="w-6 h-6 rounded-md bg-amber-600 text-white font-bold flex items-center justify-center hover:bg-amber-700 cursor-pointer">+</button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <p class="text-[10px] text-amber-800 bg-amber-50 px-2.5 py-1.5 rounded-lg border border-amber-200 leading-snug">
                                Serahkan dokumen/berkas fisik asli Anda di meja kasir toko untuk diproses.
                            </p>
                        </div>

                        <!-- GROUP 3: BARANG ATK & PERLENGKAPAN -->
                        <div x-show="Object.keys(cart).length > 0" x-cloak class="space-y-2">
                            <div class="text-[10px] font-black uppercase tracking-wider text-sky-800 flex items-center gap-1.5">
                                <i class="fa-solid fa-box-open"></i>
                                <span>Barang ATK &amp; Perlengkapan</span>
                            </div>
                            <template x-for="item in Object.values(cart)" :key="item.id">
                                <div class="flex items-center justify-between bg-gray-50 p-2.5 rounded-xl border border-gray-200/60 text-xs">
                                    <div class="flex-1 pr-2">
                                        <div class="font-bold text-gray-800" x-text="item.name"></div>
                                        <div class="text-[11px] text-[#10A760] font-semibold" x-text="formatRupiah(item.price * item.qty)"></div>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button type="button" @click="removeItem(item.id)" class="w-6 h-6 rounded-md bg-white border border-gray-300 text-gray-700 font-bold flex items-center justify-center hover:bg-gray-100 cursor-pointer">-</button>
                                        <span class="w-6 text-center font-bold text-gray-900" x-text="item.qty"></span>
                                        <button type="button" @click="addItem(item)" class="w-6 h-6 rounded-md bg-[#10A760] text-white font-bold flex items-center justify-center hover:bg-[#0D8A4E] cursor-pointer">+</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Data Pemesan -->
                    <div class="space-y-1.5 pt-2 border-t border-gray-100">
                        <label class="block text-[11px] font-bold text-gray-700">Nama Pemesan *</label>
                        <input
                            type="text"
                            x-model="customerName"
                            placeholder="Contoh: Daffa Saragih (NIM 251402011)"
                            class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#10A760] focus:border-[#10A760]"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-gray-700">No. WhatsApp Mahasiswa (Disarankan):</label>
                        <input
                            type="text"
                            x-model="customerWhatsapp"
                            placeholder="Contoh: 0857-6973-7410 / 0852..."
                            class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#10A760] focus:border-[#10A760]"
                        />
                        <p class="text-[10px] text-gray-400 leading-tight">
                            Untuk menerima salinan Bukti Invoice PDF resmi &amp; notifikasi otomatis saat pesanan selesai.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-gray-700">Catatan Umum Pesanan (Opsional):</label>
                        <textarea
                            x-model="orderNotes"
                            rows="2"
                            placeholder="Contoh: Diambil pukul 13.00 WIB selesai kuliah"
                            class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#10A760] focus:border-[#10A760]"
                        ></textarea>
                    </div>

                    <!-- PEMBAYARAN -->
                    <div class="space-y-2 pt-2 border-t border-gray-100">
                        <div class="flex items-center justify-between">
                            <label class="block text-[11px] font-bold text-gray-700">Metode Pembayaran:</label>
                            <span class="text-[10px] text-gray-400 font-medium">Pilih salah satu</span>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5 text-[11px]">
                            <label class="flex flex-col items-center p-2 rounded-xl border cursor-pointer text-center transition" :class="paymentMethod === 'qris' ? 'border-[#10A760] bg-green-50/70 font-bold text-[#10A760] shadow-sm' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                <input type="radio" value="qris" x-model="paymentMethod" class="sr-only">
                                <i class="fa-solid fa-qrcode text-base mb-1"></i>
                                <span>QRIS</span>
                                <span class="text-[9px] font-normal text-emerald-600 mt-0.5">Otomatis</span>
                            </label>
                            <label class="flex flex-col items-center p-2 rounded-xl border cursor-pointer text-center transition" :class="paymentMethod === 'transfer' ? 'border-[#10A760] bg-green-50/70 font-bold text-[#10A760] shadow-sm' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                <input type="radio" value="transfer" x-model="paymentMethod" class="sr-only">
                                <i class="fa-solid fa-building-columns text-base mb-1"></i>
                                <span>Bank Transfer</span>
                                <span class="text-[9px] font-normal text-blue-600 mt-0.5">Virtual Acc</span>
                            </label>
                            <label class="flex flex-col items-center p-2 rounded-xl border cursor-pointer text-center transition" :class="paymentMethod === 'cod' ? 'border-[#10A760] bg-green-50/70 font-bold text-[#10A760] shadow-sm' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                <input type="radio" value="cod" x-model="paymentMethod" class="sr-only">
                                <i class="fa-solid fa-money-bill-wave text-base mb-1"></i>
                                <span>Tunai</span>
                                <span class="text-[9px] font-normal text-amber-600 mt-0.5">Di Kasir</span>
                            </label>
                        </div>

                        <div x-show="paymentMethod === 'transfer'" x-cloak class="mt-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="font-bold text-slate-700">Pilih Bank Tujuan:</span>
                                <span class="text-[10px] text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full font-semibold border border-emerald-200">PoC Static VA</span>
                            </div>
                            <div class="grid grid-cols-5 gap-1 text-[11px] font-semibold text-center">
                                <button type="button" @click="selectedBank = 'BCA'" class="py-1.5 px-1 rounded-lg border text-xs transition" :class="selectedBank === 'BCA' ? 'bg-[#005EAA] text-white border-[#005EAA] shadow-sm font-bold' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'">BCA</button>
                                <button type="button" @click="selectedBank = 'Mandiri'" class="py-1.5 px-1 rounded-lg border text-xs transition" :class="selectedBank === 'Mandiri' ? 'bg-[#003366] text-white border-[#003366] shadow-sm font-bold' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'">Mandiri</button>
                                <button type="button" @click="selectedBank = 'BNI'" class="py-1.5 px-1 rounded-lg border text-xs transition" :class="selectedBank === 'BNI' ? 'bg-[#E05A2B] text-white border-[#E05A2B] shadow-sm font-bold' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'">BNI</button>
                                <button type="button" @click="selectedBank = 'BRI'" class="py-1.5 px-1 rounded-lg border text-xs transition" :class="selectedBank === 'BRI' ? 'bg-[#00529C] text-white border-[#00529C] shadow-sm font-bold' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'">BRI</button>
                                <button type="button" @click="selectedBank = 'BSI'" class="py-1.5 px-1 rounded-lg border text-xs transition" :class="selectedBank === 'BSI' ? 'bg-[#00A39D] text-white border-[#00A39D] shadow-sm font-bold' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'">BSI</button>
                            </div>
                            <div class="text-[10px] text-gray-500 flex items-center gap-1.5 pt-1">
                                <i class="fa-solid fa-circle-info text-blue-500"></i>
                                <span>No. VA: <strong class="font-mono text-gray-700">88019920260001</strong> a.n. USUClassMap Fotokopi</span>
                            </div>
                        </div>

                        <div x-show="paymentMethod === 'qris'" x-cloak class="p-2 bg-emerald-50/60 border border-emerald-100 rounded-xl text-[11px] text-emerald-800 flex items-center gap-2">
                            <i class="fa-solid fa-qrcode text-emerald-600 text-sm"></i>
                            <span>QR Code EMVCo unik akan otomatis digenerate saat klik lanjut pembayaran.</span>
                        </div>

                        <div x-show="paymentMethod === 'cod'" x-cloak class="p-2 bg-amber-50/70 border border-amber-100 rounded-xl text-[11px] text-amber-800 flex items-center gap-2">
                            <i class="fa-solid fa-store text-amber-600 text-sm"></i>
                            <span>Bayar tunai langsung di kasir toko saat mengambil dokumen/cetakan Anda.</span>
                        </div>
                    </div>

                    <!-- Validation Lock Banner (Zero Mismatch Guard) -->
                    <div x-show="getIncompletePdfMenus().length > 0" x-cloak class="p-3 rounded-xl bg-amber-50 border border-amber-300 text-[11px] text-amber-900 space-y-1">
                        <div class="font-extrabold flex items-center gap-1.5">
                            <i class="fa-solid fa-lock text-amber-600"></i>
                            <span>Lengkapi file PDF sesuai kuota menu sebelum membayar:</span>
                        </div>
                        <ul class="list-disc pl-4 space-y-0.5 font-medium">
                            <template x-for="inc in getIncompletePdfMenus()" :key="inc.key">
                                <li>
                                    <span class="font-bold" x-text="inc.title"></span>:
                                    <template x-if="inc.hasInspecting">
                                        <span>sedang memproses file PDF...</span>
                                    </template>
                                    <template x-if="!inc.hasInspecting && inc.hasError">
                                        <span>perbaiki/ganti file PDF yang bermasalah.</span>
                                    </template>
                                    <template x-if="!inc.hasInspecting && !inc.hasError && inc.missing > 0">
                                        <span x-text="'terisi ' + inc.filled + '/' + inc.quota + ' file (kurang ' + inc.missing + ' PDF lagi)'"></span>
                                    </template>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div x-show="submitError" x-cloak class="p-2.5 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 font-semibold" x-text="submitError"></div>

                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-600">Total Tagihan:</span>
                        <span class="text-base sm:text-lg font-black text-[#10A760]" x-text="formatRupiah(getSubtotal())"></span>
                    </div>

                    <div class="pt-2 flex items-center gap-2">
                        <button
                            type="button"
                            @click="submitOrder()"
                            :disabled="isCheckoutDisabled()"
                            class="flex-1 bg-[#10A760] hover:bg-[#0D8A4E] text-white py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm font-extrabold transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <i class="fa-solid fa-spinner fa-spin" x-show="submitting"></i>
                            <i class="fa-solid fa-credit-card" x-show="!submitting"></i>
                            <span x-text="submitting ? 'Memproses Pesanan...' : (getIncompletePdfMenus().length > 0 ? 'Lengkapi PDF Dahulu' : 'Lanjut ke Pembayaran')"></span>
                        </button>

                        <a
                            href="{{ $place->getDirectionUrl() }}"
                            class="bg-[#D4A017] hover:bg-[#B8880F] text-white px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs font-bold transition-all shadow-md flex items-center justify-center gap-1.5 shrink-0"
                            title="Rute Jalan Kaki di Peta Kampus"
                        >
                            <i class="fa-solid fa-route"></i>
                            <span class="hidden sm:inline">Rute</span>
                        </a>
                    </div>

                    <p class="text-[10px] text-gray-400 text-center">
                        File PDF Anda disimpan aman secara privat dan otomatis dikirim ke WhatsApp operator saat terbayar.
                    </p>
                </div>
            </div>
        </div>

        <!-- MOBILE STICKY FLOATING CART BAR (< lg) -->
        <div
            x-show="!isCartEmpty()"
            x-cloak
            class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur border-t border-gray-200 shadow-2xl px-4 py-3"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-xs font-extrabold text-gray-900" x-text="'Keranjang: ' + getItemCount() + ' Item'"></span>
                        <template x-if="getTotalMissingPdfCount() > 0">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-300" x-text="'Kurang ' + getTotalMissingPdfCount() + ' PDF'"></span>
                        </template>
                    </div>
                    <div class="text-sm font-black text-[#10A760]" x-text="formatRupiah(getSubtotal())"></div>
                </div>
                <button
                    type="button"
                    @click="scrollToCartPanel()"
                    class="px-4 py-2.5 rounded-xl bg-[#10A760] hover:bg-[#0D8A4E] text-white text-xs font-extrabold shadow-sm cursor-pointer shrink-0 flex items-center gap-1.5"
                >
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Lihat Keranjang</span>
                </button>
            </div>
        </div>

        <!-- HYBRID CHOICE MODAL: LAMINATING & JILID (Opsi A Offline vs Opsi B Upload PDF Bundel) -->
        <div
            x-show="hybridModalOpen"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
            @keydown.escape.window="hybridModalOpen = false"
        >
            <div
                @click.away="hybridModalOpen = false"
                class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-lg w-full overflow-hidden"
            >
                <div class="px-5 py-4 bg-gradient-to-r from-indigo-600 to-emerald-600 text-white flex items-center justify-between">
                    <div>
                        <span class="text-[10px] uppercase tracking-wider font-extrabold text-indigo-100 block">Pilih Cara Pengerjaan Layanan</span>
                        <h3 class="text-sm sm:text-base font-black leading-tight" x-text="selectedHybridItem ? selectedHybridItem.name : ''"></h3>
                    </div>
                    <button type="button" @click="hybridModalOpen = false" class="text-white/80 hover:text-white text-xl font-bold px-2 cursor-pointer">&times;</button>
                </div>

                <div class="p-5 space-y-3.5">
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Tarif satuan layanan ini: <strong class="text-[#10A760]" x-text="selectedHybridItem ? (selectedHybridItem.formatted_price + ' / ' + selectedHybridItem.unit) : ''"></strong>. Silakan pilih salah satu cara berikut:
                    </p>

                    <!-- OPSI A: BAWA BERKAS FISIK SENDIRI (OFFLINE) -->
                    <button
                        type="button"
                        @click="selectHybridOffline()"
                        class="w-full text-left p-4 rounded-2xl border-2 border-amber-200 hover:border-amber-500 hover:bg-amber-50/60 transition-all space-y-1.5 cursor-pointer group"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-black text-xs sm:text-sm text-gray-900 group-hover:text-amber-900 flex items-center gap-2">
                                <i class="fa-solid fa-store text-amber-600"></i>
                                <span>Opsi A — Bawa Berkas Fisik Sendiri (Offline)</span>
                            </span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-300 shrink-0">
                                Wajib Bawa Berkas
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-600 leading-relaxed">
                            Untuk berkas asli yang tidak dicetak (contoh: KTP, KTM, Ijazah asli, atau berkas yang sudah Anda print sendiri). Pengerjaan dilakukan secara <strong>offline</strong> saat Anda membawa berkas ke toko.
                        </p>
                    </button>

                    <!-- OPSI B: SEKALIAN PRINT FILE DULU (UPLOAD PDF + FINISHING) -->
                    <button
                        type="button"
                        @click="selectHybridBundlePdf()"
                        class="w-full text-left p-4 rounded-2xl border-2 border-emerald-200 hover:border-[#10A760] hover:bg-emerald-50/60 transition-all space-y-1.5 cursor-pointer group"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-black text-xs sm:text-sm text-gray-900 group-hover:text-[#0D8A4E] flex items-center gap-2">
                                <i class="fa-solid fa-file-pdf text-[#10A760]"></i>
                                <span>Opsi B — Sekalian Print File-nya Dulu (Upload PDF + Finishing)</span>
                            </span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300 shrink-0">
                                Bundel Print + PDF
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-600 leading-relaxed">
                            Unggah file PDF yang ingin dicetak sekaligus langsung di-laminating / di-jilid oleh toko. Biaya dihitung transparan: <strong>Biaya Print + Biaya Laminating/Jilid</strong>.
                        </p>
                    </button>
                </div>

                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button type="button" @click="hybridModalOpen = false" class="px-4 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 bg-white border border-gray-200 rounded-xl cursor-pointer">
                        Batal
                    </button>
                </div>
            </div>
        </div>

        <!-- IN-PAGE LIVE PDF PREVIEW MODAL -->
        <div
            x-show="previewModalOpen"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6"
            @keydown.escape.window="closePdfPreview()"
        >
            <div
                @click.away="closePdfPreview()"
                class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-4xl w-full overflow-hidden flex flex-col max-h-[90vh]"
            >
                <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between gap-3 shrink-0">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-red-500 text-white text-[10px] font-black uppercase shrink-0">PDF</span>
                            <h3 class="text-xs sm:text-sm font-black truncate" x-text="previewFile.name"></h3>
                        </div>
                        <p class="text-[11px] text-slate-300 mt-0.5 flex flex-wrap items-center gap-2">
                            <span x-text="previewFile.sizeLabel"></span>
                            <span>&bull;</span>
                            <span x-text="previewFile.isEncrypted ? (previewFile.pageCount + ' halaman (Input Manual — Terkunci)') : (previewFile.pageCount + ' halaman')"></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a
                            :href="previewFile.url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition"
                        >
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            <span>Buka di Tab Baru</span>
                        </a>
                        <button
                            type="button"
                            @click="closePdfPreview()"
                            class="text-slate-300 hover:text-white px-2.5 py-1 text-lg font-bold cursor-pointer"
                            aria-label="Tutup Preview"
                        >&times;</button>
                    </div>
                </div>

                <div x-show="previewFile.note && previewFile.note.trim()" class="px-5 py-2 bg-amber-50 border-b border-amber-200 text-xs text-amber-900 shrink-0">
                    <span class="font-extrabold">Catatan Khusus File:</span>
                    <span class="italic" x-text="previewFile.note"></span>
                </div>

                <div class="flex-1 bg-slate-800 min-h-[65vh]">
                    <template x-if="previewModalOpen && previewFile.url">
                        <iframe
                            :src="previewFile.url"
                            class="w-full h-[70vh] border-0 bg-white"
                            title="Pratinjau Dokumen PDF"
                        ></iframe>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
