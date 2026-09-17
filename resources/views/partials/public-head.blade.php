{{-- Shared Public Page Head Styles & Scripts --}}
{{-- Usage: @include('partials.public-head', ['title' => '...', 'description' => '...']) --}}
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="/favicon.png">
@php
    $seoTitle = $title ?? 'EDUJA — Platform Manajemen Sekolah Indonesia';
    $seoDescription = $description ?? 'EDUJA membantu sekolah mengelola akademik, keuangan, presensi, dan komunikasi dalam satu platform manajemen sekolah Indonesia.';
    $seoKeywords = $keywords ?? 'aplikasi manajemen sekolah, sistem informasi sekolah, aplikasi sekolah Indonesia, manajemen SPP sekolah, manajemen dana BOS, presensi digital siswa';
    $seoUrl = url()->current();
    $seoImage = asset('images/seo/eduja-og.svg');
@endphp
<title>{{ $seoTitle }}</title>
<link rel="canonical" href="{{ $seoUrl }}">

{{-- Meta SEO --}}
<meta name="description" content="{{ $seoDescription }}">
<meta name="keywords" content="{{ $seoKeywords }}">
<meta name="robots" content="index, follow, max-image-preview:large">
<meta name="author" content="EDUJA">

{{-- Open Graph --}}
<meta property="og:locale" content="id_ID">
<meta property="og:site_name" content="EDUJA">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:image:secure_url" content="{{ $seoImage }}">
<meta property="og:image:type" content="image/svg+xml">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="EDUJA — Platform Manajemen Sekolah Indonesia">
<meta property="og:type" content="website">

