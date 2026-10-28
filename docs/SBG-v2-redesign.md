# SBG v2 UI redesign

Reference: Sentra_Buah_Garut_PRD_v2_0_UIUX_Redesign_Package.zip, 28 October 2026.

Implemented shared palette, Plus Jakarta Sans, rounded surfaces, desktop sidebar, tablet/mobile navigation drawer, split login with local SVG orchard, responsive KPI layout, compact desktop filters, mobile filter sheet, canonical PO table/card presentation, and wide two-column PO detail (full screen on mobile). Calculator, catalog, archive and payment surfaces inherit the design system. Existing role checks, routes, serializers, pagination, exports, calculations and payment endpoints are preserved.

Keyboard handling includes modal focus containment, focus restoration, Escape close, visible focus and reduced motion. Single-PO payment status changes require review and disable repeat submission. Existing bulk-payment selection and confirmation remain intact.

## Validation

- Blade compilation passes.
- Financial/operational regression suite: 26 tests, 258 assertions pass (warehouse quantities, partner/supplier/KSPPG payments, calculator, date filtering and profit analysis).
- Full-suite baseline has 9 existing failures: missing Vite manifest in authentication pages and unavailable profile routes. Node 18 cannot build the installed Vite 8; Node 22 additionally exposes an absent Rolldown native binding. No dependencies or lockfiles were changed for this redesign.
- Browser screenshots use isolated SQLite test fixtures, not production records. Artifacts: `/var/www/data/sbg-qa`.

## Remaining PRD differences

The local orchard is a lightweight original vector illustration; it does not reproduce the reference artwork. Catalog and calculator keep their existing data interactions and product imagery has not been supplied. Dedicated new Master Data/Settings routes, URL-based filter migration and new payment idempotency infrastructure are outside this UI patch. The full core-screen visual and accessibility matrix still needs product review; screenshot capture alone is not acceptance.
