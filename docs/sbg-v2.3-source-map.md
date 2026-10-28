# SBG v2.3 source map and bounded QA handoff

## Scope / authority

Read all four `*.docx-extracted.txt` specifications, all four MD handoffs plus the package README, and inspected `VISUAL_NORTH_STAR_SBG_v2_3.png`. The DOCX tables are authoritative over abbreviated Markdown. Baseline is the fresh deployed `e25b9193bba6e6290e4d7322335d54dcf095df1a` / v0.1.10, snapshotted in this isolated stage as `65eb8a0`. No live deployment, commit, restart, migrations, dependency changes, credentials, financial mutations or outbound messages were performed.

Only Master Data, shared responsive sidebar, Edit Region/Admin and Popup Login configuration were changed. Existing v2.2 CSS/JS and modal components remain byte-identical. The shared sidebar necessarily changes workspace available width at its new breakpoint; it does not restyle the v2.2 dialogs.

## Existing source of truth / endpoints

| Concern | Existing source / authorization |
| --- | --- |
| Dashboard and region scope | `GET /dashboard`, `DashboardController::index`; non-super-admin region query stays scoped to the authenticated user's region |
| Regions | `Region` / `regions`: `id`, `code`, `name`, `description`; existing relation by region name |
| Accounts | `User` / `users`: `id`, `name`, `email`, `role`, `region`, `created_at`, SoftDeletes; password/remember_token hidden |
| Roles | Existing `super_admin`, `kepala_dapur`, `admin`; existing helper methods unchanged |
| Create region/account | `POST /dashboard/admin-region`; super-admin / own-region Kepala Dapur rules unchanged |
| Edit region/account | `PUT /admin/region/{id}` super-admin only; selected account must belong to that region and have an existing allowed role before any write |
| Edit managed account | `PUT /admin/account/{account}`; existing own-region Admin-only restriction for Kepala Dapur retained |
| Delete | Existing `DELETE /admin/account/{account}` and `/region/{id}`, authorization and confirmation retained; account deletion stays soft-delete |
| Popup configuration | Existing `login_popup_settings.user_id`, `enabled`, `title`, `message`; super-admin-only `POST /dashboard/users/{user}/login-popup` and `/toggle` |
| Actual login display | Existing `Auth/LoginController::authenticate` session flag, `DashboardController::index` pull-once per-user setting, escaped Blade actual announcement unchanged |
| Account info | Existing `created_at`; count of non-deleted POs created by that account, explicitly labeled “PO dibuat akun ini” rather than an invented regional total |

No new account activation field/state/table exists. The four KPIs are **Total Daerah**, **Akun Terdaftar** (non-deleted accounts represented in this scoped list, including visible super-admins), **Kepala Dapur**, **Belum Ada Akun** (regions with no non-deleted Admin/Kepala Dapur). These replace the reference's unsupported “Akun Aktif / Menunggu Aktivasi”; no fabricated trend, percentage, or activation metric is shown. Accounts without a matching existing region remain outside this list, as in the baseline. A super-admin in a region does not count as that region's Admin/Kepala Dapur assignment.

## UI / assets

- `components/sbg/master-data.blade.php`: scoped row presentation, four actual KPIs, local combined search/role/status/reset, CSV export, table/card choices. One bulk account query replaces the old per-region account queries. IDs and allowlisted account fields, never credentials, are passed into edit controls.
- `components/sbg/popup-controls-v23.blade.php`: grouped switch/Atur, server-confirmed update without optimistic ambiguity, inline toggle failures.
- `components/sbg/master-data-dialogs.blade.php`: the two isolated accessible dialogs; inline client/server errors, preserved in-memory drafts on 422/409/network failure, duplicate-submit loading state, password optional/write-only and empty on every open.
- `public/css/sbg-v23.css`: scoped v2.3 tokens `#0E5A43 / #17865D / #EAF7F0 / #FBFAF4 / #F6A21A / #E83E5B`, 100px desktop KPI cards, 12px gap, 46px table headers / 58px normal rows, 720px desktop dialog / 86dvh limit, single-column mobile sheets and reduced motion.
- `public/js/sbg-v23.js`: dashboard state mixin, session-scoped per-account UI preferences, safe text-only preview, async form recovery, CSV escaping. Existing `public/js/sbg-v2.js` provides focus traps, scroll lock, Escape and focus restoration unchanged.
- `app-navigation.blade.php`: retains the single original navigation definition. Desktop >=1024: 228px expanded / 68px rail, hover and focus tooltips with accessible names/descriptions and Master Data active state. Tablet/mobile: 292px off-canvas drawer with overlay. The existing global toolbar is retained; its search targets the current Master Data workspace.
- `public/images/sbg-v23/{hero,region,welcome}.webp`: optimized crops of the approved north-star illustration. White edge matte removed and an explicit alpha exclusion masks the sample CTA out of hero art. No sample KPI values or user text are used as dynamic UI.
- Old `components/login-popup-controls.blade.php` is intentionally left unchanged, but the new Master Data surface uses the scoped v2.3 control instead.

