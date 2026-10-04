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
