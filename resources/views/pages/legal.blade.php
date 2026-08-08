<!DOCTYPE html>
<html lang="id" class="scroll-smooth" x-data>
<head>
    @include('partials.public-head', [
        'title' => $title,
        'description' => $description,
    ])
</head>
<body class="antialiased bg-gray-50 dark:bg-gray-950">
    @include('partials.public-nav', ['activePage' => $activePage])

    <main class="pt-28 pb-20 px-6">
        <div class="max-w-5xl mx-auto">
            <div class="relative overflow-hidden rounded-[2rem] bg-brand-900 px-7 py-10 sm:px-12 sm:py-14 text-white mb-8">
                <div class="hero-orb hero-orb-1" style="opacity: .35;"></div>
                <div class="relative z-10 max-w-3xl">
                    <p class="text-[10px] font-bold uppercase tracking-[.2em] text-brand-300 mb-4">Dokumen Resmi EDUJA</p>
                    <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight leading-tight">{{ $title }}</h1>
                    <p class="mt-4 max-w-2xl text-sm sm:text-base leading-relaxed text-white/70">{{ $description }}</p>
                </div>
            </div>

            <article class="legal-prose glass-card rounded-[2rem] p-7 sm:p-12 bg-white/80 dark:bg-white/[.04]">
                {!! \Illuminate\Support\Str::markdown(file_get_contents(base_path('docs/legal/' . $document . '.md'))) !!}
            </article>
        </div>
    </main>

    @include('partials.public-footer')
    @include('partials.public-scripts')

    <style>
        .legal-prose { color: rgb(75 85 99); }
        .dark .legal-prose { color: rgb(156 163 175); }
        .legal-prose h1 { display: none; }
        .legal-prose h2 { margin: 2.25rem 0 .75rem; color: rgb(17 24 39); font-size: 1.2rem; line-height: 1.35; font-weight: 800; letter-spacing: -.01em; }
        .dark .legal-prose h2 { color: white; }
        .legal-prose h2:first-of-type { margin-top: 0; }
        .legal-prose p, .legal-prose li { font-size: .875rem; line-height: 1.8; }
        .legal-prose p { margin: .8rem 0; }
        .legal-prose ul, .legal-prose ol { margin: .75rem 0 1rem 1.25rem; padding-left: .75rem; }
        .legal-prose ul { list-style: disc; }
        .legal-prose ol { list-style: decimal; }
        .legal-prose li { padding-left: .25rem; margin: .25rem 0; }
        .legal-prose strong { color: rgb(31 41 55); font-weight: 700; }
        .dark .legal-prose strong { color: rgb(229 231 235); }
        .legal-prose a { color: rgb(13 148 136); text-decoration: underline; text-underline-offset: 3px; }
        .legal-prose blockquote { margin: 1.25rem 0; border-left: 3px solid rgb(20 184 166); border-radius: .75rem; background: rgb(20 184 166 / .08); padding: 1rem 1.25rem; }
        .legal-prose blockquote p { margin: 0; }
        .legal-prose hr { margin: 2rem 0; border-color: rgb(229 231 235); }
        .dark .legal-prose hr { border-color: rgb(255 255 255 / .1); }
    </style>
</body>
</html>
