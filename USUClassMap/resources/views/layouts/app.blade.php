<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'USUClassMap' }} - Peta Ruang Kelas Kampus USU</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body 
    class="bg-[#F3F4F6] text-gray-900 font-sans antialiased" 
    x-data="{ 
        mobileMenu: false, 
        bookmarkCount: 0,
        init() {
            const updateCount = () => {
                try {
                    const b = JSON.parse(localStorage.getItem('usuclassmap_bookmarks') || '[]');
                    this.bookmarkCount = b.length;
                } catch(e) {
                    this.bookmarkCount = 0;
                }
            };
            updateCount();
            window.addEventListener('storage', updateCount);
            window.addEventListener('usuclassmap:bookmarks-updated', (e) => {
                if (e.detail && typeof e.detail.count === 'number') {
                    this.bookmarkCount = e.detail.count;
                } else {
                    updateCount();
                }
            });
        }
    }"
>
    <!-- Navbar -->
    <nav class="fixed top-0 left-0 right-0 bg-white shadow-xs h-16 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex justify-between items-center">
            <!-- Left: Logo -->
            <div class="flex items-center">
                <a href="/" class="flex items-center gap-2.5 group">
                    <div class="w-9 h-9 rounded-xl bg-[#10A760] text-white flex items-center justify-center shadow-xs group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <span class="font-extrabold text-xl text-[#10A760] tracking-tight">USUClassMap</span>
                </a>
            </div>

            <!-- Center: Nav links (Desktop) -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="/map" class="text-gray-600 hover:text-[#10A760] font-semibold text-sm transition-colors {{ request()->is('map*') ? 'text-[#10A760]' : '' }}">
                    Peta
                </a>
                <a href="/bookmarks" class="relative text-gray-600 hover:text-[#10A760] font-semibold text-sm transition-colors flex items-center gap-1.5 {{ request()->is('bookmarks*') ? 'text-[#10A760]' : '' }}">
                    <span>Bookmark</span>
                    <span 
                        x-show="bookmarkCount > 0" 
                        x-text="bookmarkCount" 
                        class="inline-flex items-center justify-center px-1.5 py-0.2 text-[10px] font-extrabold text-white bg-[#10A760] rounded-full min-w-4.5 h-4.5 shadow-xs"
                        style="display: none;"
                    ></span>
                </a>
                <a href="/faq" class="text-gray-600 hover:text-[#10A760] font-semibold text-sm transition-colors {{ request()->is('faq*') ? 'text-[#10A760]' : '' }}">
                    FAQ
                </a>
            </div>

            <!-- Right: Dynamic Profile & Auth (Desktop) -->
            @guest
                <div class="hidden md:flex items-center gap-3 pl-6 border-l border-gray-200">
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-700 hover:text-[#10A760] transition-colors">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="px-4 py-2 text-xs font-bold text-white bg-[#10A760] hover:bg-[#0D8A4E] rounded-xl shadow-xs transition-all">
                        Daftar
                    </a>
                </div>
            @endguest

            @auth
                <div class="hidden md:flex items-center gap-3 pl-6 border-l border-gray-200 relative" x-data="{ userMenu: false }">
                    <button @click="userMenu = !userMenu" type="button" class="flex items-center gap-3 text-left focus:outline-hidden group cursor-pointer">
                        <div class="text-right">
                            <div class="text-xs font-bold text-gray-900 group-hover:text-[#10A760] transition-colors">{{ Auth::user()->name }}</div>
                            <div class="text-[11px] text-gray-500 font-medium">
                                {{ Auth::user()->nim ?? 'Mahasiswa' }} • Semester {{ Auth::user()->semester ?? '1' }}
                            </div>
                        </div>
                        <div class="w-9 h-9 rounded-full bg-[#10A760] text-white flex items-center justify-center font-bold text-sm shadow-xs ring-2 ring-green-100 group-hover:ring-[#10A760] transition-all">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                    </button>

                    <!-- Dropdown Menu -->
                    <div 
                        x-show="userMenu" 
                        @click.away="userMenu = false" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute right-0 top-12 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1.5 z-50"
                        style="display: none;"
                    >
                        <div class="px-4 py-2 border-b border-gray-100">
                            <p class="text-xs text-gray-500">Masuk sebagai</p>
                            <p class="text-xs font-bold text-gray-800 truncate">{{ Auth::user()->email }}</p>
                        </div>

                        @if(Auth::user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-[#10A760]">
                                Admin Dashboard
                            </a>
                        @endif

                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-[#10A760]">
                            Pengaturan Profil
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors">
                                Keluar (Logout)
                            </button>
                        </form>
                    </div>
                </div>
            @endauth

            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center gap-2">
                <a href="/bookmarks" class="relative p-2 text-gray-600 hover:text-[#10A760]">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                    </svg>
                    <span 
                        x-show="bookmarkCount > 0" 
                        x-text="bookmarkCount" 
                        class="absolute top-1 right-1 inline-flex items-center justify-center px-1 text-[9px] font-bold text-white bg-[#10A760] rounded-full min-w-4 h-4"
                        style="display: none;"
                    ></span>
                </a>
                <button @click="mobileMenu = !mobileMenu" type="button" class="p-2 text-gray-500 hover:text-gray-700 focus:outline-hidden">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': mobileMenu, 'inline-flex': !mobileMenu }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': !mobileMenu, 'inline-flex': mobileMenu }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div x-show="mobileMenu" @click.away="mobileMenu = false" class="md:hidden bg-white border-t border-gray-100 shadow-xl absolute w-full" style="display: none;">
            <div class="px-3 pt-2 pb-3 space-y-1">
                <a href="/map" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:text-[#10A760] hover:bg-gray-50 {{ request()->is('map*') ? 'bg-green-50 text-[#10A760]' : '' }}">Peta</a>
                <a href="/bookmarks" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:text-[#10A760] hover:bg-gray-50 {{ request()->is('bookmarks*') ? 'bg-green-50 text-[#10A760]' : '' }}">
                    <span>Bookmark</span>
                    <span x-show="bookmarkCount > 0" x-text="bookmarkCount" class="px-2 py-0.5 text-xs font-bold text-white bg-[#10A760] rounded-full"></span>
                </a>
                <a href="/faq" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:text-[#10A760] hover:bg-gray-50 {{ request()->is('faq*') ? 'bg-green-50 text-[#10A760]' : '' }}">FAQ</a>
            </div>

            @guest
                <div class="pt-3 pb-4 border-t border-gray-100 px-4 bg-gray-50 space-y-2">
                    <a href="{{ route('login') }}" class="block w-full text-center py-2 text-sm font-semibold text-gray-700 hover:text-[#10A760] bg-white border border-gray-200 rounded-xl">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="block w-full text-center py-2 text-sm font-bold text-white bg-[#10A760] hover:bg-[#0D8A4E] rounded-xl shadow-xs">
                        Daftar Akun
                    </a>
                </div>
            @endguest

            @auth
                <div class="pt-3 pb-4 border-t border-gray-100 px-4 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-[#10A760] text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="text-xs font-bold text-gray-900">{{ Auth::user()->name }}</div>
                                <div class="text-[11px] text-gray-500 font-medium">{{ Auth::user()->nim ?? 'Mahasiswa' }} • Semester {{ Auth::user()->semester ?? '1' }}</div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-xs font-bold text-red-600 hover:underline">
                                Keluar
                            </button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="pt-16 min-h-[calc(100vh-4rem)]">
        {{ $slot }}
    </main>

    @stack('scripts')
</body>
</html>
