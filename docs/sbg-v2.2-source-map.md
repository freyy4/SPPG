# SBG v2.2 source-of-truth map

Baseline: live source 893e393 / v0.1.9, isolated stage baseline 28df6e6. Only the five PRD surfaces are in scope. No production state, migrations, packages, routes, auth handlers, accounting models or services are to be changed.

| Surface | Presenter / component | Authoritative source and actions |
|---|---|---|
| Financial cards | `components/sbg/dashboard-summary.blade.php`, `components/sbg/kpi.blade.php` | `DashboardController::index` summary from the complete filtered query. Unpaid model accessor sums; existing fee-modal events remain. No comparison metadata exists, so no trend is displayed. |
| Profit analytics | `analisaKeuntunganModal()` and analytics markup in `dashboard.blade.php` | GET `dashboard.analisa`, `DashboardController::getAnalisaKeuntungan`; existing week/month/3months/all date boundaries, regional restrictions and unpaid role-specific fee SQL remain authoritative. Top three is not the grand total. A backward-compatible `totalProfit` response field may aggregate the same existing SQL expression over the same query, without limit, for the required total insight. No period-comparison endpoint exists. |
| Fee detail, all three variants | `partnerFeeModal` in `dashboard.blade.php`, shared `components/sbg/fee-detail.blade.php` | Existing `amount`, `selectedItems`, `selectedTotal`, `noteAmount`, `noteTotal`, `verificationQrDataUrl`, `printSelectedNote`, `downloadSelectedPdf`, `sendToWhatsApp` handlers. Selection remains unpaid-only; reset on open. Payment routes `dashboard.partner-fee.pay-all`, `dashboard.supplier.pay-all`, `dashboard.ksppg-fee.pay-all`; POST IDs exactly as selected, existing confirmation and server superadmin guard retained. Print/PDF are maker-price notes, **not** selected fee totals. WhatsApp uses selected fee totals. Never conflate these. |
| Logout | `dashboard.blade.php`, shared focus behavior `public/js/sbg-v2.js` | Existing POST `logout` to `Auth/AuthenticatedSessionController::destroy`; server invalidates session and regenerates CSRF. Presentation adds initial cancel focus and duplicate-submit lock only. |
| Add PO | `dashboard.blade.php`, `public/js/sbg-v2.js` | POST `dashboard.po.store` to `DashboardController::store`; original payload fields, role gates and conditional price-entry enablement retained. Existing inline quantity × maker-price previews retained verbatim. Validation stays server-authoritative, with HTML validity and returned field errors presented inline. No autosave/draft state. |

## Protected accounting and authorization

`PurchaseOrder` accessors: supplier = SBG qty × supplier SBG price; partner = SBG qty × partner margin + B qty × maker; KSPPG = SBG qty × (maker − supplier SBG − partner), or zero when supplier price is absent. Saving maintains quantity from both warehouses. All formulas and payment paid-status semantics must remain unchanged.

`DashboardController::normalizeWarehouseQuantities`, `normalizeCurrencyInputs`, `resolveManagedAdmin`, `authorizeAccess` are unchanged. `User` role predicates, routes and middleware stay unchanged. Supplier/KSPPG fee screens do **not** manufacture a B liability: the existing engine has only SBG liability for those types. Their presentation explicitly says Gudang B is not included in that payment, rather than inventing a new payable amount.

## Known baseline limitations

Fee popup data comes from `allPoData`, the current 15-row dashboard pagination, while summary cards sum the complete filtered query. This existing business dataset scope is preserved and explicitly disclosed in the UI; v2.2 must not silently change payable scope. The `all` profit filter historically has no upper date bound; preserve its existing semantics. No comparison trends can be verified, so omit all trend percentages.

## Regression boundary

Focused baseline (SQLite `:memory:`, random APP_KEY, testing, DB_URL empty, array mail/cache/session, sync queue): 23 tests / 186 assertions passed. Parent full baseline: 61 tests / 52 passed / 9 existing failures (four missing Vite manifest auth views; five absent profile routes). Do not change unrelated routes or weaken tests to hide these.

## Visual deviations justified by real content

Approved art depicts example amounts and comparisons; only decorative artwork is reused, never its text, mock data or trend badges. Real role-specific extra PO fields (responsible admin, supplier, both supplier prices) remain present even when absent from the illustration. Short laptop viewports therefore allow Add PO body scrolling but keep header/footer fixed. Rankings without verified comparison metadata display the existing fee metric only.
