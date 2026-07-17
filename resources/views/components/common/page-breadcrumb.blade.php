@props(['pageTitle' => 'Page', 'label' => 'Halaman'])

<div class="mb-4 flex flex-col gap-2 border-b border-gray-200 pb-3 dark:border-gray-800 md:flex-row md:items-end md:justify-between">
    <div>
        <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-500">
            {{ $label }}
        </p>
        <h1 class="mt-1 text-lg font-semibold leading-7 text-gray-900 dark:text-white/90">
            {{ $pageTitle }}
        </h1>
    </div>
    <nav aria-label="Breadcrumb">
        <ol class="flex items-center gap-1.5 text-xs">
            <li>
                <a class="text-gray-500 transition hover:text-brand-600 dark:text-gray-400" href="{{ url('/') }}">
                    Home
                </a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li class="font-medium text-gray-700 dark:text-gray-300">
                {{ $pageTitle }}
            </li>
        </ol>
    </nav>
</div>
