@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Presensi Harian GTK" />

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3.5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
            <i class="bx bx-check-circle text-lg"></i>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    <!-- Selector Header -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] mb-6">
        <form action="{{ route('presensi.gtk') }}" method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:items-end">
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
                    <i class="bx bx-calendar"></i> Tampilkan Presensi Tanggal
                </button>
            </div>
        </form>
    </div>

    <!-- Attendance Grid -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h4 class="font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Daftar Hadir GTK (Guru & Tenaga Kependidikan)
                </h4>
                <p class="text-xs text-gray-400 mt-1">Mengelola absensi kedatangan harian kepala sekolah, guru, bendahara, dan tata usaha.</p>
            </div>
            <span class="text-xs text-gray-400 font-sans">Tanggal: {{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('d F Y') }}</span>
        </div>

        <form action="{{ route('presensi.gtk.store') }}" method="POST">
            @csrf
            <input type="hidden" name="attendance_date" value="{{ $selectedDate }}" />

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-5">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[700px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                <th class="px-5 py-3 text-left">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Nama Lengkap / Jabatan</p>
                                </th>
                                <th class="px-5 py-3 text-center w-20">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tipe</p>
                                </th>
                                <th class="px-5 py-3 text-center w-[400px]">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status Kehadiran</p>
                                </th>
                                <th class="px-5 py-3 text-left w-64">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Catatan/Keterangan</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($teachers as $teacher)
                                @php
                                    $existing = $existingAttendances->get($teacher->id);
                                    $currentStatus = $existing ? $existing->status : 'H';
                                    $currentNote = $existing ? $existing->note : '';
                                    
                                    // Set tag badge color based on role
                                    $roleBadge = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
                                    if ($teacher->role_type === 'Kepala Sekolah') {
                                        $roleBadge = 'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400';
                                    } elseif ($teacher->role_type === 'Bendahara') {
                                        $roleBadge = 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400';
                                    } elseif ($teacher->role_type === 'Staf TU') {
                                        $roleBadge = 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400';
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-3.5">
                                        <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                            {{ $teacher->name }}
                                        </span>
                                        <span class="block text-xs text-gray-400">
                                            {{ $teacher->role_type }} | NIP. {{ $teacher->nip ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-theme-xs font-semibold {{ $roleBadge }}">
                                            {{ $teacher->staff_type ?? 'PNS' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <div class="inline-flex gap-3.5">
                                            <!-- Hadir -->
                                            <label class="flex items-center gap-1 cursor-pointer text-[11px] font-semibold text-gray-700 dark:text-gray-300">
                                                <input type="radio" name="attendances[{{ $teacher->id }}]" value="H" {{ $currentStatus === 'H' ? 'checked' : '' }}
                                                    class="h-3.5 w-3.5 border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900" />
                                                <span>Hadir</span>
                                            </label>
                                            
                                            <!-- Sakit -->
                                            <label class="flex items-center gap-1 cursor-pointer text-[11px] font-semibold text-yellow-600 dark:text-yellow-400">
                                                <input type="radio" name="attendances[{{ $teacher->id }}]" value="S" {{ $currentStatus === 'S' ? 'checked' : '' }}
                                                    class="h-3.5 w-3.5 border-gray-300 text-yellow-600 focus:ring-yellow-500 dark:border-gray-700 dark:bg-gray-900" />
                                                <span>Sakit</span>
                                            </label>
                                            
                                            <!-- Izin -->
                                            <label class="flex items-center gap-1 cursor-pointer text-[11px] font-semibold text-blue-600 dark:text-blue-400">
                                                <input type="radio" name="attendances[{{ $teacher->id }}]" value="I" {{ $currentStatus === 'I' ? 'checked' : '' }}
                                                    class="h-3.5 w-3.5 border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900" />
                                                <span>Izin</span>
                                            </label>
                                            
                                            <!-- Dinas Luar -->
                                            <label class="flex items-center gap-1 cursor-pointer text-[11px] font-semibold text-purple-600 dark:text-purple-400">
                                                <input type="radio" name="attendances[{{ $teacher->id }}]" value="DL" {{ $currentStatus === 'DL' ? 'checked' : '' }}
                                                    class="h-3.5 w-3.5 border-gray-300 text-purple-600 focus:ring-purple-500 dark:border-gray-700 dark:bg-gray-900" />
                                                <span>Dinas Luar</span>
                                            </label>

                                            <!-- Alfa -->
                                            <label class="flex items-center gap-1 cursor-pointer text-[11px] font-semibold text-red-500">
                                                <input type="radio" name="attendances[{{ $teacher->id }}]" value="A" {{ $currentStatus === 'A' ? 'checked' : '' }}
                                                    class="h-3.5 w-3.5 border-gray-300 text-red-500 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-900" />
                                                <span>Alfa</span>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <input type="text" name="notes[{{ $teacher->id }}]" value="{{ $currentNote }}" placeholder="Catatan/Alasan..."
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-8 w-full rounded-md border border-gray-300 bg-transparent px-3 text-xs text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-8 text-center text-gray-400 text-sm">
                                        Belum ada data guru/staf terdaftar di sistem.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($teachers->isNotEmpty())
                <div class="flex justify-end">
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-600 focus:outline-hidden">
                        Simpan Presensi GTK
                    </button>
                </div>
            @endif
        </form>
    </div>
@endsection
