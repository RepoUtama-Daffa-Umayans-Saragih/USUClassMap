<x-layouts.admin>
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
            <a href="{{ route('admin.rooms.index') }}" class="hover:text-emerald-600 transition-colors">Ruangan</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="text-gray-700">Edit Ruangan</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Edit Ruangan: {{ $room->name }}</h1>
        <p class="text-sm text-gray-500 mt-1">Perbarui informasi gedung, lantai, kapasitas, atau perbarui foto panorama 360 derajat.</p>
    </div>

    <div class="max-w-3xl">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sm:p-8">
            <form method="POST" action="{{ route('admin.rooms.update', $room) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- Searchable Select Gedung --}}
                <div x-data="{
                    open: false,
                    search: '',
                    selectedId: '{{ old('building_id', $room->building_id) }}',
                    selectedName: '',
                    options: {{ Js::from($buildings->map(fn($b) => ['id' => (string)$b->id, 'name' => $b->name, 'code' => $b->code, 'faculty' => $b->faculty])) }},
                    init() {
                        const found = this.options.find(o => o.id === this.selectedId);
                        if (found) {
                            this.selectedName = found.name;
                        }
                    },
                    get filteredOptions() {
                        if (!this.search.trim()) return this.options;
                        const q = this.search.toLowerCase();
                        return this.options.filter(o => 
                            o.name.toLowerCase().includes(q) || 
                            (o.code && o.code.toLowerCase().includes(q)) ||
                            (o.faculty && o.faculty.toLowerCase().includes(q))
                        );
                    },
                    selectOption(option) {
                        this.selectedId = option.id;
                        this.selectedName = option.name;
                        this.open = false;
                        this.search = '';
                    },
                    clearSelection() {
                        this.selectedId = '';
                        this.selectedName = '';
                        this.open = false;
                        this.search = '';
                    }
                }" class="relative" @click.outside="open = false">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Gedung <span class="text-red-500">*</span>
                    </label>

                    {{-- Hidden input for form submission --}}
                    <input type="hidden" name="building_id" :value="selectedId" required>

                    {{-- Dropdown trigger button --}}
                    <div class="relative">
                        <button 
                            type="button" 
                            @click="open = !open" 
                            class="w-full bg-white border rounded-lg px-3.5 py-2.5 text-left text-sm flex items-center justify-between shadow-xs transition-colors cursor-pointer"
                            :class="open ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-gray-200 hover:border-gray-300'"
                        >
                            <span x-show="selectedName" x-text="selectedName" class="text-gray-900 font-medium"></span>
                            <span x-show="!selectedName" class="text-gray-400">-- Pilih atau Cari Gedung --</span>
                            <div class="flex items-center gap-1.5 text-gray-400">
                                <span x-show="selectedName" @click.stop="clearSelection()" class="hover:text-red-500 p-0.5 rounded cursor-pointer" title="Hapus pilihan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180 text-emerald-600' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </button>
                    </div>

                    {{-- Searchable Dropdown Menu --}}
                    <div 
                        x-show="open" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute z-30 mt-1 w-full bg-white rounded-xl shadow-xl border border-gray-100 py-2 overflow-hidden"
                        style="display: none;"
                    >
                        {{-- Search Input inside Dropdown --}}
                        <div class="px-3 pb-2 border-b border-gray-100">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                                <input 
                                    x-ref="searchInput"
                                    type="text" 
                                    x-model="search" 
                                    placeholder="Ketik untuk mencari gedung..." 
                                    class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border-gray-200 border focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-hidden"
                                    @keydown.escape="open = false"
                                >
                            </div>
                        </div>

                        {{-- Options list --}}
                        <div class="max-h-60 overflow-y-auto divide-y divide-gray-50">
                            <template x-for="option in filteredOptions" :key="option.id">
                                <div 
                                    @click="selectOption(option)" 
                                    class="px-3.5 py-2.5 text-sm hover:bg-emerald-50/70 cursor-pointer flex items-center justify-between transition-colors"
                                    :class="selectedId === option.id ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-gray-700'"
                                >
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span x-text="option.name"></span>
                                            <span x-show="option.code" x-text="option.code" class="text-[11px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 font-mono"></span>
                                        </div>
                                        <div x-show="option.faculty" class="text-xs text-gray-400 mt-0.5" x-text="option.faculty"></div>
                                    </div>
                                    <svg x-show="selectedId === option.id" class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </template>
                            <div x-show="filteredOptions.length === 0" class="px-3 py-6 text-center text-xs text-gray-400">
                                Gedung tidak ditemukan dengan kata kunci "<span x-text="search" class="font-medium text-gray-600"></span>"
                            </div>
                        </div>
                    </div>

                    @error('building_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nama Ruangan --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Ruangan <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $room->name) }}" required placeholder="Contoh: Lab Komputer 1 / Ruang 204" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900 placeholder-gray-400">
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Lantai & Kapasitas --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Lantai <span class="text-red-500">*</span></label>
                        <input type="number" name="floor" value="{{ old('floor', $room->floor) }}" min="1" required class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900">
                        @error('floor')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kapasitas (Mahasiswa) <span class="text-red-500">*</span></label>
                        <input type="number" name="capacity" value="{{ old('capacity', $room->capacity) }}" min="1" max="500" required class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900">
                        @error('capacity')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Drag & Drop Foto 360 Area with Thumbnail Preview --}}
                <div x-data="{
                    isDragging: false,
                    previewUrl: null,
                    fileName: '',
                    fileSize: '',
                    hasExisting: {{ $room->photo_360_path ? 'true' : 'false' }},
                    handleFile(file) {
                        if (!file) return;
                        if (!file.type.match('image.*')) {
                            alert('Hanya file gambar yang diperbolehkan (JPEG, PNG).');
                            return;
                        }
                        this.fileName = file.name;
                        this.fileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.previewUrl = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    },
                    handleDrop(e) {
                        this.isDragging = false;
                        if (e.dataTransfer.files.length > 0) {
                            const file = e.dataTransfer.files[0];
                            this.$refs.fileInput.files = e.dataTransfer.files;
                            this.handleFile(file);
                        }
                    },
                    handleChange(e) {
                        if (e.target.files.length > 0) {
                            this.handleFile(e.target.files[0]);
                        }
                    },
                    removeFile() {
                        this.previewUrl = null;
                        this.fileName = '';
                        this.fileSize = '';
                        this.$refs.fileInput.value = '';
                    }
                }" class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">Foto Panorama 360 Derajat</label>

                    {{-- Existing photo display if available and no new file selected --}}
                    @if($room->photo_360_path)
                    <div x-show="!previewUrl" class="p-3 bg-gray-50 border border-gray-200 rounded-xl flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="relative w-16 h-12 rounded-lg overflow-hidden bg-gray-200 shrink-0 border border-gray-300">
                                <img src="{{ Storage::url($room->photo_360_path) }}" alt="Foto 360 Saat Ini" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                        <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Foto 360 Aktif
                                    </span>
                                    <span class="text-xs text-gray-500">Tersimpan di server</span>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5">Unggah foto baru di bawah untuk mengganti foto ini</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Hidden file input --}}
                    <input 
                        type="file" 
                        name="photo_360" 
                        x-ref="fileInput" 
                        accept="image/jpeg,image/png,image/jpg" 
                        class="hidden" 
                        @change="handleChange($event)"
                    >

                    {{-- Drag & drop box when NO NEW file selected --}}
                    <div 
                        x-show="!previewUrl"
                        @click="$refs.fileInput.click()"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop($event)"
                        class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all duration-200"
                        :class="isDragging ? 'border-emerald-500 bg-emerald-50/50 scale-[1.01]' : 'border-gray-300 hover:border-emerald-400 hover:bg-gray-50/50 bg-white'"
                    >
                        <div class="max-w-sm mx-auto">
                            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-2 shadow-xs">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <p class="text-sm font-bold text-gray-800 mb-0.5">
                                <span class="text-emerald-600 hover:underline">Klik untuk ganti foto</span> atau seret file ke sini
                            </p>
                            <p class="text-xs text-gray-400">Format: JPEG, JPG, PNG (Maks. 15MB) — Kosongkan bila tidak ingin mengubah foto</p>
                        </div>
                    </div>

                    {{-- Image Preview after NEW file chosen --}}
                    <div 
                        x-show="previewUrl" 
                        class="border border-emerald-200 bg-emerald-50/20 rounded-xl p-4 flex flex-col sm:flex-row items-center gap-4 transition-all"
                        style="display: none;"
                    >
                        <div class="relative w-36 h-24 rounded-lg overflow-hidden shadow-sm shrink-0 bg-gray-100 border border-gray-200">
                            <img :src="previewUrl" alt="Thumbnail Preview" class="w-full h-full object-cover">
                            <div class="absolute bottom-1 right-1 bg-black/70 text-white text-[10px] px-1.5 py-0.5 rounded font-mono">
                                Foto Baru
                            </div>
                        </div>
                        <div class="flex-1 min-w-0 text-center sm:text-left">
                            <div class="flex items-center justify-center sm:justify-start gap-2 mb-1">
                                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                                <p class="text-sm font-bold text-gray-900 truncate" x-text="fileName"></p>
                            </div>
                            <p class="text-xs text-gray-500 mb-3" x-text="'Ukuran: ' + fileSize"></p>
                            <div class="flex items-center justify-center sm:justify-start gap-2">
                                <button 
                                    type="button" 
                                    @click="$refs.fileInput.click()" 
                                    class="px-3 py-1 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer"
                                >
                                    Pilih File Lain
                                </button>
                                <button 
                                    type="button" 
                                    @click="removeFile()" 
                                    class="px-3 py-1 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                                >
                                    Batalkan Penggantian
                                </button>
                            </div>
                        </div>
                    </div>

                    @error('photo_360')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Action buttons --}}
                <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                    <button type="submit" class="inline-flex items-center gap-2 bg-[#10B981] hover:bg-emerald-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm shadow-sm transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Simpan Perubahan
                    </button>
                    <a href="{{ route('admin.rooms.index') }}" class="text-gray-500 hover:text-gray-800 text-sm font-medium transition-colors">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
