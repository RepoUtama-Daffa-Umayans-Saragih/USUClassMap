<x-layouts.app>
    <div class="max-w-5xl mx-auto px-4 py-10">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 pb-4 border-b border-gray-200">
            <div>
                <span class="px-3 py-1 bg-green-100 text-[#10A760] font-bold text-xs rounded-full uppercase tracking-wider">
                    Jadwal Favorit
                </span>
                <h1 class="text-3xl font-extrabold text-gray-900 mt-2 tracking-tight">
                    Jadwal Tersimpan
                </h1>
                <p class="text-xs text-gray-500 mt-1">Daftar jadwal kelas yang Anda tandai disimpan secara lokal di browser Anda.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="/map" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-[#10A760]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                    </svg>
                    Buka Peta
                </a>
                <button 
                    id="clear-all-bookmarks-btn" 
                    type="button"
                    class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5"
                    style="display: none;"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Hapus Semua
                </button>
            </div>
        </div>

        <!-- Bookmarks List Grid -->
        <div id="bookmarks-list-container" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Populated via JavaScript -->
        </div>

        <!-- Empty State -->
        <div id="empty-bookmarks-state" class="text-center py-16 px-4 bg-white rounded-2xl border border-dashed border-gray-300 shadow-xs" style="display: none;">
            <div class="w-16 h-16 rounded-full bg-green-50 text-[#10A760] mx-auto flex items-center justify-center mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900">Belum Ada Jadwal Tersimpan</h3>
            <p class="text-xs text-gray-500 max-w-md mx-auto mt-1 mb-6">
                Cari mata kuliah MKWK/MKWU Anda di peta interaktif, lalu klik tombol bookmark (🔖) untuk menyimpan jadwal ke halaman ini.
            </p>
            <a href="/map" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold text-xs rounded-lg shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                Cari Jadwal di Peta
            </a>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const listContainer = document.getElementById('bookmarks-list-container');
            const emptyState = document.getElementById('empty-bookmarks-state');
            const clearAllBtn = document.getElementById('clear-all-bookmarks-btn');

            function getBookmarks() {
                try {
                    return JSON.parse(localStorage.getItem('usuclassmap_bookmarks') || '[]');
                } catch (e) {
                    console.error('Error reading bookmarks:', e);
                    return [];
                }
            }

            function renderBookmarks() {
                const bookmarks = getBookmarks();

                if (bookmarks.length === 0) {
                    listContainer.innerHTML = '';
                    emptyState.style.display = 'block';
                    clearAllBtn.style.display = 'none';
                    return;
                }

                emptyState.style.display = 'none';
                clearAllBtn.style.display = 'inline-flex';

                listContainer.innerHTML = bookmarks.map((b, index) => {
                    const lat = b.latitude || 3.565245;
                    const lng = b.longitude || 98.656520;
                    const routeUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=walking`;
                    const mapUrl = `/map?q=${encodeURIComponent(b.kodeMk || b.namaMk || '')}`;

                    return `
                        <div class="p-5 bg-white border border-gray-200 hover:border-[#10A760] rounded-xl shadow-xs hover:shadow-md transition-all duration-200 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <span class="inline-block px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-800">
                                            ${b.kodeMk || 'MKWK'}
                                        </span>
                                        <h3 class="font-bold text-base text-gray-900 mt-1">${b.namaMk || 'Mata Kuliah'}</h3>
                                    </div>
                                    <button 
                                        onclick="window.removeSingleBookmark(${index})" 
                                        class="text-gray-400 hover:text-red-500 p-1 rounded-full hover:bg-red-50 transition-colors"
                                        title="Hapus Bookmark"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>

                                <div class="mt-3 space-y-1.5 text-xs text-gray-600">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-400">👨‍🏫</span>
                                        <span class="font-medium text-gray-800">${b.dosen || 'Dosen Pengampu'}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-400">🗓️</span>
                                        <span>${b.hari || '-'}, <b>${b.jamMulai || ''} - ${b.jamSelesai || ''}</b></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-400">🏛️</span>
                                        <span class="text-emerald-700 font-semibold">${b.gedung || '-'} • Ruang ${b.ruangan || '-'}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 pt-3 border-t border-gray-100 flex items-center gap-2">
                                <a href="${mapUrl}" class="flex-1 py-2 px-3 bg-green-50 hover:bg-[#10A760] text-[#10A760] hover:text-white text-xs font-bold rounded-lg text-center transition-colors flex items-center justify-center gap-1">
                                    📍 Lihat di Peta
                                </a>
                                <a href="${routeUrl}" target="_blank" rel="noopener noreferrer" class="py-2 px-3 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition-colors flex items-center gap-1" title="Buka Petunjuk Arah Google Maps">
                                    🚶 Rute
                                </a>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            window.removeSingleBookmark = function(index) {
                const bookmarks = getBookmarks();
                bookmarks.splice(index, 1);
                localStorage.setItem('usuclassmap_bookmarks', JSON.stringify(bookmarks));
                
                // Dispatch event so navbar badge updates immediately
                window.dispatchEvent(new CustomEvent('usuclassmap:bookmarks-updated', { detail: { count: bookmarks.length } }));
                renderBookmarks();
            };

            clearAllBtn.addEventListener('click', () => {
                if (confirm('Apakah Anda yakin ingin menghapus seluruh jadwal tersimpan?')) {
                    localStorage.removeItem('usuclassmap_bookmarks');
                    window.dispatchEvent(new CustomEvent('usuclassmap:bookmarks-updated', { detail: { count: 0 } }));
                    renderBookmarks();
                }
            });

            // Listen for changes from other tabs or map
            window.addEventListener('storage', () => renderBookmarks());
            window.addEventListener('usuclassmap:bookmarks-updated', () => renderBookmarks());

            renderBookmarks();
        });
    </script>
    @endpush
</x-layouts.app>
