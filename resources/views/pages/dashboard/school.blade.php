@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Dashboard Ringkasan Operasional & Keuangan" />

    <!-- SECTION 1: OPERATIONAL METRICS -->
    <span class="block mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Ringkasan Operasional Sekolah</span>
    <div id="tour-operational-metrics" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6 mb-6">
        
        <!-- Metrics Siswa -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex items-center justify-center w-10 h-10 bg-blue-50 rounded-xl dark:bg-blue-500/10 mb-3 text-blue-600 dark:text-blue-500 text-lg">
                <i class="bx bxs-graduation"></i>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Siswa Aktif</span>
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

    <section class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><h4 class="font-semibold text-gray-800 dark:text-white/90">Kehadiran 6 Bulan Terakhir</h4><p class="mt-1 text-xs text-gray-500">Rekap hadir siswa, guru, dan tendik.</p><div id="principal-attendance-chart" class="mt-4 min-h-[280px]"></div></div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><h4 class="font-semibold text-gray-800 dark:text-white/90">Pemasukan & Pengeluaran</h4><p class="mt-1 text-xs text-gray-500">Ringkasan keuangan sekolah per bulan.</p><div id="principal-finance-chart" class="mt-4 min-h-[280px]"></div></div>
    </section>

    <section class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><h4 class="font-semibold text-gray-800 dark:text-white/90">Pengumuman Terbaru</h4><div class="mt-4 space-y-3">@forelse($latestAnnouncements as $announcement)<div class="rounded-xl border border-gray-100 px-4 py-3 dark:border-gray-800"><p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $announcement->title }}</p><p class="mt-1 text-xs text-gray-500">{{ $announcement->published_at?->translatedFormat('d M Y') ?? 'Belum dipublikasikan' }}</p></div>@empty<p class="text-sm text-gray-500">Belum ada pengumuman.</p>@endforelse</div></div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><h4 class="font-semibold text-gray-800 dark:text-white/90">Konteks Sekolah Aktif</h4><p class="mt-2 text-sm leading-6 text-gray-500">Semua angka di dashboard ini mengikuti sekolah aktif yang dipilih setelah login. Gunakan dropdown sekolah di header untuk berpindah konteks.</p></div>
    </section>

    <!-- SECTION 2: FINANCIAL METRICS -->
    <span class="block mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Ringkasan Finansial Sekolah (SPP & Operasional)</span>
    <div id="tour-financial-metrics" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6 mb-6">
        
        <!-- Metrics Pendapatan SPP -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex items-center justify-center w-10 h-10 bg-green-50 rounded-xl dark:bg-green-500/10 mb-3 text-green-600 dark:text-green-500 text-lg">
                <i class="bx bx-plus-circle"></i>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Penerimaan (SPP)</span>
            <h4 class="mt-1.5 font-bold text-green-600 text-lg dark:text-green-500">
                Rp {{ number_format($totalCollected, 0, ',', '.') }}
            </h4>
        </div>

        <!-- Metrics Pengeluaran Belanja -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex items-center justify-center w-10 h-10 bg-red-50 rounded-xl dark:bg-red-500/10 mb-3 text-red-500 text-lg">
                <i class="bx bx-minus-circle"></i>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Belanja Operasional</span>
            <h4 class="mt-1.5 font-bold text-red-500 text-lg dark:text-red-400">
                Rp {{ number_format($totalExpenses, 0, ',', '.') }}
            </h4>
        </div>

        <!-- Metrics Saldo Buku BKU -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex items-center justify-center w-10 h-10 bg-brand-50 rounded-xl dark:bg-brand-500/10 mb-3 text-brand-600 dark:text-brand-500 text-lg">
                <i class="bx bx-wallet"></i>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Saldo Kas Aktif (BKU)</span>
            <h4 class="mt-1.5 font-bold text-brand-600 text-lg dark:text-brand-500">
                Rp {{ number_format($netBalance, 0, ',', '.') }}
            </h4>
        </div>

        <!-- Metrics Tunggakan -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex items-center justify-center w-10 h-10 bg-yellow-50 rounded-xl dark:bg-yellow-500/10 mb-3 text-yellow-500 text-lg">
                <i class="bx bx-error-circle"></i>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Sisa Tunggakan SPP</span>
            <h4 class="mt-1.5 font-bold text-yellow-600 text-lg dark:text-yellow-500">
                Rp {{ number_format($totalOutstanding, 0, ',', '.') }}
            </h4>
        </div>
    </div>

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
        document.addEventListener('DOMContentLoaded', () => { const attendance = @json($attendanceSeries); const finance = @json($financeSeries); if (!window.ApexCharts) return;
            new ApexCharts(document.querySelector('#principal-attendance-chart'), { chart:{type:'line',height:280,toolbar:{show:false}}, series:[{name:'Siswa',data:attendance.students},{name:'Guru',data:attendance.teachers},{name:'Tendik',data:attendance.staff}], xaxis:{categories:attendance.months}, stroke:{curve:'smooth',width:3}, colors:['#087f7a','#6366f1','#f59e0b'], legend:{position:'top'}}).render();
            new ApexCharts(document.querySelector('#principal-finance-chart'), { chart:{type:'bar',height:280,toolbar:{show:false}}, series:[{name:'Pemasukan',data:finance.income},{name:'Pengeluaran',data:finance.expenses}], xaxis:{categories:finance.months}, colors:['#16a34a','#ef4444'], plotOptions:{bar:{borderRadius:5,columnWidth:'55%'}}, tooltip:{y:{formatter:value=>'Rp '+new Intl.NumberFormat('id-ID').format(value)}}}).render(); });
    </script>

    <!-- Onboarding Tour Script -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Cek status penyelesaian tur di browser
            if (!localStorage.getItem("eduja_dashboard_tour_completed")) {
                setTimeout(() => {
                    const driver = window.driver.js.driver;
                    const driverObj = driver({
                        showProgress: true,
                        animate: true,
                        allowClose: true,
                        nextBtnText: 'Lanjut &rarr;',
                        prevBtnText: '&larr; Kembali',
                        doneBtnText: 'Selesai',
                        steps: [
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
                                    description: 'Pantau jumlah siswa aktif, rombongan belajar/kelas, dan total guru/staf secara real-time.', 
                                    side: 'bottom', 
                                    align: 'center' 
                                } 
                            },
                            { 
                                element: '#tour-financial-metrics', 
                                popover: { 
                                    title: 'Metrik Finansial Terpadu', 
                                    description: 'Pantau total penerimaan SPP, realisasi belanja operasional, sisa tunggakan SPP siswa, dan saldo kas Buku Kas Umum (BKU).', 
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
                        ],
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
