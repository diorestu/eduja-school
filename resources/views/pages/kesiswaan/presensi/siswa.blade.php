@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Presensi Harian Siswa" />

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3.5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
            <i class="bx bx-check-circle text-lg"></i>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    <!-- Selector Header -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] mb-6">
        <form action="{{ route('presensi.siswa') }}" method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Pilih Kelas / Rombel
                </label>
                <div class="relative bg-transparent">
                    <select name="school_class_id" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2 text-sm text-gray-800 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" {{ $selectedClassId == $class->id ? 'selected' : '' }}>
                                {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                        <i class="bx bx-chevron-down text-lg"></i>
                    </span>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Tanggal Presensi
                </label>
                <input type="date" name="attendance_date" value="{{ $selectedDate }}" required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 text-sm text-gray-800 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>

            <div>
                <button type="submit"
                    class="w-full h-10 rounded-lg bg-brand-500 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-brand-600 focus:outline-hidden">
                    <i class="bx bx-filter-alt"></i> Tampilkan Siswa
                </button>
            </div>
        </form>
    </div>

    <!-- Attendance Grid -->
    @if($selectedClassId)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between mb-5">
                <h4 class="font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Daftar Hadir Kelas
                </h4>
                <span class="text-xs text-gray-400">Tanggal: {{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('d F Y') }}</span>
            </div>

            <form action="{{ route('presensi.siswa.store') }}" method="POST">
                @csrf
                <input type="hidden" name="school_class_id" value="{{ $selectedClassId }}" />
                <input type="hidden" name="attendance_date" value="{{ $selectedDate }}" />

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-5">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[700px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                    <th class="px-5 py-3 text-left w-32">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">NIS</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Nama Siswa</p>
                                    </th>
                                    <th class="px-5 py-3 text-center w-20">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Gender</p>
                                    </th>
                                    <th class="px-5 py-3 text-center w-72">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status Kehadiran</p>
                                    </th>
                                    <th class="px-5 py-3 text-left w-64">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Catatan/Keterangan</p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($students as $student)
                                    @php
                                        $existing = $existingAttendances->get($student->id);
                                        $currentStatus = $existing ? $existing->status : 'H';
                                        $currentNote = $existing ? $existing->note : '';
                                    @endphp
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                        <td class="px-5 py-3.5">
                                            <span class="font-mono text-theme-sm text-gray-800 dark:text-white/90">
                                                {{ $student->nis }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <span class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                                {{ $student->name }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <span class="text-theme-sm text-gray-500">
                                                {{ $student->gender ?? 'L' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <div class="inline-flex gap-4">
                                                <!-- Hadir -->
                                                <label class="flex items-center gap-1.5 cursor-pointer text-xs font-semibold text-gray-700 dark:text-gray-300">
                                                    <input type="radio" name="attendances[{{ $student->id }}]" value="H" {{ $currentStatus === 'H' ? 'checked' : '' }}
                                                        class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900" />
                                                    <span>Hadir (H)</span>
                                                </label>
                                                
                                                <!-- Sakit -->
                                                <label class="flex items-center gap-1.5 cursor-pointer text-xs font-semibold text-yellow-600 dark:text-yellow-400">
                                                    <input type="radio" name="attendances[{{ $student->id }}]" value="S" {{ $currentStatus === 'S' ? 'checked' : '' }}
                                                        class="h-4 w-4 border-gray-300 text-yellow-600 focus:ring-yellow-500 dark:border-gray-700 dark:bg-gray-900" />
                                                    <span>Sakit (S)</span>
                                                </label>
                                                
                                                <!-- Izin -->
                                                <label class="flex items-center gap-1.5 cursor-pointer text-xs font-semibold text-blue-600 dark:text-blue-400">
                                                    <input type="radio" name="attendances[{{ $student->id }}]" value="I" {{ $currentStatus === 'I' ? 'checked' : '' }}
                                                        class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900" />
                                                    <span>Izin (I)</span>
                                                </label>
                                                
                                                <!-- Alfa -->
                                                <label class="flex items-center gap-1.5 cursor-pointer text-xs font-semibold text-red-500">
                                                    <input type="radio" name="attendances[{{ $student->id }}]" value="A" {{ $currentStatus === 'A' ? 'checked' : '' }}
                                                        class="h-4 w-4 border-gray-300 text-red-500 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-900" />
                                                    <span>Alfa (A)</span>
                                                </label>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <input type="text" name="notes[{{ $student->id }}]" value="{{ $currentNote }}" placeholder="Catatan khusus..."
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-8 w-full rounded-md border border-gray-300 bg-transparent px-3 text-xs text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">
                                            Belum ada data siswa terdaftar di dalam kelas ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($students->isNotEmpty())
                    <div class="flex justify-end">
                        <button type="submit"
                            class="rounded-lg bg-brand-500 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-600 focus:outline-hidden">
                            Simpan Lembar Presensi
                        </button>
                    </div>
                @endif
            </form>
        </div>
    @else
        <div class="rounded-2xl border border-gray-200 bg-white p-12 text-center dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-gray-400 text-4xl block mb-3">
                <i class="bx bx-select-multiple"></i>
            </span>
            <p class="text-gray-500 dark:text-gray-400 text-sm">Silakan pilih kelas/rombel dan tanggal di atas terlebih dahulu untuk memuat lembar absensi.</p>
        </div>
    @endif
@endsection