{{-- X / Twitter Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">
<meta name="twitter:image:alt" content="EDUJA — Platform Manajemen Sekolah Indonesia">

{{-- Structured data for brand/entity discovery --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => 'EDUJA',
            'url' => url('/'),
            'logo' => asset('images/logo/logo-wide.png'),
            'email' => 'info@eduja.id',
            'telephone' => '+628179792288',
            'description' => 'Platform manajemen sekolah Indonesia untuk akademik, keuangan, presensi, dan komunikasi.',
        ],
        [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'url' => url('/'),
            'name' => 'EDUJA',
            'publisher' => ['@id' => url('/').'#organization'],
            'inLanguage' => 'id-ID',
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>

{{-- Google Fonts & Icons --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400..700;1,14..32,400..500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

{{-- Tailwind v4 --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])

<script>
    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    const savedTheme = localStorage.getItem('theme');
    const theme = savedTheme || systemTheme;
    if (theme === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>

<style>
    /* Hallmark · macrostructure: Marquee Hero · tone: playful-premium · anchor hue: dark tosca · enrichment: dashboard SVG */
    :root {
        --brand-teal: #006266;
        --brand-teal-light: #009B9E;
        --accent-orange: #f39c12;
    }

    body {
        font-family: 'Inter', sans-serif;
        transition: background-color 0.4s ease, color 0.4s ease;
    }

    html.dark body {
        background-color: #070a0f;
        color: #f1f5f9;
    }
    html:not(.dark) body {
        background-color: #fafbfc;
        color: #1a1f2e;
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { border-radius: 3px; transition: background-color 0.3s; }
    html.dark ::-webkit-scrollbar-thumb { background: #1e293b; }
    html:not(.dark) ::-webkit-scrollbar-thumb { background: #cbd5e1; }
    ::-webkit-scrollbar-thumb:hover { background: var(--brand-teal); }

    /* Glassmorphism Nav */
    .glass-nav {
        backdrop-filter: blur(24px) saturate(180%);
        -webkit-backdrop-filter: blur(24px) saturate(180%);
        transition: background-color 0.4s ease, border-color 0.4s ease;
    }
    html.dark .glass-nav {
        background: rgba(7, 10, 15, 0.80);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    html:not(.dark) .glass-nav {
        background: rgba(250, 251, 252, 0.85);
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    /* Glassmorphism Card */
    .glass-card {
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        transition: all 0.45s cubic-bezier(0.16, 1, 0.3, 1);
    }
    html.dark .glass-card {
        background: rgba(255, 255, 255, 0.025);
        border: 1px solid rgba(255, 255, 255, 0.06);
    }
    html:not(.dark) .glass-card {
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid rgba(0, 0, 0, 0.07);
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.025);
    }
    html.dark .glass-card:hover {
        background: rgba(255, 255, 255, 0.045);
        border-color: rgba(0, 98, 102, 0.45);
        transform: translateY(-5px);
        box-shadow: 0 24px 50px -18px rgba(0, 98, 102, 0.2);
    }
    html:not(.dark) .glass-card:hover {
        background: rgba(255, 255, 255, 0.95);
        border-color: rgba(0, 98, 102, 0.28);
        transform: translateY(-5px);
        box-shadow: 0 24px 50px -14px rgba(0, 98, 102, 0.1);
    }

    /* Gradient Text */
    .gradient-text {
        background: linear-gradient(135deg, var(--brand-teal) 0%, var(--brand-teal-light) 60%, var(--accent-orange) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    html.dark .gradient-text-white {
        background: linear-gradient(135deg, #ffffff 20%, #94a3b8 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    html:not(.dark) .gradient-text-dark {
        background: linear-gradient(135deg, #0f172a 20%, #374151 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Section Divider */
    .section-divider {
        border-top: 1px solid;
    }
    html.dark .section-divider { border-color: rgba(255, 255, 255, 0.05); }
    html:not(.dark) .section-divider { border-color: rgba(0, 0, 0, 0.06); }

    /* Tag / Badge */
    .section-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 9999px;
        padding: 4px 14px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        border: 1px solid rgba(0, 98, 102, 0.25);
        background: rgba(0, 98, 102, 0.08);
        color: var(--brand-teal);
    }
    html.dark .section-tag {
        color: #4fd1d6;
        border-color: rgba(79, 209, 214, 0.25);
        background: rgba(79, 209, 214, 0.08);
    }

    /* Scroll-Driven Animations */
    @media (prefers-reduced-motion: no-preference) {
        @supports ((animation-timeline: view()) and (animation-range: entry)) {
            @keyframes fade-up {
                from { opacity: 0; transform: translateY(50px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .scroll-reveal {
                animation: fade-up auto linear both;
                animation-timeline: view();
                animation-range: entry 0% cover 35%;
            }
        }
    }
    /* Fallback */
    .reveal-fallback { opacity: 0; transform: translateY(40px); transition: opacity 0.7s ease, transform 0.7s cubic-bezier(0.16, 1, 0.3, 1); }
    .reveal-fallback.active { opacity: 1; transform: translateY(0); }

    /* Hero gradient background orb */
    .hero-orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(120px);
        pointer-events: none;
    }
    .hero-orb-1 {
        width: 600px; height: 600px;
        background: radial-gradient(circle, rgba(0,98,102,0.12) 0%, transparent 70%);
        top: -100px; left: 50%; transform: translateX(-50%);
    }
    html.dark .hero-orb-1 {
        background: radial-gradient(circle, rgba(0,155,158,0.15) 0%, transparent 70%);
    }
    .hero-orb-2 {
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(243,156,18,0.08) 0%, transparent 70%);
        bottom: 0; right: 0;
    }

    .campus-grid {
        background-image: linear-gradient(rgba(0, 98, 102, 0.055) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 98, 102, 0.055) 1px, transparent 1px);
        background-size: 28px 28px;
        mask-image: linear-gradient(to bottom, black, transparent 86%);
    }
    .float-card {
        animation: campus-float 6s ease-in-out infinite;
    }
    .float-card-delay { animation-delay: -2.2s; }
    .dashboard-illustration {
        filter: drop-shadow(0 24px 28px rgba(0, 47, 50, 0.14));
        transform: rotate(-1.5deg);
        transition: transform 400ms cubic-bezier(0.16, 1, 0.3, 1), filter 400ms cubic-bezier(0.16, 1, 0.3, 1);
    }
    .dashboard-illustration:hover { transform: rotate(0deg) translateY(-4px); filter: drop-shadow(0 30px 34px rgba(0, 47, 50, 0.2)); }
    .dashboard-illustration .chart-line { stroke-dasharray: 180; stroke-dashoffset: 180; animation: draw-chart 1.4s 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    @keyframes draw-chart { to { stroke-dashoffset: 0; } }
    @keyframes campus-float {
        0%, 100% { transform: translateY(0) rotate(-1deg); }
        50% { transform: translateY(-10px) rotate(1deg); }
    }
    @media (prefers-reduced-motion: reduce) {
        .float-card, .dashboard-illustration .chart-line { animation: none; }
        .dashboard-illustration .chart-line { stroke-dashoffset: 0; }
    }

    /* Stat counter pulse */
    @keyframes pulse-ring {
        0% { transform: scale(1); opacity: 0.4; }
        100% { transform: scale(1.5); opacity: 0; }
    }
    .stat-ring::before {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: inherit;
        background: rgba(0, 98, 102, 0.2);
        animation: pulse-ring 2s ease-out infinite;
    }

    /* Custom form focus */
    input:focus, textarea:focus, select:focus {
        outline: none;
        border-color: var(--brand-teal) !important;
        box-shadow: 0 0 0 3px rgba(0, 98, 102, 0.12);
    }

    /* Alpine theme store script (injected once globally) */
</style>
