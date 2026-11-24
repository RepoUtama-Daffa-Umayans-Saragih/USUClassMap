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

            return {
                ...window.usuCatalogFilter(window.usuFotocopyCatalog),
                printServices: Array.isArray(window.usuPrintServices) ? window.usuPrintServices : [],
                finishingServices: Array.isArray(window.usuFinishingServices) ? window.usuFinishingServices : [],

                // Contextual Multi-PDF state (HIDDEN by default until user picks Print / Hybrid Print+Finishing)
                pdfPanelVisible: false,
                activePrintServiceId: '',
                activeLockedFinishingId: '',
                activePaperSize: 'A4',
                activeContextLabel: '',
                pdfFiles: [],
                pdfDragOver: false,
                pdfGlobalError: '',

                // Hybrid Choice Modal state
                hybridModalOpen: false,
                selectedHybridItem: null,

                // Physical-Only Offline Reminder Banner
                physicalNoticeText: '',

                // Separate Shopping Carts: Offline Physical Services vs Standard ATK Goods
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

                init() {
                    this.activePrintServiceId = this.defaultPrintServiceId();
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

                scrollToPdfSection() {
                    this.$nextTick(() => {
                        const el = document.getElementById('contextual-pdf-section');
                        if (el) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    });
                },

                handleCatalogItemClick(item) {
                    const behavior = item.order_behavior || (item.item_type === 'barang' ? 'physical_goods' : 'digital_print');

                    if (behavior === 'digital_print') {
                        this.activePrintServiceId = String(item.id);
                        this.activeLockedFinishingId = '';
                        this.activePaperSize = this.detectPaperSize(item.name);
                        this.activeContextLabel = 'Mode Cetak Online: ' + item.name;
                        this.pdfPanelVisible = true;
                        this.scrollToPdfSection();
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
                    this.activeLockedFinishingId = String(item.id);
                    this.activePrintServiceId = this.smartPrintForFinishing(item);
                    this.activePaperSize = this.detectPaperSize(item.name);
                    this.activeContextLabel = 'Bundel Aktif: Print PDF + ' + item.name;
                    this.pdfPanelVisible = true;
                    this.hybridModalOpen = false;
                    this.selectedHybridItem = null;
                    this.scrollToPdfSection();
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

                async waitForPdfInspector(maxWaitMs = 2500) {
                    const start = Date.now();
                    while (typeof window.usuInspectPdfFile !== 'function' && (Date.now() - start) < maxWaitMs) {
                        await new Promise(r => setTimeout(r, 80));
                    }
                    return typeof window.usuInspectPdfFile === 'function' ? window.usuInspectPdfFile : null;
                },

                handlePdfInputChange(event) {
                    const files = Array.from(event.target.files || []);
                    if (files.length > 0) {
                        this.enqueuePdfFiles(files);
                    }
                    event.target.value = '';
                },

                handlePdfDrop(event) {
                    this.pdfDragOver = false;
                    const files = Array.from(event.dataTransfer?.files || []);
                    if (files.length > 0) {
                        this.enqueuePdfFiles(files);
                    }
                },

                async enqueuePdfFiles(files) {
                    this.pdfGlobalError = '';
                    this.pdfPanelVisible = true;
                    const inspector = await this.waitForPdfInspector();

                    for (const file of files) {
                        if (this.pdfFiles.length >= 10) {
                            this.pdfGlobalError = 'Maksimal 10 file PDF dalam satu pesanan.';
                            break;
                        }

                        const currentTotalBytes = this.pdfFiles.reduce((acc, f) => acc + (f.sizeBytes || 0), 0);
                        if (currentTotalBytes + file.size > 80 * 1024 * 1024) {
                            this.pdfGlobalError = 'Total ukuran seluruh file PDF melebihi batas maksimal 80 MB.';
                            break;
                        }

                        const uid = 'pdf-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
                        const entry = {
                            uid,
                            file,
                            name: file.name,
                            sizeBytes: file.size,
                            sizeLabel: this.formatFileSize(file.size),
                            inspecting: true,
                            valid: false,
                            error: null,
                            warning: null,
                            pageCount: 1,
                            isEncrypted: false,
                            printServiceId: this.activePrintServiceId || this.defaultPrintServiceId(),
                            paperSize: this.activePaperSize || 'A4',
                            finishingServiceId: this.activeLockedFinishingId || '',
                            isBundledFinishing: Boolean(this.activeLockedFinishingId),
                            copies: 1,
                            note: ''
                        };

                        this.pdfFiles.push(entry);
                        const reactiveIdx = this.pdfFiles.findIndex(item => item.uid === uid);

                        let result;
                        if (inspector) {
                            result = await inspector(file);
                        } else {
                            const isPdfExt = file.name.toLowerCase().endsWith('.pdf');
                            result = isPdfExt
                                ? { valid: true, pageCount: 1, isEncrypted: false, warning: 'Detektor PDF sedang dimuat; mohon cek jumlah halaman manual.' }
                                : { valid: false, error: 'Hanya file .pdf yang diperbolehkan.' };
                        }

                        if (reactiveIdx !== -1 && this.pdfFiles[reactiveIdx]) {
                            this.pdfFiles[reactiveIdx].inspecting = false;
                            this.pdfFiles[reactiveIdx].valid = Boolean(result.valid);
                            this.pdfFiles[reactiveIdx].error = result.error || null;
                            this.pdfFiles[reactiveIdx].warning = result.warning || null;
                            this.pdfFiles[reactiveIdx].pageCount = Number(result.pageCount || 1);
                            this.pdfFiles[reactiveIdx].isEncrypted = Boolean(result.isEncrypted);
                        }
                    }
                },

                removePdfFile(uid) {
                    this.pdfFiles = this.pdfFiles.filter(f => f.uid !== uid);
                    this.pdfGlobalError = '';
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

                getPdfFileBreakdown(pdf) {
                    if (!pdf.valid || pdf.error) {
                        return { printQty: 0, printUnit: 0, printSubtotal: 0, finishQty: 0, finishUnit: 0, finishSubtotal: 0, finishName: '', totalSubtotal: 0 };
                    }
                    const printSvc = this.getPrintService(pdf.printServiceId);
                    const finishSvc = this.getFinishingService(pdf.finishingServiceId);
                    const pages = Math.max(1, Number(pdf.pageCount || 1));
                    const copies = Math.max(1, Number(pdf.copies || 1));
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

                getPdfFileSubtotal(pdf) {
                    return this.getPdfFileBreakdown(pdf).totalSubtotal;
                },

                validPdfFiles() {
                    return this.pdfFiles.filter(f => f.valid && !f.error);
                },

                getPdfSubtotal() {
                    return this.validPdfFiles().reduce((sum, pdf) => sum + this.getPdfFileSubtotal(pdf), 0);
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
                    return this.validPdfFiles().length + this.getOfflineItemCount() + this.getCartItemCount();
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

                formatRupiah(number) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(number || 0));
                },

                submitOrder() {
                    this.submitError = '';
                    const readyPdfs = this.validPdfFiles();
                    const hasInvalidPdfs = this.pdfFiles.some(f => f.error || f.inspecting);

                    if (hasInvalidPdfs) {
                        this.submitError = 'Masih ada file PDF yang sedang diproses atau bermasalah. Hapus file yang gagal sebelum melanjutkan.';
                        return;
                    }

                    if (readyPdfs.length === 0 && Object.keys(this.offlineCart).length === 0 && Object.keys(this.cart).length === 0) {
                        this.submitError = 'Keranjang pesanan masih kosong! Pilih layanan cetak (dan unggah PDF), layanan offline, atau barang ATK di sebelah kiri terlebih dahulu.';
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

                    const pdfConfigs = readyPdfs.map(pdf => {
                        const printSvc = this.getPrintService(pdf.printServiceId);
                        const finishSvc = this.getFinishingService(pdf.finishingServiceId);
                        return {
                            page_count: Math.max(1, Number(pdf.pageCount || 1)),
                            copies: Math.max(1, Number(pdf.copies || 1)),
                            print_service_id: pdf.printServiceId ? Number(pdf.printServiceId) : null,
                            print_mode: printSvc ? printSvc.name : 'Print Hitam Putih A4',
                            paper_size: pdf.paperSize || 'A4',
                            finishing_service_id: pdf.finishingServiceId ? Number(pdf.finishingServiceId) : null,
                            finishing_option: finishSvc ? finishSvc.name : null,
                            is_encrypted: Boolean(pdf.isEncrypted),
                            note: (pdf.note || '').trim()
                        };
                    });

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

                    readyPdfs.forEach((pdf) => {
                        formData.append('pdf_files[]', pdf.file, pdf.name);
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
        class="max-w-6xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6"
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
                target="_blank"
                class="inline-flex items-center gap-2 bg-[#D4A017] hover:bg-[#B8880F] text-white text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-xs w-full sm:w-auto justify-center"
            >
                <i class="fa-solid fa-location-dot text-xs"></i>
                <span>Pergi ke Maps</span>
            </a>
        </div>

        <!-- Main Layout: Catalog-First Left, Grouped Sticky Cart Right -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- LEFT 2 COLS -->
            <div class="lg:col-span-2 space-y-6">

                <!-- SECTION 1 (POSISI UTAMA): KATALOG LAYANAN CETAK, FINISHING & BARANG ATK -->
                <div class="space-y-4">
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-gray-900 flex items-center gap-2 mb-1">
                            <span class="w-1.5 h-5 bg-[#10A760] rounded-full"></span>
                            <span>Pilih Layanan Cetak, Finishing &amp; Barang ATK</span>
                        </h2>
                        <p class="text-xs text-gray-500">
                            Pilih layanan yang Anda butuhkan terlebih dahulu. Area unggah PDF akan terbuka otomatis hanya ketika Anda memilih layanan Print/Cetak atau opsi bundel Print + Laminating/Jilid.
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

                <!-- SECTION 2 (KONTEKSTUAL — TERSEMBUNYI SECARA DEFAULT): UPLOAD MULTI-PDF + BUNDEL -->
                <div
                    id="contextual-pdf-section"
                    x-show="pdfPanelVisible || pdfFiles.length > 0"
                    x-transition
                    x-cloak
                    class="bg-white rounded-2xl border-2 border-emerald-300 shadow-sm p-4 sm:p-6 space-y-4"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-emerald-100">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base sm:text-lg font-black text-gray-900 flex items-center gap-2">
                                    <span class="w-1.5 h-5 bg-[#10A760] rounded-full"></span>
                                    <span>Unggah Dokumen PDF untuk Dicetak (Multi-PDF)</span>
                                </h2>
                                <span x-show="activeContextLabel" class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200" x-text="activeContextLabel"></span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Halaman tiap PDF terdeteksi otomatis via <span class="font-semibold">pdf.js</span>. Cantumkan catatan khusus per file agar langsung tampil di WhatsApp operator toko saat lunas.
                            </p>
                        </div>
                        <button
                            type="button"
                            x-show="pdfFiles.length === 0"
                            @click="pdfPanelVisible = false"
                            class="text-xs font-bold text-gray-500 hover:text-gray-800 px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 self-start cursor-pointer"
                        >
                            Tutup Area PDF
                        </button>
                    </div>

                    <input
                        type="file"
                        id="multiPdfInput"
                        multiple
                        accept=".pdf,application/pdf"
                        @change="handlePdfInputChange($event)"
                        class="hidden"
                    >

                    <div
                        @click="document.getElementById('multiPdfInput').click()"
                        @dragover.prevent="pdfDragOver = true"
                        @dragleave.prevent="pdfDragOver = false"
                        @drop.prevent="handlePdfDrop($event)"
                        :class="pdfDragOver ? 'border-[#10A760] bg-emerald-50/70 ring-2 ring-[#10A760]/30' : 'border-emerald-300 hover:border-[#10A760] hover:bg-emerald-50/30'"
                        class="border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-2.5 group"
                    >
                        <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-[#10A760] flex items-center justify-center text-xl group-hover:scale-105 transition-transform shadow-2xs">
                            <i class="fa-solid fa-file-pdf"></i>
                        </div>
                        <div>
                            <p class="text-xs sm:text-sm font-extrabold text-gray-800 group-hover:text-[#0D8A4E]">
                                Tarik &amp; lepas file PDF Anda di sini, atau klik untuk memilih file
                            </p>
                            <p class="text-[11px] text-gray-500 mt-0.5">
                                Format wajib <strong>.pdf</strong> &bull; Maks 25 MB/file &bull; Maks 10 file (total 80 MB) &bull; Mendukung Auto Page Count &amp; Note per PDF
                            </p>
                        </div>
                    </div>

                    <div x-show="pdfGlobalError" x-cloak class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span x-text="pdfGlobalError"></span>
                    </div>

                    <!-- Per-File PDF Configuration Cards -->
                    <div class="space-y-3.5" x-show="pdfFiles.length > 0" x-cloak>
                        <template x-for="(pdf, idx) in pdfFiles" :key="pdf.uid">
                            <div
                                class="rounded-2xl border p-4 space-y-3 transition-all"
                                :class="pdf.error ? 'border-red-300 bg-red-50/40' : 'border-emerald-200 bg-slate-50/60'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-xs text-white shrink-0 shadow-2xs"
                                            :class="pdf.error ? 'bg-red-500' : 'bg-[#10A760]'">
                                            <span x-text="'PDF #' + (idx + 1)"></span>
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="font-extrabold text-gray-900 text-xs sm:text-sm truncate" x-text="pdf.name"></h4>
                                            <div class="flex flex-wrap items-center gap-1.5 mt-1 text-[11px]">
                                                <span class="px-2 py-0.5 rounded-full bg-white border border-gray-200 text-gray-600 font-semibold" x-text="pdf.sizeLabel"></span>

                                                <template x-if="pdf.inspecting">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200 font-bold">
                                                        <i class="fa-solid fa-spinner fa-spin"></i> Mendeteksi halaman...
                                                    </span>
                                                </template>

                                                <template x-if="!pdf.inspecting && pdf.valid && !pdf.isEncrypted">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold">
                                                        <i class="fa-solid fa-wand-magic-sparkles text-[10px]"></i>
                                                        <span x-text="pdf.pageCount + ' Halaman (Terdeteksi Otomatis pdf.js)'"></span>
                                                    </span>
                                                </template>

                                                <template x-if="!pdf.inspecting && pdf.valid && pdf.isEncrypted">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200 font-bold">
                                                        <i class="fa-solid fa-lock text-[10px]"></i> PDF Terkunci Password
                                                    </span>
                                                </template>

                                                <template x-if="pdf.finishingServiceId">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 border border-indigo-200 font-bold">
                                                        <i class="fa-solid fa-layer-group text-[10px]"></i> Bundel Print + Finishing
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        @click="removePdfFile(pdf.uid)"
                                        class="text-xs font-bold text-red-600 hover:bg-red-50 px-2.5 py-1 rounded-lg transition cursor-pointer shrink-0"
                                    >
                                        <i class="fa-solid fa-trash-can mr-1"></i>Hapus
                                    </button>
                                </div>

                                <div x-show="pdf.error" class="p-2.5 rounded-xl bg-red-100/70 border border-red-200 text-xs text-red-800 font-semibold" x-text="pdf.error"></div>

                                <div x-show="pdf.warning && !pdf.error" class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-800 flex items-start gap-2">
                                    <i class="fa-solid fa-shield-halved text-amber-600 mt-0.5"></i>
                                    <span x-text="pdf.warning"></span>
                                </div>

                                <div x-show="!pdf.inspecting && pdf.valid && !pdf.error" class="space-y-3 pt-1">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                        <div class="sm:col-span-2">
                                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Layanan Cetak *</label>
                                            <select
                                                x-model="pdf.printServiceId"
                                                class="w-full text-xs rounded-xl border-gray-200 bg-white focus:border-[#10A760] focus:ring-[#10A760] py-2"
                                            >
                                                <template x-for="svc in printServices" :key="svc.id">
                                                    <option :value="svc.id" x-text="svc.name + ' (' + formatRupiah(svc.price) + '/' + svc.unit + ')'"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Ukuran Kertas</label>
                                            <select
                                                x-model="pdf.paperSize"
                                                class="w-full text-xs rounded-xl border-gray-200 bg-white focus:border-[#10A760] focus:ring-[#10A760] py-2"
                                            >
                                                <option value="A4">A4 (21 x 29.7 cm)</option>
                                                <option value="F4">F4 / Folio</option>
                                                <option value="A3">A3 (Poster/Besar)</option>
                                                <option value="Legal">Legal</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-bold text-gray-700 mb-1">
                                                Halaman <span x-show="pdf.isEncrypted" class="text-amber-600">(Manual*)</span>
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="5000"
                                                x-model.number="pdf.pageCount"
                                                :readonly="!pdf.isEncrypted"
                                                :class="pdf.isEncrypted ? 'bg-white border-amber-300' : 'bg-gray-100 border-gray-200 text-gray-700'"
                                                class="w-full text-xs font-bold rounded-xl py-2 px-3 focus:border-[#10A760] focus:ring-[#10A760]"
                                            >
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                                        <div>
                                            <label class="block text-[11px] font-bold text-gray-700 mb-1">
                                                Finishing / Laminating / Jilid
                                                <span x-show="pdf.finishingServiceId" class="text-indigo-600">(Bundel)</span>
                                            </label>
                                            <select
                                                x-model="pdf.finishingServiceId"
                                                :class="pdf.finishingServiceId ? 'border-indigo-300 bg-indigo-50/40 font-semibold' : 'border-gray-200 bg-white'"
                                                class="w-full text-xs rounded-xl focus:border-[#10A760] focus:ring-[#10A760] py-2"
                                            >
                                                <option value="">Tanpa Jilid / Laminating (Rp 0)</option>
                                                <template x-for="fin in finishingServices" :key="fin.id">
                                                    <option :value="fin.id" x-text="fin.name + ' (+' + formatRupiah(fin.price) + '/' + fin.unit + ')'"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Jumlah Rangkap</label>
                                            <div class="inline-flex items-center bg-white border border-gray-200 rounded-xl overflow-hidden w-full">
                                                <button type="button" @click="if (pdf.copies > 1) pdf.copies--" class="px-3 py-2 text-gray-700 font-bold hover:bg-gray-100 cursor-pointer">-</button>
                                                <input type="number" min="1" max="500" x-model.number="pdf.copies" class="w-full text-center text-xs font-extrabold border-none focus:ring-0 py-2">
                                                <button type="button" @click="pdf.copies++" class="px-3 py-2 text-white bg-[#10A760] font-bold hover:bg-[#0D8A4E] cursor-pointer">+</button>
                                            </div>
                                        </div>

                                        <div class="bg-white rounded-xl border border-emerald-200 px-3 py-2 text-right space-y-0.5">
                                            <div class="text-[10px] text-gray-500" x-text="getPdfFileBreakdown(pdf).printQty + 'x Print = ' + formatRupiah(getPdfFileBreakdown(pdf).printSubtotal)"></div>
                                            <template x-if="getPdfFileBreakdown(pdf).finishQty > 0">
                                                <div class="text-[10px] text-indigo-700 font-semibold" x-text="'+ ' + getPdfFileBreakdown(pdf).finishQty + 'x ' + getPdfFileBreakdown(pdf).finishName + ' = ' + formatRupiah(getPdfFileBreakdown(pdf).finishSubtotal)"></div>
                                            </template>
                                            <div class="text-sm font-black text-[#10A760]" x-text="formatRupiah(getPdfFileSubtotal(pdf))"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-800 mb-1">
                                            <i class="fa-regular fa-note-sticky text-[#10A760] mr-1"></i>
                                            Catatan Khusus File Ini (Dikirim ke WA Operator):
                                        </label>
                                        <textarea
                                            x-model="pdf.note"
                                            maxlength="500"
                                            rows="2"
                                            placeholder="Contoh: Halaman 1 & 5 cetak BERWARNA, sisanya hitam putih, langsung dilaminating / dijilid..."
                                            class="w-full text-xs px-3 py-2 bg-white border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#10A760] focus:border-[#10A760]"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- RIGHT 1 COL: GROUPED STICKY CART & CHECKOUT -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 sticky top-20 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-cart-shopping text-[#10A760] text-lg"></i>
                            <h3 class="font-extrabold text-gray-900 text-sm sm:text-base">Keranjang Pesanan</h3>
                        </div>
                        <span class="text-xs font-bold text-[#10A760] bg-green-50 px-2.5 py-0.5 rounded-full" x-text="getItemCount() + ' Item'"></span>
                    </div>

                    <!-- Grouped Cart Summary -->
                    <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                        <template x-if="validPdfFiles().length === 0 && Object.keys(offlineCart).length === 0 && Object.keys(cart).length === 0">
                            <div class="text-center py-6 text-gray-400 text-xs">
                                <i class="fa-solid fa-cart-shopping text-2xl text-gray-300 mb-2 block"></i>
                                Keranjang masih kosong.<br>Pilih layanan atau barang ATK di sebelah kiri.
                            </div>
                        </template>

                        <!-- GROUP 1: DOKUMEN CETAK ONLINE (MULTI-PDF & BUNDEL) -->
                        <div x-show="validPdfFiles().length > 0" x-cloak class="space-y-2">
                            <div class="text-[10px] font-black uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span>Dokumen Cetak Online (Multi-PDF)</span>
                            </div>
                            <template x-for="pdf in validPdfFiles()" :key="pdf.uid">
                                <div class="bg-emerald-50/70 p-2.5 rounded-xl border border-emerald-200/80 text-xs space-y-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="font-bold text-gray-900 truncate" x-text="pdf.name"></span>
                                        <span class="font-black text-[#10A760] shrink-0" x-text="formatRupiah(getPdfFileSubtotal(pdf))"></span>
                                    </div>
                                    <div class="text-[11px] text-gray-600" x-text="getPdfFileBreakdown(pdf).printQty + 'x Print (' + formatRupiah(getPdfFileBreakdown(pdf).printSubtotal) + ')'"></div>
                                    <template x-if="getPdfFileBreakdown(pdf).finishQty > 0">
                                        <div class="text-[11px] text-indigo-700 font-bold" x-text="'+ ' + getPdfFileBreakdown(pdf).finishQty + 'x ' + getPdfFileBreakdown(pdf).finishName + ' (' + formatRupiah(getPdfFileBreakdown(pdf).finishSubtotal) + ')'"></div>
                                    </template>
                                    <template x-if="pdf.note && pdf.note.trim()">
                                        <div class="text-[10px] text-amber-800 bg-amber-50 px-2 py-1 rounded-lg border border-amber-200 italic truncate" x-text="'Note: ' + pdf.note"></div>
                                    </template>
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

                    <div x-show="submitError" x-cloak class="p-2.5 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 font-semibold" x-text="submitError"></div>

                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-600">Total Tagihan:</span>
                        <span class="text-base sm:text-lg font-black text-[#10A760]" x-text="formatRupiah(getSubtotal())"></span>
                    </div>

                    <div class="pt-2 flex items-center gap-2">
                        <button
                            type="button"
                            @click="submitOrder()"
                            :disabled="submitting"
                            class="flex-1 bg-[#10A760] hover:bg-[#0D8A4E] text-white py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm font-extrabold transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <i class="fa-solid fa-spinner fa-spin" x-show="submitting"></i>
                            <i class="fa-solid fa-credit-card" x-show="!submitting"></i>
                            <span x-text="submitting ? 'Memproses Pesanan...' : 'Lanjut ke Pembayaran'"></span>
                        </button>

                        <a
                            href="{{ $place->getDirectionUrl() }}"
                            target="_blank"
                            class="bg-[#D4A017] hover:bg-[#B8880F] text-white px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs font-bold transition-all shadow-md flex items-center justify-center gap-1.5 shrink-0"
                            title="Pergi ke Maps"
                        >
                            <i class="fa-solid fa-location-dot"></i>
                            <span class="hidden sm:inline">Maps</span>
                        </a>
                    </div>

                    <p class="text-[10px] text-gray-400 text-center">
                        File PDF Anda disimpan aman secara privat dan otomatis dikirim ke WhatsApp operator saat terbayar.
                    </p>
                </div>
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
    </div>
</x-layouts.app>
