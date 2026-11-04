<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - USUClassMap</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F3F4F6] min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-gray-100 p-8 my-8">
        <!-- Logo -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[#10A760] text-white shadow-lg mb-3">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Daftar Akun</h1>
            <p class="text-xs font-semibold uppercase tracking-wider text-[#10A760] mt-1">USUClassMap Mahasiswa & Pengguna</p>
        </div>

        <!-- Error Messages -->
        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-50 text-red-700 text-xs rounded-xl">
                <ul class="list-disc pl-4 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <!-- Name -->
            <div>
                <label for="name" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Nama Lengkap</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-[#10A760] transition-all"
                    placeholder="Contoh: Daffa Umayans Saragih">
            </div>

            <!-- Email Address -->
            <div>
                <label for="email" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-[#10A760] transition-all"
                    placeholder="nama@students.usu.ac.id">
            </div>

            <!-- NIM & Semester -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="nim" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">NIM (Opsional)</label>
                    <input id="nim" type="text" name="nim" value="{{ old('nim') }}"
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-[#10A760] transition-all"
                        placeholder="251402011">
                </div>
                <div>
                    <label for="semester" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Semester</label>
                    <select id="semester" name="semester"
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-[#10A760] transition-all">
                        @for($i = 1; $i <= 8; $i++)
                            <option value="{{ $i }}" {{ old('semester') == $i ? 'selected' : '' }}>Semester {{ $i }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Kata Sandi</label>
                <input id="password" type="password" name="password" required autocomplete="new-password"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-[#10A760] transition-all"
                    placeholder="••••••••">
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Konfirmasi Kata Sandi</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-[#10A760] transition-all"
                    placeholder="••••••••">
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full py-3 bg-[#10A760] hover:bg-[#0D8A4E] text-white font-bold text-sm rounded-xl shadow-lg hover:shadow-xl transition-all cursor-pointer">
                Daftar Akun Baru
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-gray-100 text-center text-xs text-gray-500">
            Sudah punya akun? <a href="{{ route('login') }}" class="font-bold text-[#10A760] hover:underline">Masuk di sini</a>
        </div>
    </div>
</body>
</html>
