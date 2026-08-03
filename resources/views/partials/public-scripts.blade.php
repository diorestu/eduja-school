{{-- Alpine.js Theme Store - shared across all public pages --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('theme', {
            init() {
                const saved = localStorage.getItem('theme');
                this.theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                this.applyTheme();
            },
            theme: 'light',
            toggle() {
                this.theme = this.theme === 'light' ? 'dark' : 'light';
                localStorage.setItem('theme', this.theme);
                this.applyTheme();
            },
            applyTheme() {
                if (this.theme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        });
    });

    // Scroll-reveal fallback
    if (!CSS.supports('(animation-timeline: view()) and (animation-range: entry)')) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) entry.target.classList.add('active');
            });
        }, { threshold: 0.12 });

        document.querySelectorAll('.scroll-reveal').forEach(el => {
            el.classList.add('reveal-fallback');
            observer.observe(el);
        });
    }
</script>
