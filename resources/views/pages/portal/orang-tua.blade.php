@extends('layouts.fullscreen-layout')

@section('content')
    <div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 flex flex-col font-sans antialiased text-zinc-900 dark:text-zinc-50 relative"
         x-data="{ 
            activeTab: 'home', 
            activeDay: 'Senin',
            billingSubTab: 'invoices',
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
                            <span class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">Portal Orang Tua</span>
                            <h1 class="text-base font-black tracking-tight" x-text="activeTab === 'schedule' ? 'Jadwal Anak' : (activeTab === 'grades' ? 'Nilai Akademik' : (activeTab === 'billing' ? 'Keuangan & SPP' : 'Pengajuan Izin'))">Portal Orang Tua</h1>
                        </div>
                    </template>
                </div>
                
                {{-- Dynamic Child Switcher Select --}}
                <div class="flex items-center gap-2">
                    <select onchange="window.location.href='?student_id=' + this.value" 
                        class="h-8 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 px-2 text-[11px] font-bold text-zinc-700 dark:text-zinc-300 focus:outline-hidden cursor-pointer">
                        @foreach($students as $s)
                            <option value="{{ $s->id }}" {{ $s->id == $student->id ? 'selected' : '' }}>
                                {{ explode(' ', trim($s->name))[0] }} ({{ $s->nis }})
                            </option>
                        @endforeach
                    </select>

                    @if(auth()->user()->hasRole(['super_admin', 'kepsek', 'tu', 'staf_tu', 'bendahara']))
                        <a href="/dashboard" class="inline-flex h-8 items-center justify-center rounded-lg border border-zinc-200 dark:border-zinc-800 px-2.5 text-[11px] font-bold text-zinc-650 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-850">
                            Admin
                        </a>
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
                            <span class="text-[9px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest block">Memantau Anak</span>
                            <h3 class="font-bold text-sm leading-tight text-zinc-900 dark:text-zinc-100">{{ $student->name }}</h3>
                            <p class="text-xs text-zinc-500 mt-0.5">{{ $className }} · {{ $classLocation }}</p>
                        </div>
                    </div>

                    {{-- Simple Attendance Panel --}}
                    <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-4">
                        <div class="flex justify-between items-center">
                            <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Presensi Hari Ini</h4>
                            
                            @if($todayAttendance)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold 
                                    @if($todayAttendance->status === 'H') bg-emerald-500/10 text-emerald-600
                                    @elseif($todayAttendance->status === 'S') bg-amber-500/10 text-amber-600
                                    @elseif($todayAttendance->status === 'I') bg-indigo-500/10 text-indigo-600
                                    @else bg-rose-500/10 text-rose-600
                                    @endif">
                                    @if($todayAttendance->status === 'H') Hadir
                                    @elseif($todayAttendance->status === 'S') Sakit
                                    @elseif($todayAttendance->status === 'I') Izin
                                    @else Alpa
                                    @endif
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-850 px-2 py-0.5 text-[10px] font-bold text-zinc-500">
                                    Belum Presensi
                                </span>
                            @endif
                        </div>

                        {{-- Attendance Details --}}
                        <div class="text-center py-2">
                            @if($todayAttendance && $todayAttendance->clock_in_at)
                                <div class="text-3xl font-black tracking-tight">
                                    {{ substr($todayAttendance->clock_in_at, 0, 5) }}
                                </div>
                                <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-1">
                                    Jam masuk tercatat
                                    @if($todayAttendance->clock_out_at)
                                        · Pulang: {{ substr($todayAttendance->clock_out_at, 0, 5) }}
                                    @endif
                                </div>
                            @else
                                <div class="text-xl font-bold text-zinc-450 dark:text-zinc-500">-- : --</div>
                                <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-1">Absensi masuk belum terdaftar hari ini.</div>
                            @endif
                        </div>

                        {{-- Quick Stats --}}
                        <div class="grid grid-cols-2 gap-3 border-t border-zinc-100 dark:border-zinc-800 pt-4 text-center">
                            <div>
                                <span class="text-[9px] text-zinc-400 uppercase tracking-wider block font-bold">Rasio Hadir</span>
                                <h4 class="text-sm font-black mt-0.5 text-zinc-850 dark:text-zinc-250">{{ $stats['rate'] }}%</h4>
                            </div>
                            <div class="border-l border-zinc-100 dark:border-zinc-800">
                                <span class="text-[9px] text-zinc-400 uppercase tracking-wider block font-bold">Total Hadir</span>
                                <h4 class="text-sm font-black mt-0.5 text-zinc-850 dark:text-zinc-250">{{ $stats['hadir'] }} Hari</h4>
                            </div>
                        </div>
                    </div>

                    {{-- Wali Kelas Row --}}
                    <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex justify-between items-center">
                        <div>
                            <span class="text-[9px] text-zinc-400 font-bold uppercase tracking-wider block">Wali Kelas</span>
                            <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200">{{ $homeroomTeacher->name }}</span>
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 block mt-0.5">NIP: {{ $homeroomTeacher->nip ?? '-' }}</span>
                        </div>
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $homeroomTeacher->phone ?? '081234567890') }}" target="_blank"
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-450 hover:bg-emerald-500/25 transition">
                            <i class="bx bxl-whatsapp text-lg"></i>
                        </a>
                    </div>

                    {{-- Child Recent History --}}
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Absensi Terakhir Anak</h4>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden bg-white dark:bg-zinc-900">
                            @forelse($attendanceHistory as $hist)
                                <div class="p-3.5 flex justify-between items-center text-xs">
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
                            @empty
                                <div class="p-6 text-center text-xs text-zinc-400 dark:text-zinc-500">Belum ada riwayat kehadiran.</div>
                            @endforelse
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
                    <div class="space-y-3">
                        @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $day)
                            <div x-show="activeDay === '{{ $day }}'" class="space-y-3">
                                
                                @if($day === 'Senin')
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs bg-white dark:bg-zinc-900">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Upacara Bendera</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Lapangan Utama</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-650 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:00 - 07:45</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs bg-white dark:bg-zinc-900">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Matematika Wajib</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Budi Santoso, S.Pd. · Ruang 102</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-650 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:45 - 09:15</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30 flex justify-between items-start text-xs opacity-60">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Istirahat Pagi</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Kantin Sekolah</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-650 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">09:15 - 09:45</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs bg-white dark:bg-zinc-900">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">IPA Fisika</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Sri Wahyuni, S.Si. · Lab Fisika</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-650 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">09:45 - 11:15</span>
                                    </div>
                                @elseif($day === 'Selasa')
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs bg-white dark:bg-zinc-900">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Jasmani (PJOK)</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Budi Santoso, S.Pd. · Lapangan Olahraga</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-650 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:00 - 08:30</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs bg-white dark:bg-zinc-900">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Bahasa Inggris</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Ahmad Hidayat, S.Pd. · Ruang 102</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-650 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">08:30 - 10:00</span>
                                    </div>
                                @elseif($day === 'Rabu')
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs bg-white dark:bg-zinc-900">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Pendidikan Agama</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Sri Wahyuni, S.Si. · Ruang Kelas</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-650 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:00 - 08:30</span>
                                    </div>
                                @elseif($day === 'Kamis')
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs bg-white dark:bg-zinc-900">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">PPKn</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Budi Santoso, S.Pd. · Ruang 102</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-650 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:00 - 08:30</span>
                                    </div>
                                @elseif($day === 'Jumat')
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex justify-between items-start text-xs bg-white dark:bg-zinc-900">
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Pramuka</h4>
                                            <p class="text-zinc-400 dark:text-zinc-500 mt-1">Lapangan Sekolah</p>
                                        </div>
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-650 dark:text-zinc-300 font-bold px-2 py-0.5 rounded-md">07:00 - 08:00</span>
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
                        <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider mb-3">Grafik Nilai Anak</h4>
                        <div id="parent-grades-chart-mobile" class="min-h-[250px]"></div>
                    </div>

                    {{-- Grades List --}}
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Rincian Rapor Akademik</h4>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden bg-white dark:bg-zinc-900">
                            @php
                                $seed = $student->id;
                                $grades = [
                                    ['subject' => 'Matematika Wajib', 'tugas' => 75 + ($seed * 3) % 21, 'uts' => 70 + ($seed * 5) % 26, 'uas' => 75 + ($seed * 7) % 21],
                                    ['subject' => 'IPA Fisika', 'tugas' => 80 + ($seed * 4) % 16, 'uts' => 75 + ($seed * 6) % 21, 'uas' => 78 + ($seed * 8) % 18],
                                    ['subject' => 'Kimia Terapan', 'tugas' => 70 + ($seed * 2) % 26, 'uts' => 68 + ($seed * 3) % 28, 'uas' => 72 + ($seed * 4) % 24],
                                    ['subject' => 'Bahasa Indonesia', 'tugas' => 85 + ($seed * 5) % 11, 'uts' => 80 + ($seed * 7) % 16, 'uas' => 82 + ($seed * 9) % 14],
                                    ['subject' => 'Bahasa Inggris', 'tugas' => 78 + ($seed * 6) % 18, 'uts' => 75 + ($seed * 8) % 21, 'uas' => 80 + ($seed * 2) % 16],
                                    ['subject' => 'PJOK', 'tugas' => 80 + ($seed * 7) % 16, 'uts' => 85 + ($seed * 9) % 11, 'uas' => 82 + ($seed * 3) % 14],
                                    ['subject' => 'IPS Sejarah', 'tugas' => 72 + ($seed * 8) % 24, 'uts' => 70 + ($seed * 2) % 26, 'uas' => 74 + ($seed * 4) % 22],
                                ];
                                
                                foreach ($grades as &$g) {
                                    $g['akhir'] = round(($g['tugas'] * 0.3) + ($g['uts'] * 0.3) + ($g['uas'] * 0.4), 1);
                                    $g['grade'] = $g['akhir'] >= 85 ? 'A' : ($g['akhir'] >= 75 ? 'B' : ($g['akhir'] >= 65 ? 'C' : 'D'));
                                    $g['status'] = $g['akhir'] >= 70 ? 'Lulus' : 'Remedial';
                                }
                                unset($g);
                            @endphp
                            @foreach($grades as $g)
                                <div class="p-4 flex justify-between items-center text-xs hover:bg-zinc-50/50 dark:hover:bg-zinc-850/50 transition">
                                    <div>
                                        <h5 class="font-bold text-zinc-800 dark:text-zinc-200">{{ $g['subject'] }}</h5>
                                        <p class="text-[10px] text-zinc-400 mt-0.5">Tugas: {{ $g['tugas'] }} · UTS: {{ $g['uts'] }} · UAS: {{ $g['uas'] }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <span class="font-black text-sm block">{{ $g['akhir'] }}</span>
                                            <span class="text-[9px] font-semibold tracking-wide block {{ $g['status'] === 'Lulus' ? 'text-emerald-500' : 'text-rose-500' }}">{{ $g['status'] }}</span>
                                        </div>
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-black bg-zinc-100 dark:bg-zinc-800">
                                            {{ $g['grade'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ================= TAB 4: BILLING ================= --}}
                <div x-show="activeTab === 'billing'" class="space-y-6">
                    
                    {{-- Sub-Tab Menu: Tagihan vs Tabungan --}}
                    <div class="flex justify-between gap-1 p-1 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl">
                        <button @click="billingSubTab = 'invoices'" 
                            :class="billingSubTab === 'invoices' ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                            class="flex-1 text-center py-2 text-xs font-bold rounded-lg transition-all duration-200">
                            Tagihan SPP
                        </button>
                        <button @click="billingSubTab = 'savings'" 
                            :class="billingSubTab === 'savings' ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                            class="flex-1 text-center py-2 text-xs font-bold rounded-lg transition-all duration-200">
                            Tabungan Siswa
                        </button>
                    </div>

                    {{-- SUB-TAB CONTENT 1: INVOICES & PAYMENTS --}}
                    <div x-show="billingSubTab === 'invoices'" class="space-y-6">
                        {{-- Invoices List --}}
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Tagihan Aktif</h4>
                            <div class="space-y-3">
                                @forelse($invoices as $inv)
                                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-3 text-xs">
                                        <div class="flex justify-between items-center">
                                            <span class="font-bold text-zinc-400 dark:text-zinc-500">{{ $inv->invoice_number }}</span>
                                            <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold
                                                @if($inv->status === 'Lunas') bg-emerald-500/10 text-emerald-600
                                                @elseif($inv->status === 'Cicilan') bg-amber-500/10 text-amber-600
                                                @else bg-rose-500/10 text-rose-600
                                                @endif">
                                                {{ $inv->status }}
                                            </span>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">
                                                {{ $inv->invoiceItems->first()?->name ?? 'Iuran Komite/SPP' }}
                                            </h4>
                                            <p class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-1">
                                                Tempo: {{ $inv->due_date ? $inv->due_date->locale('id')->isoFormat('D MMM YYYY') : '-' }}
                                            </p>
                                        </div>
                                        <div class="flex justify-between items-center pt-2 border-t border-zinc-100 dark:border-zinc-800">
                                            <div>
                                                <span class="text-[9px] text-zinc-400 uppercase">Sisa Tagihan</span>
                                                <div class="font-bold text-zinc-850 dark:text-zinc-200">
                                                    Rp {{ number_format($inv->remaining_amount, 0, ',', '.') }}
                                                </div>
                                            </div>
                                            @if($inv->status !== 'Lunas')
                                                <button class="bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900 font-bold px-3.5 py-1.5 rounded-lg hover:opacity-90 transition active:scale-95 text-[11px]">
                                                    Bayar
                                                </button>
                                            @else
                                                <button class="border border-zinc-200 dark:border-zinc-800 font-bold px-3.5 py-1.5 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-850 transition active:scale-95 text-[11px]">
                                                    Cetak
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-6 text-center text-xs text-zinc-400 dark:text-zinc-500 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl">Tidak ada rincian tagihan untuk saat ini.</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- Kwitansi / Transaksi Pembayaran --}}
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Riwayat Pembayaran Kwitansi</h4>
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden bg-white dark:bg-zinc-900">
                                @php $hasPayments = false; @endphp
                                @foreach($invoices as $inv)
                                    @foreach($inv->transactions as $tx)
                                        @php $hasPayments = true; @endphp
                                        <div class="p-3.5 flex justify-between items-center text-xs">
                                            <div>
                                                <h5 class="font-bold text-zinc-850 dark:text-zinc-200">{{ $tx->receipt_number }}</h5>
                                                <p class="text-[10px] text-zinc-400">
                                                    {{ \Carbon\Carbon::parse($tx->payment_date)->locale('id')->isoFormat('D MMM YYYY') }} · {{ $tx->payment_method }}
                                                </p>
                                            </div>
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                                Rp {{ number_format($tx->amount_paid, 0, ',', '.') }}
                                            </span>
                                        </div>
                                    @endforeach
                                @endforeach
                                @if(!$hasPayments)
                                    <div class="p-6 text-center text-xs text-zinc-400 dark:text-zinc-500">Belum ada riwayat transaksi pembayaran.</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- SUB-TAB CONTENT 2: SAVINGS (TABUNGAN) --}}
                    <div x-show="billingSubTab === 'savings'" class="space-y-6">
                        {{-- Savings Balance Card --}}
                        <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-2">
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider block">Saldo Tabungan Aktif</span>
                            <div class="text-3xl font-black text-zinc-900 dark:text-white">
                                Rp {{ number_format($savingsBalance, 0, ',', '.') }}
                            </div>
                            <p class="text-[10px] text-zinc-450 dark:text-zinc-500 mt-1">Saldo tabungan anak dapat dicairkan atau ditambah melalui kasir sekolah.</p>
                        </div>

                        {{-- Savings Transaction History --}}
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Riwayat Mutasi Tabungan</h4>
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden bg-white dark:bg-zinc-900">
                                @forelse($savings as $saving)
                                    <div class="p-3.5 flex justify-between items-center text-xs">
                                        <div>
                                            <h5 class="font-bold text-zinc-850 dark:text-zinc-200">
                                                {{ $saving->type === 'credit' ? 'Setoran Tabungan' : 'Penarikan Tabungan' }}
                                            </h5>
                                            <p class="text-[10px] text-zinc-400">
                                                {{ \Carbon\Carbon::parse($saving->created_at)->locale('id')->isoFormat('D MMM YYYY, HH:mm') }} · {{ $saving->reference_number }}
                                            </p>
                                        </div>
                                        <span class="font-bold {{ $saving->type === 'credit' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-455' }}">
                                            {{ $saving->type === 'credit' ? '+' : '-' }} Rp {{ number_format($saving->amount, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @empty
                                    <div class="p-6 text-center text-xs text-zinc-400 dark:text-zinc-500">Belum ada riwayat transaksi tabungan anak.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ================= TAB 5: PERMISSION ================= --}}
                <div x-show="activeTab === 'permissions'" class="space-y-6">
                    
                    {{-- Form --}}
                    <div class="p-5 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-4">
                        <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Form Pengajuan Izin/Sakit</h4>

                        <form action="{{ route('portal.siswa.permission') }}" method="POST" class="space-y-4 text-xs" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="student_id" value="{{ $student->id }}">
                            
                            <div>
                                <label class="mb-1.5 block font-bold text-zinc-500" for="request_type">
                                    Jenis Izin
                                </label>
                                <select id="request_type" name="request_type" required
                                    class="h-9 w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-1 text-xs text-zinc-800 focus:border-zinc-900 focus:outline-hidden dark:border-zinc-800 dark:bg-zinc-950 dark:text-white">
                                    <option value="Izin" class="dark:bg-zinc-950">Izin (Hal Penting)</option>
                                    <option value="Sakit" class="dark:bg-zinc-950">Sakit (Kesehatan)</option>
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
                                    Keterangan / Alasan
                                </label>
                                <textarea id="reason" name="reason" rows="3" required placeholder="Jelaskan alasan detail ketidakhadiran anak..."
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
                                Kirim Surat Izin
                            </button>
                        </form>
                    </div>

                    {{-- Requests Status History --}}
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Status Pengajuan Terakhir</h4>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden bg-white dark:bg-zinc-900">
                            @forelse($permissionRequests as $req)
                                <div class="p-4 flex justify-between items-center text-xs">
                                    <div>
                                        <h5 class="font-bold text-zinc-850 dark:text-zinc-200">{{ $req->request_type }}</h5>
                                        <p class="text-[10px] text-zinc-400 block mt-0.5">
                                            {{ \Carbon\Carbon::parse($req->start_date)->locale('id')->isoFormat('D MMM') }}
                                            @if($req->end_date && $req->end_date != $req->start_date)
                                                - {{ \Carbon\Carbon::parse($req->end_date)->locale('id')->isoFormat('D MMM YYYY') }}
                                            @endif
                                        </p>
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
                                <div class="p-6 text-center text-xs text-zinc-400 dark:text-zinc-500">Belum ada riwayat pengajuan izin.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

        </div>

        {{-- 3. BOTTOM TAB BAR FOR PARENTS (PWA style fixed bottom navigation bar with 5 icons) --}}
        <div class="fixed bottom-0 left-0 right-0 border-t border-zinc-200 dark:border-zinc-800 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md z-50">
            <div class="max-w-2xl mx-auto w-full px-4 py-2.5 flex justify-between items-center">
                <button @click="activeTab = 'home'" 
                    :class="activeTab === 'home' ? 'text-zinc-950 dark:text-white' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-650'"
                    class="flex flex-col items-center justify-center flex-1 py-1 transition duration-150">
                    <i class="bx bx-home-alt text-lg"></i>
                    <span class="text-[8px] font-bold mt-1">Beranda</span>
                </button>
                <button @click="activeTab = 'schedule'" 
                    :class="activeTab === 'schedule' ? 'text-zinc-950 dark:text-white' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-650'"
                    class="flex flex-col items-center justify-center flex-1 py-1 transition duration-150">
                    <i class="bx bx-calendar text-lg"></i>
                    <span class="text-[8px] font-bold mt-1">Jadwal</span>
                </button>
                <button @click="activeTab = 'grades'" 
                    :class="activeTab === 'grades' ? 'text-zinc-950 dark:text-white' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-650'"
                    class="flex flex-col items-center justify-center flex-1 py-1 transition duration-150">
                    <i class="bx bx-star text-lg"></i>
                    <span class="text-[8px] font-bold mt-1">Nilai</span>
                </button>
                <button @click="activeTab = 'billing'" 
                    :class="activeTab === 'billing' ? 'text-zinc-950 dark:text-white' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-650'"
                    class="flex flex-col items-center justify-center flex-1 py-1 transition duration-150">
                    <i class="bx bx-wallet text-lg"></i>
                    <span class="text-[8px] font-bold mt-1">Tagihan</span>
                </button>
                <button @click="activeTab = 'permissions'" 
                    :class="activeTab === 'permissions' ? 'text-zinc-950 dark:text-white' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-650'"
                    class="flex flex-col items-center justify-center flex-1 py-1 transition duration-150">
                    <i class="bx bx-receipt text-lg"></i>
                    <span class="text-[8px] font-bold mt-1">Izin</span>
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
                    name: 'Nilai Akhir Anak',
                    data: @json(collect($grades)->pluck('akhir'))
                }, {
                    name: 'Rata-rata Kelas',
                    data: @json(collect($grades)->map(fn($g) => $g['akhir'] - 3.5 + (($student->id * 2) % 6)))
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
                colors: ['#18181b', '#a1a1aa'], // Apple/Shadcn minimal color scheme
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

            const chart = new ApexCharts(document.querySelector("#parent-grades-chart-mobile"), options);
            chart.render();
        });
    </script>
@endpush
