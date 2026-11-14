<x-layouts.admin>
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
            <a href="{{ route('admin.buildings.index') }}" class="hover:text-emerald-600 transition-colors">Gedung</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="text-gray-700">Edit Gedung</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Edit Gedung: {{ $building->name }}</h1>
        <p class="text-sm text-gray-500 mt-1">Perbarui data gedung dan sesuaikan titik lokasi pada peta interaktif Leaflet.</p>
    </div>

    <form method="POST" action="{{ route('admin.buildings.update', $building) }}">
        @csrf
        @method('PUT')
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            {{-- LEFT PANEL: Form Input Vertikal (±35%) --}}
            <div class="w-full lg:w-[35%]">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <div class="border-b border-gray-100 pb-3">
                        <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Edit Informasi Gedung
                        </h2>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Gedung <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $building->name) }}" required placeholder="Contoh: Gedung FASILKOM-TI" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900 placeholder-gray-400">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Kode Gedung <span class="text-red-500">*</span></label>
                        <input type="text" name="code" value="{{ old('code', $building->code) }}" required placeholder="Contoh: FST-A" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900 placeholder-gray-400">
                        @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Fakultas / Unit</label>
                        <input type="text" name="faculty" value="{{ old('faculty', $building->faculty) }}" placeholder="Contoh: Fakultas Ilmu Komputer dan TI" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900 placeholder-gray-400">
                        @error('faculty') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Latitude (Read-Only) 🔒</label>
                            <input type="text" name="latitude" id="latitude" value="{{ old('latitude', $building->latitude) }}" readonly required class="w-full rounded-lg border-gray-200 bg-gray-50 shadow-sm px-3.5 py-2.5 border text-sm font-mono text-gray-700 cursor-not-allowed select-all focus:outline-hidden">
                            @error('latitude') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Longitude (Read-Only) 🔒</label>
                            <input type="text" name="longitude" id="longitude" value="{{ old('longitude', $building->longitude) }}" readonly required class="w-full rounded-lg border-gray-200 bg-gray-50 shadow-sm px-3.5 py-2.5 border text-sm font-mono text-gray-700 cursor-not-allowed select-all focus:outline-hidden">
                            @error('longitude') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="p-3 bg-emerald-50 border border-emerald-100 rounded-lg flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p class="text-xs text-emerald-800 leading-relaxed">
                            <span class="font-semibold">The Magic UX:</span> Geser pin merah pada peta di sebelah kanan atau klik lokasi baru untuk memperbarui koordinat secara otomatis.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jumlah Lantai <span class="text-red-500">*</span></label>
                        <input type="number" name="floor_count" value="{{ old('floor_count', $building->floor_count) }}" min="1" max="20" required class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900">
                        @error('floor_count') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="description" rows="3" placeholder="Deskripsi fasilitas atau catatan gedung..." class="w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 px-3.5 py-2.5 border text-sm text-gray-900 placeholder-gray-400">{{ old('description', $building->description) }}</textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3 pt-3 border-t border-gray-100">
                        <button type="submit" class="inline-flex items-center gap-2 bg-[#10B981] hover:bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-semibold text-sm shadow-sm transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Simpan Perubahan
                        </button>
                        <a href="{{ route('admin.buildings.index') }}" class="text-gray-500 hover:text-gray-800 text-sm font-medium transition-colors">Batal</a>
                    </div>
                </div>
            </div>

            {{-- RIGHT PANEL: Peta Interaktif Leaflet (±65%) --}}
            <div class="w-full lg:w-[65%]">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></div>
                            <h3 class="text-sm font-bold text-gray-800">Peta Interaktif: {{ $building->name }}</h3>
                        </div>
                        <span class="text-xs text-gray-500 bg-white px-2.5 py-1 rounded-full border border-gray-200">
                            Draggable Marker Active
                        </span>
                    </div>

                    <div class="relative">
                        <div id="buildingMap" class="w-full z-0" style="height: 580px;"></div>

                        {{-- Map overlay instruction --}}
                        <div class="absolute top-4 left-4 z-10 bg-white/95 backdrop-blur-xs px-3.5 py-2 rounded-lg shadow-md border border-gray-200/80 text-xs text-gray-700 flex items-center gap-2">
                            <span class="inline-block w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                            <span>Tarik & lepas pin merah untuk memposisikan ulang gedung</span>
                        </div>

                        {{-- Floating Coordinates Pill --}}
                        <div class="absolute bottom-4 left-4 right-4 z-10 bg-white/95 backdrop-blur-xs px-4 py-2.5 rounded-lg shadow-md border border-gray-200 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div class="flex items-center gap-4">
                                <span class="text-gray-500">Lat: <strong id="displayLat" class="text-gray-900 font-mono">{{ $building->latitude }}</strong></span>
                                <span class="text-gray-500">Lng: <strong id="displayLng" class="text-gray-900 font-mono">{{ $building->longitude }}</strong></span>
                            </div>
                            <button type="button" id="resetPinBtn" class="text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1 cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Kembalikan ke Posisi Awal
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .leaflet-container {
            font-family: inherit;
        }
        .custom-red-pin {
            background: none;
            border: none;
        }
    </style>
    @endpush

    @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const displayLat = document.getElementById('displayLat');
            const displayLng = document.getElementById('displayLng');
            const resetBtn = document.getElementById('resetPinBtn');

            const originalLat = parseFloat("{{ $building->latitude }}") || 3.5652417;
            const originalLng = parseFloat("{{ $building->longitude }}") || 98.6568434;

            const initialLat = parseFloat(latInput.value) || originalLat;
            const initialLng = parseFloat(lngInput.value) || originalLng;

            // Inisialisasi Peta Leaflet
            const map = L.map('buildingMap', {
                center: [initialLat, initialLng],
                zoom: 17,
                zoomControl: true
            });

            // Tile Layer OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19,
            }).addTo(map);

            // Icon Marker Pin Merah Draggable
            const redIcon = L.divIcon({
                className: 'custom-red-pin',
                html: `
                    <div style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3)); transform: translate(-50%, -100%);">
                        <svg width="34" height="42" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 0C7.58 0 4 3.58 4 8C4 13.54 12 24 12 24C12 24 20 13.54 20 8C20 3.58 16.42 0 12 0ZM12 11C10.34 11 9 9.66 9 8C9 6.34 10.34 5 12 5C13.66 5 15 6.34 15 8C15 9.66 13.66 11 12 11Z" fill="#DC2626"/>
                        </svg>
                    </div>
                `,
                iconSize: [0, 0],
                iconAnchor: [0, 0]
            });

            // Buat Draggable Marker
            const marker = L.marker([initialLat, initialLng], {
                draggable: true,
                icon: redIcon
            }).addTo(map);

            marker.bindPopup('<b>{{ $building->name }}</b><br><span class="text-xs text-gray-500">Geser untuk mengubah titik koordinat</span>').openPopup();

            // Fungsi Sinkronisasi Real-Time (The Magic UX)
            function updateCoordinates(lat, lng) {
                const formattedLat = Number(lat).toFixed(7);
                const formattedLng = Number(lng).toFixed(7);

                latInput.value = formattedLat;
                lngInput.value = formattedLng;

                if (displayLat) displayLat.textContent = formattedLat;
                if (displayLng) displayLng.textContent = formattedLng;
            }

            // Real-time drag event
            marker.on('drag', function(e) {
                const pos = e.target.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
            });

            marker.on('dragend', function(e) {
                const pos = e.target.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
                marker.setPopupContent('<b>{{ $building->name }}</b><br><span class="text-xs text-emerald-600 font-mono">' + Number(pos.lat).toFixed(6) + ', ' + Number(pos.lng).toFixed(6) + '</span>');
            });

            // Klik peta untuk memindahkan marker
            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                updateCoordinates(e.latlng.lat, e.latlng.lng);
            });

            // Tombol reset ke posisi awal gedung
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    marker.setLatLng([originalLat, originalLng]);
                    map.panTo([originalLat, originalLng]);
                    updateCoordinates(originalLat, originalLng);
                });
            }

            // Trigger invalidateSize setelah render
            setTimeout(() => {
                map.invalidateSize();
            }, 300);
        });
    </script>
    @endpush
</x-layouts.admin>
