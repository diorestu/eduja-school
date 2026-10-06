@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Dashboard Ringkasan Operasional & Keuangan" />

    @php
        $isTreasurer = auth()->user()?->hasRole('bendahara');
    @endphp

    @if ($isTreasurer)
        <!-- 4-COLUMN STATISTIC COUNTER (TREASURER) -->
        @include('pages.dashboard.partials.financial-metrics')

        <!-- STATS KESEHATAN PENAGIHAN SPP (DI ATAS GRAFIK CHART) -->
        @include('pages.dashboard.partials.finance-health')

        <!-- 2-COLUMN CHART (TREASURER) -->
        <section class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] min-w-0 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3 pb-2 border-b border-gray-100 dark:border-gray-800/60">
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white/90">Pemasukan & Pengeluaran</h4>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tren komparasi arus kas masuk dan belanja operasional per bulan.</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-medium text-gray-600 dark:text-gray-300">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Pemasukan
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-medium text-gray-600 dark:text-gray-300">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Pengeluaran
                        </span>
                    </div>
                </div>
                <div class="w-full min-w-0 overflow-hidden">
                    <div id="treasurer-finance-chart" class="w-full"></div>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] min-w-0 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3 pb-2 border-b border-gray-100 dark:border-gray-800/60">
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white/90">Arus Kas Bersih (Net Cash Flow)</h4>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Surplus / defisit kas per bulan (Penerimaan dikurangi Pengeluaran).</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-medium text-gray-600 dark:text-gray-300">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#087f7a]"></span> Arus Kas Bersih
                        </span>
                    </div>
                </div>
                <div class="w-full min-w-0 overflow-hidden">
                    <div id="treasurer-netflow-chart" class="w-full"></div>
                </div>
            </div>
        </section>
    @else
        <!-- SECTION 1: OPERATIONAL METRICS -->
        <span class="block mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Ringkasan Operasional Sekolah</span>
        <div id="tour-operational-metrics" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6 mb-6">
            
            <!-- Metrics Siswa -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-center w-10 h-10 bg-blue-50 rounded-xl dark:bg-blue-500/10 mb-3 text-blue-600 dark:text-blue-500 text-lg">
                    <i class="bx bxs-graduation"></i>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Murid Aktif</span>
                <h4 class="mt-1.5 font-bold text-gray-800 text-xl dark:text-white/90">
                    {{ $totalStudents }} <span class="text-xs font-normal text-gray-400">siswa</span>
                </h4>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-500"><i class="bx bx-id-card"></i></div>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Tendik</span>
                <h4 class="mt-1.5 text-xl font-bold text-gray-800 dark:text-white/90">{{ $totalStaff }} <span class="text-xs font-normal text-gray-400">tendik</span></h4>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-500/10 dark:text-cyan-500"><i class="bx bx-git-branch"></i></div>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Jurusan</span>
                <h4 class="mt-1.5 text-xl font-bold text-gray-800 dark:text-white/90">{{ $departmentCount }} <span class="text-xs font-normal text-gray-400">jurusan</span></h4>
            </div>

            <!-- Metrics GTK -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-center w-10 h-10 bg-indigo-50 rounded-xl dark:bg-indigo-500/10 mb-3 text-indigo-600 dark:text-indigo-500 text-lg">
                    <i class="bx bxs-group"></i>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total GTK (Guru & Staf)</span>
                <h4 class="mt-1.5 font-bold text-gray-800 text-xl dark:text-white/90">
                    {{ $totalTeachers }} <span class="text-xs font-normal text-gray-400">pegawai</span>
                </h4>
            </div>

            <!-- Metrics Kelas -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-center w-10 h-10 bg-purple-50 rounded-xl dark:bg-purple-500/10 mb-3 text-purple-600 dark:text-purple-500 text-lg">
                    <i class="bx bxs-door-open"></i>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Jumlah Rombel/Kelas</span>
                <h4 class="mt-1.5 font-bold text-gray-800 text-xl dark:text-white/90">
                    {{ $totalClasses }} <span class="text-xs font-normal text-gray-400">kelas</span>
                </h4>
            </div>
        </div>

        <!-- SECTION 2: CHARTS (ATTENDANCE & FINANCE) -->
        <section class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] min-w-0 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3 pb-2 border-b border-gray-100 dark:border-gray-800/60">
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white/90">Kehadiran 6 Bulan Terakhir</h4>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Rekap hadir murid, guru, dan tendik.</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-medium text-gray-600 dark:text-gray-300">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#087f7a]"></span> Siswa
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-medium text-gray-600 dark:text-gray-300">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span> Guru
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-medium text-gray-600 dark:text-gray-300">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Tendik
                        </span>
                    </div>
                </div>
                <div class="w-full min-w-0 overflow-hidden">
                    <div id="principal-attendance-chart" class="w-full"></div>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] min-w-0 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3 pb-2 border-b border-gray-100 dark:border-gray-800/60">
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white/90">Pemasukan & Pengeluaran</h4>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Ringkasan keuangan sekolah per bulan.</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-medium text-gray-600 dark:text-gray-300">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Pemasukan
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-medium text-gray-600 dark:text-gray-300">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Pengeluaran
                        </span>
                    </div>
                </div>
                <div class="w-full min-w-0 overflow-hidden">
                    <div id="principal-finance-chart" class="w-full"></div>
                </div>
            </div>
        </section>

        <!-- SECTION 3: ANNOUNCEMENTS & CONTEXT -->
        <section class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="font-semibold text-gray-800 dark:text-white/90">Pengumuman Terbaru</h4>
                <div class="mt-4 space-y-3">
                    @forelse($latestAnnouncements as $announcement)
                        <div class="rounded-xl border border-gray-100 px-4 py-3 dark:border-gray-800">
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $announcement->title }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $announcement->published_at?->translatedFormat('d M Y') ?? 'Belum dipublikasikan' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada pengumuman.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="font-semibold text-gray-800 dark:text-white/90">Konteks Sekolah Aktif</h4>
                <p class="mt-2 text-sm leading-6 text-gray-500">Semua angka di dashboard ini mengikuti sekolah aktif yang dipilih setelah login. Gunakan dropdown sekolah di header untuk berpindah konteks.</p>
            </div>
        </section>

        <!-- SECTION 4: FINANCIAL METRICS (NON-TREASURER) -->
        @include('pages.dashboard.partials.financial-metrics')

        <!-- SECTION 5: KESEHATAN PENAGIHAN SPP (NON-TREASURER) -->
        @include('pages.dashboard.partials.finance-health')
    @endif

    <!-- Secondary section -->
    <div class="grid grid-cols-12 gap-4 md:gap-6">
        
        <!-- Left: Recent Payments Log -->
        <div class="col-span-12 xl:col-span-8">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between mb-5">
                    <h4 class="font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                        Buku Penerimaan SPP Terbaru
                    </h4>
                    <a href="{{ route('spp.laporan.index') }}" class="text-xs font-semibold text-brand-500 hover:text-brand-600">
                        Lihat Semua
                    </a>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[500px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/20">
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">No. Kuitansi</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Siswa</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tanggal Bayar</p>
                                    </th>
                                    <th class="px-5 py-3 text-left">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Jumlah Bayar</p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($recentTransactions as $tx)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                        <td class="px-5 py-4">
                                            <span class="font-mono text-theme-sm text-gray-800 dark:text-white/90">{{ $tx->receipt_number }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $tx->invoice->student->name }}</span>
                                            <span class="block text-xs text-gray-400">NIS: {{ $tx->invoice->student->nis }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                {{ $tx->payment_date->translatedFormat('d M Y') }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="text-green-600 font-semibold text-theme-sm dark:text-green-500">
                                                Rp {{ number_format($tx->amount_paid, 0, ',', '.') }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-gray-400 text-sm">
                                            Belum ada transaksi pembayaran hari ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Class Distribution & Gender Demographic -->
        <div class="col-span-12 xl:col-span-4 space-y-6">
            
            <!-- Student Distribution per Class -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-4 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Distribusi Rombel (Siswa/Kelas)
                </h4>

                <div class="space-y-4">
                    @foreach($classesWithCounts as $class)
                        <div>
                            <div class="flex items-center justify-between mb-1 text-xs">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $class->name }}</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $class->students_count }} siswa</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 dark:bg-gray-800">
                                @php
                                    $percentage = $totalStudents > 0 ? ($class->students_count / $totalStudents) * 100 : 0;
                                @endphp
                                <div class="bg-brand-500 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Gender Demographics -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h4 class="mb-4 font-semibold text-gray-800 text-theme-lg dark:text-white/90">
                    Demografis Gender Siswa
                </h4>

                <div class="flex items-center justify-around py-2">
                    <div class="text-center">
                        <span class="block text-3xl font-bold text-blue-600 dark:text-blue-500">{{ $genderL }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Laki-laki (L)</span>
                    </div>
                    <div class="w-px h-12 bg-gray-200 dark:bg-gray-800"></div>
                    <div class="text-center">
                        <span class="block text-3xl font-bold text-pink-500 dark:text-pink-400">{{ $genderP }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Perempuan (P)</span>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.ApexCharts) return;

            const attendance = @json($attendanceSeries);
            const finance = @json($financeSeries);

            const formatCompactRupiah = (val) => {
                if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
                const absVal = Math.abs(val);
                const sign = val < 0 ? '-' : '';
                if (absVal >= 1_000_000_000) {
                    return sign + 'Rp ' + (absVal / 1_000_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
                }
                if (absVal >= 1_000_000) {
                    return sign + 'Rp ' + (absVal / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'jt';
                }
                if (absVal >= 1_000) {
                    return sign + 'Rp ' + (absVal / 1_000).toFixed(0) + 'rb';
                }
                return sign + 'Rp ' + Math.round(absVal);
            };

            const baseLineOptions = {
                chart: {
                    type: 'line',
                    height: 270,
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    fontFamily: 'Outfit, sans-serif',
                    parentHeightOffset: 0
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    curve: 'straight',
                    width: 2.5
                },
                markers: {
                    size: 4,
                    strokeWidth: 2,
                    strokeColors: '#ffffff',
                    hover: { size: 6 }
                },
                grid: {
                    borderColor: 'rgba(148, 163, 184, 0.15)',
                    strokeDashArray: 3,
                    xaxis: { lines: { show: false } },
                    yaxis: { lines: { show: true } },
                    padding: { top: 12, right: 16, bottom: 4, left: 10 }
                },
                legend: {
                    show: false
                }
            };

            // Attendance Line Chart (Principal/Admin)
            const attendanceEl = document.querySelector('#principal-attendance-chart');
            if (attendanceEl && attendance?.months) {
                new ApexCharts(attendanceEl, {
                    ...baseLineOptions,
                    series: [
                        { name: 'Siswa', data: attendance.students || [] },
                        { name: 'Guru', data: attendance.teachers || [] },
                        { name: 'Tendik', data: attendance.staff || [] }
                    ],
                    xaxis: {
                        categories: attendance.months || [],
                        labels: {
                            rotate: 0,
                            rotateAlways: false,
                            hideOverlappingLabels: true,
                            trim: true,
                            style: { colors: '#94a3b8', fontSize: '11px', fontFamily: 'inherit' },
                            formatter: (val) => {
                                if (!val) return '';
                                const parts = String(val).trim().split(' ');
                                return parts.length === 2 ? parts[0] + " '" + parts[1].slice(-2) : val;
                            }
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        tooltip: { enabled: false }
                    },
                    yaxis: {
                        min: 0,
                        forceNiceScale: true,
                        labels: {
                            minWidth: 32,
                            maxWidth: 42,
                            formatter: val => Math.round(val),
                            style: { colors: '#94a3b8', fontSize: '11px', fontFamily: 'inherit' }
                        }
                    },
                    colors: ['#087f7a', '#6366f1', '#f59e0b'],
                    tooltip: {
                        shared: true,
                        intersect: false,
                        theme: 'light',
                        y: { formatter: val => (typeof val === 'number') ? val + ' kehadiran' : val }
                    }
                }).render();
            }

            // Finance Line Chart (Principal or Treasurer)
            const financeEl = document.querySelector('#principal-finance-chart') || document.querySelector('#treasurer-finance-chart');
            if (financeEl && finance?.months) {
                new ApexCharts(financeEl, {
                    ...baseLineOptions,
                    series: [
                        { name: 'Pemasukan', data: finance.income || [] },
                        { name: 'Pengeluaran', data: finance.expenses || [] }
                    ],
                    xaxis: {
                        categories: finance.months || [],
                        labels: {
                            rotate: 0,
                            rotateAlways: false,
                            hideOverlappingLabels: true,
                            trim: true,
                            style: { colors: '#94a3b8', fontSize: '11px', fontFamily: 'inherit' },
                            formatter: (val) => {
                                if (!val) return '';
                                const parts = String(val).trim().split(' ');
                                return parts.length === 2 ? parts[0] + " '" + parts[1].slice(-2) : val;
                            }
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        tooltip: { enabled: false }
                    },
                    yaxis: {
                        min: 0,
                        forceNiceScale: true,
                        labels: {
                            minWidth: 48,
                            maxWidth: 62,
                            formatter: formatCompactRupiah,
                            style: { colors: '#94a3b8', fontSize: '11px', fontFamily: 'inherit' }
                        }
                    },
                    colors: ['#10b981', '#f43f5e'],
                    tooltip: {
                        shared: true,
                        intersect: false,
                        theme: 'light',
                        y: {
                            formatter: value => (typeof value === 'number') ? 'Rp ' + new Intl.NumberFormat('id-ID').format(value) : value
                        }
                    }
                }).render();
            }

            // Treasurer Net Cash Flow Line Chart
            const netFlowEl = document.querySelector('#treasurer-netflow-chart');
            if (netFlowEl && finance?.months) {
                const netFlow = (finance.income || []).map((inc, i) => {
                    const exp = (finance.expenses && finance.expenses[i]) ? finance.expenses[i] : 0;
                    return inc - exp;
                });

                new ApexCharts(netFlowEl, {
                    ...baseLineOptions,
                    series: [
                        { name: 'Arus Kas Bersih', data: netFlow }
                    ],
                    xaxis: {
                        categories: finance.months || [],
                        labels: {
                            rotate: 0,
                            rotateAlways: false,
                            hideOverlappingLabels: true,
                            trim: true,
                            style: { colors: '#94a3b8', fontSize: '11px', fontFamily: 'inherit' },
                            formatter: (val) => {
                                if (!val) return '';
                                const parts = String(val).trim().split(' ');
                                return parts.length === 2 ? parts[0] + " '" + parts[1].slice(-2) : val;
                            }
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        tooltip: { enabled: false }
                    },
                    yaxis: {
                        forceNiceScale: true,
                        labels: {
                            minWidth: 48,
                            maxWidth: 62,
                            formatter: formatCompactRupiah,
                            style: { colors: '#94a3b8', fontSize: '11px', fontFamily: 'inherit' }
                        }
                    },
                    colors: ['#087f7a'],
                    tooltip: {
                        shared: true,
                        intersect: false,
                        theme: 'light',
                        y: {
                            formatter: value => (typeof value === 'number') ? 'Rp ' + new Intl.NumberFormat('id-ID').format(value) : value
                        }
                    }
                }).render();
            }
        });
    </script>

    <!-- Onboarding Tour Script -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Cek status penyelesaian tur di browser
            if (!localStorage.getItem("eduja_dashboard_tour_completed")) {
                setTimeout(() => {
                    const driver = window.driver?.js?.driver;
                    if (!driver) return;

                    const allSteps = [
                        { 
                            element: '#sidebar', 
                            popover: { 
                                title: 'Menu Navigasi Utama', 
                                description: 'Akses cepat ke modul Akademik/Kesiswaan, kasir SPP, pengelolaan RKAS BOS, Tabungan, dan Presensi Harian.', 
                                side: 'right', 
                                align: 'start' 
                            } 
                        },
                        { 
                            element: '#tour-operational-metrics', 
                            popover: { 
                                title: 'Metrik Operasional Sekolah', 
                                description: 'Pantau jumlah murid aktif, rombongan belajar/kelas, dan total guru/staf secara real-time.', 
                                side: 'bottom', 
                                align: 'center' 
                            } 
                        },
                        { 
                            element: '#tour-financial-metrics', 
                            popover: { 
                                title: 'Metrik Finansial Terpadu', 
                                description: 'Pantau total penerimaan SPP, realisasi belanja operasional, sisa tunggakan SPP murid, dan saldo kas Buku Kas Umum (BKU).', 
                                side: 'bottom', 
                                align: 'center' 
                            } 
                        },
                        { 
                            element: '#user-profile-menu', 
                            popover: { 
                                title: 'Menu Sesi & Profil', 
                                description: 'Ubah pengaturan profil Anda, ganti mode terang/gelap, atau keluar dari aplikasi (Sign Out).', 
                                side: 'left', 
                                align: 'center' 
                            } 
                        }
                    ];

                    const activeSteps = allSteps.filter(step => document.querySelector(step.element));
                    if (activeSteps.length === 0) return;

                    const driverObj = driver({
                        showProgress: true,
                        animate: true,
                        allowClose: true,
                        nextBtnText: 'Lanjut &rarr;',
                        prevBtnText: '&larr; Kembali',
                        doneBtnText: 'Selesai',
                        steps: activeSteps,
                        onDestroyed: () => {
                            localStorage.setItem("eduja_dashboard_tour_completed", "true");
                        }
                    });

                    driverObj.drive();
                }, 800); // Penundaan halus 800ms agar halaman terender sempurna terlebih dahulu
            }
        });
    </script>
@endsection
