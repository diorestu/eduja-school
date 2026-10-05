<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/png" href="/favicon.png">


    <title>{{ $title ?? 'Dashboard' }} | EDUJA</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Boxicons CSS -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <!-- Driver.js (User Onboarding Tour) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css"/>
    <script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js"></script>

    <!-- Alpine.js -->
    {{-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <!-- Theme Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' :
                        'light';
                    this.theme = savedTheme || systemTheme;
                    this.updateTheme();
                },
                theme: 'light',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    if (this.theme === 'dark') {
                        html.classList.add('dark');
                        body?.classList.add('dark', 'bg-gray-900');
                    } else {
                        html.classList.remove('dark');
                        body?.classList.remove('dark', 'bg-gray-900');
                    }
                }
            });

            Alpine.store('sidebar', {
                // Initialize based on screen size
                isExpanded: window.innerWidth >= 1280, // true for desktop, false for mobile
                isMobileOpen: false,
                isHovered: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    // When toggling desktop sidebar, ensure mobile menu is closed
                    this.isMobileOpen = false;
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                    // Don't modify isExpanded when toggling mobile menu
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    // Only allow hover effects on desktop when sidebar is collapsed
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });

            if (typeof Alpine !== 'undefined' && !Alpine._moneyDirectiveRegistered) {
                Alpine._moneyDirectiveRegistered = true;
                Alpine.directive('money', (el, { expression }, { effect, evaluateLater }) => {
                    if (window.attachCurrencyMask) window.attachCurrencyMask(el);
                    if (expression) {
                        const getVal = evaluateLater(expression);
                        effect(() => {
                            getVal(val => {
                                if (val !== undefined && val !== null && window.formatCurrencyMask) {
                                    const formatted = window.formatCurrencyMask(val);
                                    if (el.value !== formatted) {
                                        el.value = formatted;
                                    }
                                }
                            });
                        });
                    }
                });
            }
        });

        // Global currency masking helper with thousands delimiter '.'
        function formatCurrencyMask(value) {
            if (value === null || value === undefined || value === '') return '';
            const clean = String(value).replace(/\D/g, '');
            if (!clean) return '';
            return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function unmaskCurrency(value) {
            if (value === null || value === undefined || value === '') return '';
            return String(value).replace(/\D/g, '');
        }

        function attachCurrencyMask(input) {
            if (!input || input._currencyMaskAttached) return;
            input._currencyMaskAttached = true;
            input.type = 'text';
            input.inputMode = 'numeric';
            input.autocomplete = 'off';

            const formatSelf = () => {
                const cursor = input.selectionStart || 0;
                const oldLen = input.value.length;
                const clean = unmaskCurrency(input.value);
                const formatted = formatCurrencyMask(clean);

                if (input.value !== formatted) {
                    input.value = formatted;
                    const newLen = formatted.length;
                    const newCursor = Math.max(0, cursor + (newLen - oldLen));
                    input.setSelectionRange(newCursor, newCursor);
                }
            };

            input.addEventListener('input', formatSelf);

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace') {
                    const pos = input.selectionStart;
                    if (pos > 0 && input.value[pos - 1] === '.') {
                        e.preventDefault();
                        const raw = input.value.slice(0, pos - 2) + input.value.slice(pos);
                        const formatted = formatCurrencyMask(unmaskCurrency(raw));
                        input.value = formatted;
                        const newPos = Math.max(0, pos - 2);
                        input.setSelectionRange(newPos, newPos);
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
            });

            if (input.value) {
                input.value = formatCurrencyMask(input.value);
            }
        }

        function initCurrencyMasks(root = document) {
            const inputs = root.querySelectorAll('input[data-mask="currency"], input.mask-currency, input[data-currency]');
            inputs.forEach(attachCurrencyMask);
        }

        window.formatCurrencyMask = formatCurrencyMask;
        window.formatCurrency = formatCurrencyMask;
        window.unmaskCurrency = unmaskCurrency;
        window.attachCurrencyMask = attachCurrencyMask;
        window.initCurrencyMasks = initCurrencyMasks;

        document.addEventListener('DOMContentLoaded', () => {
            initCurrencyMasks();

            const observer = new MutationObserver(() => {
                initCurrencyMasks();
            });
            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
            }

            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (!form || !form.querySelectorAll) return;
                form.querySelectorAll('input[data-mask="currency"], input.mask-currency, input[data-currency]').forEach(input => {
                    input.value = unmaskCurrency(input.value);
                });
            }, true);
        });
    </script>

    <!-- Apply dark mode immediately to prevent flash -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const theme = savedTheme || systemTheme;
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    
</head>

<body
    x-data="{ 'loaded': true}"
    x-init="$store.sidebar.isExpanded = window.innerWidth >= 1280;
    const checkMobile = () => {
        if (window.innerWidth < 1280) {
            $store.sidebar.setMobileOpen(false);
            $store.sidebar.isExpanded = false;
        } else {
            $store.sidebar.isMobileOpen = false;
            $store.sidebar.isExpanded = true;
        }
    };
    window.addEventListener('resize', checkMobile);">

    {{-- preloader --}}
    <x-common.preloader/>
    {{-- preloader end --}}

    <div class="min-h-screen xl:flex">
        @if(!request()->routeIs('yayasan.*'))
            @include('layouts.backdrop')
            @include('layouts.sidebar')
        @endif

        <div class="min-w-0 flex-1 transition-all duration-300 ease-in-out"
            :class="{
                'xl:ml-[290px]': !{{ request()->routeIs('yayasan.*') ? 'true' : 'false' }} && ($store.sidebar.isExpanded || $store.sidebar.isHovered),
                'xl:ml-[90px]': !{{ request()->routeIs('yayasan.*') ? 'true' : 'false' }} && !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
                'ml-0': $store.sidebar.isMobileOpen
            }">
            <!-- app header start -->
            @include('layouts.app-header')
            <!-- app header end -->
            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                @yield('content')
            </div>
        </div>

    </div>

</body>

@stack('scripts')

</html>
