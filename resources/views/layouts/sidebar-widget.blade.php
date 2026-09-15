@php
    $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();
@endphp

<div class="mx-auto mb-2 w-full max-w-56 rounded-xl bg-gray-50 px-3 py-3 text-center dark:bg-white/[0.03] border border-gray-100 dark:border-gray-800/40">
    <div class="flex items-center justify-center w-8 h-8 mx-auto mb-2 bg-brand-50 rounded-lg dark:bg-brand-500/10 text-brand-600 dark:text-brand-500">
        <i class="bx bx-info-circle text-lg"></i>
    </div>
    
    <h3 class="mb-1 font-bold text-gray-900 text-xs dark:text-white">
        Eduja System v1.0
    </h3>
    
    <p class="mb-3.5 text-gray-400 text-[10px] leading-relaxed">
        Tahun Ajaran:<br>
        <span class="font-semibold text-gray-700 dark:text-gray-300">
            @if($activeYear)
                {{ $activeYear->year }} ({{ $activeYear->semester }})
            @else
                Belum Ditentukan
            @endif
        </span>
    </p>
    
    <a href="mailto:info@eduja.id?subject=Bantuan%20Sistem%20Eduja"
        class="flex items-center justify-center py-1.5 px-3 font-semibold text-white rounded-md bg-brand-500 hover:bg-brand-600 text-[11px] transition-colors gap-1">
        <i class="bx bx-envelope text-xs"></i>
        Hubungi Support
    </a>
</div>
