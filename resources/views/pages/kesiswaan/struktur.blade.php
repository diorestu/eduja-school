@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Struktur Organisasi Sekolah" />

    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03] overflow-x-auto">
        <!-- Main tree container centered horizontally -->
        <div class="min-w-[800px] flex flex-col items-center py-10">
            
            <!-- TOP LEVEL: Kepala Sekolah -->
            <div class="flex flex-col items-center">
                <div class="px-6 py-4 bg-brand-600 text-white rounded-2xl shadow-md w-64 text-center border border-brand-700 relative">
                    <!-- Icon Principal -->
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-brand-700 border-2 border-white dark:border-gray-900 text-white rounded-full w-8 h-8 flex items-center justify-center shadow-xs">
                        <i class="bx bxs-graduation text-base"></i>
                    </div>
                    <span class="block font-bold text-base mt-2">{{ $principal ? $principal->name : 'Dr. H. Ahmad Dahlan, M.Pd.' }}</span>
                    <span class="block text-[10px] uppercase tracking-wider text-brand-200 font-semibold mt-1">Kepala Sekolah</span>
                    <span class="block text-[9px] text-brand-100 font-mono mt-0.5">NIP. {{ $principal?->nip ?? '-' }}</span>
                </div>
                <!-- Connector Line Down from Principal -->
                <div class="w-0.5 h-10 bg-brand-300 dark:bg-brand-800"></div>
            </div>

            <!-- MIDDLE LEVEL: Bendahara & Tata Usaha -->
            <div class="relative w-full max-w-3xl mx-auto">
                <!-- Center vertical line running down the absolute middle of this section -->
                <div class="absolute top-0 bottom-0 left-1/2 -translate-x-1/2 w-0.5 bg-brand-300 dark:bg-brand-800"></div>
                
                <div class="grid grid-cols-2 gap-0 relative">
                    
                    <!-- Left Column: Bendahara -->
                    <div class="relative pt-6 px-6 flex flex-col items-center">
                        <!-- Horizontal line from center of Left column to its right edge -->
                        <div class="absolute top-0 right-0 left-1/2 h-0.5 bg-brand-300 dark:bg-brand-800"></div>
                        <!-- Vertical drop to card - Positioned absolutely to touch top-0 line and the card at pt-6 -->
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-0.5 h-6 bg-brand-300 dark:bg-brand-800"></div>
                        
                        <div class="px-5 py-3.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-white rounded-xl shadow-xs w-56 text-center border border-gray-200 dark:border-gray-700/80 z-10">
                            <span class="block font-semibold text-sm text-gray-900 dark:text-white">{{ $treasurer ? $treasurer->name : 'Siti Khadijah, S.E.' }}</span>
                            <span class="block text-[9px] uppercase tracking-wider text-gray-500 dark:text-gray-400 mt-1.5 font-bold">Bendahara Sekolah</span>
                            <span class="block text-[9px] text-gray-400 dark:text-gray-500 font-mono mt-0.5">NIP. {{ $treasurer?->nip ?? '-' }}</span>
                        </div>
                    </div>

                    <!-- Right Column: Staf TU -->
                    <div class="relative pt-6 px-6 flex flex-col items-center">
                        <!-- Horizontal line from left edge of Right column to its center -->
                        <div class="absolute top-0 left-0 right-1/2 h-0.5 bg-brand-300 dark:bg-brand-800"></div>
                        <!-- Vertical drop to card - Positioned absolutely to touch top-0 line and the card at pt-6 -->
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-0.5 h-6 bg-brand-300 dark:bg-brand-800"></div>
                        
                        <div class="px-5 py-3.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-white rounded-xl shadow-xs w-56 text-center border border-gray-200 dark:border-gray-700/80 z-10">
                            @if($staff->isNotEmpty())
                                <span class="block font-semibold text-sm text-gray-900 dark:text-white">{{ $staff->first()->name }}</span>
                                <span class="block text-[9px] text-gray-400 dark:text-gray-500 font-mono mt-0.5">NIP. {{ $staff->first()->nip ?? '-' }}</span>
                            @else
                                <span class="block font-semibold text-sm text-gray-900 dark:text-white">Rina Herawati, A.Md.</span>
                                <span class="block text-[9px] text-gray-400 dark:text-gray-500 font-mono mt-0.5">NIP. -</span>
                            @endif
                            <span class="block text-[9px] uppercase tracking-wider text-gray-500 dark:text-gray-400 mt-1.5 font-bold">Staf Tata Usaha</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Connector Down from Middle Section to Bottom Section -->
            <div class="w-0.5 h-10 bg-brand-300 dark:bg-brand-800"></div>

            <!-- BOTTOM LEVEL: Guru Pengajar -->
            <div class="relative w-full max-w-5xl mx-auto">
                <div class="grid grid-cols-3 gap-0 relative">
                    
                    @php $totalTeachers = $teachers->count(); @endphp
                    @forelse($teachers as $index => $teacher)
                        <div class="relative pt-6 px-4 flex flex-col items-center">
                            
                            <!-- Horizontal connectors for teachers row -->
                            @if($totalTeachers > 1)
                                @if($index === 0)
                                    <!-- First Teacher -->
                                    <div class="absolute top-0 right-0 left-1/2 h-0.5 bg-brand-300 dark:bg-brand-800"></div>
                                @elseif($index === $totalTeachers - 1)
                                    <!-- Last Teacher -->
                                    <div class="absolute top-0 left-0 right-1/2 h-0.5 bg-brand-300 dark:bg-brand-800"></div>
                                @else
                                    <!-- Middle Teacher -->
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-brand-300 dark:bg-brand-800"></div>
                                @endif
                            @endif
                            
                            <!-- Vertical drop to teacher card - Positioned absolutely to touch top-0 line and the card at pt-6 -->
                            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-0.5 h-6 bg-brand-300 dark:bg-brand-800"></div>
                            
                            <div class="px-4 py-4 bg-gray-50 dark:bg-gray-800/20 text-gray-800 dark:text-white rounded-xl shadow-xs w-60 text-center border border-gray-200 dark:border-gray-700/80 z-10">
                                <span class="block font-semibold text-sm text-brand-600 dark:text-brand-400">{{ $teacher->name }}</span>
                                <span class="block text-[9px] uppercase tracking-wider text-gray-500 dark:text-gray-400 mt-1 font-bold">Guru Pengajar</span>
                                <span class="block text-[9px] text-gray-400 dark:text-gray-500 font-mono mt-0.5">NIP. {{ $teacher->nip ?? '-' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-3 text-center text-gray-400 py-4">
                            Belum ada data guru pengajar yang terdaftar.
                        </div>
                    @endforelse
                    
                </div>
            </div>

        </div>
    </div>
@endsection
