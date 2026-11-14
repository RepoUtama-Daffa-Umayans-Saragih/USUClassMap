<x-layouts.admin-fotokopi>
    <div class="mb-6">
        <a href="{{ route('admin-fotokopi.stores.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-emerald-600 transition-colors mb-2">
            &larr; Kembali ke Daftar Mitra
        </a>
        <h1 class="text-2xl font-black text-gray-900">Tambah Mitra Toko Fotocopy</h1>
        <p class="text-xs text-gray-500 mt-1">Daftarkan gerai mitra percetakan & fotocopy baru di kawasan kampus USU.</p>
    </div>

    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-xs max-w-4xl">
        <form method="POST" action="{{ route('admin-fotokopi.stores.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nama Toko *</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Berkah Abadi Copy & ATK Perpustakaan" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                    @error('name')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Lokasi Kampus *</label>
                    <input type="text" name="location_name" value="{{ old('location_name') }}" placeholder="Contoh: Pelataran Parkir Perpustakaan Pusat USU" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                    @error('location_name')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Jam Operasional *</label>
                    <input type="text" name="opening_hours" value="{{ old('opening_hours', '07:30 - 20:30') }}" placeholder="07:30 - 20:30" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Hari Operasional *</label>
                    <input type="text" name="operating_days" value="{{ old('operating_days', 'Senin - Sabtu') }}" placeholder="Senin - Sabtu" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nomor WhatsApp</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="+6282277809900" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Akun Instagram</label>
                    <input type="text" name="instagram" value="{{ old('instagram') }}" placeholder="@berkahabadi_usu" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Facebook Page</label>
                    <input type="text" name="facebook" value="{{ old('facebook') }}" placeholder="berkahabadi.copy" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Harga Mulai (Rp)</label>
                    <input type="number" name="starting_price" value="{{ old('starting_price', 200) }}" min="0" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Rating Awal</label>
                    <input type="number" step="0.1" name="rating" value="{{ old('rating', 4.8) }}" min="1" max="5" required class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Status Toko</label>
                    <select name="is_open" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        <option value="1" {{ old('is_open', '1') == '1' ? 'selected' : '' }}>Buka (Aktif)</option>
                        <option value="0" {{ old('is_open') == '0' ? 'selected' : '' }}>Tutup Sementara</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Kasir Penanggung Jawab</label>
                    <select name="user_id" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                        <option value="">-- Pilih Akun Kasir --</option>
                        @foreach($cashiers as $c)
                            <option value="{{ $c->id }}" {{ old('user_id') == $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->email }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Latitude Peta</label>
                    <input type="number" step="0.000001" name="latitude" value="{{ old('latitude', '3.565250') }}" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Longitude Peta</label>
                    <input type="number" step="0.000001" name="longitude" value="{{ old('longitude', '98.654850') }}" class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Deskripsi / Tentang Toko</label>
                <textarea name="description" rows="3" placeholder="Paling strategis di dekat Perpustakaan Pusat USU. Melayani fotocopy referensi buku perpus, cetak online via WA..." class="w-full text-xs rounded-xl border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 p-2.5">{{ old('description') }}</textarea>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin-fotokopi.stores.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold">Batal</a>
                <button type="submit" class="px-5 py-2.5 bg-[#10B981] hover:bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-xs">Simpan Mitra Toko</button>
            </div>
        </form>
    </div>
</x-layouts.admin-fotokopi>
