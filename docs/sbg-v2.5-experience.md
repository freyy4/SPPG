# SBG v2.5 — frontend experience handoff

## State and scope

Implemented in the isolated `stage` tree; **not deployed and not visually certified**. Browser/server launch is deliberately deferred until the parent integrates all three source owners. No Chromium worker, HTTP fixture, production authentication, service restart, migration against production, or commit was performed by this phase.

Read `integration-contract.md` and all four complete DOCX extractions. Reviewed all five reference images. Dashboard/Add/Edit PO source and their dedicated CSS/JS remain the PO owner's files. `public/js/sbg-v2.js`, app code, routes, migrations and Composer were not edited by this owner.

## Implemented surfaces

- Login: independent HTML branding/headline/body/trust content and alpha-masked approved illustration; two-panel viewport composition, short-laptop variants, compact mobile composition, 54px inputs and 56px CTA. Password visibility, remember-me, real forgot-password link, server validation/status and submit-loading/duplicate-submit prevention are connected to the existing login form. Fields/actions are never clipped to disguise overflow.
- Settings: actual `settings.account` view with profile/account/security query-tab navigation, sidebar/mobile navigation, support rail and approved landscape/fruit art. Profile PATCH sends only name/phone. Managed email, role and region are read-only and have **no submitted field names**. Photo upload is a real multipart CSRF form, and only the authenticated private avatar endpoint is used; there is no unvalidated client preview presented as a saved photo.
- Account: account identity display, scoped light/dark/system selector, and honest unsupported-language/external-account/trusted-device copy. No self-delete or email/role/region editing was introduced.
- Security: real current-password/new-password/confirmation form; advisory strength meter reflecting the backend 12-character/mixed-case/number/symbol policy. PIN enable/change/disable forms, progressive six-cell masked PIN input with paste/backspace navigation, and ordinary six-digit fallback fields without JavaScript. No existing credential is populated from flashed old input.
- TOTP: backend-generated setup QR and manual key, first-code confirmation, enabled state, remaining recovery-code count, once-only recovery-code panel, regeneration and disable forms with current password plus OTP/recovery input. No external QR service, fake secret or client-only security toggle.
- Sessions: real supported session entries and current-session indicator; revoke form only when backend enumeration/revocation is supported. Unsupported drivers have explicit copy instead of invented devices/locations/timestamps.
- Challenge: independent `auth.two-factor-challenge` page, separate real OTP and recovery forms, real cancel POST, CSRF and server errors; no password is resubmitted.

All form actions follow the contract endpoint/name pairs. `Route::has()` fallbacks resolve to the exact contract URL during parallel integration, not to a simulated handler. Backend security remains authoritative.

## Theme integration

`components/sbg/theme-head.blade.php` resolves preference before paint, sets `data-theme`, `data-theme-preference`, `data-theme-account` and `color-scheme`, then loads public semantic CSS/JS. Storage uses `sbg-theme:<account-id>` or `sbg-theme:guest`; invalid/missing/blocked storage falls back to system preference. OS changes and cross-tab updates are supported. Global selection and the public-home toggle share the same controller; the old unscoped homepage controller was removed without changing quote/catalog/navigation functions.

Included on login/challenge/settings, app/guest layouts, public home and public verification pages. PO owner supplies the dashboard inclusion hook (present in integrated source during focused tests).

Existing v2/v2.3/v2.4/fruit/receipt/public-home color literals now resolve through semantic dark aliases while retaining the exact original light literal as a CSS fallback. Geometry, filters, arithmetic, export and payment functions are untouched. Utility classes, inputs, overlays, dropdowns, status surfaces, borders and scrollbars have compatibility rules. New surfaces use the palette directly. A QR's white quiet zone is intentional machine-readable content, not an unthemed application card. Reduced-motion disables decorative animation and transitions. Runtime visual contrast and legacy-chart rendering still need the parent browser audit.

Approved-art generation lives outside the deployable repo in `experience-art.py`. The mask excludes the reference's header/hero/form text lanes; assertions are in `experience-art-mask.json`. The crate's physical brand mark remains part of the approved illustration. A composited preview was inspected. No sample account name/email/amount is baked into application art.

## Actual verification in this phase

- `tests/Feature/SbgV25ExperienceTest.php`: **8 passed, 75 assertions** on SQLite `:memory:` with random APP_KEY, empty DB_URL, testing environment, array session/mail/cache and synchronous queue. Includes actual HTTP login/settings/dashboard responses, actual TOTP setup payload rendering, profile validation, managed-field protection, CSRF/form contract rendering, challenge rendering, and a real creator-backed synthetic PurchaseOrder/schema preflight. This is focused UI/integration coverage, not the backend owner's complete security suite.
- `node --test tests/UI/sbg-v25-theme.test.cjs tests/UI/sbg-v25-experience.test.cjs`: **11 passed**. Production scripts execute in VM/element test doubles for account-scoped prepaint/theme persistence/system changes/storage denial, homepage theme toggle, PIN aggregation/paste/navigation, password toggle and loading/bfcache restoration. These are browser-free unit tests, **not** viewport, browser focus or physical-device proof.
- Existing PostCSS parsed all eight owned CSS entry files successfully. JS syntax checks, fixture PHP syntax check and owned diff-whitespace checks passed.
- RED logs precede login, profile, security, challenge, legacy semantic-theme and public-theme-toggle behavior slices. Additional runtime and integrated-contract tests supplement those slices.

