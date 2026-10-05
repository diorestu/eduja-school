/**
 * Currency masking with thousands limiter '.' (Indonesian Rupiah style)
 */

export function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return '';
    const clean = String(value).replace(/\D/g, '');
    if (!clean) return '';
    return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

export function unmaskCurrency(value) {
    if (value === null || value === undefined || value === '') return '';
    return String(value).replace(/\D/g, '');
}

export function attachCurrencyMask(input) {
    if (!input || input._currencyMaskAttached) return;
    input._currencyMaskAttached = true;

    input.type = 'text';
    input.inputMode = 'numeric';
    input.autocomplete = 'off';

    const formatSelf = (e) => {
        const cursor = input.selectionStart || 0;
        const oldLen = input.value.length;
        const clean = unmaskCurrency(input.value);
        const formatted = formatCurrency(clean);

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
                const formatted = formatCurrency(unmaskCurrency(raw));
                input.value = formatted;
                const newPos = Math.max(0, pos - 2);
                input.setSelectionRange(newPos, newPos);
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    });

    if (input.value) {
        input.value = formatCurrency(input.value);
    }
}

export function initCurrencyMasks(root = document) {
    const inputs = root.querySelectorAll('input[data-mask="currency"], input.mask-currency, input[data-currency]');
    inputs.forEach(attachCurrencyMask);
}

// Global hook for Alpine and dynamic DOM
if (typeof window !== 'undefined') {
    window.formatCurrency = formatCurrency;
    window.unmaskCurrency = unmaskCurrency;
    window.initCurrencyMasks = initCurrencyMasks;
    window.attachCurrencyMask = attachCurrencyMask;

    document.addEventListener('DOMContentLoaded', () => {
        initCurrencyMasks();

        // Re-scan when new DOM elements or modals are opened
        const observer = new MutationObserver(() => {
            initCurrencyMasks();
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // Unmask inputs on form submit to ensure clean numeric transmission
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!form || !form.querySelectorAll) return;
            form.querySelectorAll('input[data-mask="currency"], input.mask-currency, input[data-currency]').forEach(input => {
                input.value = unmaskCurrency(input.value);
            });
        }, true);
    });
}
