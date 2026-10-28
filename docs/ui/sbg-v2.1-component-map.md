# SBG v2.1 implementation map

The v2.1 ZIP supplies the layout reference. The existing Laravel application remains authoritative for transactions, permissions and calculations.

| Responsibility | Implementation |
| --- | --- |
| Dashboard state, existing dialogs, progressive PO fetch | `resources/views/dashboard.blade.php` |
| Sidebar, top bar, mobile bottom navigation | `resources/views/components/sbg/app-navigation.blade.php` |
| Responsive KPI strip / mobile payment summary | `components/sbg/dashboard-summary.blade.php`, `components/sbg/kpi.blade.php` |
| Shared POST filters / mobile sheet | `components/sbg/po-filter.blade.php` |
| Paginated table / purpose-built cards / overflow actions | `components/sbg/po-list.blade.php`, `components/sbg/po-overflow.blade.php` |
| Compact two-column detail / mobile sheet | `components/sbg/po-detail.blade.php` |
| Icons, fruit thumbnails, status badges | `components/sbg/icon.blade.php`, `fruit-thumbnail.blade.php`, `status-badge.blade.php`, `public/images/sbg-fruits.svg` |
| Tokens, shell, responsive geometry and preserved login styling | `public/css/sbg-v2.css` |
| Dialog focus, focus return, scroll locking, payment review, amount sizing | `public/js/sbg-v2.js` |
| Isolated fixtures, totals, payment authorization, soft deletion | `tests/Feature/CompactPurchaseOrderWorkspaceTest.php` |
| Responsive screenshots and interaction regression | `tests/Browser/compact-po-workspace.mjs` |

Paths beginning `components/` are relative to `resources/views/`.

## Data contracts

`DashboardController::index()` still supplies `purchaseOrders`, `summary`, regions and account data. KPI fields are unchanged: `total_nilai_po`, `po_pending`, `po_selesai`, `total_laba_mitra`, `total_bayar_supplier`, `total_laba_ksppg`. Table and card views render the same paginated collection. Filters retain the existing POST/session contract and reset route.

The detail opens immediately from the canonical row, then refreshes through `dashboard.po.show`. A request counter prevents an older response replacing a newly selected PO. Financial mutations wait for a successful authoritative refresh; a failure has a retry action. No financial values are recomputed by the new components.

Payment forms retain `dashboard.po.toggle-payment` and the three existing `payment_type` values. Paid status remains reversible, as the existing backend allows; review explicitly precedes submission. Region authorization and `canManageFinancials()` remain unchanged. Edit and soft-delete endpoints are unchanged. The existing kitchen-note payload, QR, print and PDF functions remain in use.

Print/Download have moved from top KPI cards into the existing three payment dialogs, where selection and totals remain available. The original aggregate/page scoping of those dialogs is unchanged.

## Layout decisions

KPI cards are 124 px tall on desktop; six columns are used when readable, with three columns for compact widths or exceptionally long amounts. The amount sizing helper changes typography only, never financial data. Mobile uses three operational cards and stacked payment rows.

The common desktop detail uses a 780 px panel (820 px on large displays), with a maximum height of 640 px. The mobile sheet has a stable header and one content scroll area within 92dvh. Exceptional notes are disclosed separately and may scroll.

Mobile has home, orders and existing master-data views. Bottom navigation opens home/orders/calculator or the navigation drawer. Create PO remains available in the mobile header; analysis and authorized master data remain available through the drawer. No notification count, growth percentage, or unsupported bulk selection was invented.

Tiny fruit thumbnails use a local SVG sprite. They preserve fruit identity without downloading the large reference artwork; their vector style differs from the reference's rendered fruit photography.

## Reproduce verification

Run from `/var/www/sppg` with a compatible Node runtime and Playwright available externally (no application dependencies changed):

```bash
SBG_BROWSER_FIXTURES=/var/www/data/sbg-v21-qa/fixtures php artisan test --compact tests/Feature/CompactPurchaseOrderWorkspaceTest.php
SBG_BROWSER_EVIDENCE=/var/www/data/sbg-v21-qa SBG_PLAYWRIGHT_MODULE=/path/to/playwright node tests/Browser/compact-po-workspace.mjs
```

The browser uses an isolated local fixture server. It does not authenticate against or mutate production. Feature tests use in-memory SQLite per `phpunit.xml`.
