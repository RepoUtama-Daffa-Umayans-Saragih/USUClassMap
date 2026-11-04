/**
 * USUClassMap - Interactive Geospatial, Multi-Mode Media, Route Navigation & Smart Focus Module (Phase 6.6)
 *
 * Integrates Leaflet.js, Smart Focus & Highlight on search results (putting targeted room at index 0),
 * Visual Smart Room Cards with Thumbnails, Multi-Mode Media Viewer (Photo, Clean 360°, Video POV),
 * GPS Walking Route Navigation, SPARQL API, User Schedule Visualization with Gold Highlight,
 * Bookmarks & Recent Searches.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Check if map container exists
    const mapElement = document.getElementById('map');
    if (!mapElement) return;

    // =========================================================================
    // STATE & CONFIGURATION
    // =========================================================================
    const DEFAULT_CENTER = [3.565245, 98.656520]; // USU Campus Coordinates
    const DEFAULT_ZOOM = 16;
    const RECENT_SEARCHES_KEY = 'usuclassmap_recent_searches';
    const BOOKMARKS_KEY = 'usuclassmap_bookmarks';

    let map = null;
    let buildingMarkers = {}; // building_id -> L.Marker
    let allBuildings = [];
    let currentSchedules = [];
    let userSchedules = [];
    let userSchedulesByBuilding = {}; // building_code -> [schedule, ...]
    let currentUser = null;
    let activeDay = '';
    let activeQuery = '';
    let debounceTimer = null;

    // DOM Elements
    const searchInput = document.getElementById('map-search-input');
    const clearSearchBtn = document.getElementById('clear-search-btn');
    const dayFilterContainer = document.getElementById('day-filters');
    const searchResultsContainer = document.getElementById('search-results');
    const resultsCountEl = document.getElementById('results-count');
    const sourceBadgeEl = document.getElementById('source-badge');
    const recentSearchesContainer = document.getElementById('recent-searches-container');

    // =========================================================================
    // 1. INITIALIZE LEAFLET MAP
    // =========================================================================
    function initMap() {
        map = L.map('map', {
            zoomControl: false,
        }).setView(DEFAULT_CENTER, DEFAULT_ZOOM);

        // Add Zoom Control to Top Right
        L.control.zoom({ position: 'topright' }).addTo(map);

        // Standard OpenStreetMap Tile Layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | <b>USUClassMap</b>'
        }).addTo(map);

        // Initialize Recent Searches UI
        renderRecentSearches();

        // Load Initial Buildings & User Schedules (Phase 6.6)
        fetchBuildings().then(() => {
            fetchUserSchedules().then(() => {
                handleInitialUrlParams();
            });
        });
    }

    // =========================================================================
    // 2. RECENT SEARCHES (LOCALSTORAGE - MAX 5)
    // =========================================================================
    function getRecentSearches() {
        try {
            return JSON.parse(localStorage.getItem(RECENT_SEARCHES_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function saveRecentSearch(keyword) {
        if (!keyword || keyword.trim().length < 2) return;
        const cleanKw = keyword.trim();
        let searches = getRecentSearches();

        searches = searches.filter(s => s.toLowerCase() !== cleanKw.toLowerCase());
        searches.unshift(cleanKw);

        if (searches.length > 5) {
            searches = searches.slice(0, 5);
        }

        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(searches));
        renderRecentSearches();
    }

    function renderRecentSearches() {
        if (!recentSearchesContainer) return;
        const searches = getRecentSearches();

        if (searches.length === 0) {
            recentSearchesContainer.style.display = 'none';
            recentSearchesContainer.innerHTML = '';
            return;
        }

        recentSearchesContainer.style.display = 'block';
        recentSearchesContainer.innerHTML = `
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Pencarian Terakhir:</span>
                <button onclick="window.USUClassMap.clearAllRecentSearches()" class="text-[10px] text-gray-400 hover:text-red-500 transition-colors cursor-pointer">
                    Hapus Semua
                </button>
            </div>
            <div class="flex flex-wrap gap-1.5">
                ${searches.map(s => `
                    <button 
                        type="button" 
                        onclick="window.USUClassMap.applyRecentSearch('${s.replace(/'/g, "\\'")}')" 
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs bg-white hover:bg-green-50 text-gray-700 hover:text-[#10A760] border border-gray-200 hover:border-[#10A760] rounded-full shadow-2xs transition-all cursor-pointer"
                    >
                        <span>🔍</span>
                        <span>${s}</span>
                    </button>
                `).join('')}
            </div>
        `;
    }

    // =========================================================================
    // 3. BOOKMARKS HELPER & STATE
    // =========================================================================
    function getBookmarks() {
        try {
            return JSON.parse(localStorage.getItem(BOOKMARKS_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function isScheduleBookmarked(scheduleId) {
        const bookmarks = getBookmarks();
        return bookmarks.some(b => b.id === scheduleId);
    }

    function toggleBookmark(schedule) {
        try {
            let bookmarks = getBookmarks();
            const existsIndex = bookmarks.findIndex(b => b.id === schedule.id);

            if (existsIndex > -1) {
                bookmarks.splice(existsIndex, 1);
                localStorage.setItem(BOOKMARKS_KEY, JSON.stringify(bookmarks));
                broadcastBookmarkUpdate(bookmarks.length);
                return false;
            } else {
                bookmarks.push({
                    id: schedule.id,
                    namaMk: schedule.mata_kuliah?.nama || 'Mata Kuliah',
                    kodeMk: schedule.mata_kuliah?.kode || '',
                    dosen: schedule.dosen?.nama || 'Dosen Pengampu',
                    hari: schedule.hari,
                    jamMulai: schedule.jam_mulai,
                    jamSelesai: schedule.jam_selesai,
                    ruangan: schedule.ruangan?.nama || '',
                    gedung: schedule.gedung?.nama || '',
                    latitude: schedule.gedung?.latitude,
                    longitude: schedule.gedung?.longitude,
                });
                localStorage.setItem(BOOKMARKS_KEY, JSON.stringify(bookmarks));
                broadcastBookmarkUpdate(bookmarks.length);
                return true;
            }
        } catch (err) {
            console.error('Failed to toggle bookmark:', err);
            return false;
        }
    }

    function broadcastBookmarkUpdate(count) {
        window.dispatchEvent(new CustomEvent('usuclassmap:bookmarks-updated', {
            detail: { count: count }
        }));
    }

    // =========================================================================
    // 4. CUSTOM SVG MARKER ICONS (LEAFLET DIVICONS)
    // =========================================================================
    function createBuildingIcon(label, isHighlighted = false) {
        const bgClass = isHighlighted ? '#D4A017' : '#10A760';
        const ringHtml = isHighlighted ? `<div class="absolute -inset-2 rounded-full bg-yellow-400 opacity-40 animate-ping"></div>` : '';

        return L.divIcon({
            className: 'custom-leaflet-marker',
            html: `
                <div class="relative flex flex-col items-center group cursor-pointer">
                    ${ringHtml}
                    <div style="background-color: ${bgClass};" class="w-9 h-9 rounded-full shadow-lg flex items-center justify-center text-white border-2 border-white transform transition-transform group-hover:scale-110">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div class="mt-1 px-2 py-0.5 bg-gray-900/80 text-white text-[11px] font-semibold rounded shadow backdrop-blur-xs whitespace-nowrap">
                        ${label}
                    </div>
                </div>
            `,
            iconSize: [36, 52],
            iconAnchor: [18, 48],
            popupAnchor: [0, -48]
        });
    }

    /**
     * Custom Pin Oranye/Emas Khusus Gedung dengan Jadwal Kuliah Saya (Phase 6.6)
     */
    function createUserScheduleIcon(label, myClassCount = 1) {
        return L.divIcon({
            className: 'custom-leaflet-marker user-schedule-marker',
            html: `
                <div class="relative flex flex-col items-center group cursor-pointer">
                    <div class="absolute -inset-2 rounded-full bg-yellow-400 opacity-60 animate-ping"></div>
                    
                    <div style="background-color: #EA580C;" class="w-10 h-10 rounded-full shadow-xl flex items-center justify-center text-white border-2 border-white ring-2 ring-yellow-400 transform transition-transform group-hover:scale-115">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>

                    <div class="absolute -top-1.5 -right-1.5 bg-yellow-500 text-gray-900 text-[10px] font-black px-1.5 py-0.5 rounded-full border border-white shadow-sm flex items-center gap-0.5">
                        <span>⭐</span><span>${myClassCount}</span>
                    </div>

                    <div class="mt-1 px-2 py-0.5 bg-orange-950/90 text-orange-200 text-[11px] font-bold rounded shadow border border-orange-500/40 backdrop-blur-xs whitespace-nowrap">
                        📚 ${label}
                    </div>
                </div>
            `,
            iconSize: [40, 56],
            iconAnchor: [20, 52],
            popupAnchor: [0, -52]
        });
    }

    // =========================================================================
    // 5. MULTIMEDIA DATA MOCKUP INJECTION (PHASE 6.6)
    // =========================================================================
    function getRoomMultimediaData(room, building) {
        const roomName = room.name || 'Ruangan';

        const digitsMatch = roomName.match(/\d+/g);
        let is360 = true;
        if (digitsMatch && digitsMatch.length > 0) {
            const lastNum = parseInt(digitsMatch[digitsMatch.length - 1], 10);
            is360 = (lastNum % 2 !== 0);
        } else {
            is360 = (roomName.length % 2 !== 0);
        }

        const mediaType = is360 ? '360' : 'video';

        const placeholders = [
            'https://images.unsplash.com/photo-1580582932707-520aed937b7b?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1577495508048-b635879837f1?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=800&q=80'
        ];

        const thumbIndex = Math.abs((room.id || 0) + roomName.charCodeAt(0)) % placeholders.length;
        const thumbnailUrl = room.photo_360 || placeholders[thumbIndex];

        const sampleVideoUrl = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
        const sample360Url = room.photo_360 || 'https://images.unsplash.com/photo-1557971370-e7298ee473fb?auto=format&fit=crop&w=2000&q=80';

        return {
            is_360: is360,
            media_type: mediaType,
            thumbnail_url: thumbnailUrl,
            media_src: is360 ? sample360Url : sampleVideoUrl,
            title: `Ruang ${roomName} — ${building.name || 'Gedung USU'}`
        };
    }

    // =========================================================================
    // 6. FETCH BUILDINGS, USER SCHEDULES & POPUP BUILDER
    // =========================================================================
    async function fetchBuildings() {
        try {
            const response = await fetch('/api/buildings');
            const result = await response.json();

            if (result.status === 'success' && Array.isArray(result.data)) {
                allBuildings = result.data;
                renderBuildingMarkers(allBuildings);
            }
        } catch (error) {
            console.error('Error fetching buildings:', error);
        }
    }

    async function fetchUserSchedules() {
        try {
            const response = await fetch('/api/my-schedules', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) return;

            const result = await response.json();

            if (result.status === 'success' && Array.isArray(result.data) && result.data.length > 0) {
                userSchedules = result.data;
                currentUser = result.user;

                userSchedulesByBuilding = {};
                userSchedules.forEach(schedule => {
                    const code = schedule.gedung?.kode || schedule.gedung?.nama;
                    if (code) {
                        if (!userSchedulesByBuilding[code]) {
                            userSchedulesByBuilding[code] = [];
                        }
                        userSchedulesByBuilding[code].push(schedule);
                    }
                });

                renderBuildingMarkers(allBuildings);
                renderDefaultSidebar();
            }
        } catch (error) {
            console.log('[USUClassMap] Info: Belum login atau jadwal tidak ditemukan.');
        }
    }

    function renderBuildingMarkers(buildings, highlightedBuildingIds = new Set()) {
        Object.values(buildingMarkers).forEach(marker => map.removeLayer(marker));
        buildingMarkers = {};

        const bounds = [];

        buildings.forEach(building => {
            if (!building.latitude || !building.longitude) return;

            const isHighlighted = highlightedBuildingIds.has(building.id) || highlightedBuildingIds.has(building.code);
            const schedulesForThisBuilding = userSchedulesByBuilding[building.code] || userSchedulesByBuilding[building.name] || [];
            
            const myClassesInBuilding = schedulesForThisBuilding.filter(s => s.is_my_class === true);
            const hasMyClasses = myClassesInBuilding.length > 0;

            let icon;
            if (hasMyClasses && !isHighlighted) {
                icon = createUserScheduleIcon(building.code || building.name, myClassesInBuilding.length);
            } else {
                icon = createBuildingIcon(building.code || building.name, isHighlighted);
            }

            const marker = L.marker([building.latitude, building.longitude], { icon: icon });
            
            const popupContent = generateBuildingPopupContent(
                building, 
                schedulesForThisBuilding, 
                hasMyClasses
            );

            marker.bindPopup(popupContent, { 
                maxWidth: 360, 
                minWidth: 340, 
                className: 'custom-popup usu-custom-popup' 
            });

            marker.on('click', () => {
                marker.openPopup();
            });

            marker.addTo(map);
            buildingMarkers[building.id] = marker;
            bounds.push([building.latitude, building.longitude]);
        });

        if (highlightedBuildingIds.size > 0 && bounds.length > 0) {
            const highlightBounds = buildings
                .filter(b => highlightedBuildingIds.has(b.id) || highlightedBuildingIds.has(b.code))
                .map(b => [b.latitude, b.longitude]);

            if (highlightBounds.length > 0) {
                map.fitBounds(highlightBounds, { padding: [50, 50], maxZoom: 17 });
            }
        }
    }

    /**
     * REVISI UI/UX POPUP LEAFLET DENGAN SMART FOCUS & HIGHLIGHT (PHASE 6.6)
     * - Menempatkan ruangan target hasil pencarian (targetRoomId/targetRoomName) di index 0 (paling atas).
     * - Menyorot kartu ruangan target dengan border biru, ring menyala, dan lencana "🎯 Hasil Pencarian".
     * - Menampilkan kotak thumbnail foto kelas (w-20 h-20) di sisi kiri.
     */
    function generateBuildingPopupContent(building, schedulesForBuilding = [], isUserSchedule = false, targetRoomId = null, targetRoomName = null, targetScheduleId = null, targetCourseCode = null, targetCourseName = null) {
        const routeUrl = `https://www.google.com/maps/dir/?api=1&destination=${building.latitude},${building.longitude}&travelmode=walking`;

        // 1. Determine list of rooms
        let roomsList = [];
        if (building.rooms && Array.isArray(building.rooms) && building.rooms.length > 0) {
            roomsList = [...building.rooms];
        } else if (schedulesForBuilding.length > 0) {
            const seen = new Set();
            schedulesForBuilding.forEach(s => {
                if (s.ruangan && !seen.has(s.ruangan.nama)) {
                    seen.add(s.ruangan.nama);
                    roomsList.push({
                        id: s.ruangan.id || Math.floor(Math.random() * 1000),
                        name: s.ruangan.nama,
                        floor: s.ruangan.lantai || s.ruangan.floor || 1,
                        photo_360: s.ruangan.foto_360 || null,
                        schedules: []
                    });
                }
            });
        }

        // Map schedules to room names
        const schedulesByRoomName = {};
        schedulesForBuilding.forEach(s => {
            const rName = (s.ruangan?.nama || s.ruangan?.name || '').trim();
            if (rName) {
                if (!schedulesByRoomName[rName]) {
                    schedulesByRoomName[rName] = [];
                }
                schedulesByRoomName[rName].push(s);
            }
        });

        // Helper 1: Check if room matches the target search
        const isRoomTarget = (room) => {
            if (!targetRoomId && !targetRoomName) return false;
            if (targetRoomId && (String(room.id) === String(targetRoomId))) return true;
            if (targetRoomName && room.name) {
                const cleanR = room.name.toLowerCase().replace(/\s+/g, '');
                const cleanT = targetRoomName.toLowerCase().replace(/\s+/g, '');
                return cleanR === cleanT || cleanR.includes(cleanT) || cleanT.includes(cleanR);
            }
            return false;
        };

        // Helper 2: Check if schedule matches the target search (Phase 6.7)
        const isScheduleTarget = (s) => {
            if (!targetScheduleId && !targetCourseCode && !targetCourseName) return false;
            if (targetScheduleId && String(s.id) === String(targetScheduleId)) return true;
            
            const sCode = (s.mata_kuliah?.kode || s.course_code || '').toLowerCase().trim();
            if (targetCourseCode && sCode && sCode === targetCourseCode.toLowerCase().trim()) return true;

            const sName = (s.mata_kuliah?.nama || s.course_name || '').toLowerCase().trim();
            if (targetCourseName && sName) {
                const cleanTarget = targetCourseName.toLowerCase().trim();
                if (sName === cleanTarget || sName.includes(cleanTarget) || cleanTarget.includes(sName)) return true;
            }

            return false;
        };

        // INSTRUCTION 2: Logika Sorting Dinamis Ruangan - Pindahkan ruangan target ke index 0 (paling atas)
        roomsList.sort((a, b) => {
            const aTarget = isRoomTarget(a);
            const bTarget = isRoomTarget(b);
            if (aTarget && !bTarget) return -1;
            if (!aTarget && bTarget) return 1;

            // Secondary: Room containing target schedule
            const aSchedules = a.schedules || schedulesByRoomName[a.name] || [];
            const bSchedules = b.schedules || schedulesByRoomName[b.name] || [];

            const aHasTargetSchedule = aSchedules.some(s => isScheduleTarget(s));
            const bHasTargetSchedule = bSchedules.some(s => isScheduleTarget(s));
            if (aHasTargetSchedule && !bHasTargetSchedule) return -1;
            if (!aHasTargetSchedule && bHasTargetSchedule) return 1;

            // Tertiary: Room with is_my_class
            const aHasMyClass = aSchedules.some(s => s.is_my_class);
            const bHasMyClass = bSchedules.some(s => s.is_my_class);
            if (aHasMyClass && !bHasMyClass) return -1;
            if (!aHasMyClass && bHasMyClass) return 1;

            return 0;
        });

        const myClassesInBuilding = schedulesForBuilding.filter(s => s.is_my_class === true);

        // 2. Build Visual Room Cards with Thumbnail & Smart Highlight (Phase 6.6 & 6.7)
        let roomCardsHtml = '';
        if (roomsList.length > 0) {
            roomCardsHtml = roomsList.map(room => {
                const isTarget = isRoomTarget(room);
                const mediaData = getRoomMultimediaData(room, building);
                
                let roomSchedules = [];
                if (room.schedules && Array.isArray(room.schedules) && room.schedules.length > 0) {
                    roomSchedules = [...room.schedules];
                } else if (schedulesByRoomName[room.name]) {
                    roomSchedules = [...schedulesByRoomName[room.name]];
                }

                // PHASE 6.7: Logika Sorting Ganda - Pindahkan Jadwal Target ke Urutan Nomor 1 (Index 0)
                roomSchedules.sort((a, b) => {
                    const aIsTarget = isScheduleTarget(a);
                    const bIsTarget = isScheduleTarget(b);
                    if (aIsTarget && !bIsTarget) return -1;
                    if (!aIsTarget && bIsTarget) return 1;

                    if (a.is_my_class && !b.is_my_class) return -1;
                    if (!a.is_my_class && b.is_my_class) return 1;

                    return 0;
                });

                // Render list jadwal di ruangan
                let schedulesHtml = '';
                if (roomSchedules.length > 0) {
                    schedulesHtml = `
                        <div class="mt-1 space-y-1">
                            ${roomSchedules.map(s => {
                                const isTargetSchedule = isScheduleTarget(s);
                                const mkName = s.mata_kuliah?.nama || s.course_name || 'Mata Kuliah';
                                const timeRange = `${s.jam_mulai || s.time_start || '08:00'} - ${s.jam_selesai || s.time_end || '09:40'}`;
                                const lecturerName = s.dosen?.nama || s.lecturer_name || 'Dosen Pengampu';
                                const classGroup = s.kelompok_kelas || s.kelas || s.class_group || 'MKWU';

                                // PHASE 6.7: UI/UX Highlight Jadwal Target (Pilihan User)
                                if (isTargetSchedule) {
                                    return `
                                        <div class="bg-blue-100/90 border-l-4 border-blue-600 text-blue-950 p-2 rounded-r-md mb-1.5 shadow-xs font-bold text-[11px] ring-1 ring-blue-300 transition-all">
                                            <div class="flex items-center justify-between gap-1">
                                                <div class="flex items-center gap-1 truncate font-black text-blue-900">
                                                    <span class="text-blue-600 shrink-0">🎯</span>
                                                    <span class="truncate">${mkName} (${timeRange})</span>
                                                </div>
                                                <span class="text-[9px] bg-blue-600 text-white font-extrabold px-1.5 py-0.5 rounded-full shrink-0 animate-pulse">
                                                    Pilihan Anda
                                                </span>
                                            </div>
                                            <div class="text-[10px] text-blue-800 font-medium truncate pl-4 mt-0.5">
                                                ${lecturerName} • Kelas ${classGroup}
                                            </div>
                                        </div>
                                    `;
                                } else if (s.is_my_class === true) {
                                    return `
                                        <div class="bg-yellow-50 border border-yellow-400 text-yellow-900 p-1.5 rounded-md shadow-2xs text-[11px] font-semibold leading-tight">
                                            <div class="flex items-center gap-1 truncate">
                                                <span class="text-amber-500 shrink-0">⭐</span>
                                                <span class="truncate">${mkName} (${timeRange})</span>
                                            </div>
                                            <div class="text-[10px] text-yellow-800/80 font-normal truncate pl-3 mt-0.5">
                                                ${lecturerName} • Kelas ${classGroup}
                                            </div>
                                        </div>
                                    `;
                                } else {
                                    return `
                                        <div class="bg-gray-50 border border-gray-200 text-gray-600 p-1 rounded-md text-[11px] leading-tight">
                                            <div class="flex items-center gap-1 truncate">
                                                <span class="text-gray-400 shrink-0">•</span>
                                                <span class="truncate font-medium text-gray-800">${mkName} (${timeRange})</span>
                                            </div>
                                            <div class="text-[10px] text-gray-400 truncate pl-2 mt-0.5">
                                                ${lecturerName} • Kelas ${classGroup}
                                            </div>
                                        </div>
                                    `;
                                }
                            }).join('')}
                        </div>
                    `;
                } else {
                    schedulesHtml = `
                        <p class="text-gray-400 italic text-[10px] mt-1 flex items-center gap-1">
                            <span>⚪</span>
                            <span>Tidak ada kelas saat ini</span>
                        </p>
                    `;
                }

                // Room Data object payload for multi-mode modal
                const roomPayload = {
                    id: room.id,
                    name: room.name,
                    floor: room.floor || 1,
                    capacity: room.capacity || 30,
                    photo_url: mediaData.thumbnail_url,
                    photo_360_url: room.photo_360 || mediaData.media_src,
                    video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                    schedules: roomSchedules
                };

                const buildingPayload = {
                    id: building.id,
                    name: building.name,
                    code: building.code,
                    latitude: building.latitude,
                    longitude: building.longitude
                };

                const roomJson = JSON.stringify(roomPayload).replace(/"/g, '&quot;');
                const buildingJson = JSON.stringify(buildingPayload).replace(/"/g, '&quot;');

                // INSTRUCTION 3: UI/UX Highlight khusus Ruangan Target
                const cardClasses = isTarget
                    ? 'flex gap-3 bg-blue-50/95 border-2 border-blue-500 shadow-md ring-4 ring-blue-100 p-2.5 rounded-xl mb-3 cursor-pointer transition-all target-room-highlight group relative'
                    : 'flex gap-3 bg-white p-2 rounded-lg shadow-sm mb-3 border border-gray-100 hover:border-[#10A760] hover:bg-green-50/30 cursor-pointer transition-all group';

                const targetBadgeHtml = isTarget
                    ? `<span class="px-2 py-0.5 bg-blue-600 text-white font-extrabold text-[10px] rounded-full flex items-center gap-1 shadow-xs animate-pulse shrink-0">
                           <span>🎯</span>
                           <span>Hasil Pencarian</span>
                       </span>`
                    : `<span class="text-[10px] font-bold text-[#10A760] group-hover:translate-x-0.5 transition-transform shrink-0 flex items-center gap-0.5">
                           <span>Detail</span>
                           <span>➔</span>
                       </span>`;

                const guideTextHtml = isTarget
                    ? `<p class="text-[10px] text-blue-700 font-bold flex items-center gap-1 mt-0.5">
                           <span>🎯</span>
                           <span>Ruangan yang Anda cari — Klik untuk Detail</span>
                       </p>`
                    : `<p class="text-[10px] text-[#10A760] font-medium flex items-center gap-1 mt-0.5">
                           <span>✨</span>
                           <span>Klik untuk Detail & Multimedia</span>
                       </p>`;

                const safeRoomIdentifier = room.id || (room.name ? room.name.replace(/\s+/g, '_') : Math.floor(Math.random()*1000));

                return `
                    <div 
                        id="room-card-${safeRoomIdentifier}"
                        data-target-room="${isTarget ? 'true' : 'false'}"
                        onclick="window.USUClassMap.openRoomDetailModal(${roomJson}, ${buildingJson})" 
                        class="${cardClasses}"
                        title="Klik untuk melihat foto besar, tur 360°, dan rute jalan"
                    >
                        <!-- Kiri: Visual Thumbnail Kelas (w-20 h-20) -->
                        <div class="w-20 h-20 rounded-md overflow-hidden flex-shrink-0 bg-gray-100 relative shadow-2xs">
                            <img 
                                src="${mediaData.thumbnail_url}" 
                                alt="${room.name}" 
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                loading="lazy"
                            />
                            <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/60 via-transparent to-transparent p-1">
                                <span class="text-[9px] font-bold text-white px-1 py-0.2 rounded bg-black/40 backdrop-blur-2xs">
                                    Lt. ${room.floor || 1}
                                </span>
                            </div>
                        </div>

                        <!-- Kanan: Area Info & Jadwal -->
                        <div class="flex-1 min-w-0 flex flex-col justify-center">
                            <div class="flex items-center justify-between gap-1">
                                <h5 class="font-bold text-xs ${isTarget ? 'text-blue-950 font-black' : 'text-gray-900'} group-hover:text-[#10A760] transition-colors truncate">
                                    🚪 Ruang ${room.name}
                                </h5>
                                ${targetBadgeHtml}
                            </div>

                            ${guideTextHtml}

                            <div class="mt-1">
                                ${schedulesHtml}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            roomCardsHtml = `
                <div class="text-center py-6 px-3 bg-white rounded-xl border border-dashed border-gray-200">
                    <p class="text-xs text-gray-400 italic">Belum ada data ruangan terdaftar di gedung ini.</p>
                </div>
            `;
        }

        // Header Highlights & Tags
        const badgeBg = myClassesInBuilding.length > 0 ? 'bg-orange-600' : 'bg-[#10A760]';
        const userScheduleTag = myClassesInBuilding.length > 0 
            ? `<span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 border border-yellow-300 text-[10px] font-extrabold rounded-full flex items-center gap-1 shadow-2xs">
                   <span>⭐</span>
                   <span>${myClassesInBuilding.length} Kelas Saya</span>
               </span>` 
            : '';

        return `
            <div class="font-sans text-gray-800 overflow-hidden rounded-xl">
                <!-- 1. STICKY HEADER -->
                <div class="sticky top-0 bg-white z-10 border-b border-gray-200 p-3.5 relative shadow-2xs">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block px-2 py-0.5 ${badgeBg} text-white text-[10px] font-black rounded tracking-wide shadow-2xs">
                                ${building.code || 'GEDUNG'}
                            </span>
                            ${userScheduleTag}
                        </div>
                        <span class="text-[10px] text-gray-400 font-medium">${roomsList.length} Ruangan</span>
                    </div>

                    <h4 class="font-black text-sm text-gray-900 mt-1.5 leading-tight">${building.name}</h4>
                    <p class="text-[11px] text-gray-500 line-clamp-1 mt-0.5">${building.description || 'Fakultas / Fasilitas Kampus Universitas Sumatera Utara'}</p>

                    <!-- Tombol Petunjuk Arah -->
                    <a href="${routeUrl}" target="_blank" rel="noopener noreferrer" class="w-full mt-2.5 px-3 py-2 bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold text-xs rounded-lg shadow-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer">
                        <span>📍</span>
                        <span>Petunjuk Arah (Google Maps)</span>
                    </a>
                </div>

                <!-- 2. SCROLLABLE BODY (VISUAL SMART ROOM CARDS) -->
                <div class="max-h-[260px] overflow-y-auto p-2.5 bg-gray-50 custom-scrollbar">
                    <div class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wider mb-2 px-1 flex items-center justify-between">
                        <span>Daftar Ruangan & Jadwal</span>
                        <span class="text-[9px] text-[#10A760] font-semibold">Klik kartu untuk tur</span>
                    </div>
                    ${roomCardsHtml}
                </div>
            </div>
        `;
    }

    // =========================================================================
    // 7. SEARCH & FILTER SCHEDULES
    // =========================================================================
    async function searchSchedules() {
        const query = activeQuery.trim();
        const day = activeDay.trim();

        if (searchResultsContainer) {
            searchResultsContainer.innerHTML = `
                <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                    <svg class="animate-spin h-8 w-8 text-[#10A760] mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span class="text-xs font-semibold text-gray-600">Mencari via SPARQL & Semantic Layer...</span>
                </div>
            `;
        }

        if (!query && !day) {
            currentSchedules = [];
            renderBuildingMarkers(allBuildings);
            renderDefaultSidebar();
            if (resultsCountEl) resultsCountEl.textContent = `${allBuildings.length} Gedung`;
            if (sourceBadgeEl) {
                sourceBadgeEl.textContent = 'Semua Gedung';
                sourceBadgeEl.className = 'px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-800';
            }
            return;
        }

        if (query) {
            saveRecentSearch(query);
        }

        try {
            const url = new URL('/api/schedules/search', window.location.origin);
            if (query) url.searchParams.set('q', query);
            if (day) url.searchParams.set('day', day);

            const response = await fetch(url.toString());
            const result = await response.json();

            if (result.status === 'success') {
                currentSchedules = result.data || [];

                if (resultsCountEl) resultsCountEl.textContent = `${currentSchedules.length} Jadwal`;
                if (sourceBadgeEl) {
                    sourceBadgeEl.textContent = result.source || 'SPARQL';
                    sourceBadgeEl.className = result.source?.includes('SPARQL')
                        ? 'px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-800'
                        : 'px-2 py-0.5 text-[10px] font-bold rounded bg-blue-100 text-blue-800';
                }

                const highlightedBuildingCodes = new Set();
                const schedulesByBuilding = {};

                currentSchedules.forEach(schedule => {
                    const buildingCode = schedule.gedung?.kode || schedule.gedung?.nama;
                    if (buildingCode) {
                        highlightedBuildingCodes.add(buildingCode);
                        if (!schedulesByBuilding[buildingCode]) {
                            schedulesByBuilding[buildingCode] = [];
                        }
                        schedulesByBuilding[buildingCode].push(schedule);
                    }
                });

                renderSearchMarkers(allBuildings, highlightedBuildingCodes, schedulesByBuilding);
                renderSidebarResults(currentSchedules);
            }
        } catch (error) {
            console.error('Error searching schedules:', error);
            if (searchResultsContainer) {
                searchResultsContainer.innerHTML = `
                    <div class="p-4 bg-red-50 text-red-600 rounded-xl text-xs text-center border border-red-200">
                        Terjadi kesalahan saat memuat data jadwal. Silakan coba kembali.
                    </div>
                `;
            }
        }
    }

    function renderSearchMarkers(buildings, highlightedCodes, schedulesByBuilding) {
        Object.values(buildingMarkers).forEach(marker => map.removeLayer(marker));
        buildingMarkers = {};

        const highlightBounds = [];

        buildings.forEach(building => {
            if (!building.latitude || !building.longitude) return;

            const isMatched = highlightedCodes.has(building.code) || highlightedCodes.has(building.name);
            const schedules = isMatched ? (schedulesByBuilding[building.code] || schedulesByBuilding[building.name] || []) : [];

            const icon = createBuildingIcon(building.code || building.name, isMatched);
            const marker = L.marker([building.latitude, building.longitude], {
                icon: icon,
                opacity: isMatched ? 1.0 : 0.4
            });

            const popupContent = generateBuildingPopupContent(building, schedules, false);
            marker.bindPopup(popupContent, { 
                maxWidth: 360, 
                minWidth: 340, 
                className: 'custom-popup usu-custom-popup' 
            });

            marker.on('click', () => {
                marker.openPopup();
            });

            marker.addTo(map);
            buildingMarkers[building.id] = marker;

            if (isMatched) {
                highlightBounds.push([building.latitude, building.longitude]);
            }
        });

        if (highlightBounds.length > 0) {
            map.fitBounds(highlightBounds, { padding: [50, 50], maxZoom: 17 });
        }
    }

    // =========================================================================
    // 8. SIDEBAR RENDERING WITH SMART FOCUS TRIGGER (PHASE 6.6)
    // =========================================================================
    function renderDefaultSidebar() {
        if (!searchResultsContainer) return;

        let userBannerHtml = '';
        if (currentUser && userSchedules.length > 0) {
            const myClassesTotal = userSchedules.filter(s => s.is_my_class === true).length;
            const buildingCount = Object.keys(userSchedulesByBuilding).length;
            
            userBannerHtml = `
                <div class="p-3.5 bg-gradient-to-r from-amber-500 to-orange-600 rounded-xl text-white shadow-md mb-3 border border-yellow-400">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-yellow-100">
                                <span>⭐</span>
                                <span>Jadwal Mahasiswa Terdaftar</span>
                            </div>
                            <h4 class="font-bold text-sm text-white mt-0.5">${currentUser.name || 'Mahasiswa'}</h4>
                            <p class="text-[11px] text-yellow-100 mt-0.5">
                                ${myClassesTotal} Kelas Anda • ${buildingCount} Gedung Kuliah
                            </p>
                        </div>
                        <button 
                            onclick="window.USUClassMap.focusUserSchedules()" 
                            class="px-2.5 py-1.5 bg-white text-orange-700 hover:bg-yellow-50 font-bold text-xs rounded-lg shadow-sm transition-all cursor-pointer"
                            title="Tampilkan semua gedung kelas Anda"
                        >
                            Fokus 🎯
                        </button>
                    </div>
                </div>
            `;
        }

        searchResultsContainer.innerHTML = `
            <div class="space-y-2.5">
                ${userBannerHtml}
                <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Gedung Kampus USU:</div>
                ${allBuildings.map(b => {
                    const buildingSchedules = userSchedulesByBuilding[b.code] || userSchedulesByBuilding[b.name] || [];
                    const myClasses = buildingSchedules.filter(s => s.is_my_class === true);
                    const hasMyClass = myClasses.length > 0;

                    const classBadge = hasMyClass 
                        ? `<span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 border border-yellow-300 font-bold text-[10px] rounded-full">⭐ ${myClasses.length} Kelas Saya</span>` 
                        : '';
                    return `
                        <div onclick="window.USUClassMap.focusBuilding(${b.id})" class="p-3.5 bg-white hover:bg-green-50/60 border ${hasMyClass ? 'border-yellow-400 bg-yellow-50/30' : 'border-gray-200'} hover:border-[#10A760] rounded-xl shadow-xs cursor-pointer transition-all duration-150 group">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold px-2 py-0.5 ${hasMyClass ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-800'} rounded-md group-hover:bg-[#10A760] group-hover:text-white transition-colors">
                                        ${b.code}
                                    </span>
                                    ${classBadge}
                                </div>
                                <span class="text-[11px] text-gray-400 font-medium">${b.rooms_count || b.rooms?.length || 0} Ruangan</span>
                            </div>
                            <h4 class="font-bold text-sm text-gray-900 mt-2">${b.name}</h4>
                            <p class="text-xs text-gray-500 line-clamp-1 mt-0.5">${b.description || 'Gedung perkuliahan'}</p>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function renderSidebarResults(schedules) {
        if (!searchResultsContainer) return;

        if (schedules.length === 0) {
            searchResultsContainer.innerHTML = `
                <div class="text-center py-10 px-4 bg-white rounded-2xl border border-dashed border-gray-300 shadow-2xs">
                    <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-500 mx-auto flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h4 class="text-sm font-bold text-gray-900">Jadwal Tidak Ditemukan</h4>
                    <p class="mt-1 text-xs text-gray-500 leading-relaxed max-w-xs mx-auto">
                        Tidak ada jadwal yang cocok dengan kata kunci atau filter hari yang Anda pilih.
                    </p>
                    <button 
                        type="button" 
                        onclick="window.USUClassMap.resetFilters()" 
                        class="mt-4 px-4 py-2 bg-gray-100 hover:bg-[#10A760] text-gray-700 hover:text-white text-xs font-bold rounded-lg transition-colors cursor-pointer"
                    >
                        Reset Pencarian & Filter
                    </button>
                </div>
            `;
            return;
        }

        searchResultsContainer.innerHTML = `
            <div class="space-y-3">
                ${schedules.map(s => {
                    const isBookmarked = isScheduleBookmarked(s.id);
                    const isMyClass = s.is_my_class === true;

                    const safeGedungNama = (s.gedung?.nama || '').replace(/'/g, "\\'");
                    const safeRuanganNama = (s.ruangan?.nama || '').replace(/'/g, "\\'");
                    const safeKodeMk = (s.mata_kuliah?.kode || '').replace(/'/g, "\\'");
                    const safeNamaMk = (s.mata_kuliah?.nama || '').replace(/'/g, "\\'");
                    const ruanganId = s.ruangan?.id || 'null';
                    const gedungId = s.gedung?.id || 'null';
                    const scheduleId = s.id || 'null';

                    return `
                        <div class="p-4 bg-white border ${isMyClass ? 'border-yellow-400 bg-yellow-50/20' : 'border-gray-200'} hover:border-[#10A760] rounded-xl shadow-xs hover:shadow-md transition-all duration-150">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-block px-2 py-0.5 text-[10px] font-bold rounded ${s.mata_kuliah.tipe === 'MKWU' ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-800'}">
                                            ${s.mata_kuliah.tipe || 'MKWK'} • ${s.mata_kuliah.sks} SKS
                                        </span>
                                        ${isMyClass ? '<span class="px-1.5 py-0.5 bg-yellow-100 text-yellow-800 font-bold text-[9px] rounded">⭐ Kelas Saya</span>' : ''}
                                    </div>
                                    <h4 class="font-bold text-sm text-gray-900 mt-1">${s.mata_kuliah.nama}</h4>
                                    <span class="text-[11px] text-gray-500 font-mono">${s.mata_kuliah.kode} • Kelas ${s.kelompok_kelas || s.kelas || 'A'}</span>
                                </div>
                                <button 
                                    onclick="window.USUClassMap.handleBookmarkToggle(${JSON.stringify(s).replace(/"/g, '&quot;')}, this)" 
                                    title="${isBookmarked ? 'Hapus Bookmark' : 'Simpan Bookmark'}" 
                                    class="p-1.5 rounded-full hover:bg-green-50 transition-colors cursor-pointer ${isBookmarked ? 'text-[#D4A017]' : 'text-gray-300 hover:text-[#10A760]'}"
                                >
                                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                        <path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-2.5 text-xs text-gray-600 flex items-center gap-1.5">
                                <span>👨‍🏫</span>
                                <span class="font-medium truncate text-gray-800">${s.dosen.nama}</span>
                            </div>

                            <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                                <span class="font-bold text-gray-700">🗓️ ${s.hari}, ${s.jam_mulai}-${s.jam_selesai}</span>
                                <span class="font-bold text-emerald-700">🏛️ ${s.gedung.nama} (${s.ruangan.nama})</span>
                            </div>

                            <!-- INSTRUCTION 1: Tombol 'Lihat di Peta' dengan Parameter Smart Focus Lengkap (Ruangan & Jadwal) -->
                            <div class="mt-3 flex items-center gap-2">
                                <button 
                                    onclick="window.USUClassMap.focusBuildingWithTarget(${gedungId}, '${safeGedungNama}', ${s.gedung.latitude}, ${s.gedung.longitude}, ${ruanganId}, '${safeRuanganNama}', ${scheduleId}, '${safeKodeMk}', '${safeNamaMk}')" 
                                    class="flex-1 py-2 px-2.5 bg-green-50 hover:bg-[#10A760] text-[#10A760] hover:text-white text-xs font-bold rounded-lg text-center transition-colors cursor-pointer flex items-center justify-center gap-1.5 group"
                                >
                                    <span>📍</span>
                                    <span>Lihat di Peta</span>
                                </button>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=${s.gedung.latitude},${s.gedung.longitude}&travelmode=walking" target="_blank" rel="noopener noreferrer" class="py-2 px-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold transition-colors cursor-pointer" title="Petunjuk Rute Google Maps">
                                    🚶
                                </a>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    // =========================================================================
    // 9. EVENT LISTENERS & URL PARAMS
    // =========================================================================
    function handleInitialUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const queryParam = urlParams.get('q') || urlParams.get('keyword');
        const dayParam = urlParams.get('day');

        if (queryParam) {
            activeQuery = queryParam;
            if (searchInput) {
                searchInput.value = queryParam;
                if (clearSearchBtn) clearSearchBtn.style.display = 'block';
            }
        }

        if (dayParam && dayFilterContainer) {
            const btn = dayFilterContainer.querySelector(`button[data-day="${dayParam}"]`);
            if (btn) {
                activeDay = dayParam;
                btn.classList.add('bg-[#10A760]', 'text-white');
                btn.classList.remove('bg-white', 'text-[#10A760]');
            }
        }

        if (queryParam || dayParam) {
            searchSchedules();
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            activeQuery = e.target.value;
            if (clearSearchBtn) {
                clearSearchBtn.style.display = activeQuery ? 'block' : 'none';
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                searchSchedules();
            }, 400);
        });
    }

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            activeQuery = '';
            clearSearchBtn.style.display = 'none';
            searchSchedules();
        });
    }

    if (dayFilterContainer) {
        dayFilterContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-day]');
            if (!btn) return;

            const selectedDay = btn.getAttribute('data-day');

            if (activeDay === selectedDay) {
                activeDay = '';
                btn.classList.remove('bg-[#10A760]', 'text-white');
                btn.classList.add('bg-white', 'text-[#10A760]', 'border-[#10A760]');
            } else {
                activeDay = selectedDay;
                dayFilterContainer.querySelectorAll('button[data-day]').forEach(b => {
                    b.classList.remove('bg-[#10A760]', 'text-white');
                    b.classList.add('bg-white', 'text-[#10A760]', 'border-[#10A760]');
                });
                btn.classList.add('bg-[#10A760]', 'text-white');
                btn.classList.remove('bg-white', 'text-[#10A760]');
            }

            searchSchedules();
        });
    }

    // =========================================================================
    // 10. GLOBAL HELPERS & SMART FOCUS WITH HIGHLIGHT (PHASE 6.6)
    // =========================================================================
    window.openRoomDetailModal = function(room, building, defaultMode = 'photo') {
        window.dispatchEvent(new CustomEvent('open-room-detail-modal', {
            detail: {
                room: room || {},
                building: building || {},
                defaultMode: defaultMode || 'photo'
            }
        }));
    };

    // Backward compatibility for multimedia modal triggers
    window.openMultimediaModal = function(type, src, title) {
        window.openRoomDetailModal(
            { name: title, photo_360: src, photo_360_url: src, video_url: src },
            {},
            type === '360' ? '360' : 'video'
        );
    };

    window.USUClassMap = {
        openRoomDetailModal: window.openRoomDetailModal,
        openMultimediaModal: window.openMultimediaModal,

        /**
         * Smart Focus & Highlight Trigger (Phase 6.6 & 6.7)
         * Membuka popup gedung, menaruh targetRoom di posisi index 0, dan menaruh targetSchedule di posisi index 0 dalam card ruangan dengan highlight visual
         */
        focusBuildingWithTarget: (buildingId, buildingName, lat, lng, targetRoomId, targetRoomName, targetScheduleId = null, targetCourseCode = null, targetCourseName = null) => {
            // Find target building object
            let building = null;
            if (buildingId) {
                building = allBuildings.find(b => b.id == buildingId);
            }
            if (!building && buildingName) {
                building = allBuildings.find(b => b.name?.toLowerCase() === buildingName.toLowerCase() || b.code?.toLowerCase() === buildingName.toLowerCase());
            }
            if (!building && lat && lng) {
                building = allBuildings.find(b => Math.abs(b.latitude - lat) < 0.0001 && Math.abs(b.longitude - lng) < 0.0001);
            }

            if (!building) return;

            const targetLat = lat || building.latitude;
            const targetLng = lng || building.longitude;

            // Get relevant schedules for this building
            const schedulesForThisBuilding = currentSchedules.filter(s => 
                (s.gedung?.kode === building.code || s.gedung?.nama === building.name)
            );
            const fallbackSchedules = schedulesForThisBuilding.length > 0 
                ? schedulesForThisBuilding 
                : (userSchedulesByBuilding[building.code] || userSchedulesByBuilding[building.name] || []);

            const hasMyClass = fallbackSchedules.some(s => s.is_my_class === true);

            // Generate popup with target room & target schedule at index 0 and highlighted
            const newPopupContent = generateBuildingPopupContent(
                building, 
                fallbackSchedules, 
                hasMyClass, 
                targetRoomId, 
                targetRoomName,
                targetScheduleId,
                targetCourseCode,
                targetCourseName
            );

            // Pan map to location smoothly
            map.flyTo([targetLat, targetLng], 18, { animate: true, duration: 0.8 });

            const marker = buildingMarkers[building.id];
            if (marker) {
                marker.setPopupContent(newPopupContent);
                setTimeout(() => {
                    marker.openPopup();

                    // Auto-scroll fallback guarantee
                    setTimeout(() => {
                        const targetCard = document.querySelector('[data-target-room="true"], .target-room-highlight');
                        if (targetCard) {
                            targetCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }, 250);
                }, 400);
            }
        },

        focusBuilding: (buildingId) => {
            const marker = buildingMarkers[buildingId];
            if (marker) {
                map.setView(marker.getLatLng(), 18, { animate: true });
                marker.openPopup();
            }
        },

        focusBuildingByCoordinates: (lat, lng, buildingName, targetRoomId = null, targetRoomName = null, targetScheduleId = null, targetCourseCode = null, targetCourseName = null) => {
            window.USUClassMap.focusBuildingWithTarget(null, buildingName, lat, lng, targetRoomId, targetRoomName, targetScheduleId, targetCourseCode, targetCourseName);
        },

        focusUserSchedules: () => {
            const userCoords = [];
            allBuildings.forEach(b => {
                const bSchedules = userSchedulesByBuilding[b.code] || userSchedulesByBuilding[b.name] || [];
                const hasMyClass = bSchedules.some(s => s.is_my_class === true);
                if (hasMyClass) {
                    userCoords.push([b.latitude, b.longitude]);
                }
            });

            if (userCoords.length > 0) {
                map.fitBounds(userCoords, { padding: [60, 60], maxZoom: 17 });
                const firstBuilding = allBuildings.find(b => {
                    const bSchedules = userSchedulesByBuilding[b.code] || userSchedulesByBuilding[b.name] || [];
                    return bSchedules.some(s => s.is_my_class === true);
                });
                if (firstBuilding && buildingMarkers[firstBuilding.id]) {
                    setTimeout(() => {
                        buildingMarkers[firstBuilding.id].openPopup();
                    }, 400);
                }
            }
        },

        applyRecentSearch: (keyword) => {
            if (searchInput) {
                searchInput.value = keyword;
                if (clearSearchBtn) clearSearchBtn.style.display = 'block';
            }
            activeQuery = keyword;
            searchSchedules();
        },

        clearAllRecentSearches: () => {
            localStorage.removeItem(RECENT_SEARCHES_KEY);
            renderRecentSearches();
        },

        resetFilters: () => {
            if (searchInput) searchInput.value = '';
            activeQuery = '';
            activeDay = '';
            if (clearSearchBtn) clearSearchBtn.style.display = 'none';
            if (dayFilterContainer) {
                dayFilterContainer.querySelectorAll('button[data-day]').forEach(b => {
                    b.classList.remove('bg-[#10A760]', 'text-white');
                    b.classList.add('bg-white', 'text-[#10A760]', 'border-[#10A760]');
                });
            }
            searchSchedules();
        },

        handleBookmarkToggle: (schedule, buttonElement) => {
            const isNowBookmarked = toggleBookmark(schedule);
            
            if (currentSchedules.length > 0) {
                renderSidebarResults(currentSchedules);
            }

            if (isNowBookmarked) {
                buttonElement?.classList.add('text-[#D4A017]');
                buttonElement?.classList.remove('text-gray-400');
            }
        }
    };

    // Initialize Map
    initMap();
});
