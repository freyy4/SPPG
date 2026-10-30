# SBG v2.5 — unified Add/Edit purchase orders

## Scope

Stage-only implementation. No production writes, deployment, commits, restarts, browser worker, or full-suite execution in this phase.

Both entry points render `components/sbg/po-form-modal.blade.php`, its four section partials, and `po-field.blade.php`. The old inline Edit form is removed. Dashboard opens Edit with a fresh authorized GET of the canonical PO, not a stale table-row copy. Loading/error states disable mutation. Out-of-order responses cannot repopulate a closed or subsequently opened dialog.

Dashboard includes `components.sbg.theme-head` after its existing stylesheets, then the dedicated PO stylesheet/script. New colors consume semantic tokens supplied by the experience agent. Existing `public/js/sbg-v2.js` is unchanged and retains focus containment, body locking, and focus restoration. The server-rendered legacy `data-sbg-create-form` marker remains compatible with presentation tests; Alpine initialization removes it **before DOMContentLoaded** so the old create-only validator does not add duplicate error IDs/listeners. Browser automation should use `data-po-form` instead.

## Source-of-truth preservation

- `PurchaseOrder` model/accessors, all financial calculations, other Dashboard methods, routes, and original RBAC remain unchanged by this work.
- All persisted original fields remain: date, category, priority, responsible admin, item, supplier, SBG/B quantities, unit, independent supplier prices, maker price, partner margin, status, notes.
- Category, priority and notes really exist in the baseline model and are validated/persisted. No new schema or pretend fields were introduced.
- Real statuses are **Cari Supplier, Sortir, Pengiriman, Selesai, Retur, Kekurangan, Perubahan Item**. No invented Draft status or transition policy.
- Regular admins do not see supplier/responsible-admin controls. Server authorization still rejects cross-region reads/updates and ignores forged supplier mutations.
- Maker price already includes the margin in this application. The displayed price is therefore maker price, **not maker + margin** from the illustration. Live warehouse/PO values are the original maker-price multiplication, shared between Add/Edit. Server-owned supplier/partner/KSPPG fee formulas are not reimplemented in JS.
- Original four-digit price-enablement gates remain. Invalid/missing fields are not silently supplied with invented values.
- Fixed fixture: quantities 10.5 + 2.25, supplier SBG 6,000, supplier B 7,500.50, maker 10,000, partner margin 1,000 => quantity **12.75**, partner fee **33,000**, supplier amount **63,000**, KSPPG fee **31,500**, maker PO value **127,500**. Asserted for actual create and every original status update.

## Submission, validation and concurrency

Create retains the native POST/redirect/success-toast contract. Edit sends FormData POST with `_method=PUT`, CSRF, actual PO ID and optional `updated_at` to the original update endpoint. JSON success reloads the dashboard with the existing flashed success message. Legacy non-JSON updates keep redirects and do not require a version token.

The update method validates the optional date token, re-reads the row under a transaction/row lock, and rejects mismatched timestamps with HTTP 409. Comparison happens before saving. Whole-second schema timestamps are advanced monotonically on this endpoint (including legacy updates), preventing two same-second edits or a legacy update from resurrecting a consumed token. Under rapid bursts, `updated_at` can be a few seconds ahead of wall time; no monetary/status formula changes. Other protected mutation endpoints were not modified; this is not a new system-wide version column. Multiprocess locking on the production database has not been exercised here; SQLite tests prove sequential conflict/readback behavior, not cross-process MySQL contention.

422, 409, malformed/non-JSON responses and network failures leave the draft intact and re-enable the form. Aggregate `quantity` errors map to the SBG quantity field and an accessible summary. Native legacy validation redirects reopen the appropriate dialog with old inputs/errors. Price input retains decimal commas (`7500,50` -> `7.500,50`), fixing the old formatter's dropped-comma regression without editing global JS. ISO dates, decimal values and legacy null warehouse-B price/quantity fallbacks are preserved.

All modal close buttons, Cancel, desktop backdrop and Escape use `requestClose()`: native confirmation on dirty drafts, rejection preserves input, and saving blocks close/double submission. Native confirmation keeps focus with the invoking control; shared dialog infrastructure handles the surrounding focus trap. Edit explicitly captures the same PO's visible row-menu trigger and restores it on the next Alpine tick, instead of returning focus to a now-hidden menu/detail button. Full browser behavior remains a parent QA gate.

## Exact browser selectors

