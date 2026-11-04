<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SATU USU | USUClassMap Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .bg-campus {
            background-image: url('https://images.unsplash.com/photo-1562774053-701939374585?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .bg-campus::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(0, 80, 40, 0.75) 0%, rgba(16, 167, 96, 0.45) 50%, rgba(0, 0, 0, 0.35) 100%);
            z-index: 1;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 font-sans antialiased">
    <div class="min-h-screen flex flex-col lg:flex-row">

        {{-- ===== KOLOM KIRI: HERO / BRANDING (Latar Kampus) ===== --}}
        <div class="relative bg-campus w-full lg:w-[55%] min-h-[220px] lg:min-h-screen flex items-end lg:items-center p-8 sm:p-12 lg:p-16">
            <div class="relative z-10 max-w-lg">
                {{-- Badge --}}
                <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/15 backdrop-blur-xs rounded-full text-white/90 text-xs font-semibold mb-6 border border-white/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    USUClassMap × SATU USU
                </div>

                {{-- Heading --}}
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white leading-tight tracking-tight mb-4">
                    Halo Sobat USU 👋
                </h1>
                <p class="text-base sm:text-lg text-white/85 leading-relaxed font-medium max-w-md">
                    Dengan <span class="font-bold text-white">SATU USU</span> kamu dapat mengakses banyak sistem yang tersedia pada Universitas Sumatera Utara hanya dengan satu akun.
                </p>

                {{-- Feature badges --}}
                <div class="mt-8 flex flex-wrap gap-3">
                    <div class="flex items-center gap-2 text-white/80 text-xs font-semibold bg-white/10 backdrop-blur-xs px-3 py-1.5 rounded-lg border border-white/10">
                        <svg class="w-4 h-4 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Sinkronisasi Jadwal
                    </div>
                    <div class="flex items-center gap-2 text-white/80 text-xs font-semibold bg-white/10 backdrop-blur-xs px-3 py-1.5 rounded-lg border border-white/10">
                        <svg class="w-4 h-4 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Peta Interaktif
                    </div>
                    <div class="flex items-center gap-2 text-white/80 text-xs font-semibold bg-white/10 backdrop-blur-xs px-3 py-1.5 rounded-lg border border-white/10">
                        <svg class="w-4 h-4 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        SPARQL Search
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== KOLOM KANAN: CARD LOGIN ===== --}}
        <div class="w-full lg:w-[45%] flex items-center justify-center p-6 sm:p-10 lg:p-16 bg-[#F8F9FA]">
            <div class="w-full max-w-md">

                {{-- Logo SATU --}}
                <div class="text-center mb-8">
                    <div class="inline-flex items-center gap-3 mb-2">
                        {{-- USU Crest Icon --}}
                        <div class="w-11 h-11 rounded-xl bg-[#D4A017] text-white flex items-center justify-center shadow-sm">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422A12.083 12.083 0 0121 12.64V17a2 2 0 01-2 2H5a2 2 0 01-2-2v-4.36c0-1.135.396-2.234 1.12-3.107L12 14z"></path></svg>
                        </div>
                        <span class="text-3xl font-black tracking-tight">
                            <span class="text-[#10A760]">SATU</span>
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Admin Login — Universitas Sumatera Utara</p>
                </div>

                {{-- Login Card --}}
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="p-7 sm:p-8">

                        {{-- Session Status --}}
                        @if (session('status'))
                            <div class="mb-5 p-3 bg-green-50 text-[#10A760] text-sm font-medium rounded-xl border border-green-100">
                                {{ session('status') }}
                            </div>
                        @endif

                        {{-- Sync Success Flash --}}
                        @if (session('success'))
                            <div class="mb-5 p-3 bg-green-50 text-[#10A760] text-sm font-medium rounded-xl border border-green-100 flex items-center gap-2">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                {{ session('success') }}
                            </div>
                        @endif

                        {{-- Validation Errors --}}
                        @if ($errors->any())
                            <div class="mb-5 p-3 bg-red-50 text-red-600 text-xs rounded-xl border border-red-100">
                                <ul class="list-disc pl-4 space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" class="space-y-5">
                            @csrf

                            {{-- Identitas (NIM/NIP/Email) --}}
                            <div>
                                <label for="nim" class="block text-sm font-bold text-gray-800 mb-1.5">
                                    Identitas (NIP/NIM/Email USU)
                                </label>
                                <input 
                                    id="nim" 
                                    type="text" 
                                    name="nim" 
                                    value="{{ old('nim') }}" 
                                    required 
                                    autofocus 
                                    autocomplete="username"
                                    placeholder="Masukkan Identitas"
                                    class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder-gray-400 focus:outline-hidden focus:ring-2 focus:ring-[#10A760] focus:border-[#10A760] transition-all"
                                >
                            </div>

                            {{-- Password --}}
                            <div x-data="{ showPw: false }">
                                <label for="password" class="block text-sm font-bold text-gray-800 mb-1.5">
                                    Password
                                </label>
                                <div class="relative">
                                    <input 
                                        id="password" 
                                        :type="showPw ? 'text' : 'password'" 
                                        name="password" 
                                        required 
                                        autocomplete="current-password"
                                        placeholder="Masukkan Password"
                                        class="w-full px-4 py-3 pr-12 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder-gray-400 focus:outline-hidden focus:ring-2 focus:ring-[#10A760] focus:border-[#10A760] transition-all"
                                    >
                                    <button 
                                        @click="showPw = !showPw" 
                                        type="button" 
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer"
                                    >
                                        <svg x-show="!showPw" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path></svg>
                                        <svg x-show="showPw" style="display:none;" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Remember Me --}}
                            <div class="flex items-center justify-between">
                                <label for="remember_me" class="inline-flex items-center">
                                    <input id="remember_me" type="checkbox" name="remember" class="w-4 h-4 text-[#10A760] border-gray-300 rounded-sm focus:ring-[#10A760]">
                                    <span class="ml-2 text-xs text-gray-600 font-medium">Ingat saya</span>
                                </label>
                            </div>

                            {{-- Submit Button --}}
                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center gap-2 px-7 py-3 bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition-all cursor-pointer">
                                    Login
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Footer Card --}}
                    <div class="px-8 py-4 bg-gray-50 border-t border-gray-100 text-center">
                        <p class="text-xs text-gray-500">
                            <span class="font-semibold text-gray-700">Lupa Password?</span>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="font-bold text-[#10A760] hover:underline ml-1">Reset Password</a>
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Register link --}}
                <div class="mt-6 text-center text-xs text-gray-500">
                    Belum punya akun lokal? <a href="{{ route('register') }}" class="font-bold text-[#10A760] hover:underline">Daftar di sini</a>
                </div>

                {{-- Copyright --}}
                <div class="mt-8 text-center text-[10px] text-gray-400">
                    &copy; {{ date('Y') }} USUClassMap — Kelompok 3 Web Semantik USU
                </div>
            </div>
        </div>
    </div>
</body>
</html>
