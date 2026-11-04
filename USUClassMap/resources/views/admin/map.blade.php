<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Spatial Dashboard - USUClassMap</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>

    <!-- Pannellum 360° Virtual Tour CSS & JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
    <script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

    <!-- Vite Assets (Tailwind & Alpine) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Data Passing Terisolasi dari Blade ke JS -->
    <script>
        window.ADMIN_MAP_CONFIG = {
            csrfToken: '{{ csrf_token() }}',
            buildings: @json($buildings),
            courses: @json($courses),
            lecturers: @json($lecturers),
            endpoints: {
                building: '{{ url("/admin-sementara-/map/building") }}',
                updateBuilding: '{{ url("/admin-sementara-/map/building") }}',
                room: '{{ url("/admin-sementara-/map/room") }}',
                updateRoom: '{{ url("/admin-sementara-/map/room") }}',
                schedule: '{{ url("/admin-sementara-/map/schedule") }}',
                fetchRooms: '{{ url("/admin-sementara-/map/rooms") }}',
                roomMedia: '{{ url("/admin-sementara-/map/rooms") }}',
                updateMetadata: '{{ url("/admin-sementara-/map/media") }}',
                deleteMedia: '{{ url("/admin-sementara-/map/media") }}'
            }
        };
    </script>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">
    <div class="h-screen w-full flex overflow-hidden relative" x-data="adminMapDashboard()">
        
        <!-- ========================================================================= -->
        <!-- LEFT SIDEBAR (Alpine Control Hub)                                         -->
        <!-- ========================================================================= -->
        <div 
            id="admin-sidebar"
            class="w-[430px] bg-white shadow-2xl z-20 flex flex-col overflow-hidden border-r border-gray-200" 
        >
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-gray-100 bg-white flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-black text-gray-800 tracking-tight">
                        USUClass<span class="text-[#10A760]">Map</span> Admin
                    </h1>
                    <p class="text-[11px] text-gray-500">Pusat Kendali Spasial & Multimedia Ruangan</p>
                </div>
                <a href="{{ route('admin.dashboard') }}" class="text-xs font-bold text-gray-600 hover:text-[#10A760] px-2.5 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 transition flex items-center gap-1">
                    <span>⬅</span> Dashboard
                </a>
            </div>

            <!-- Global Context Indicator Bar -->
            <div x-show="globalBuildingId" class="px-4 py-2 bg-emerald-50 border-b border-emerald-200 flex items-center justify-between transition-all" style="display: none;">
                <div class="flex items-center gap-2 truncate">
                    <span class="w-2 h-2 rounded-full bg-[#10A760] animate-ping"></span>
                    <span class="text-xs font-bold text-emerald-900 truncate">
                        Konteks: <span x-text="globalBuildingName" class="underline"></span>
                    </span>
                </div>
                <button type="button" @click="cancelEdit()" class="text-[11px] font-bold text-emerald-700 hover:text-emerald-900 px-2 py-0.5 rounded bg-emerald-100 hover:bg-emerald-200 transition cursor-pointer">
                    Lepas ✕
                </button>
            </div>

            <!-- Tab Navigation -->
            <div class="flex p-2.5 bg-gray-50 gap-1.5 border-b border-gray-100">
                <button 
                    data-tab="building"
                    @click="activeTab = 'building'"
                    :class="activeTab === 'building' ? 'bg-[#10A760] text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="flex-1 py-2 px-1 rounded-lg text-xs font-bold flex items-center justify-center gap-1 transition-colors cursor-pointer">
                    <span>🏛️</span> Gedung
                </button>
                <button 
                    data-tab="room"
                    @click="activeTab = 'room'"
                    :class="activeTab === 'room' ? 'bg-[#10A760] text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="flex-1 py-2 px-1 rounded-lg text-xs font-bold flex items-center justify-center gap-1 transition-colors cursor-pointer">
                    <span>🚪</span> Ruangan
                </button>
                <button 
                    data-tab="schedule"
                    @click="activeTab = 'schedule'"
                    :class="activeTab === 'schedule' ? 'bg-[#10A760] text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="flex-1 py-2 px-1 rounded-lg text-xs font-bold flex items-center justify-center gap-1 transition-colors cursor-pointer">
                    <span>📅</span> Jadwal
                </button>
            </div>

            <!-- Scrollable Form Container -->
            <div class="p-4 space-y-4 overflow-y-auto flex-1 custom-scrollbar">
                
                <!-- ========================================================================= -->
                <!-- TAB 1: TAMBAH / EDIT GEDUNG                                               -->
                <!-- ========================================================================= -->
                <div x-show="activeTab === 'building'" x-cloak>
                    <!-- Edit Mode Indicator Banner -->
                    <div x-show="editMode" class="mb-3 p-3 bg-amber-50 border border-amber-300 rounded-xl flex items-center gap-2.5 shadow-xs" style="display: none;">
                        <span class="text-amber-600 text-xl">✏️</span>
                        <div>
                            <p class="text-xs font-bold text-amber-900">Mode Edit Gedung Aktif</p>
                            <p class="text-[11px] text-amber-700">Geser marker kuning di peta untuk menyesuaikan koordinat</p>
                        </div>
                    </div>

                    <h2 class="text-sm font-extrabold text-gray-800 mb-3 flex items-center gap-2">
                        <span x-show="!editMode">🏛️ Tambah Gedung Baru</span>
                        <span x-show="editMode" style="display: none;">✏️ Edit Informasi Gedung</span>
                    </h2>

                    <form id="form-building" class="space-y-3" @submit.prevent="submitBuilding()">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Nama Gedung</label>
                            <input type="text" name="name" x-model="buildingForm.name" required placeholder="Contoh: Gedung Fakultas Kedokteran" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Kode Gedung</label>
                            <input type="text" name="code" x-model="buildingForm.code" required placeholder="Contoh: GD-FK" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white font-mono">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Fakultas / Unit</label>
                            <input type="text" name="faculty" x-model="buildingForm.faculty" placeholder="Contoh: Fakultas Kedokteran" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Deskripsi</label>
                            <textarea name="description" x-model="buildingForm.description" rows="2" placeholder="Deskripsi gedung..." class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white"></textarea>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Jumlah Lantai</label>
                            <input type="number" name="floor_count" x-model="buildingForm.floor_count" min="1" max="20" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Latitude</label>
                                <input type="text" id="latitude" name="latitude" x-model="buildingForm.latitude" readonly placeholder="Geser di peta →" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed outline-none font-mono">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Longitude</label>
                                <input type="text" id="longitude" name="longitude" x-model="buildingForm.longitude" readonly placeholder="Geser di peta →" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed outline-none font-mono">
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-2">
                            <button 
                                x-show="!editMode" 
                                id="btn-submit-building" 
                                type="submit" 
                                :disabled="submittingBuilding"
                                class="w-full py-2.5 text-sm font-bold text-white bg-[#10A760] hover:bg-[#0D8A4E] disabled:bg-gray-400 rounded-lg shadow-md flex items-center justify-center gap-2 transition cursor-pointer"
                            >
                                <span x-show="!submittingBuilding">➕ Simpan Gedung</span>
                                <span x-show="submittingBuilding" style="display: none;">⏳ Menyimpan...</span>
                            </button>

                            <div x-show="editMode" class="flex flex-col gap-2" style="display: none;">
                                <button 
                                    id="btn-update-building" 
                                    type="submit" 
                                    :disabled="submittingBuilding"
                                    class="w-full py-2.5 text-sm font-bold text-white bg-amber-500 hover:bg-amber-600 disabled:bg-gray-400 rounded-lg shadow-md flex items-center justify-center gap-2 transition cursor-pointer"
                                >
                                    <span x-show="!submittingBuilding">🔄 Update Data Gedung</span>
                                    <span x-show="submittingBuilding" style="display: none;">⏳ Mengupdate...</span>
                                </button>
                                
                                <button 
                                    type="button" 
                                    @click="cancelEdit()" 
                                    class="w-full py-2.5 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg border border-gray-300 transition cursor-pointer"
                                >
                                    ✕ Batal Edit
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ========================================================================= -->
                <!-- TAB 2: TAMBAH & KELOLA MULTIMEDIA RUANGAN                                 -->
                <!-- ========================================================================= -->
                <div x-show="activeTab === 'room'" x-cloak>
                    <!-- Mode Selector: Tambah Baru vs Kelola Ruangan Existing -->
                    <div class="mb-3 p-1 bg-gray-100 rounded-xl grid grid-cols-2 gap-1 border border-gray-200">
                        <button 
                            type="button" 
                            @click="selectRoomMode(false)" 
                            :class="!roomEditMode ? 'bg-white text-[#10A760] shadow-xs' : 'text-gray-600 hover:text-gray-800'"
                            class="py-1.5 text-xs font-bold rounded-lg transition-all text-center cursor-pointer"
                        >
                            ➕ Tambah Ruangan
                        </button>
                        <button 
                            type="button" 
                            @click="selectRoomMode(true)" 
                            :class="roomEditMode ? 'bg-white text-[#10A760] shadow-xs' : 'text-gray-600 hover:text-gray-800'"
                            class="py-1.5 text-xs font-bold rounded-lg transition-all text-center cursor-pointer"
                        >
                            🖼️ Kelola & Media (<span x-text="roomEditMode && currentRoomId ? 'Edit' : 'Pilih'"></span>)
                        </button>
                    </div>

                    <!-- Room Selector when in Manage Mode -->
                    <div x-show="roomEditMode" class="mb-3 space-y-2 p-3 bg-emerald-50/60 border border-emerald-200 rounded-xl" style="display: none;">
                        <label class="text-xs font-bold text-emerald-900 block">Pilih Ruangan yang Akan Dikelola:</label>
                        <select 
                            x-model="selectedRoomIdToManage" 
                            @change="loadRoomForManagement(selectedRoomIdToManage)"
                            class="w-full px-3 py-1.5 text-xs border border-emerald-300 rounded-lg bg-white outline-none focus:ring-2 focus:ring-[#10A760]"
                        >
                            <option value="">-- Pilih Ruangan untuk Kelola Media --</option>
                            <template x-for="r in availableRooms" :key="r.id">
                                <option :value="String(r.id)" x-text="r.name + ' (Lt. ' + r.floor + ')'" :selected="String(r.id) === String(currentRoomId)"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Room Form -->
                    <h2 class="text-sm font-extrabold text-gray-800 mb-2 flex items-center justify-between">
                        <span x-text="roomEditMode && currentRoomId ? '✏️ Edit Data Ruangan #' + currentRoomId : '🚪 Tambah Ruangan Baru'"></span>
                    </h2>

                    <form id="form-room" class="space-y-3" @submit.prevent="submitRoom()">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Gedung</label>
                            <select 
                                name="building_id" 
                                x-model="roomForm.building_id" 
                                @change="fetchRoomsForSchedule(roomForm.building_id)"
                                required 
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white"
                                :class="globalBuildingId ? 'ring-2 ring-emerald-400 bg-emerald-50/30' : ''"
                            >
                                <option value="">-- Pilih Gedung --</option>
                                <template x-for="b in buildingsList" :key="b.id">
                                    <option :value="String(b.id)" x-text="b.code + ' - ' + b.name" :selected="String(b.id) === String(roomForm.building_id)"></option>
                                </template>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Nama / Nomor Ruangan</label>
                            <input type="text" name="name" x-model="roomForm.name" required placeholder="Contoh: R.301 / Lab Komputer" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Lantai</label>
                                <input type="number" name="floor" x-model="roomForm.floor" min="1" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Kapasitas (Org)</label>
                                <input type="number" name="capacity" x-model="roomForm.capacity" min="1" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                            </div>
                        </div>

                        <div>
                            <button 
                                id="btn-submit-room" 
                                type="submit" 
                                :disabled="submittingRoom"
                                class="w-full py-2.5 text-sm font-bold text-white bg-[#10A760] hover:bg-[#0D8A4E] disabled:bg-gray-400 rounded-lg shadow-md transition cursor-pointer flex items-center justify-center gap-2"
                            >
                                <span x-show="!submittingRoom" x-text="roomEditMode && currentRoomId ? '🔄 Update Data Ruangan' : '➕ Simpan Ruangan'"></span>
                                <span x-show="submittingRoom" style="display: none;">⏳ Menyimpan...</span>
                            </button>
                        </div>
                    </form>

                    <!-- ================================================================= -->
                    <!-- SUB-PANEL: IMMERSIVE MULTIMEDIA MANAGER (PHASE 7.3)               -->
                    <!-- ================================================================= -->
                    <div x-show="roomEditMode && currentRoomId" class="mt-6 pt-5 border-t-2 border-dashed border-gray-200 space-y-4" style="display: none;">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-extrabold text-gray-800 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>📸</span> Multimedia Ruangan
                                </h3>
                                <p class="text-[11px] text-gray-500">Unggah Foto Biasa, Foto 360° & Video Rute</p>
                            </div>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800" x-text="roomMediaList.length + ' Media'"></span>
                        </div>

                        <!-- Upload Zone (Drag & Drop UI) -->
                        <div class="p-3.5 bg-gray-50 border-2 border-dashed border-gray-300 hover:border-[#10A760] rounded-xl transition space-y-3">
                            <div class="grid grid-cols-3 gap-1.5">
                                <label 
                                    class="flex flex-col items-center justify-center p-2 rounded-lg border text-center cursor-pointer transition text-xs font-bold"
                                    :class="mediaUploadForm.type === 'photo' ? 'border-[#10A760] bg-emerald-50 text-emerald-800' : 'border-gray-200 bg-white text-gray-600'"
                                >
                                    <input type="radio" name="media_type" value="photo" x-model="mediaUploadForm.type" class="sr-only">
                                    <span class="text-base">📷</span>
                                    <span class="text-[10px] mt-0.5">Foto Biasa</span>
                                </label>
                                <label 
                                    class="flex flex-col items-center justify-center p-2 rounded-lg border text-center cursor-pointer transition text-xs font-bold"
                                    :class="mediaUploadForm.type === '360' ? 'border-[#10A760] bg-emerald-50 text-emerald-800' : 'border-gray-200 bg-white text-gray-600'"
                                >
                                    <input type="radio" name="media_type" value="360" x-model="mediaUploadForm.type" class="sr-only">
                                    <span class="text-base">🌐</span>
                                    <span class="text-[10px] mt-0.5">Foto 360°</span>
                                </label>
                                <label 
                                    class="flex flex-col items-center justify-center p-2 rounded-lg border text-center cursor-pointer transition text-xs font-bold"
                                    :class="mediaUploadForm.type === 'video' ? 'border-[#10A760] bg-emerald-50 text-emerald-800' : 'border-gray-200 bg-white text-gray-600'"
                                >
                                    <input type="radio" name="media_type" value="video" x-model="mediaUploadForm.type" class="sr-only">
                                    <span class="text-base">🎬</span>
                                    <span class="text-[10px] mt-0.5">Video Rute</span>
                                </label>
                            </div>

                            <!-- File Selector & Trigger -->
                            <div class="space-y-2">
                                <input 
                                    type="file" 
                                    id="room-media-file-input"
                                    x-ref="roomMediaInput"
                                    @change="handleFileSelected($event)"
                                    accept=".jpg,.jpeg,.png,.mp4" 
                                    class="w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-[#10A760] file:text-white hover:file:bg-[#0D8A4E] file:cursor-pointer"
                                >
                                
                                <label class="flex items-center gap-2 text-xs font-medium text-gray-600 cursor-pointer">
                                    <input type="checkbox" x-model="mediaUploadForm.is_primary" class="rounded text-[#10A760] focus:ring-[#10A760]">
                                    <span>Jadikan Media Utama Ruangan</span>
                                </label>
                            </div>

                            <button 
                                type="button" 
                                @click="uploadRoomMedia()"
                                :disabled="uploadingMedia || !mediaUploadForm.file"
                                class="w-full py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 rounded-lg shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <span x-show="!uploadingMedia">📤 Unggah Media</span>
                                <span x-show="uploadingMedia" style="display: none;">⏳ Mengunggah (Max 50MB)...</span>
                            </button>
                        </div>

                        <!-- Gallery of Uploaded Media -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold text-gray-700">Daftar Media Tersimpan:</h4>
                            
                            <div x-show="loadingMediaList" class="p-4 text-center text-xs text-gray-400" style="display: none;">
                                ⏳ Memuat galeri media...
                            </div>

                            <div x-show="!loadingMediaList && roomMediaList.length === 0" class="p-4 text-center text-xs text-gray-400 bg-gray-50 rounded-lg">
                                Belum ada media yang diunggah untuk ruangan ini.
                            </div>

                            <div class="space-y-2 max-h-[300px] overflow-y-auto pr-1">
                                <template x-for="media in roomMediaList" :key="media.id">
                                    <div class="p-2.5 bg-white border border-gray-200 rounded-xl flex items-center justify-between gap-3 shadow-xs hover:border-gray-300 transition">
                                        <!-- Thumbnail / Icon -->
                                        <div class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center border border-gray-200 relative">
                                            <template x-if="media.type === 'photo' || media.type === '360'">
                                                <img :src="media.file_url" class="w-full h-full object-cover" alt="Media Preview">
                                            </template>
                                            <template x-if="media.type === 'video'">
                                                <span class="text-xl">🎬</span>
                                            </template>
                                            <template x-if="media.is_primary">
                                                <span class="absolute bottom-0 right-0 bg-[#10A760] text-[8px] text-white px-1 font-bold rounded-tl">★</span>
                                            </template>
                                        </div>

                                        <!-- Info -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span 
                                                    class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded"
                                                    :class="{
                                                        'bg-blue-100 text-blue-800': media.type === 'photo',
                                                        'bg-amber-100 text-amber-800': media.type === '360',
                                                        'bg-purple-100 text-purple-800': media.type === 'video'
                                                    }"
                                                    x-text="media.type"
                                                ></span>
                                                <span x-show="media.is_primary" class="text-[10px] text-[#10A760] font-bold">Utama</span>
                                            </div>
                                            <p class="text-[11px] text-gray-500 truncate mt-0.5 font-mono" x-text="media.file_path.split('/').pop()"></p>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex items-center gap-1">
                                            <!-- Hotspot 360 Config Button -->
                                            <template x-if="media.type === '360'">
                                                <button 
                                                    type="button" 
                                                    @click="open360ConfigModal(media)"
                                                    class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded text-[11px] font-bold transition flex items-center gap-1 cursor-pointer"
                                                    title="Konfigurasi Hotspot 360°"
                                                >
                                                    <span>🌐</span> Hotspot
                                                </button>
                                            </template>

                                            <!-- Delete Button -->
                                            <button 
                                                type="button" 
                                                @click="deleteRoomMedia(media.id)"
                                                class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition cursor-pointer"
                                                title="Hapus Media"
                                            >
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- TAB 3: TAMBAH JADWAL                                                      -->
                <!-- ========================================================================= -->
                <div x-show="activeTab === 'schedule'" x-cloak>
                    <!-- Context Notification -->
                    <div x-show="globalBuildingId" class="mb-3 p-2.5 bg-emerald-50 border border-emerald-300 rounded-xl flex items-center justify-between text-xs" style="display: none;">
                        <span class="font-bold text-emerald-800">🎯 Filter Gedung Terkunci Otomatis</span>
                        <span class="text-[11px] px-2 py-0.5 rounded bg-emerald-200 text-emerald-900 font-bold" x-text="globalBuildingName"></span>
                    </div>

                    <h2 class="text-sm font-extrabold text-gray-800 mb-3 flex items-center gap-2">
                        <span>📅</span> Tambah Jadwal Perkuliahan
                    </h2>

                    <form id="form-schedule" class="space-y-3" @submit.prevent="submitSchedule()">
                        <!-- Filter Gedung Dropdown -->
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Gedung</label>
                            <select 
                                id="schedule-building-filter" 
                                x-model="scheduleForm.building_id" 
                                @change="fetchRoomsForSchedule(scheduleForm.building_id)"
                                required 
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white"
                                :class="globalBuildingId ? 'ring-2 ring-emerald-400 bg-emerald-50/30' : ''"
                            >
                                <option value="">-- Pilih Gedung --</option>
                                <template x-for="b in buildingsList" :key="b.id">
                                    <option :value="String(b.id)" x-text="b.code + ' - ' + b.name" :selected="String(b.id) === String(scheduleForm.building_id)"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Dependent Room Select -->
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Ruangan Kelas</label>
                                <span x-show="loadingRooms" class="text-[11px] text-amber-600 animate-pulse font-semibold" style="display: none;">Memuat ruangan...</span>
                            </div>
                            <select 
                                id="schedule-room-id" 
                                name="room_id" 
                                x-model="scheduleForm.room_id" 
                                required 
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white"
                            >
                                <option value="">-- Pilih Ruangan --</option>
                                <template x-for="r in availableRooms" :key="r.id">
                                    <option :value="String(r.id)" x-text="r.name + ' (Lt. ' + r.floor + ', Kapasitas: ' + r.capacity + ')'" :selected="String(r.id) === String(scheduleForm.room_id)"></option>
                                </template>
                            </select>
                        </div>

                        <!-- ON-THE-FLY COURSE CREATOR SECTION -->
                        <div class="space-y-2 pt-1 border-t border-gray-100">
                            <label class="text-xs font-bold text-gray-700 uppercase tracking-wider block">Mata Kuliah</label>
                            
                            <div class="grid grid-cols-2 gap-1.5 p-1 bg-gray-100 rounded-xl border border-gray-200">
                                <label 
                                    class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-bold cursor-pointer transition-all select-none"
                                    :class="scheduleForm.course_mode === 'existing' ? 'bg-white text-[#10A760] shadow-xs' : 'text-gray-500 hover:text-gray-700'"
                                >
                                    <input type="radio" name="course_mode_toggle" value="existing" x-model="scheduleForm.course_mode" class="sr-only">
                                    <span>📚</span>
                                    <span>Pilih Matkul</span>
                                </label>
                                <label 
                                    class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-xs font-bold cursor-pointer transition-all select-none"
                                    :class="scheduleForm.course_mode === 'new' ? 'bg-white text-[#10A760] shadow-xs' : 'text-gray-500 hover:text-gray-700'"
                                >
                                    <input type="radio" name="course_mode_toggle" value="new" x-model="scheduleForm.course_mode" class="sr-only">
                                    <span>✨</span>
                                    <span>Buat Baru</span>
                                </label>
                            </div>

                            <div x-show="scheduleForm.course_mode === 'existing'">
                                <select 
                                    id="schedule-course-select" 
                                    name="course_id" 
                                    x-model="scheduleForm.course_id" 
                                    :required="scheduleForm.course_mode === 'existing'"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white"
                                >
                                    <option value="">-- Pilih Mata Kuliah --</option>
                                    <template x-for="c in coursesList" :key="c.id">
                                        <option :value="String(c.id)" x-text="c.code + ' - ' + c.name" :selected="String(c.id) === String(scheduleForm.course_id)"></option>
                                    </template>
                                </select>
                            </div>

                            <div x-show="scheduleForm.course_mode === 'new'" class="space-y-2 p-3 bg-emerald-50/50 border border-emerald-200 rounded-xl" style="display: none;">
                                <div class="space-y-1">
                                    <label class="text-[11px] font-bold text-gray-700">Kode Matkul Baru</label>
                                    <input type="text" name="new_course_code" x-model="scheduleForm.new_course_code" :required="scheduleForm.course_mode === 'new'" placeholder="Contoh: MKW-101" class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none bg-white font-mono">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[11px] font-bold text-gray-700">Nama Mata Kuliah</label>
                                    <input type="text" name="new_course_name" x-model="scheduleForm.new_course_name" :required="scheduleForm.course_mode === 'new'" placeholder="Contoh: Pendidikan Kewarganegaraan" class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none bg-white">
                                </div>
                            </div>
                        </div>

                        <!-- Dosen Dropdown -->
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Dosen Pengampu</label>
                            <select name="lecturer_id" x-model="scheduleForm.lecturer_id" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                                <option value="">-- Pilih Dosen --</option>
                                <template x-for="l in lecturersList" :key="l.id">
                                    <option :value="String(l.id)" x-text="l.name + (l.nip ? ' (' + l.nip + ')' : '')"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Hari & Waktu -->
                        <div class="grid grid-cols-3 gap-2">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Hari</label>
                                <select name="day" x-model="scheduleForm.day" required class="w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white font-medium">
                                    <option value="">Pilih</option>
                                    <option value="Senin">Senin</option>
                                    <option value="Selasa">Selasa</option>
                                    <option value="Rabu">Rabu</option>
                                    <option value="Kamis">Kamis</option>
                                    <option value="Jumat">Jumat</option>
                                    <option value="Sabtu">Sabtu</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Mulai</label>
                                <input type="time" name="time_start" x-model="scheduleForm.time_start" required class="w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Selesai</label>
                                <input type="time" name="time_end" x-model="scheduleForm.time_end" required class="w-full px-2 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                            </div>
                        </div>

                        <!-- Kelas & Semester -->
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Kelompok Kelas</label>
                                <input type="text" name="class_group" x-model="scheduleForm.class_group" required placeholder="Contoh: A / KOM-A" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider block">Semester</label>
                                <input type="text" name="semester" x-model="scheduleForm.semester" required placeholder="Ganjil 2026/2027" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10A760] focus:border-transparent outline-none transition bg-white">
                            </div>
                        </div>

                        <div class="pt-2 pb-2">
                            <button 
                                id="btn-submit-schedule" 
                                type="submit" 
                                :disabled="submittingSchedule"
                                class="w-full py-2.5 text-sm font-bold text-white bg-[#10A760] hover:bg-[#0D8A4E] disabled:bg-gray-400 rounded-lg shadow-md transition cursor-pointer flex items-center justify-center gap-2"
                            >
                                <span x-show="!submittingSchedule">➕ Simpan Jadwal Perkuliahan</span>
                                <span x-show="submittingSchedule" style="display: none;">⏳ Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- RIGHT MAP CANVAS                                                          -->
        <!-- ========================================================================= -->
        <div class="flex-1 z-10 relative">
            <div id="admin-map" class="w-full h-full"></div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: PANNELLUM 360° HOTSPOT CONFIGURATOR (PHASE 7.3)                    -->
        <!-- ========================================================================= -->
        <div 
            x-show="is360ModalOpen" 
            x-cloak
            class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/80 backdrop-blur-xs p-4 sm:p-6"
            style="display: none;"
        >
            <div 
                @click.away="close360Modal()"
                class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] border border-gray-200 animate-in fade-in zoom-in duration-200"
            >
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-gray-900 text-white flex items-center justify-between border-b border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <span class="text-xl">🌐</span>
                        <div>
                            <h3 class="text-sm font-black tracking-wide">360° Virtual Tour & Hotspot Configurator</h3>
                            <p class="text-[11px] text-gray-400">Klik pada panorama untuk menangkap koordinat Pitch & Yaw navigasi</p>
                        </div>
                    </div>
                    <button type="button" @click="close360Modal()" class="text-gray-400 hover:text-white p-1 rounded-lg text-lg font-bold">
                        ✕
                    </button>
                </div>

                <!-- Modal Body: 2 Columns (Panorama Viewer & Hotspot Manager) -->
                <div class="flex-1 overflow-hidden grid grid-cols-1 lg:grid-cols-3 gap-0">
                    <!-- Left (2 cols): Pannellum Canvas -->
                    <div class="lg:col-span-2 bg-black relative flex items-center justify-center min-h-[380px] lg:min-h-[500px]">
                        <div id="panorama-editor" class="w-full h-full min-h-[380px] lg:min-h-[500px]"></div>
                        
                        <!-- Overlay Instruction Badge -->
                        <div class="absolute top-3 left-3 bg-black/70 backdrop-blur-xs text-white text-[11px] font-bold px-3 py-1.5 rounded-lg border border-white/20 pointer-events-none">
                            🎯 Klik di sembarang titik foto 360° untuk mengisi Pitch & Yaw
                        </div>
                    </div>

                    <!-- Right (1 col): Hotspot Creator & List -->
                    <div class="p-5 bg-gray-50 border-l border-gray-200 flex flex-col overflow-y-auto space-y-4 max-h-[500px]">
                        <!-- New Hotspot Form -->
                        <div class="p-3.5 bg-white border border-gray-200 rounded-xl shadow-xs space-y-3">
                            <h4 class="text-xs font-black text-gray-800 uppercase tracking-wider flex items-center gap-1.5">
                                <span>➕</span> Tambah Hotspot Baru
                            </h4>

                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-gray-500 uppercase">Pitch (°)</label>
                                    <input type="text" x-model="newHotspot.pitch" placeholder="Contoh: -12.5" class="w-full px-2.5 py-1.5 text-xs font-mono border border-gray-300 rounded-md bg-gray-50">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-gray-500 uppercase">Yaw (°)</label>
                                    <input type="text" x-model="newHotspot.yaw" placeholder="Contoh: 145.2" class="w-full px-2.5 py-1.5 text-xs font-mono border border-gray-300 rounded-md bg-gray-50">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">Label / Teks Tooltip</label>
                                <input type="text" x-model="newHotspot.text" placeholder="Contoh: Pintu Keluar / Lorong Lt. 3" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md">
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">Hubungkan ke Ruangan (Opsional)</label>
                                <select x-model="newHotspot.targetRoomId" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md bg-white">
                                    <option value="">-- Navigasi Bebas / Teks Saja --</option>
                                    <template x-for="r in availableRooms" :key="r.id">
                                        <option :value="String(r.id)" x-text="r.name + ' (Lt. ' + r.floor + ')'"></option>
                                    </template>
                                </select>
                            </div>

                            <button 
                                type="button" 
                                @click="addHotspotToList()" 
                                class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow-xs flex items-center justify-center gap-1 cursor-pointer"
                            >
                                <span>➕</span> Sisipkan ke Hotspot List
                            </button>
                        </div>

                        <!-- Current Hotspots List -->
                        <div class="space-y-2 flex-1">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-black text-gray-800 uppercase tracking-wider">Hotspot Terpasang</h4>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-200 text-gray-700" x-text="hotspots.length + ' Titik'"></span>
                            </div>

                            <template x-if="hotspots.length === 0">
                                <p class="text-xs text-gray-400 text-center py-4 bg-white rounded-xl border border-gray-200">
                                    Belum ada hotspot. Klik pada panorama untuk menambahkan.
                                </p>
                            </template>

                            <div class="space-y-2 max-h-[160px] overflow-y-auto pr-1">
                                <template x-for="(h, idx) in hotspots" :key="idx">
                                    <div class="p-2.5 bg-white border border-gray-200 rounded-xl flex items-center justify-between text-xs shadow-xs">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-gray-800 truncate" x-text="h.text || 'Hotspot ' + (idx + 1)"></p>
                                            <p class="text-[10px] text-gray-500 font-mono">P: <span x-text="h.pitch"></span>°, Y: <span x-text="h.yaw"></span>°</p>
                                        </div>
                                        <button type="button" @click="removeHotspot(idx)" class="text-red-500 hover:text-red-700 p-1 text-sm font-bold">
                                            ✕
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Save Metadata Button -->
                        <div class="pt-2">
                            <button 
                                type="button" 
                                @click="save360Hotspots()" 
                                :disabled="savingHotspots"
                                class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-400 text-white rounded-xl text-xs font-bold shadow-md transition flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <span x-show="!savingHotspots">💾 Simpan Konfigurasi 360°</span>
                                <span x-show="savingHotspots" style="display: none;">⏳ Menyimpan...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <!-- TOAST NOTIFICATION CONTAINER -->
    <div id="toast-container" class="fixed top-4 right-4 z-[99999] space-y-2 pointer-events-none"></div>

    <!-- Leaflet JS CDN -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    
    <!-- Alpine.js Component Declaration (Isolated Brain) -->
    <script>
        function registerAdminMapDashboard() {
            Alpine.data('adminMapDashboard', () => ({
                activeTab: 'building',
                editMode: false,
                currentBuildingId: null,
                globalBuildingId: '',
                globalBuildingName: '',
                buildingsList: window.ADMIN_MAP_CONFIG?.buildings || [],
                coursesList: window.ADMIN_MAP_CONFIG?.courses || [],
                lecturersList: window.ADMIN_MAP_CONFIG?.lecturers || [],
                availableRooms: [],
                loadingRooms: false,

                // Room Management & Multimedia States (Phase 7.3)
                roomEditMode: false,
                currentRoomId: null,
                selectedRoomIdToManage: '',
                roomMediaList: [],
                loadingMediaList: false,
                uploadingMedia: false,
                mediaUploadForm: {
                    type: 'photo',
                    file: null,
                    is_primary: false
                },

                // 360 Hotspot Configurator States
                is360ModalOpen: false,
                current360Media: null,
                hotspots: [],
                newHotspot: {
                    pitch: '0.00',
                    yaw: '0.00',
                    text: '',
                    targetRoomId: ''
                },
                savingHotspots: false,
                viewer360Instance: null,

                // Form States
                submittingBuilding: false,
                submittingRoom: false,
                submittingSchedule: false,

                buildingForm: {
                    name: '',
                    code: '',
                    faculty: '',
                    description: '',
                    floor_count: 1,
                    latitude: '',
                    longitude: ''
                },
                roomForm: {
                    building_id: '',
                    name: '',
                    floor: 1,
                    capacity: 30
                },
                scheduleForm: {
                    building_id: '',
                    room_id: '',
                    course_mode: 'existing',
                    course_id: '',
                    new_course_code: '',
                    new_course_name: '',
                    lecturer_id: '',
                    day: '',
                    time_start: '',
                    time_end: '',
                    class_group: '',
                    semester: 'Ganjil 2026/2027'
                },

                init() {
                    // 1. Context Selection from Leaflet Marker Click
                    window.addEventListener('edit-building', (e) => {
                        const bData = e.detail || {};
                        this.editMode = true;
                        this.currentBuildingId = bData.id;
                        this.globalBuildingId = String(bData.id);
                        this.globalBuildingName = bData.name || 'Gedung Terpilih';
                        this.activeTab = 'building';

                        this.buildingForm.name = bData.name || '';
                        this.buildingForm.code = bData.code || '';
                        this.buildingForm.faculty = bData.faculty || '';
                        this.buildingForm.description = bData.description || '';
                        this.buildingForm.floor_count = bData.floor_count || 1;
                        this.buildingForm.latitude = bData.latitude || '';
                        this.buildingForm.longitude = bData.longitude || '';

                        this.roomForm.building_id = String(bData.id);
                        this.scheduleForm.building_id = String(bData.id);

                        this.fetchRoomsForSchedule(bData.id);
                    });

                    // 2. Live GPS Drag updates from Leaflet
                    window.addEventListener('coordinate-updated', (e) => {
                        this.buildingForm.latitude = e.detail.lat;
                        this.buildingForm.longitude = e.detail.lng;
                    });
                },

                cancelEdit() {
                    this.editMode = false;
                    this.currentBuildingId = null;
                    this.globalBuildingId = '';
                    this.globalBuildingName = '';
                    this.resetBuildingForm();
                    window.dispatchEvent(new CustomEvent('cancel-edit-building'));
                },

                resetBuildingForm() {
                    this.buildingForm = {
                        name: '',
                        code: '',
                        faculty: '',
                        description: '',
                        floor_count: 1,
                        latitude: '',
                        longitude: ''
                    };
                },

                // Switch Room Mode: Create New vs Manage/Multimedia
                selectRoomMode(isManage) {
                    this.roomEditMode = isManage;
                    if (!isManage) {
                        this.currentRoomId = null;
                        this.selectedRoomIdToManage = '';
                        this.roomMediaList = [];
                        this.roomForm.name = '';
                        this.roomForm.floor = 1;
                        this.roomForm.capacity = 30;
                    }
                },

                // Load Room Details & Media when selected from dropdown
                async loadRoomForManagement(roomId) {
                    if (!roomId) {
                        this.currentRoomId = null;
                        this.roomMediaList = [];
                        return;
                    }

                    const room = this.availableRooms.find(r => String(r.id) === String(roomId));
                    if (room) {
                        this.currentRoomId = room.id;
                        this.roomForm.name = room.name;
                        this.roomForm.floor = room.floor;
                        this.roomForm.capacity = room.capacity;
                        this.roomForm.building_id = String(room.building_id);
                        this.fetchRoomMedia(room.id);
                    }
                },

                async fetchRoomsForSchedule(buildingId) {
                    if (!buildingId) {
                        this.availableRooms = [];
                        this.scheduleForm.room_id = '';
                        return;
                    }
                    this.loadingRooms = true;
                    try {
                        const endpoint = `${window.ADMIN_MAP_CONFIG.endpoints.fetchRooms}/${buildingId}`;
                        const res = await fetch(endpoint, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': window.ADMIN_MAP_CONFIG.csrfToken
                            }
                        });
                        if (res.ok) {
                            this.availableRooms = await res.json();
                        } else {
                            this.availableRooms = [];
                        }
                    } catch (e) {
                        console.error('Error fetching rooms:', e);
                        this.availableRooms = [];
                    } finally {
                        this.loadingRooms = false;
                    }
                },

                // =========================================================================
                // MULTIMEDIA MANAGEMENT AJAX METHODS (Phase 7.3)
                // =========================================================================
                async fetchRoomMedia(roomId) {
                    if (!roomId) return;
                    this.loadingMediaList = true;
                    try {
                        const endpoint = `${window.ADMIN_MAP_CONFIG.endpoints.roomMedia}/${roomId}/media`;
                        const res = await fetch(endpoint, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': window.ADMIN_MAP_CONFIG.csrfToken
                            }
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.roomMediaList = data.media || [];
                        } else {
                            this.roomMediaList = [];
                        }
                    } catch (err) {
                        console.error('Error fetching media:', err);
                        this.roomMediaList = [];
                    } finally {
                        this.loadingMediaList = false;
                    }
                },

                handleFileSelected(event) {
                    const files = event.target.files;
                    if (files && files.length > 0) {
                        this.mediaUploadForm.file = files[0];
                    } else {
                        this.mediaUploadForm.file = null;
                    }
                },

                async uploadRoomMedia() {
                    if (!this.currentRoomId) {
                        window.showAdminToast('Pilih ruangan terlebih dahulu.', 'error');
                        return;
                    }
                    if (!this.mediaUploadForm.file) {
                        window.showAdminToast('Pilih file foto atau video yang akan diunggah.', 'error');
                        return;
                    }

                    this.uploadingMedia = true;

                    // Construct binary FormData
                    const formData = new FormData();
                    formData.append('file', this.mediaUploadForm.file);
                    formData.append('type', this.mediaUploadForm.type);
                    formData.append('is_primary', this.mediaUploadForm.is_primary ? 1 : 0);

                    try {
                        const endpoint = `${window.ADMIN_MAP_CONFIG.endpoints.roomMedia}/${this.currentRoomId}/media`;
                        const res = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': window.ADMIN_MAP_CONFIG.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                        const result = await res.json();

                        if (res.ok && result.success) {
                            window.showAdminToast(result.message || 'Media berhasil diunggah!', 'success');
                            this.mediaUploadForm.file = null;
                            if (this.$refs.roomMediaInput) this.$refs.roomMediaInput.value = '';
                            
                            // Refresh media list
                            this.fetchRoomMedia(this.currentRoomId);
                        } else {
                            if (result.errors) {
                                const first = Object.values(result.errors)[0];
                                window.showAdminToast(Array.isArray(first) ? first[0] : first, 'error');
                            } else {
                                window.showAdminToast(result.message || 'Gagal mengunggah media.', 'error');
                            }
                        }
                    } catch (err) {
                        window.showAdminToast('Koneksi server gagal saat upload.', 'error');
                        console.error(err);
                    } finally {
                        this.uploadingMedia = false;
                    }
                },

                async deleteRoomMedia(mediaId) {
                    if (!confirm('Apakah Anda yakin ingin menghapus media ini?')) return;

                    try {
                        const endpoint = `${window.ADMIN_MAP_CONFIG.endpoints.deleteMedia}/${mediaId}`;
                        const res = await fetch(endpoint, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': window.ADMIN_MAP_CONFIG.csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const result = await res.json();
                        if (res.ok && result.success) {
                            window.showAdminToast('Media berhasil dihapus.', 'success');
                            this.fetchRoomMedia(this.currentRoomId);
                        } else {
                            window.showAdminToast(result.message || 'Gagal menghapus media.', 'error');
                        }
                    } catch (err) {
                        window.showAdminToast('Koneksi server gagal.', 'error');
                        console.error(err);
                    }
                },

                // =========================================================================
                // 360° HOTSPOT CONFIGURATOR (PANNELLUM JS INTEGRATION)
                // =========================================================================
                open360ConfigModal(media) {
                    this.current360Media = media;
                    this.hotspots = Array.isArray(media.metadata?.hotspots) ? [...media.metadata.hotspots] : [];
                    this.newHotspot = { pitch: '0.00', yaw: '0.00', text: '', targetRoomId: '' };
                    this.is360ModalOpen = true;

                    this.$nextTick(() => {
                        if (this.viewer360Instance) {
                            try { this.viewer360Instance.destroy(); } catch (e) {}
                        }

                        const pannellumHotspots = this.hotspots.map((h, i) => ({
                            pitch: parseFloat(h.pitch),
                            yaw: parseFloat(h.yaw),
                            type: 'info',
                            text: h.text || 'Hotspot ' + (i + 1)
                        }));

                        this.viewer360Instance = pannellum.viewer('panorama-editor', {
                            type: 'equirectangular',
                            panorama: media.file_url,
                            autoLoad: true,
                            showZoomCtrl: true,
                            showFullscreenCtrl: false,
                            hotSpots: pannellumHotspots
                        });

                        // Capture clicks on panorama editor
                        const panoramaEl = document.getElementById('panorama-editor');
                        if (panoramaEl) {
                            panoramaEl.onmouseup = (e) => {
                                if (this.viewer360Instance) {
                                    const coords = this.viewer360Instance.mouseEventToCoords(e);
                                    if (coords && coords.length >= 2) {
                                        this.newHotspot.pitch = coords[0].toFixed(2);
                                        this.newHotspot.yaw = coords[1].toFixed(2);
                                    }
                                }
                            };
                        }
                    });
                },

                close360Modal() {
                    if (this.viewer360Instance) {
                        try { this.viewer360Instance.destroy(); } catch (e) {}
                        this.viewer360Instance = null;
                    }
                    this.is360ModalOpen = false;
                    this.current360Media = null;
                },

                addHotspotToList() {
                    if (!this.newHotspot.pitch || !this.newHotspot.yaw) {
                        window.showAdminToast('Klik pada panorama untuk menentukan Pitch & Yaw.', 'error');
                        return;
                    }

                    this.hotspots.push({
                        pitch: this.newHotspot.pitch,
                        yaw: this.newHotspot.yaw,
                        text: this.newHotspot.text || 'Titik Navigasi',
                        targetRoomId: this.newHotspot.targetRoomId || null
                    });

                    // Add hotspot to live viewer
                    if (this.viewer360Instance) {
                        this.viewer360Instance.addHotSpot({
                            pitch: parseFloat(this.newHotspot.pitch),
                            yaw: parseFloat(this.newHotspot.yaw),
                            type: 'info',
                            text: this.newHotspot.text || 'Titik Navigasi'
                        });
                    }

                    this.newHotspot = { pitch: '0.00', yaw: '0.00', text: '', targetRoomId: '' };
                    window.showAdminToast('Hotspot ditambahkan ke daftar preview.', 'info');
                },

                removeHotspot(index) {
                    this.hotspots.splice(index, 1);
                    // Re-render viewer with updated hotspots
                    if (this.current360Media) {
                        this.open360ConfigModal({
                            ...this.current360Media,
                            metadata: { ...this.current360Media.metadata, hotspots: this.hotspots }
                        });
                    }
                },

                async save360Hotspots() {
                    if (!this.current360Media) return;
                    this.savingHotspots = true;

                    const metadataPayload = {
                        hotspots: this.hotspots,
                        updated_at: new Date().toISOString()
                    };

                    try {
                        const endpoint = `${window.ADMIN_MAP_CONFIG.endpoints.updateMetadata}/${this.current360Media.id}/metadata`;
                        const res = await fetch(endpoint, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': window.ADMIN_MAP_CONFIG.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ metadata: metadataPayload })
                        });

                        const result = await res.json();
                        if (res.ok && result.success) {
                            window.showAdminToast('Konfigurasi hotspot 360° berhasil disimpan!', 'success');
                            this.fetchRoomMedia(this.currentRoomId);
                            this.close360Modal();
                        } else {
                            window.showAdminToast(result.message || 'Gagal menyimpan konfigurasi.', 'error');
                        }
                    } catch (err) {
                        window.showAdminToast('Koneksi server gagal.', 'error');
                        console.error(err);
                    } finally {
                        this.savingHotspots = false;
                    }
                },

                // =========================================================================
                // FORM SUBMIT METHODS
                // =========================================================================
                async submitBuilding() {
                    this.submittingBuilding = true;
                    const isEdit = Boolean(this.editMode && this.currentBuildingId);
                    const url = isEdit
                        ? `${window.ADMIN_MAP_CONFIG.endpoints.updateBuilding}/${this.currentBuildingId}`
                        : window.ADMIN_MAP_CONFIG.endpoints.building;
                    const method = isEdit ? 'PUT' : 'POST';

                    try {
                        const res = await fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': window.ADMIN_MAP_CONFIG.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.buildingForm)
                        });

                        const result = await res.json();

                        if (res.ok && result.success) {
                            window.showAdminToast(result.message || 'Gedung berhasil disimpan!', 'success');

                            if (isEdit) {
                                const idx = this.buildingsList.findIndex(b => b.id === result.building.id);
                                if (idx >= 0) this.buildingsList[idx] = result.building;
                                window.dispatchEvent(new CustomEvent('building-updated-on-map', { detail: result.building }));
                                this.cancelEdit();
                            } else {
                                this.buildingsList.push(result.building);
                                window.dispatchEvent(new CustomEvent('building-created-on-map', { detail: result.building }));
                                this.resetBuildingForm();
                            }
                        } else {
                            if (result.errors) {
                                const first = Object.values(result.errors)[0];
                                window.showAdminToast(Array.isArray(first) ? first[0] : first, 'error');
                            } else {
                                window.showAdminToast(result.message || 'Terjadi kesalahan validasi.', 'error');
                            }
                        }
                    } catch (err) {
                        window.showAdminToast('Koneksi server gagal.', 'error');
                    } finally {
                        this.submittingBuilding = false;
                    }
                },

                async submitRoom() {
                    this.submittingRoom = true;
                    const isEdit = Boolean(this.roomEditMode && this.currentRoomId);
                    const url = isEdit
                        ? `${window.ADMIN_MAP_CONFIG.endpoints.updateRoom}/${this.currentRoomId}`
                        : window.ADMIN_MAP_CONFIG.endpoints.room;
                    const method = isEdit ? 'PUT' : 'POST';

                    try {
                        const res = await fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': window.ADMIN_MAP_CONFIG.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.roomForm)
                        });

                        const result = await res.json();

                        if (res.ok && result.success) {
                            window.showAdminToast(result.message || 'Data ruangan berhasil disimpan!', 'success');
                            
                            // If this was a new room, set as current room for immediate multimedia uploads
                            if (!isEdit && result.room) {
                                this.roomEditMode = true;
                                this.currentRoomId = result.room.id;
                                this.selectedRoomIdToManage = String(result.room.id);
                            }

                            // Refresh room list
                            if (this.roomForm.building_id) {
                                this.fetchRoomsForSchedule(this.roomForm.building_id);
                            }
                        } else {
                            if (result.errors) {
                                const first = Object.values(result.errors)[0];
                                window.showAdminToast(Array.isArray(first) ? first[0] : first, 'error');
                            } else {
                                window.showAdminToast(result.message || 'Gagal menyimpan ruangan.', 'error');
                            }
                        }
                    } catch (err) {
                        window.showAdminToast('Koneksi server gagal.', 'error');
                    } finally {
                        this.submittingRoom = false;
                    }
                },

                async submitSchedule() {
                    this.submittingSchedule = true;
                    try {
                        const res = await fetch(window.ADMIN_MAP_CONFIG.endpoints.schedule, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': window.ADMIN_MAP_CONFIG.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.scheduleForm)
                        });

                        const result = await res.json();

                        if (res.ok && result.success) {
                            window.showAdminToast(result.message || 'Jadwal berhasil ditambahkan!', 'success');

                            if (result.new_course) {
                                this.coursesList.push(result.new_course);
                                this.scheduleForm.course_id = String(result.new_course.id);
                                this.scheduleForm.course_mode = 'existing';
                                this.scheduleForm.new_course_code = '';
                                this.scheduleForm.new_course_name = '';
                                window.showAdminToast(`✨ Matkul [${result.new_course.code}] Dibuat!`, 'info');
                            }

                            this.scheduleForm.room_id = '';
                            this.scheduleForm.day = '';
                            this.scheduleForm.time_start = '';
                            this.scheduleForm.time_end = '';
                            this.scheduleForm.class_group = '';
                        } else {
                            if (result.errors) {
                                const first = Object.values(result.errors)[0];
                                window.showAdminToast(Array.isArray(first) ? first[0] : first, 'error');
                            } else {
                                window.showAdminToast(result.message || 'Gagal menyimpan jadwal.', 'error');
                            }
                        }
                    } catch (err) {
                        window.showAdminToast('Koneksi server gagal.', 'error');
                    } finally {
                        this.submittingSchedule = false;
                    }
                }
            }));
        }

        if (window.Alpine) {
            registerAdminMapDashboard();
        } else {
            document.addEventListener('alpine:init', registerAdminMapDashboard);
        }
    </script>
    
    <!-- Admin Map Script (Leaflet Map Interaction) -->
    <script src="/js/admin-map.js" defer></script>
</body>
</html>
