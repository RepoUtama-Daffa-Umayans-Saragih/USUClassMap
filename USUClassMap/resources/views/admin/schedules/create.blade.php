<x-layouts.admin>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Tambah Jadwal</h1>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <form method="POST" action="{{ route('admin.schedules.store') }}">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Mata Kuliah</label>
                <select name="course_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                    <option value="">-- Pilih Mata Kuliah --</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" {{ old('course_id') == $course->id ? 'selected' : '' }}>
                            {{ $course->code }} - {{ $course->name }}
                        </option>
                    @endforeach
                </select>
                @error('course_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Dosen</label>
                <select name="lecturer_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                    <option value="">-- Pilih Dosen --</option>
                    @foreach($lecturers as $lecturer)
                        <option value="{{ $lecturer->id }}" {{ old('lecturer_id') == $lecturer->id ? 'selected' : '' }}>
                            {{ $lecturer->name }}
                        </option>
                    @endforeach
                </select>
                @error('lecturer_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Ruangan</label>
                <select name="room_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                    <option value="">-- Pilih Ruangan --</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}" {{ old('room_id') == $room->id ? 'selected' : '' }}>
                            {{ $room->name }} ({{ $room->building->name }})
                        </option>
                    @endforeach
                </select>
                @error('room_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Hari</label>
                <select name="day" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                    <option value="">-- Pilih Hari --</option>
                    @foreach($days as $day)
                        <option value="{{ $day }}" {{ old('day') == $day ? 'selected' : '' }}>
                            {{ $day }}
                        </option>
                    @endforeach
                </select>
                @error('day')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jam Mulai</label>
                    <input type="time" name="time_start" value="{{ old('time_start') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                    @error('time_start')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jam Selesai</label>
                    <input type="time" name="time_end" value="{{ old('time_end') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                    @error('time_end')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                <input type="text" name="semester" value="{{ old('semester') }}" placeholder="Ganjil 2024/2025" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                @error('semester')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Kelompok Kelas</label>
                <input type="text" name="class_group" value="{{ old('class_group') }}" placeholder="A" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#10A760] focus:ring-[#10A760] px-3 py-2 border">
                @error('class_group')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="bg-[#10A760] text-white px-6 py-2 rounded-lg hover:bg-green-700 shadow">Simpan</button>
                <a href="{{ route('admin.schedules.index') }}" class="text-gray-600 hover:text-gray-800">Batal</a>
            </div>
        </form>
    </div>
</x-layouts.admin>