## Compatibility and security details

Explicit `enabled` requests use the v2.3 maximums 100/500; clients omitting `enabled` retain the legacy 150/5000 maximums and automatic-enable behavior. Enabled content preserves the original **title OR message** validity rule; disabled configurations may be empty. The PRD's ambiguous empty-content wording is not interpreted as requiring both fields and breaking old clients. Existing longer saved text is not truncated on read; v2.3 saves return inline validation until shortened.

`App\Support\PopupVersion` is a content fingerprint, not a credential. The new form includes it, update checks it inside the per-account transaction and returns 409/current safe fields on mismatch. The draft is not overwritten until the operator explicitly selects **Muat versi server**. Legacy requests may omit this optional token. This is content-based conflict detection, not a new database revision system; an A→B→A sequence is not distinguished from unchanged content. Actual popup output stays Blade-escaped; preview exclusively uses Alpine `x-text`.

Region edit now validates the original fields and verifies the target before writing, then updates the existing region/account atomically. This fixes the baseline's possibility of renaming a region before verifying account membership or validating an email. The existing rename semantics remain unchanged: this is not a new region-name cascade migration for other users or historic POs. No financial fields or formulas are changed.

## Evidence / execution

Evidence is outside the repository at `/root/.hermes/cache/scratch/sbg-v23`:

- `red-tests.log`: initial five new regressions failed against the baseline as expected; `toggle-red.log`: additional row-toggle JSON validation failure before its fix.
- `focused-final.log`: **21 tests passed, 197 assertions**, covering nine new v2.3 tests plus original LoginPopup and v2.2 presentation/analytics tests. Real in-process SQLite mutations are read back for region edits, blank password preservation, roles, account/region create/delete, popup config/toggle/version conflict and actual login scope/once-only/escaped display.
- `before-matrix.json`, `screenshots/before`: original UI captured at every mandatory viewport before UI edits.
- `qa-{viewport}.json`: all **8 mandatory Chromium viewports passed** for Master Data, sidebar state, edit, popup, counters, safe preview, inline validation, traps and reduced motion. Per-viewport outputs are persisted immediately; one browser worker used.
- `qa-extra.json`: actual backend 422/409 responses, offline error recovery, preserved filters after reload, actual CSV download/readback, role-scoped pages, >=44px mobile dialog targets, reduced-height test.
- `comparisons`: side-by-side approved panels / actual synthetic screenshots at 1440, 1366 and 390. The illustration is a composite, not a matching-resolution reference screenshot; strict pixel-drift tolerances cannot be certified from it.
- `sbg-v23-{desktop,mobile}-transitions.gif`: actual Chromium screenshot-frame recordings of sidebar and edit/popup transitions; `recording-manifest.json` records scenes.
- `result.json`: exact changed paths, hashes, commands/results, protected-file checks, checklist, limitations and fixture shutdown state.

Safe test environment: `APP_ENV=testing`, random `APP_KEY`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, empty `DB_URL`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `SESSION_DRIVER=array`. Browser router is **synthetic request-local memory**, loopback 18084, outside the repo. It resets on each request; browser write responses are real but persistence/readback proof is from in-process PHPUnit, not a fabricated persistent browser fixture.

## Known limits / intentional visual deviations

- Product sign-off and exhaustive <=2px pixel/contrast/performance auditing are not measured; no unqualified P0/P1=0 claim is made. Actual geometry, viewport bounds, keyboard flows, touch targets and errors are measured in evidence.
- Mobile row cards intentionally differ from desktop table composition for readability. Unsupported activation metrics/trends are replaced by honest labels. Redundant overflow menus with no additional authorized actions are not invented.
- The sample notifications/global header user-menu redesign is not expanded into unrelated functionality. The actual login announcement keeps its original logo/presentation; the local compact illustrated configuration preview is presentation-only and does not replace authentication UI.
- Current create-account dialog, password reset, financial/PO/calculator/analysis/logout views are preserved, not redesigned.
- Reduced viewport height is a virtual-keyboard approximation, not physical iOS/Android verification. Screen reader narration, touch-device browsers and production performance remain unmeasured.
- Full baseline had five existing ProfileTest 404 failures (parent `baseline-tests.json`); those unrelated routes are not repaired. Parent performs final full-suite/review/deploy. No Node/Vite build was necessary for additive public CSS/JS; project dependencies were not installed.
