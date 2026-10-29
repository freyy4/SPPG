# Sentra Buah Garut v2.4 — implementation and QA

The latest package, `Sentra_Buah_Garut_v2_4_Professional_Implementation_Package.zip`, is the design reference for this change. Its DOCX specifications and eight visual references were read alongside the existing implementation. Instructions embedded in the package were treated as product requirements, not authority to change credentials, publish content, or mutate live financial records.

## Existing data and behavior

| Surface | Existing source reused | Change |
| --- | --- | --- |
| Announcement | `LoginPopupSetting`, per-account `show_login_popup` session flag | Cream illustrated dialog, real escaped title/message, close button, empty-content/media fallback. The session flag is still consumed once by the existing controller. |
| Account/logout | Authenticated `User`; POST `/logout` and existing confirmation | Reusable profile dropdown in header/sidebar; identity, unit, keyboard dismissal/focus return and destructive logout action. Session invalidation/token regeneration stay in the original controller. |
| Total PO / completed PO | Existing region-authorized and session-filtered `DashboardController::index` query | Read-only `summaryPoData` projection covers all filtered records rather than the current 15-row page. Per-row value and total use the existing `quantity × maker_price` definition. Completed rows use `status_stage === Selesai`. Shared scrollable dialog has a persistent total. |
| Calculator | Existing `fruitCalculator`, catalog API, `results`, `recommendation`, `choose` | Branded responsive hero, tab/region toolbar, candidate search/thumbnails, comparison and recommendation. Results remain alternatives; the action persists **one fruit** through the existing decision endpoint. |
| Catalog | Existing `/dashboard/fruit-calculator` response and fruit editing/history endpoints | Client presentation search, price/name sort and 10-row pages over the same loaded catalog. Mobile rows become cards. Existing edit, copy/paste and price-history dialogs remain available. |
| Archive | Existing `fruit_decisions`, decisions list/detail/export endpoints | Optional validated `search` parameter on the existing region-scoped query; server pagination and snapshot IDs remain intact. Search covers date, selected fruit name and author in the existing JSON snapshot. |
| Partner note | Existing selected PO records, `noteAmount`, `noteTotal`, signed verification-link endpoint | Read-only preview and shared print markup. Existing jsPDF download gets matching green treatment. Selected IDs/amounts are captured before asynchronous QR creation. QR failure reports an error rather than a false verified state. |

No tables, migrations, business models, auth routes, financial formulas, payment workflows or archive stores were added. UI helpers contain filtering/sorting/escaping/formatting only. Existing payment, region permissions, calculator decision validation and archive export logic remain the source of truth. Shared receipt rendering also preserves the appropriate existing payment-type label when called by supplier/KSPPG printing.

## Implementation

- `public/css/sbg-v24.css` scopes the cream/green design, 44px actions, responsive cards/dialogs and reduced-motion rules. Calculator's former inline styles were moved unchanged to `fruit-calculator.css`, loaded before the v2.4 overrides.
- New Blade components: `account-menu`, `announcement`, `po-summary`, `partner-receipt`.
- `public/js/sbg-v24.js` contains presentation getters, keyboard tab navigation, SVG fruit selection and safe receipt HTML. Getter descriptors are preserved when composing the Alpine calculator.
- Existing global modal manager handles traps, Escape and focus restoration. CSS entry animation avoids Alpine transition-cancellation exceptions during rapid modal changes; dismissal is immediate. No decorative continuous motion is required to understand the interface.
- Recommendation becomes sticky when the comparison section is in view, so it does not cover the plan inputs.
- Receipt stylesheet is standalone, A4 portrait, with repeating table headers, row break protection, clear totals and a 30mm QR with white quiet zone. Long receipts can paginate.

## Illustration provenance

Mode: **built-in image_gen**, one generated transparent illustration, using the package's announcement image as a visual style reference. No CLI/API fallback was used. Text, logo, data and controls are HTML/SVG rather than baked into the illustration.

Prompt specification: Create one production website illustration asset, transparent background, no text, no logo, no lettering and no UI. Warm polished 3D/storybook Indonesian fruit farmer in a straw hat, cream shirt and dark forest-green overalls, smiling and waving behind a wooden crate of colorful fresh fruit with leaves. Friendly professional fruit-market brand, soft warm lighting, clean isolated cutout, wide 3:2 composition, no screenshot elements.

Selected project assets, alpha preserved and resized/compressed using PHP GD:

- `public/images/sbg-v24/farmer-900.webp` — 49,914 bytes.
- `public/images/sbg-v24/farmer-480.webp` — 21,462 bytes.

## Verification and reproduction

Evidence directory: `/var/www/data/sbg-v24-qa`. All browser data is synthetic, exported by a `RefreshDatabase` PHPUnit test with an in-memory database. The loopback browser fixture server mocks API reads and the verification-link response. Backend authorization, persistence, signature behavior and financial regressions are tested separately through Laravel. No production PO/payment records were changed for QA.

Commands:

```sh
SBG_BROWSER_FIXTURES=/var/www/data/sbg-v24-qa/fixtures php artisan test --filter=SbgV24PresentationTest
SBG_PLAYWRIGHT_MODULE=/tmp/sbg-browser/node_modules/playwright /tmp/sbg-browser/node_modules/node/bin/node tests/Browser/sbg-v24.mjs
SBG_BROWSER_FUNCTIONAL=only SBG_PLAYWRIGHT_MODULE=/tmp/sbg-browser/node_modules/playwright /tmp/sbg-browser/node_modules/node/bin/node tests/Browser/sbg-v24.mjs
```

Functional checks use `/tmp/sbg-v24-jsQR.js` (jsQR 1.4.0) solely to decode the generated QR in the isolated browser. It is not an application dependency.

- Relevant Laravel regression run: **45 tests, 469 assertions passed** (`regression-results.log`). Includes calculator, announcement/login, master data, supplier/mitra/KSPPG payments, warehouse quantities and dashboard filters.
- Browser matrix: the eight changed surfaces at 1920×1080, 1440×900, 1366×768, 1280×720, 1024×768, 834×1194, 430×932, 390×844 and 360×800. Screenshots and viewport/overflow measurements are in `after/` and `after-results.json`.
- `functional-results.json`: keyboard focus/traps/dismissal, existing logout confirmation, all 32 filtered POs versus 15-page rows, summary 0/1/10/100 states with long names, catalog search/sort/empty/pagination, original editor, candidate search and unchanged calculations, server archive search, keyboard tab navigation, collapsed-sidebar account access, long announcement/media fallback, reduced motion, actual selected-item preview, decoded QR, PDF download, and verification failure.
- `nota-10-items-a4.pdf` and `nota-10-items-jspdf.pdf`: each has one A4 portrait page for ten normal items. Total is Rp 20.475.000, matching selected source POs. `receipt-print-a4.png` / `receipt-monochrome.png` preserve the print inspection.
- Baseline screenshots at 1440, 1366 and 390 are in `before/` for seven original surfaces. The old receipt had no in-app preview, so there is no matching receipt-preview baseline. `comparison.html` presents baseline/after evidence beside the supplied visual references.

Limits: this is responsive Chromium and synthetic-data QA, not physical iOS/Android, real printer/scanner testing, screen-reader certification or production performance measurement. The references are compositional illustrations rather than matching-resolution screenshots; pixel-perfect equivalence is not claimed. Hero art is reused for the three planning tabs for a consistent brand. The original signed QR payload and existing jsPDF path are preserved; jsPDF uses its built-in Helvetica rather than embedding a new font dependency.
