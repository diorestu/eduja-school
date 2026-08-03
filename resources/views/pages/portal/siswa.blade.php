@extends('layouts.fullscreen-layout')

@section('content')
    <div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 flex flex-col font-sans antialiased text-zinc-900 dark:text-zinc-50 relative"
         x-data="{ 
            activeTab: 'home', 
            activeDay: 'Senin',
            currentTime: '',
            currentDate: '',
            init() {
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const updateTime = () => {
                    const now = new Date();
                    this.currentTime = now.toTimeString().split(' ')[0].substring(0, 5);
                    this.currentDate = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()];
                };
                updateTime();
                setInterval(updateTime, 1000);
            }
         }">

        {{-- 1. APP HEADER --}}
        <div class="border-b border-zinc-200 dark:border-zinc-800/80 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md sticky top-0 z-50">
            <div class="max-w-2xl mx-auto w-full px-5 py-4 flex items-center justify-between">
                <div class="flex items-center">
                    <template x-if="activeTab === 'home'">
                        <img src="/images/logo/logo-wide.png" alt="Eduja Logo" class="h-8 w-auto dark:brightness-0 dark:invert">
                    </template>
                    <template x-if="activeTab !== 'home'">
                        <div>
                            <span class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">Portal Siswa</span>
                            <h1 class="text-base font-black tracking-tight" x-text="activeTab === 'schedule' ? 'Jadwal Kelas' : (activeTab === 'grades' ? 'Laporan Rapor' : 'Pengajuan Izin')">Portal Siswa</h1>
                        </div>
                    </template>
                </div>
                <div class="flex items-center gap-2">
                    @if(auth()->user()->hasRole(['super_admin', 'kepsek', 'tu', 'staf_tu', 'bendahara']))
                        <a href="/dashboard" class="inline-flex items-center gap-1 rounded-lg border border-zinc-200 dark:border-zinc-800 px-2.5 py-1 text-[10px] font-bold text-zinc-650 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-850">
                            <i class="bx bx-left-arrow-alt text-xs"></i> Admin
                        </a>
                    @else
                        <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800 px-2.5 py-1 text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                            {{ $student->nis }}
                        </span>
                    @endif

                    {{-- Logout Button --}}
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-zinc-200 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-850 hover:text-zinc-800 dark:hover:text-zinc-200 transition" title="Keluar">
                            <i class="bx bx-log-out text-sm"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- 2. SCROLLABLE CONTAINER --}}
        <div class="flex-1 max-w-2xl mx-auto w-full px-5 py-6 pb-24 space-y-6">

            {{-- Session Flash Notifications (Placed below the header) --}}
            @if(session('success'))
                <div class="flex items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-xs font-medium text-emerald-600 dark:text-emerald-400 backdrop-blur-md transition-all duration-300">
                    <i class="bx bx-check-circle text-lg"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="flex items-center gap-3 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 text-xs font-medium text-rose-600 dark:text-rose-400 backdrop-blur-md transition-all duration-300">
                    <i class="bx bx-error-circle text-lg"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

                {{-- ================= TAB 1: HOME ================= --}}
                <div x-show="activeTab === 'home'" class="space-y-6">
                    
                    {{-- Student Simple Card --}}
                    <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30 flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900 font-black text-lg">
                            {{ strtoupper(substr($student->name, 0, 2)) }}
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-sm leading-tight text-zinc-900 dark:text-zinc-100">{{ $student->name }}</h3>
                            <p class="text-xs text-zinc-500 mt-0.5">{{ $className }} · {{ $classLocation }}</p>
                        </div>
                    </div>

                    {{-- Simple Attendance Panel --}}
                    <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-4">
                        <div class="flex justify-between items-center">
                            <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Presensi Mandiri</h4>
                            <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                <span class="mr-1 h-1 w-1 rounded-full bg-emerald-500 animate-pulse"></span>
                                GPS Aktif
                            </span>
                        </div>

                        {{-- Digital Clock --}}
                        <div class="text-center py-2">
                            <div class="text-3xl font-black tracking-tight" x-text="currentTime">00:00</div>
                            <div class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-0.5" x-text="currentDate">-</div>
                        </div>

                        {{-- Buttons --}}
                        <div class="grid grid-cols-2 gap-3">
                            <form action="{{ route('portal.siswa.attendance') }}" method="POST">
                                @csrf
                                <input type="hidden" name="type" value="masuk">
                                <button type="submit" 
                                    @if($todayAttendance && $todayAttendance->clock_in_at) disabled @endif
                                    class="w-full flex flex-col items-center justify-center rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 py-3 transition hover:bg-zinc-100/50 dark:bg-zinc-900/50 dark:hover:bg-zinc-800/50 disabled:opacity-40 disabled:pointer-events-none group">
                                    <i class="bx bxs-log-in text-lg text-zinc-700 dark:text-zinc-300"></i>
                                    <span class="mt-1 text-xs font-bold">Masuk</span>
                                    <span class="text-[9px] text-zinc-400 mt-0.5">
                                        {{ $todayAttendance && $todayAttendance->clock_in_at ? substr($todayAttendance->clock_in_at, 0, 5) : '--:--' }}
                                    </span>
                                </button>
                            </form>

                            <form action="{{ route('portal.siswa.attendance') }}" method="POST">
                                @csrf
                                <input type="hidden" name="type" value="pulang">
                                <button type="submit" 
                                    @if(!$todayAttendance || !$todayAttendance->clock_in_at || ($todayAttendance && $todayAttendance->clock_out_at)) disabled @endif
                                    class="w-full flex flex-col items-center justify-center rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 py-3 transition hover:bg-zinc-100/50 dark:bg-zinc-900/50 dark:hover:bg-zinc-800/50 disabled:opacity-40 disabled:pointer-events-none group">
                                    <i class="bx bxs-log-out text-lg text-zinc-700 dark:text-zinc-300"></i>
                                    <span class="mt-1 text-xs font-bold">Pulang</span>
                                    <span class="text-[9px] text-zinc-400 mt-0.5">
                                        {{ $todayAttendance && $todayAttendance->clock_out_at ? substr($todayAttendance->clock_out_at, 0, 5) : '--:--' }}
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Simple Stats Grid --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/30 dark:bg-zinc-900/20">
                            <span class="text-[9px] font-bold text-zinc-400 uppercase">Rata-rata Nilai</span>
                            <div class="text-xl font-black mt-1">85.4</div>
                        </div>
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/30 dark:bg-zinc-900/20">
                            <span class="text-[9px] font-bold text-zinc-400 uppercase">Kehadiran Kelas</span>
                            <div class="text-xl font-black mt-1">{{ $stats['rate'] }}%</div>
                        </div>
                    </div>

                    {{-- Wali Kelas Row --}}
                    <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex justify-between items-center">
                        <div>
                            <span class="text-[9px] text-zinc-400 font-bold uppercase tracking-wider block">Wali Kelas</span>
                            <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200">{{ $homeroomTeacher->name }}</span>
                        </div>
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $homeroomTeacher->phone ?? '081234567890') }}" target="_blank"
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-450 hover:bg-emerald-500/25 transition">
                            <i class="bx bxl-whatsapp text-lg"></i>
                        </a>
                    </div>

                    {{-- Recent Attendance History --}}
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Absensi Terakhir</h4>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800 border-t border-b border-zinc-100 dark:border-zinc-800">
                            @foreach($attendanceHistory as $hist)
                                <div class="py-2.5 flex justify-between items-center text-xs">
                                    <span class="font-medium text-zinc-600 dark:text-zinc-400">
                                        {{ \Carbon\Carbon::parse($hist->attendance_date)->locale('id')->isoFormat('dddd, D MMM') }}
                                    </span>
                                    <span class="font-bold 
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
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ================= TAB 2: SCHEDULE ================= --}}
                <div x-show="activeTab === 'schedule'" class="space-y-6">
                    
                    {{-- Day Switcher Horizontal --}}
                    <div class="flex justify-between gap-1 p-1 bg-zinc-100 dark:bg-zinc-800 rounded-xl">
                        @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $day)
                            <button @click="activeDay = '{{ $day }}'" 
                                :class="activeDay === '{{ $day }}' ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                                class="flex-1 text-center py-2 text-xs font-bold rounded-lg transition-all duration-200">
                                {{ substr($day, 0, 3) }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Day Schedule List --}}
                    <div class="space-y-4">
                        @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $day)
                            <div x-show="activeDay === '{{ $day }}'" class="space-y-3">
                                
                                @if($day === 'Senin')
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Upacara Bendera</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Lapangan Utama</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:00 - 07:45</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Matematika Wajib</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Budi Santoso, S.Pd. · Ruang 102</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:45 - 09:15</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30 flex justify-between items-start text-xs opacity-60">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Istirahat Pagi</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Kantin Sekolah</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">09:15 - 09:45</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">IPA Fisika</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Sri Wahyuni, S.Si. · Lab Fisika</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">09:45 - 11:15</span>
                                    </div>
                                @elseif($day === 'Selasa')
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Jasmani (PJOK)</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Budi Santoso, S.Pd. · Lapangan Olahraga</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:00 - 08:30</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Bahasa Inggris</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Ahmad Hidayat, S.Pd. · Ruang 102</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">08:30 - 10:00</span>
                                    </div>
                                @elseif($day === 'Rabu')
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Pendidikan Agama</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Sri Wahyuni, S.Si. · Ruang Kelas</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:00 - 08:30</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Seni Budaya</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Ahmad Hidayat, S.Pd. · Aula Seni</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">08:30 - 10:00</span>
                                    </div>
                                @elseif($day === 'Kamis')
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">PPKn</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Budi Santoso, S.Pd. · Ruang 102</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:00 - 08:30</span>
                                    </div>
                                @elseif($day === 'Jumat')
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Pramuka</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Lapangan Sekolah</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:00 - 08:00</span>
                                    </div>
                                @endif

                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ================= TAB 3: GRADES ================= --}}
                <div x-show="activeTab === 'grades'" class="space-y-6">
                    
                    {{-- Minimalist Chart Container --}}
                    <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
                        <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider mb-3">Grafik Nilai</h4>
                        <div id="grades-comparison-chart-mobile" class="min-h-[250px]"></div>
                    </div>

                    {{-- Grades List style --}}
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Rincian Mata Pelajaran</h4>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden bg-white dark:bg-zinc-900">
                            @php
                                $grades = [
                                    ['subject' => 'Matematika Wajib', 'akhir' => 84.6, 'grade' => 'A', 'status' => 'Lulus'],
                                    ['subject' => 'IPA Fisika', 'akhir' => 85.1, 'grade' => 'A', 'status' => 'Lulus'],
                                    ['subject' => 'Kimia Terapan', 'akhir' => 74.8, 'grade' => 'B', 'status' => 'Lulus'],
                                    ['subject' => 'Bahasa Indonesia', 'akhir' => 92.1, 'grade' => 'A', 'status' => 'Lulus'],
                                    ['subject' => 'Bahasa Inggris', 'akhir' => 86.7, 'grade' => 'A', 'status' => 'Lulus'],
                                    ['subject' => 'PJOK', 'akhir' => 92.2, 'grade' => 'A', 'status' => 'Lulus'],
                                    ['subject' => 'IPS Sejarah', 'akhir' => 77.3, 'grade' => 'B', 'status' => 'Lulus'],
                                ];
                            @endphp
                            @foreach($grades as $g)
                                <div class="p-4 flex justify-between items-center text-xs hover:bg-zinc-50/50 dark:hover:bg-zinc-850/50 transition">
                                    <div>
                                        <h5 class="font-bold text-zinc-800 dark:text-zinc-200">{{ $g['subject'] }}</h5>
                                        <span class="text-[10px] text-zinc-400 mt-0.5 block">Status: {{ $g['status'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-black text-sm">{{ $g['akhir'] }}</span>
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-black
                                            {{ $g['grade'] === 'A' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400' }}">
                                            {{ $g['grade'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ================= TAB 4: PERMISSION ================= --}}
                <div x-show="activeTab === 'permissions'" class="space-y-6">
                    
                    {{-- Minimal Form --}}
                    <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-4">
                        <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Form Pengajuan</h4>

                        <form action="{{ route('portal.siswa.permission') }}" method="POST" class="space-y-4 text-xs" enctype="multipart/form-data">
                            @csrf
                            <div>
                                <label class="mb-1.5 block font-bold text-zinc-500" for="request_type">
                                    Jenis Izin
                                </label>
                                <select id="request_type" name="request_type" required
                                    class="h-9 w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-1 text-xs text-zinc-800 focus:border-zinc-900 focus:outline-hidden dark:border-zinc-800 dark:bg-zinc-950 dark:text-white">
                                    <option value="Izin" class="dark:bg-zinc-950">Izin</option>
                                    <option value="Sakit" class="dark:bg-zinc-950">Sakit</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1.5 block font-bold text-zinc-500" for="start_date">
                                        Dari Tanggal
                                    </label>
                                    <input id="start_date" name="start_date" type="date" required value="{{ date('Y-m-d') }}"
                                        class="h-9 w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-1 text-xs text-zinc-800 focus:border-zinc-900 focus:outline-hidden dark:border-zinc-800 dark:bg-zinc-950 dark:text-white">
                                </div>
                                <div>
                                    <label class="mb-1.5 block font-bold text-zinc-500" for="end_date">
                                        Sampai Tanggal
                                    </label>
                                    <input id="end_date" name="end_date" type="date" required value="{{ date('Y-m-d') }}"
                                        class="h-9 w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-1 text-xs text-zinc-800 focus:border-zinc-900 focus:outline-hidden dark:border-zinc-800 dark:bg-zinc-950 dark:text-white">
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block font-bold text-zinc-500" for="reason">
                                    Alasan
                                </label>
                                <textarea id="reason" name="reason" rows="3" required placeholder="Berikan alasan pengajuan..."
                                    class="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-xs text-zinc-800 placeholder:text-zinc-400 focus:border-zinc-900 focus:outline-hidden dark:border-zinc-800 dark:bg-zinc-950 dark:text-white"></textarea>
                            </div>

                            {{-- Camera Attachment Input --}}
                            <div>
                                <label class="mb-1.5 block font-bold text-zinc-500" for="attachment">
                                    Foto Surat Keterangan / Surat Dokter (Kamera Langsung)
                                </label>
                                <input type="file" id="attachment" name="attachment" accept="image/*" capture="environment"
                                    class="w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-1.5 text-xs text-zinc-800 focus:border-zinc-900 focus:outline-hidden dark:border-zinc-800 dark:bg-zinc-950 dark:text-white file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:bg-zinc-900 file:text-white dark:file:bg-zinc-100 dark:file:text-zinc-900 cursor-pointer">
                                <span class="text-[10px] text-zinc-450 dark:text-zinc-500 mt-1 block font-medium">Hanya menerima foto langsung dari kamera (tidak bisa memilih file dari galeri).</span>
                            </div>

                            <button type="submit"
                                class="h-9 w-full rounded-lg bg-zinc-900 text-white font-bold hover:bg-zinc-800 transition active:scale-95 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200">
                                Kirim Pengajuan
                              </button>
                        </form>
                    </div>

                    {{-- Requests Status History --}}
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Status Terakhir</h4>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden bg-white dark:bg-zinc-900">
                            @forelse($permissionRequests as $req)
                                <div class="p-4 flex justify-between items-center text-xs">
                                    <div>
                                        <h5 class="font-bold text-zinc-850 dark:text-zinc-200">{{ $req->request_type }}</h5>
                                        <span class="text-[10px] text-zinc-400 block mt-0.5">{{ \Carbon\Carbon::parse($req->start_date)->locale('id')->isoFormat('D MMM') }}</span>
                                    </div>
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold
                                        @if($req->status === 'approved') bg-emerald-500/10 text-emerald-600
                                        @elseif($req->status === 'rejected') bg-rose-500/10 text-rose-600
                                        @else bg-amber-500/10 text-amber-600
                                        @endif">
                                        @if($req->status === 'approved') Disetujui
                                        @elseif($req->status === 'rejected') Ditolak
                                        @else Pending
                                        @endif
                                    </span>
                                </div>
                            @empty
                                <div class="p-6 text-center text-xs text-zinc-400 dark:text-zinc-500">Belum ada pengajuan izin.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

        </div>

        {{-- 3. APP STICKY BOTTOM NAVIGATION (PWA style fixed bottom navigation bar) --}}
        <div class="fixed bottom-0 left-0 right-0 border-t border-zinc-200 dark:border-zinc-800 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md z-50">
            <div class="max-w-2xl mx-auto w-full px-6 py-2.5 flex justify-between items-center">
                <button @click="activeTab = 'home'" 
                    :class="activeTab === 'home' ? 'text-zinc-950 dark:text-white' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-650'"
                    class="flex flex-col items-center justify-center flex-1 py-1 transition duration-150">
                    <i class="bx bx-home-alt text-xl"></i>
                    <span class="text-[9px] font-bold mt-1">Beranda</span>
                </button>
                <button @click="activeTab = 'schedule'" 
                    :class="activeTab === 'schedule' ? 'text-zinc-950 dark:text-white' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-650'"
                    class="flex flex-col items-center justify-center flex-1 py-1 transition duration-150">
                    <i class="bx bx-calendar text-xl"></i>
                    <span class="text-[9px] font-bold mt-1">Jadwal</span>
                </button>
                <button @click="activeTab = 'grades'" 
                    :class="activeTab === 'grades' ? 'text-zinc-950 dark:text-white' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-650'"
                    class="flex flex-col items-center justify-center flex-1 py-1 transition duration-150">
                    <i class="bx bx-star text-xl"></i>
                    <span class="text-[9px] font-bold mt-1">Nilai</span>
                </button>
                <button @click="activeTab = 'permissions'" 
                    :class="activeTab === 'permissions' ? 'text-zinc-950 dark:text-white' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-650'"
                    class="flex flex-col items-center justify-center flex-1 py-1 transition duration-150">
                    <i class="bx bx-receipt text-xl"></i>
                    <span class="text-[9px] font-bold mt-1">Izin</span>
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- ApexCharts Script --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
                    height: 250,
                    toolbar: {
                        show: false
                    },
                    fontFamily: 'Inter, sans-serif'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 4,
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
                            fontSize: '10px',
                            fontWeight: 500
                        }
                    }
                },
                yaxis: {
                    min: 50,
                    max: 100,
                    labels: {
                        style: {
                            colors: '#9ca3af',
                            fontSize: '10px',
                            fontWeight: 500
                        }
                    }
                },
                colors: ['#18181b', '#a1a1aa'], // Apple/Shadcn minimal color scheme (zinc-900, zinc-400)
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
                    fontSize: '10px',
                    fontWeight: 500,
                    labels: {
                        colors: '#9ca3af'
                    }
                },
                grid: {
                    borderColor: '#f4f4f5',
                    strokeDashArray: 4
                }
            };

            const chart = new ApexCharts(document.querySelector("#grades-comparison-chart-mobile"), options);
            chart.render();
        });
    </script>
@endpush
