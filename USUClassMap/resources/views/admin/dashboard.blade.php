<x-layouts.admin>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Total Jadwal</p>
                <p class="text-2xl font-semibold text-gray-900">{{ $totalSchedules ?? 0 }}</p>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6 flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Total Dosen</p>
                <p class="text-2xl font-semibold text-gray-900">{{ $totalLecturers ?? 0 }}</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Total Gedung</p>
                <p class="text-2xl font-semibold text-gray-900">{{ $totalBuildings ?? 0 }}</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Total Ruangan</p>
                <p class="text-2xl font-semibold text-gray-900">{{ $totalRooms ?? 0 }}</p>
            </div>
        </div>
    </div>

    <div class="bg-[#10A760] rounded-lg shadow mb-8 text-white p-6">
        <h2 class="text-xl font-bold mb-2">Selamat Datang di Admin Panel USUClassMap</h2>
        <p class="opacity-90">Kelola data jadwal perkuliahan MKWK/MKWU, dosen, gedung, dan ruangan untuk ditampilkan pada aplikasi peta interaktif.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow">
            <div class="p-5 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Jadwal Terbaru</h3>
            </div>
            <div class="p-5">
                <div class="text-center text-gray-500 py-8">
                    Belum ada jadwal yang ditambahkan.
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="p-5 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Aktivitas Terkini</h3>
            </div>
            <div class="p-5">
                <div class="text-center text-gray-500 py-8">
                    Belum ada aktivitas yang tercatat.
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
