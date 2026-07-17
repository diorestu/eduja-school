@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" label="Portal" />

    {{-- Session Flash Notifications --}}
    @if(session('success'))
        <div class="mb-5 flex items-center gap-3 rounded-xl border border-success-200 bg-success-50/50 p-4 text-sm font-medium text-success-800 dark:border-success-500/20 dark:bg-success-500/5 dark:text-success-400 backdrop-blur-md transition-all duration-300">
            <i class="bx bx-check-circle text-xl text-success-600 dark:text-success-400"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 flex items-center gap-3 rounded-xl border border-error-200 bg-error-50/50 p-4 text-sm font-medium text-error-800 dark:border-error-500/20 dark:bg-error-500/5 dark:text-error-400 backdrop-blur-md transition-all duration-300">
            <i class="bx bx-error-circle text-xl text-error-600 dark:text-error-400"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        {{-- LEFT COLUMN: PROFILE & ATTENDANCE --}}
        <div class="lg:col-span-1 space-y-6">
            
            {{-- PROFILE CARD --}}
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-xs transition-all duration-300 hover:shadow-md dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-brand-500/10 blur-2xl"></div>
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-tr from-brand-500 to-indigo-600 text-2xl font-bold text-white shadow-lg shadow-brand-500/20">
                        {{ strtoupper(substr($student->name, 0, 2)) }}
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $student->name }}</h3>
                        <p class="text-sm font-medium text-brand-600 dark:text-brand-400">{{ $className }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">NISN: {{ $student->nisn }} | NIS: {{ $student->nis }}</p>
                    </div>
                </div>
                
                <div class="mt-6 grid grid-cols-2 gap-4 border-t border-gray-100 pt-5 dark:border-gray-800">
                    <div>
                        <span class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider font-semibold">Orang Tua/Wali</span>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-0.5">{{ $student->parent_name }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider font-semibold">No. Telepon</span>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-0.5">{{ $student->phone ?? '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- INTERACTIVE ATTENDANCE CARD --}}
            <div x-data="{ 
                currentTime: '', 
                currentDate: '', 
                init() {
                    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                    const updateTime = () => {
                        const now = new Date();
                        this.currentTime = now.toTimeString().split(' ')[0];
                        this.currentDate = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
                    };
                    updateTime();
                    setInterval(updateTime, 1000);
                } 
            }" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs transition-all duration-300 hover:shadow-md dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-950 dark:text-white">Presensi Mandiri</h3>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                        <span class="mr-1.5 h-1.5 w-1.5 rounded-full bg-emerald-600 dark:bg-emerald-500 animate-pulse"></span>
                        Lokasi Sesuai
                    </span>
                </div>

                {{-- Time Clock --}}
                <div class="my-6 text-center">
                    <div class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white" x-text="currentTime">00:00:00</div>
                    <div class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400" x-text="currentDate">-</div>
                </div>

                {{-- Quick Attendance Buttons --}}
                <div class="grid grid-cols-2 gap-4">
                    {{-- Clock In --}}
                    <form action="{{ route('portal.siswa.attendance') }}" method="POST">
                        @csrf
                        <input type="hidden" name="type" value="masuk">
                        <button type="submit" 
                            @if($todayAttendance && $todayAttendance->clock_in_at) disabled @endif
                            class="flex w-full flex-col items-center justify-center rounded-xl border border-brand-200 bg-brand-50/50 p-4 transition-all duration-200 hover:bg-brand-100/50 active:scale-98 disabled:pointer-events-none disabled:border-gray-200 disabled:bg-gray-50 dark:border-brand-500/20 dark:bg-brand-500/5 dark:hover:bg-brand-500/10 dark:disabled:border-gray-800 dark:disabled:bg-gray-900/20 group">
                            <i class="bx bxs-log-in text-2xl text-brand-600 dark:text-brand-400 transition-transform group-hover:scale-110"></i>
                            <span class="mt-2 text-sm font-bold text-gray-900 dark:text-white">Masuk</span>
                            <span class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                @if($todayAttendance && $todayAttendance->clock_in_at)
                                    {{ substr($todayAttendance->clock_in_at, 0, 5) }}
                                @else
                                    -- : --
                                @endif
                            </span>
                        </button>
                    </form>

                    {{-- Clock Out --}}
                    <form action="{{ route('portal.siswa.attendance') }}" method="POST">
                        @csrf
                        <input type="hidden" name="type" value="pulang">
                        <button type="submit" 
                            @if(!$todayAttendance || !$todayAttendance->clock_in_at || ($todayAttendance && $todayAttendance->clock_out_at)) disabled @endif
                            class="flex w-full flex-col items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 transition-all duration-200 hover:bg-indigo-100/50 active:scale-98 disabled:pointer-events-none disabled:border-gray-200 disabled:bg-gray-50 dark:border-indigo-500/20 dark:bg-indigo-500/5 dark:hover:bg-indigo-500/10 dark:disabled:border-gray-800 dark:disabled:bg-gray-900/20 group">
                            <i class="bx bxs-log-out text-2xl text-indigo-600 dark:text-indigo-400 transition-transform group-hover:scale-110"></i>
                            <span class="mt-2 text-sm font-bold text-gray-900 dark:text-white">Pulang</span>
                            <span class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                @if($todayAttendance && $todayAttendance->clock_out_at)
                                    {{ substr($todayAttendance->clock_out_at, 0, 5) }}
                                @else
                                    -- : --
                                @endif
                            </span>
                        </button>
                    </form>
                </div>

                {{-- Action / Request Permission --}}
                <div class="mt-5 flex justify-center">
                    <button id="open-permission-modal"
                        class="inline-flex items-center gap-2 text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline dark:text-brand-400 dark:hover:text-brand-300">
                        <i class="bx bx-calendar-edit"></i>
                        Ajukan Izin / Sakit
                    </button>
                </div>
            </div>

            {{-- ATTENDANCE HISTORY LIST --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-bold text-gray-950 dark:text-white mb-4">Riwayat Kehadiran Terakhir</h3>
                <div class="flow-root">
                    <ul class="-my-4 divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($attendanceHistory as $hist)
                            <li class="py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @if($hist->status === 'H')
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                            <i class="bx bx-check"></i>
                                        </div>
                                    @elseif($hist->status === 'S')
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                                            <i class="bx bx-first-aid"></i>
                                        </div>
                                    @elseif($hist->status === 'I')
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                                            <i class="bx bx-envelope"></i>
                                        </div>
                                    @else
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                                            <i class="bx bx-x"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                                            {{ \Carbon\Carbon::parse($hist->attendance_date)->locale('id')->isoFormat('dddd, D MMMM') }}
                                        </p>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
                                            @if($hist->clock_in_at)
                                                Masuk: {{ substr($hist->clock_in_at, 0, 5) }} @if($hist->clock_out_at) · Pulang: {{ substr($hist->clock_out_at, 0, 5) }} @endif
                                            @else
                                                {{ $hist->status === 'H' ? 'Hadir (Manual)' : ($hist->status === 'S' ? 'Sakit' : ($hist->status === 'I' ? 'Izin' : 'Alpa')) }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <span class="text-xs font-bold 
                                    @if($hist->status === 'H') text-emerald-600 dark:text-emerald-400
                                    @elseif($hist->status === 'S') text-amber-600 dark:text-amber-400
                                    @elseif($hist->status === 'I') text-indigo-600 dark:text-indigo-400
                                    @else text-rose-600 dark:text-rose-400
                                    @endif">
                                    @if($hist->status === 'H') Hadir
                                    @elseif($hist->status === 'S') Sakit
                                    @elseif($hist->status === 'I') Izin
                                    @else Alpa
                                    @endif
                                </span>
                            </li>
                        @empty
                            <li class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">Belum ada riwayat kehadiran bulan ini.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

        </div>

        {{-- RIGHT COLUMN: WEEKLY SCHEDULES & GRADES --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- QUICK STATS ROW --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Kehadiran</span>
                    <h4 class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ $stats['rate'] }}%</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Total Hadir: {{ $stats['hadir'] }} hari</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Rata-Rata Nilai</span>
                    <h4 class="mt-2 text-2xl font-black text-gray-900 dark:text-white">85.4</h4>
                    <p class="text-[10px] text-emerald-600 mt-1 font-semibold flex items-center gap-0.5">
                        <i class="bx bx-trending-up"></i> +1.2 dari target
                    </p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Peringkat Kelas</span>
                    <h4 class="mt-2 text-2xl font-black text-gray-900 dark:text-white">3 <span class="text-sm font-normal text-gray-400">/ 32</span></h4>
                    <p class="text-[10px] text-gray-500 mt-1">Konsisten di Top 5</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Izin & Sakit</span>
                    <h4 class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ $stats['izin'] + $stats['sakit'] }}</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Izin: {{ $stats['izin'] }} · Sakit: {{ $stats['sakit'] }}</p>
                </div>
            </div>

            {{-- WEEKLY CLASS SCHEDULE --}}
            <div x-data="{ activeTab: 'Senin' }" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div>
                        <h3 class="text-base font-bold text-gray-950 dark:text-white">Jadwal Kelas Mingguan</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Mata pelajaran aktif hari Senin - Jumat</p>
                    </div>
                    
                    {{-- Tabs --}}
                    <div class="flex flex-wrap gap-1.5 rounded-xl bg-gray-50 p-1 dark:bg-gray-900">
                        @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $day)
                            <button @click="activeTab = '{{ $day }}'" 
                                :class="activeTab === '{{ $day }}' ? 'bg-white text-gray-950 font-bold shadow-xs dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200">
                                {{ $day }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Schedule List --}}
                <div class="relative pl-6 before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-[2px] before:bg-gray-100 dark:before:bg-gray-800">
                    
                    {{-- SENIN --}}
                    <div x-show="activeTab === 'Senin'" class="space-y-6">
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Upacara Bendera</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Lapangan Utama</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    07:00 - 07:45
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Matematika Wajib</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Budi Santoso, S.Pd. · Ruang 102</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    07:45 - 09:15
                                </span>
                            </div>
                        </div>
                        <div class="relative group opacity-60">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-gray-300 bg-white dark:bg-gray-950"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Istirahat Pagi</h4>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Kantin / Area Sekolah</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-500 dark:bg-gray-900 dark:text-gray-500">
                                    09:15 - 09:45
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">IPA Fisika</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Sri Wahyuni, S.Si. · Lab Fisika</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    09:45 - 11:15
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Bahasa Indonesia</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Ahmad Hidayat, S.Pd. · Ruang 102</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    11:15 - 12:45
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- SELASA --}}
                    <div x-show="activeTab === 'Selasa'" class="space-y-6">
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Pendidikan Jasmani (PJOK)</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Budi Santoso, S.Pd. · Lapangan Olahraga</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    07:00 - 08:30
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Bahasa Inggris</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Ahmad Hidayat, S.Pd. · Ruang 102</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    08:30 - 10:00
                                </span>
                            </div>
                        </div>
                        <div class="relative group opacity-60">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-gray-300 bg-white dark:bg-gray-950"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Istirahat Pagi</h4>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Kantin / Area Sekolah</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-500 dark:bg-gray-900 dark:text-gray-500">
                                    10:00 - 10:30
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">IPS Sejarah</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Sri Wahyuni, S.Si. · Ruang 102</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    10:30 - 12:00
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- RABU --}}
                    <div x-show="activeTab === 'Rabu'" class="space-y-6">
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Pendidikan Agama & Budi Pekerti</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Sri Wahyuni, S.Si. · Ruang Kelas</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    07:00 - 08:30
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Seni Budaya</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Ahmad Hidayat, S.Pd. · Aula Seni</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    08:30 - 10:00
                                </span>
                            </div>
                        </div>
                        <div class="relative group opacity-60">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-gray-300 bg-white dark:bg-gray-950"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Istirahat Pagi</h4>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Kantin / Area Sekolah</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-500 dark:bg-gray-900 dark:text-gray-500">
                                    10:00 - 10:30
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Kimia Terapan</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Sri Wahyuni, S.Si. · Lab Kimia</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    10:30 - 12:00
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- KAMIS --}}
                    <div x-show="activeTab === 'Kamis'" class="space-y-6">
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Pendidikan Pancasila & Kewarganegaraan</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Budi Santoso, S.Pd. · Ruang 102</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    07:00 - 08:30
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Matematika Wajib</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Budi Santoso, S.Pd. · Ruang 102</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    08:30 - 10:00
                                </span>
                            </div>
                        </div>
                        <div class="relative group opacity-60">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-gray-300 bg-white dark:bg-gray-950"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Istirahat Pagi</h4>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Kantin / Area Sekolah</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-500 dark:bg-gray-900 dark:text-gray-500">
                                    10:00 - 10:30
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Biologi Seluler</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Sri Wahyuni, S.Si. · Lab Biologi</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    10:30 - 12:00
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- JUMAT --}}
                    <div x-show="activeTab === 'Jumat'" class="space-y-6">
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Pramuka / Pembiasaan Diri</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Lapangan Sekolah</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    07:00 - 08:00
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Bahasa Inggris Praktis</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Ahmad Hidayat, S.Pd. · Ruang 102</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    08:00 - 09:30
                                </span>
                            </div>
                        </div>
                        <div class="relative group opacity-60">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-gray-300 bg-white dark:bg-gray-950"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Istirahat Pagi</h4>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Kantin / Area Sekolah</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-500 dark:bg-gray-900 dark:text-gray-500">
                                    09:30 - 10:00
                                </span>
                            </div>
                        </div>
                        <div class="relative group">
                            <span class="absolute -left-[20px] top-1.5 h-3 w-3 rounded-full border-2 border-brand-500 bg-white dark:bg-gray-950 group-hover:scale-120 transition-all duration-200"></span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pl-4">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Geografi & Kebumian</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Guru: Sri Wahyuni, S.Si. · Ruang 102</p>
                                </div>
                                <span class="inline-flex self-start sm:self-center items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    10:00 - 11:30
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- GRADES MONITOR & CHART --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div>
                        <h3 class="text-base font-bold text-gray-950 dark:text-white">Monitor Nilai Akademik</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Hasil penilaian komparatif semester ganjil</p>
                    </div>
                </div>

                {{-- Chart container --}}
                <div class="mb-6 rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-900/40">
                    <div id="grades-comparison-chart" class="min-h-[280px]"></div>
                </div>

                {{-- Grades Table --}}
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[640px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                <th class="pb-3 font-semibold">Mata Pelajaran</th>
                                <th class="pb-3 text-center font-semibold">Tugas Harian</th>
                                <th class="pb-3 text-center font-semibold">Nilai UTS</th>
                                <th class="pb-3 text-center font-semibold">Nilai UAS</th>
                                <th class="pb-3 text-center font-semibold">Nilai Akhir</th>
                                <th class="pb-3 text-center font-semibold">Grade</th>
                                <th class="pb-3 text-right font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @php
                                $grades = [
                                    ['subject' => 'Matematika Wajib', 'tugas' => 85, 'uts' => 80, 'uas' => 88, 'akhir' => 84.6, 'grade' => 'A', 'status' => 'Lulus'],
                                    ['subject' => 'IPA Fisika', 'tugas' => 90, 'uts' => 85, 'uas' => 82, 'akhir' => 85.1, 'grade' => 'A', 'status' => 'Lulus'],
                                    ['subject' => 'Kimia Terapan', 'tugas' => 78, 'uts' => 72, 'uas' => 75, 'akhir' => 74.8, 'grade' => 'B', 'status' => 'Lulus'],
                                    ['subject' => 'Bahasa Indonesia', 'tugas' => 95, 'uts' => 90, 'uas' => 92, 'akhir' => 92.1, 'grade' => 'A', 'status' => 'Lulus'],
                                    ['subject' => 'Bahasa Inggris', 'tugas' => 88, 'uts' => 85, 'uas' => 87, 'akhir' => 86.7, 'grade' => 'A', 'status' => 'Lulus'],
                                    ['subject' => 'PJOK', 'tugas' => 92, 'uts' => 95, 'uas' => 90, 'akhir' => 92.2, 'grade' => 'A', 'status' => 'Lulus'],
                                    ['subject' => 'IPS Sejarah', 'tugas' => 80, 'uts' => 75, 'uas' => 78, 'akhir' => 77.3, 'grade' => 'B', 'status' => 'Lulus'],
                                ];
                            @endphp
                            @foreach($grades as $g)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10 transition-colors">
                                    <td class="py-3 text-sm font-bold text-gray-850 dark:text-gray-200">{{ $g['subject'] }}</td>
                                    <td class="py-3 text-center text-sm font-semibold text-gray-600 dark:text-gray-400">{{ $g['tugas'] }}</td>
                                    <td class="py-3 text-center text-sm font-semibold text-gray-600 dark:text-gray-400">{{ $g['uts'] }}</td>
                                    <td class="py-3 text-center text-sm font-semibold text-gray-600 dark:text-gray-400">{{ $g['uas'] }}</td>
                                    <td class="py-3 text-center text-sm font-bold text-gray-900 dark:text-white">{{ $g['akhir'] }}</td>
                                    <td class="py-3 text-center text-sm">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-black
                                            {{ $g['grade'] === 'A' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400' }}">
                                            {{ $g['grade'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-right text-sm">
                                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                            {{ $g['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

    {{-- HTML5 DIALOG FOR PERMISSION REQUEST (ACCESSIBLE / LIGHT DISMISS STYLE) --}}
    <dialog id="permission-dialog" class="backdrop:bg-gray-950/40 backdrop:backdrop-blur-xs rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-950 max-w-md w-full focus:outline-hidden transition-all duration-300">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
            <h3 class="text-base font-bold text-gray-950 dark:text-white">Formulir Pengajuan Izin / Sakit</h3>
            <button id="close-permission-dialog" class="text-gray-400 hover:text-gray-700 dark:hover:text-white text-xl">
                <i class="bx bx-x"></i>
            </button>
        </div>

        <form action="{{ route('portal.siswa.permission') }}" method="POST" class="mt-4 space-y-4">
            @csrf
            <div>
                <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-400" for="request_type">
                    Jenis Pengajuan
                </label>
                <select id="request_type" name="request_type" required
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="Izin" class="dark:bg-gray-900">Izin (Hal Penting)</option>
                    <option value="Sakit" class="dark:bg-gray-900">Sakit (Kondisi Kesehatan)</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-400" for="start_date">
                        Mulai Tanggal
                    </label>
                    <input id="start_date" name="start_date" type="date" required value="{{ date('Y-m-d') }}"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-400" for="end_date">
                        Hingga Tanggal
                    </label>
                    <input id="end_date" name="end_date" type="date" required value="{{ date('Y-m-d') }}"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-400" for="reason">
                    Alasan / Keterangan
                </label>
                <textarea id="reason" name="reason" rows="3" required placeholder="Berikan alasan pengajuan..."
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-gradient-to-tr from-brand-500 to-indigo-600 px-4 text-sm font-bold text-white transition hover:from-brand-650 hover:to-indigo-700 shadow-md shadow-brand-500/10 active:scale-98">
                    Kirim Pengajuan
                </button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    {{-- ApexCharts Script --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Native Dialog triggers
            const dialog = document.getElementById('permission-dialog');
            const openBtn = document.getElementById('open-permission-modal');
            const closeBtn = document.getElementById('close-permission-dialog');

            if (openBtn && dialog) {
                openBtn.addEventListener('click', () => dialog.showModal());
            }
            if (closeBtn && dialog) {
                closeBtn.addEventListener('click', () => dialog.close());
            }

            // Close dialog when click outside (light-dismiss fallback)
            if (dialog) {
                dialog.addEventListener('click', (event) => {
                    const rect = dialog.getBoundingClientRect();
                    const isInDialog = (rect.top <= event.clientY && event.clientY <= rect.top + rect.height &&
                        rect.left <= event.clientX && event.clientX <= rect.left + rect.width);
                    if (!isInDialog) {
                        dialog.close();
                    }
                });
            }

            // ApexCharts setup
            const options = {
                series: [{
                    name: 'Nilai Akhir',
                    data: [84.6, 85.1, 74.8, 92.1, 86.7, 92.2, 77.3]
                }, {
                    name: 'Rata-rata Kelas',
                    data: [80.0, 78.5, 76.0, 85.0, 81.2, 88.0, 75.5]
                }],
                chart: {
                    type: 'bar',
                    height: 280,
                    toolbar: {
                        show: false
                    },
                    fontFamily: 'Inter, sans-serif'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 6,
                        endingShape: 'rounded'
                    },
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ['transparent']
                },
                xaxis: {
                    categories: ['MTK', 'Fisika', 'Kimia', 'B. Indo', 'B. Ingg', 'PJOK', 'Sejarah'],
                    labels: {
                        style: {
                            colors: '#9ca3af',
                            fontSize: '11px',
                            fontWeight: 500
                        }
                    }
                },
                yaxis: {
                    title: {
                        text: 'Skor Nilai',
                        style: {
                            color: '#9ca3af',
                            fontSize: '11px',
                            fontWeight: 500
                        }
                    },
                    min: 50,
                    max: 100,
                    labels: {
                        style: {
                            colors: '#9ca3af',
                            fontSize: '11px',
                            fontWeight: 500
                        }
                    }
                },
                colors: ['#4f46e5', '#38bdf8'],
                fill: {
                    opacity: 1
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + " / 100"
                        }
                    }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    fontSize: '12px',
                    fontWeight: 500,
                    labels: {
                        colors: '#9ca3af'
                    }
                },
                grid: {
                    borderColor: '#f1f5f9',
                    strokeDashArray: 4
                }
            };

            const chart = new ApexCharts(document.querySelector("#grades-comparison-chart"), options);
            chart.render();
        });
    </script>
@endpush
