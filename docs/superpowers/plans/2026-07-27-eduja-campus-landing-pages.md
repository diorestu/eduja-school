# Eduja Campus Public Website Implementation Plan

**Goal:** Redesign EDUJA's public marketing website for SMP and SMK audiences across Beranda, Layanan, Harga, and Kontak.

**Architecture:** Keep the existing Laravel Blade pages and public partials. Centralize shared visual tokens and navigation in the current public CSS/partials, then refine each page's content and section layout without adding a frontend framework or external runtime.

**Tech Stack:** Laravel Blade, Tailwind CSS v4, Vite, existing Boxicons and public assets.

## Global Constraints

- Position EDUJA for SMP and SMK first.
- Use the approved Eduja Campus direction: premium education with fun, friendly visual moments.
- Preserve existing routes: `/`, `/layanan`, `/pricing`, and `/contact`.
- Keep body typography on the existing Inter system and use medium-weight hierarchy rather than heavy default headings.
- Keep all page interactions accessible with keyboard focus states and responsive layouts.

### Task 1: Shared public shell

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/views/partials/public-nav.blade.php`
- Modify: `resources/views/partials/public-footer.blade.php`

- [ ] Refine public tokens, decorative shapes, card surfaces, buttons, and reveal motion in the existing stylesheet.
- [ ] Ensure the four public links share active state, mobile navigation, and consistent CTA treatment.
- [ ] Keep existing icon and asset dependencies intact.
- [ ] Verify `npm run build` succeeds.

### Task 2: Beranda

**Files:**
- Modify: `resources/views/pages/landing.blade.php`

- [ ] Replace generic SaaS copy with SMP/SMK-specific positioning.
- [ ] Build an asymmetrical hero with a dashboard preview card, school workflow chips, and approachable CTA hierarchy.
- [ ] Add service cards, role-based workflow, proof/statistics, testimonial, and final demo CTA sections.
- [ ] Keep sections responsive and preserve existing public partial includes.

### Task 3: Layanan and Harga

**Files:**
- Modify: `resources/views/pages/layanan.blade.php`
- Modify: `resources/views/pages/pricing.blade.php`

- [ ] Reframe services around academic, finance, attendance, student savings, and leadership needs for SMP/SMK.
- [ ] Use distinct but restrained visual accents per service card.
- [ ] Add pricing cards for Starter, Growth, and School Partner with a clear recommended plan and consultation CTA.
- [ ] Preserve anchors used by footer links and ensure pricing CTAs lead to `/contact`.

### Task 4: Kontak and verification

**Files:**
- Modify: `resources/views/pages/contact.blade.php`
- Test: public route smoke checks through Laravel route list and Vite build.

- [ ] Make the contact page a focused demo request flow with SMP/SMK selection, needs selection, contact details, WhatsApp/email alternatives, and FAQ.
- [ ] Verify all four routes render through `php artisan route:list` and compile with `npm run build`.
- [ ] Check Blade syntax and confirm no broken public links or missing asset references.
