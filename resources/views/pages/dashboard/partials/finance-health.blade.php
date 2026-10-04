@php
    $healthTone = [
        'green' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-400',
        'yellow' => 'border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-900/60 dark:bg-yellow-500/10 dark:text-yellow-400',
        'orange' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/60 dark:bg-orange-500/10 dark:text-orange-400',
        'red' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-400',
    ];
@endphp

<section class="mb-6">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
        <div>
            <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Kesehatan Penagihan SPP</span>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Indikator ini dihitung dari tagihan dan pembayaran pada sekolah aktif.</p>
        </div>
        <span class="text-xs text-gray-500">Total tagihan: <strong class="text-gray-800 dark:text-white/90">Rp {{ number_format($financeHealth['totalBilled'], 0, ',', '.') }}</strong></span>
    </div>
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        @foreach (['collection' => 'Collection Rate', 'arrears' => 'Rasio Tunggakan'] as $key => $title)
            @php
                $metric = $financeHealth[$key];
            @endphp
            <article class="rounded-2xl border p-5 {{ $healthTone[$metric['color']] }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide opacity-80">{{ $title }}</p>
                        <p class="mt-2 text-3xl font-bold">{{ number_format($metric['percentage'], 1, ',', '.') }}%</p>
                    </div>
                    <span class="rounded-full bg-white/70 px-2.5 py-1 text-xs font-bold dark:bg-black/10">{{ $metric['label'] }}</span>
                </div>
                <p class="mt-4 text-sm font-semibold">{{ $metric['headline'] }}</p>
                <p class="mt-1 text-xs leading-5 opacity-90">{{ $metric['description'] }}</p>
            </article>
        @endforeach
        <article class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Siswa Menunggak</p>
            <p class="mt-2 text-3xl font-bold text-gray-800 dark:text-white/90">{{ number_format($financeHealth['delinquentStudents']['percentage'], 1, ',', '.') }}%</p>
            <p class="mt-3 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $financeHealth['delinquentStudents']['count'] }} dari {{ $financeHealth['delinquentStudents']['total'] }} siswa aktif</p>
            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Jumlah siswa dengan sisa tagihan dibanding jumlah siswa aktif di sekolah ini.</p>
        </article>
    </div>
</section>
