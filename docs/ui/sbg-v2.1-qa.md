# SBG v2.1 completed QA checklist

Scope: compact dashboard, PO list/filter, desktop detail and mobile sheet. References: the desktop/mobile Visual North Star supplied in the v2.1 package. Evidence uses isolated in-memory SQLite fixtures, never production mutations.

## Measured acceptance

| Area | Result | Evidence |
| --- | --- | --- |
| Typography / palette | PASS — Plus Jakarta Sans actually loaded; v2.1 tokens used; core desktop detail text ≥12 px and mobile detail text ≥13 px | `after-results.json`, stylesheet |
| Shell / greeting | PASS — compact greeting, sidebar, search/account alignment; no page overflow | Dashboard screenshots |
| KPI strip | PASS — six equal 124 px cards; 12 px gaps and 16 px padding; readable unwrapped amounts; no Print/Download | Browser geometry assertions |
| KPI responsive states | PASS — six / three desktop columns as space and amount length permit; three operational mobile cards plus stacked balances | Dashboard screenshots |
| Filter | PASS — desktop 42 px controls; compact panel ≤112 px; mobile search remains inline and advanced fields use a sheet | Browser assertions, `filter-390.png` |
| Table / cards | PASS — 56 px desktop rows; mobile PO cards 108–132 px; overflow does not accidentally open a PO | Browser assertions and PO screenshots |
| Detail geometry | PASS — 780 px common desktop panel, 820 px on wide screens; no common-case internal scroll | Table below |
| Detail contents / actions | PASS — two columns, full warehouse/fee data, payment row and distinct edit/delete/kitchen-note row | `detail-1366.png` |
| Exceptional content | PASS — long supplier wraps; optional long notes can expand and scroll without page overflow | Interaction assertions |
| Keyboard / focus | PASS — focus enters/traps/returns; Tab wrapping and Escape checked; closed mobile navigation is inert | `behavior-results.json` |
| Payment / destructive safety | PASS — explicit review, accepted submit counted once, loading disables repeat submit; delete cancel leaves detail open | Browser checks plus backend permission tests |
| Print / Download relocation | PASS — all three existing payment dialogs expose their Print/Download modes; selection/export implementations retained | Interaction assertions |
| Mobile | PASS — purpose-built home/orders views, fixed bottom navigation, 92dvh sheet, stable sheet header and one scrolling body | Mobile screenshots |
| Touch / safe areas | PASS — new mobile shell/list controls ≥44 px; safe-area padding on bottom navigation, drawer and sheets | Browser dimensions and CSS |
| Motion / contrast | PASS — reduced motion removes transitions; checked body/action/status color pairs exceed AA 4.5:1 | `contrast-results.json`, browser assertion |
| Calculator / catalog / archive | PASS — existing views open without page overflow or JavaScript errors | `calculator-*-390.png`, regression tests |
| Financial / authorization regression | PASS — 29 tests, 300 assertions; includes warehouse quantities, totals, all payment types, region denial, create/edit and soft deletion | `regression-results.json` |
| Compile / syntax | PASS — Blade cache, JavaScript syntax and whitespace checks | Local checks |

## Mandatory viewport matrix

| Viewport | Modal width × height | Common desktop scroll | Page overflow | Font loaded |
| --- | --- | --- | --- | --- |
| 1920 × 1080 | 820 × 623 | No | No | Yes |
| 1440 × 900 | 780 × 623 | No | No | Yes |
| 1366 × 768 | 780 × 623 | No | No | Yes |
| 1280 × 720 | 780 × 623 | No | No | Yes |
| 1024 × 768 | 780 × 623 | No | No | Yes |
| 834 × 1194 | 770 × 623 | No | No | Yes |
| 768 × 1024 | 704 × 623 | No | No | Yes |
| 430 × 932 | 430 × 857 | N/A — mobile content scroll allowed | No | Yes |
| 390 × 844 | 390 × 776 | N/A — mobile content scroll allowed | No | Yes |
| 375 × 812 | 375 × 747 | N/A — mobile content scroll allowed | No | Yes |
| 360 × 800 | 360 × 736 | N/A — mobile content scroll allowed | No | Yes |

## Visual review and implementation notes

- Before/after comparison: `/var/www/data/sbg-v21-qa/comparison.html`.
- All viewport overview: `/var/www/data/sbg-v21-qa/gallery.html` and `gallery.png`.
- The desktop common detail changed from 900 px wide with internal scrolling to the compact, fully visible information/payment/management composition. Mobile now uses a bottom sheet instead of a desktop dialog stretched to the screen.
- Fruit thumbnails are small original vectors, rather than the reference's rendered photographic fruit. No unsupported notifications, growth percentages or bulk actions were added.
- KPI labels intentionally use up to two lines in narrower cards. Amounts remain on one line; unusually large values can trigger a three-column layout rather than overflow.
- Baseline and final screenshots use different fixture amounts to stress large-number readability. They are layout comparisons, not financial before/after evidence. Backend regression assertions verify financial behavior separately.
- Desktop no-scroll assertions apply to the common record. Expanded exceptional notes may scroll; mobile uses one scrollable content region by design.
- QA is browser based. Safe-area handling is implemented in CSS; physical device/browser chrome/virtual-keyboard variations were not certified. Existing print/PDF generation functions were preserved; browser coverage checks mode reachability, not an external WhatsApp delivery.
- No changes were made to `app/`, `database/` or `routes/`. No data model, formula or payment authorization was replaced.
- No P0/P1 blocker was observed in the scoped fixture and regression checks. Numeric geometry is measured; the supplied multi-screen artwork is a design reference rather than an identical-resolution pixel baseline.