Logs: `/root/.hermes/cache/scratch/sbg-v25/experience-focused-tests.log`, `experience-runtime-tests.log`, and `experience-red-*.log`.

## Invalid legacy baseline

The parent inspected `before/dashboard-light-1366x768.png`: it is a blank Chrome HTTP ERROR 500, **not a dashboard screenshot**. The original capture lacked HTTP/app/data assertions, so all those legacy cases are invalid/untrusted as visual baseline evidence. Originals are retained, with `experience-evidence/before/INVALID-BASELINE.json`. This is an isolated fixture failure, not evidence that production was broken.

The synthetic router now supplies the required actual `created_by` user foreign key and valid existing PO status values. Focused PHPUnit proves that schema/data shape renders an HTTP200 dashboard containing `x-data="dashboardApp"` and a real fixture order. The router itself has only been syntax-checked in this recovery phase; it has **not** been started or certified over HTTP.

## Parent-only bounded browser follow-up

Use only after integration/resource recovery. Keep one worker and stop only owned test processes. Do not touch protected WhatsApp Chromium or infrastructure.

Outside-repo scripts:
- `experience-router.php`: synthetic request-local in-memory SQLite; fresh random key; real models/schema; local backend-generated setup/recovery illustration fixtures. Every response is labelled `X-SBG-Fixture: synthetic-request-local-memory` and no-store. Request-local mutations do not persist across requests; use PHPUnit for mutation/readback correctness.
- `experience-qa.cjs`: compatibility entry point to `experience-qa-bounded.cjs`. Original timeout-prone source preserved as `experience-qa.legacy-timeout.cjs`; never use that legacy runner as acceptance proof.
- Bounded runner uses the explicit requested Chrome executable, GPU off, renderer-process-limit=1, DOMContentLoaded plus application-ready/data checks (not networkidle0). One viewport per invocation, default 2 cases, enforced maximum 4. Each case writes JSON immediately, including failures. HTTP200 + fixture header + app identity + nonzero model-backed PO rows are prerequisites to accepting dashboard screenshots. Visible dialog count/geometry is non-vacuous. Actual clicks scroll targets to center and require `elementFromPoint` hit testing, including mobile PO menu/edit actions and account-menu actions.

Example **after** the parent has launched its owned loopback fixture:

```sh
VP_INDEX=2 CASE_OFFSET=0 MAX_CASES=2 node /root/.hermes/cache/scratch/sbg-v25/experience-qa.cjs
```

`VP_INDEX` mapping: 0=1920x1080, 1=1440x900, 2=1366x768, 3=1280x720, 4=1024x768, 5=834x1194, 6=768x1024, 7=430x932, 8=390x844, 9=360x800.

Each viewport has ordered scenes login/dashboard/dropdown/add/edit/profile/account/security/challenge/recovery/totp-setup, first light, then dark. Offsets 0..21 select from those scenes. Issue small bounded calls; do not run the full matrix in one long RPC. The aggregator derives the complete expected set from the viewport/theme/scene Cartesian product, deduplicates cases and refuses zero/partial coverage as success:

```sh
python3 /root/.hermes/cache/scratch/sbg-v25/experience-aggregate.py --compare
```

Comparison hooks map login/edit/profile/security/dark dashboard to the five approved reference filenames. Comparison sheets are generated **only** from verified successful cases and remain `generated-not-reviewed` until a human inspects them. Native reference and screenshot dimensions differ: sheets are qualitative side-by-side review, not fabricated pixel-drift certification.

Stable selectors: `[data-sbg-login]`, `[data-sbg-settings]`, `[data-active-tab]`, `[data-sbg-challenge]`, `[data-password-toggle]`, `[data-pin-input]`, `.sbg-pin-cells`, `[data-totp-setup]`, `[data-recovery-once]`, `[data-theme-select]`, `[data-theme-toggle]`, `[data-account-menu]`, dashboard `[x-data="dashboardApp"]`, PO `[data-po-mode]`.

### Still required before release

All ten viewport light/dark pairs; real no-scroll measurements at 1366x768/1280x720 and normal-height mobile; all five side-by-side reviews; target-size/contrast/white-island/legacy-chart/modal/dropdown/receipt audit; Tab/Shift-Tab/Escape and focus-return walkthrough; PIN paste/correction on the actual browser; reduced-motion and native browser zoom; reduced-height keyboard approximation (not a physical keyboard/device claim); real backend end-to-end security/regression suite and parent independent review. Concrete P0/P1 failures block release. **No P0/P1-zero or all-browser-passed claim is made.**