| Target | Selector |
|---|---|
| Create opener, mobile | `[aria-label="Buat PO Baru"]` |
| Row menu | `.sbg-po-overflow > button[aria-label^="Aksi PO "]` |
| Edit action | visible `.sbg-po-menu button` with text `Edit PO` (or detail footer button with that text) |
| Modal roots | `[data-po-mode="create"]`, `[data-po-mode="edit"]` |
| Panel/form | `[data-po-mode="edit"] .sbg-po25-panel`, `[data-po-mode="edit"] [data-po-form]` |
| Field | `#po25-edit-item_name` (replace field/mode as needed) |
| All field IDs | `po25-{create|edit}-{transaction_date|category|priority|created_by|item_name|supplier_name|quantity_sbg|quantity_b|unit|supplier_price|supplier_price_b|maker_price|partner_margin|status_stage|notes}` |
| Sections | `[data-po-mode="edit"] [data-po-section="transaction"]` (also product/pricing/status) |
| Scroller/footer | `.sbg-po25-body`, `.sbg-po25-footer` within the visible root |
| Dirty indicator | visible root's `data-po-dirty="true"` |
| Close/cancel/save | `[data-dialog-close]`, `.sbg-po25-cancel`, `[data-po-submit]` within root |
| Errors | `[data-po-message]`, `[aria-invalid="true"]`, `#po25-edit-item_name-error` |
| Live values | `[data-po-warehouse-value="sbg"]`, `[data-po-warehouse-value="b"]`, `[data-po-estimate]`, `[data-po-total]` |

## Parent real-user QA steps (not executed in this phase)

Use synthetic accounts/orders on the isolated fixture only, never the restored production account. Request-local fixtures reset between HTTP requests: do not claim persistence from them; PHPUnit below performs actual mutation/readback in one process. Parent may use a persistent isolated synthetic database for a complete browser write/readback flow.

1. At each of **1920x1080, 1440x900, 1366x768, 1280x720, 1024x768, 834x1194, 768x1024, 430x932, 390x844, 360x800**, open Add using the actual visible button and Edit via row menu and via detail. Wait for visible nonzero geometry and canonical GET completion before measurements.
2. Require exactly one visible PO modal, four sections, no duplicate field/error IDs, and no document horizontal overflow. Desktop panel target is 940px (within 880–980 where viewport permits), max-height viewport minus 40px. Mobile <=767px is a fullscreen sheet with stacked sections. Verify only body scrolls and footer stays reachable, including short heights/keyboard-height approximation.
3. Prepopulate every real field, including ISO date, both quantities/prices, category, all priority values, responsible admin, notes and a non-default supported status. Legacy null B fields should retain their original fallback. Change maker/quantities; compare the fixed fixture amounts above, without adding margin to maker price.
4. Clear item/date/unit, omit one warehouse quantity, enter zero total or too many quantity decimals. Save: verify focus lands on the first error, inline errors are announced, draft survives, no mutation occurs. Also cause a real server-side invalid status/assignee response.
5. Edit notes. Separately attempt X, Cancel, Escape, desktop backdrop; reject confirmation each time and verify the exact draft is intact and focus remains usable. Accept confirmation and verify close plus focus restoration to a visible trigger, not a hidden menu item. Reopen Edit: canonical server data replaces the abandoned draft. Tab/Shift+Tab must stay inside the modal. Mobile has no exposed backdrop; test its X/Cancel/Escape paths.
6. Save a real synthetic edit: exactly one existing ID is updated (not inserted), original status semantics and fee readback match; double-click cannot submit twice. Confirm success feedback and that refresh/reopen shows persisted values.
7. Open the same PO in two independent contexts. Save first; save second with its old token: verify 409, visible conflict copy, all second draft fields preserved, no overwrite. Follow instruction to copy draft and reopen before retry. Test blocked canonical GET and network/server errors; no fabricated success or enabled empty-ID submit.
8. Repeat as super_admin/kepala_dapur/admin. Supplier and responsible-admin fields absent for regular admin; direct forged supplier changes ignored, cross-region direct requests 403. No fake Draft status.
9. Repeat Add/Edit and validation/loading states in light and dark, plus reduced-motion. Require readable tokens, visible focus, >=44px controls, hit-test button centers, and screenshots. Compare to the supplied north star; actual business fields/formulas take precedence over unsupported sample copy.
10. Capture per-viewport measurements/screenshots immediately. This handoff **does not claim pixel certification, all-viewports pass, or P0/P1=0**; those require the deferred parent browser pass and product sign-off.

## Reproducible focused tests

Run from the stage directory only (there is no `.env` or cached config there):

```sh
APP_ENV=testing APP_KEY="base64:$(openssl rand -base64 32)" \
DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL='' \
CACHE_STORE=array MAIL_MAILER=array SESSION_DRIVER=array QUEUE_CONNECTION=sync \
timeout 60 php artisan test --compact \
  --filter='SbgV25PoTest|SbgV22PresentationTest|WarehouseQuantityTest|PartnerFeePaymentTest|KsppgBulkPaymentTest|SupplierBulkPaymentTest'
```

RED/GREEN logs and machine-readable checks live outside the deployable repo in `/root/.hermes/cache/scratch/sbg-v25/po-*.log`, `po-protected-check.json`, and `po-result.json`. Dedicated tests exercise real SQLite create/update/readback/RBAC/status/validation/conflict behavior; the Node assertions execute the actual shared state factory with minimal browser transport/DOM stubs, not a real browser. Full suite intentionally deferred while other agents edit.
