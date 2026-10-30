# SBG v2.5 account/security backend

## Scope and integration

Stage-only implementation based on `bc1ff2600f30f5ec0c2bb77c57b210ca1cc1250c` / v0.1.15 and the four complete v2.5 spec extractions. Existing identity, soft-deletion, role/region checks and business calculations remain the source of truth. No production data, environment, credentials, services, source commits or migrations were changed. No account is automatically enrolled and GET settings never generates credentials.

Frontend ownership is separate. Backend uses `settings.account` and `auth.two-factor-challenge`; no replacement/stub templates were created here. The parent must perform integrated HTML/browser QA and the complete regression suite.

## Routes and fields

All settings endpoints require `auth` + `auth.session` inside the existing CSRF-protected web stack. There is one routed POST `/login`, through `AuthenticatedSessionController` and `LoginRequest`; the duplicate legacy route was removed. Both password PUT aliases delegate to one security policy.

| Method/path | Route name | Fields |
|---|---|---|
| GET `/settings/account` | `account.settings` | `tab=profile\|account\|security` |
| PATCH `/settings/account/profile` | `account.profile.update` | `name`, optional `phone` |
| POST `/settings/account/photo` | `account.photo.update` | multipart `photo` |
| GET `/settings/account/avatar` | `account.avatar` | none; own photo only |
| PUT `/account/password` | `account.password.update` | `current_password`, `password`, `password_confirmation`; **also `code` or `recovery_code` when 2FA enabled** |
| PUT `/password` | `password.update` | same policy/fields as above |
| POST `/settings/account/pin` | `account.pin.enable` | `current_password`, `pin`, `pin_confirmation` |
| PUT `/settings/account/pin` | `account.pin.change` | previous fields plus `current_pin` |
| DELETE `/settings/account/pin` | `account.pin.disable` | `current_password`, `current_pin` |
| POST `/settings/account/pin/verify` | `account.pin.verify` | `pin` |
| POST `/settings/account/totp/setup` | `account.totp.setup` | `current_password` |
| POST `/settings/account/totp/confirm` | `account.totp.confirm` | `code` |
| DELETE `/settings/account/totp` | `account.totp.disable` | `current_password`, `code` or `recovery_code` |
| POST `/settings/account/recovery-codes` | `account.recovery.regenerate` | `current_password`, `code` or `recovery_code` |
| DELETE `/settings/account/sessions` | `account.sessions.revoke` | `current_password` |
| GET/POST `/two-factor-challenge` | `two-factor.challenge` / `two-factor.verify` | POST `code` or `recovery_code` |
| POST `/two-factor-challenge/cancel` | `two-factor.cancel` | none |

Default mutations redirect to `account.settings?tab=security` (profile/photo use `tab=profile`) with `status`. Validation uses Laravel field-keyed errors. JSON requests get structured `{status,...}` / validation errors, not fake success. Missing/expired login challenge returns 403, unsupported session revocation and already-enabled enrollment return 409, failed verifications return 422, security-check lockout returns 429. Existing login lockout keeps Laravel's email validation-error convention.

Settings exposes exactly `$user`, `$securityState` (`pin_enabled`, `totp_enabled`, `recovery_codes_remaining`, `setup`), `$activeSessions`, `$sessionManagementSupported`, `$recoveryCodes`. Pending `setup` has `manual_key`, `provisioning_uri`, `qr_data_uri`; an enabled secret is never returned. Sessions use real DB metadata and ISO-8601 `last_active_at`; display `id` is SHA-256 of the session ID, never the bearer session ID itself. No invented geography or device labels.

## Credential and authentication policies

- Password change: current password every time, minimum 12 characters, mixed case, number and symbol, confirmation, maximum 72 UTF-8 bytes to avoid bcrypt truncation. Enabled 2FA additionally requires a fresh TOTP or unused recovery code. Bad input types return validation errors.
- PIN: opt-in, exactly six ASCII digits including leading zeroes; only a framework `Hash` hash is stored. Enable/change/disable require current password; change/disable also verify current PIN. Verification is a step-up hook setting `security.pin_verified_at`, not a new transaction authorization policy. Credential strings are not silently trimmed.
- TOTP: OTPHP 11.5.0, SHA-1 / six digits / 30-second period (Google Authenticator-compatible); explicit PSR clock and library verification, not custom OTP crypto. Accept current/adjacent steps; persist the winning counter, reject that counter and all older counters thereafter. Device/server clock synchronization is operationally required.
- Setup secret is encrypted using the existing application key and expires after ten minutes. Setup does not enable 2FA. Activation requires a real first code, consumes that time step, clears pending material and creates eight recovery codes.
- QR is a local 320px PNG generated with BaconQrCode/GD; no external QR service receives secrets.
- Recovery codes are 80-bit random lowercase hexadecimal strings; framework hashes only in the user row. Generation/regeneration replaces old hashes. Consumption removes a hash under a row lock in a DB transaction. Sequential replay is tested; production-database parallel contention was not exercised against a live DB.
- HTML redirect transport encrypts once-only recovery material with an account binding and five-minute expiry; settings pulls it once. JSON generation returns plaintext once directly and leaves no recoverable plaintext session copy. All relevant responses, including validation errors, are private/no-store.
- Password verification at login uses SessionGuard's validation-only timing envelope. If 2FA is enabled, it does **not** authenticate or issue a remember cookie. Pending login holds account ID, expiry, remember choice and a password-hash fingerprint, never the submitted password or OTP secret. Challenge expires in five minutes, detects changed passwords/deleted accounts/disabled 2FA, and authenticates only after locked factor consumption. Cancel invalidates the pending session. Session IDs regenerate at credential/challenge boundaries.
- Account and account+IP rate-limit keys cap each password/PIN/TOTP check at five failures per five minutes; successful verification clears its check counters. TOTP/recovery share a purpose limit, so switching methods or IP does not bypass the account cap. Login also has account-wide and email+IP limits. Production must use a shared persistent cache; array cache is testing-only.
- Audit storage accepts only an event allowlist plus user ID, timestamp, IP and bounded user agent. No request bodies, passwords, PINs, OTPs, secrets, recovery codes or raw session IDs are inserted. Failed checks are recorded after the controller transaction rolls back. Audit table access/retention is an operational permission/privacy concern, not a new public endpoint.
- Exception old-input exclusions cover all new secret fields. No secret logging was added. No self-delete endpoint or registration/enrollment backdoor was added.

## Session policy

Actual database-driver session enumeration/revocation is supported; other drivers honestly show unsupported and reject the dedicated revoke endpoint (409). Password changes, enabling/disabling TOTP and explicit revocation delete other DB session rows and rotate remember tokens. Current active session is retained; all previous remember tokens, including the old current-device token, become invalid. `auth.session` applies to protected dashboard/auth/settings routes, allowing password changes to invalidate old hash-bearing sessions too.

Laravel 13's AuthenticateSession writes its password HMAC **after** the controller response. The service refreshes the guard's User instance and current-session HMAC after rotation; the regression test discards the cached guard between requests and proves the real current login survives. Testing only `actingAs` with a stale cached user missed this defect initially.

For non-DB drivers, token rotation and password-hash middleware still apply, but comprehensive cross-device enumeration/deletion and enrollment revocation are not claimed. Deployment contract specifies the database driver. Cookie configuration was not modified; preserve production Secure/HttpOnly/SameSite=lax settings. Do not clear or rotate APP_KEY casually: active and pending TOTP secrets depend on it.

## Profile/photos

Only own name/phone are mutable; submitted email/role/region/avatar_path/user_id changes are rejected. Existing RBAC behavior is untouched. JPEG/PNG must match allowed extension and MIME, be <=2MiB, <=4096px per side and <=16 million pixels, and actually decode in GD. The server resamples and encodes a new JPEG (max width 512px), stripping original bytes/metadata/appended payloads. Random `.jpg` names live under private local storage `avatars/{own-id}/`; there is no public-storage symlink or user-controlled retrieval path. Replacement failures do not report success or change the saved avatar pointer; previous valid files are removed only after successful DB mutation. The authenticated avatar endpoint enforces the owner/path pattern and nosniff.

## Additive migration and release precautions

`2026_10_30_000001_add_account_security.php` adds nullable `phone`, `avatar_path`, `pin_hash`, encrypted-secret/pending-state fields, TOTP enable timestamp/last step, hashed-recovery JSON storage, and an indexed append-only `security_audit_events` table. It does not update existing account rows, passwords, roles or enrollment defaults. SQLite-memory tests execute the actual migration up, down and up again, preserving the preexisting account identity/password/role/region.

**Parent/release owner only:** back up DB, application key and owned source; verify runtime extensions/dependencies and DB/session/cache settings; deploy additive schema before enabling the new login code (login now audits to the new table), then deploy the reviewed backend/frontend together. No production migration command was executed by this task.

**Rollback is destructive:** `down()` drops all new profile/security columns and security audit history. Retain the additive schema for normal source rollback; do not run down with active enrollments/new code, as dropping 2FA state can remove enforcement. Any destructive down requires an explicit backup/restore plan and release-owner approval. Never rotate credentials or auto-enroll existing users as part of deployment.

## Dependencies and references

Only the previously installed justified additions are retained: `spomky-labs/otphp` 11.5.0, `bacon/bacon-qr-code` v3.1.1, `paragonie/constant_time_encoding` v3.1.3 and `dasprid/enum` 1.0.7. No existing locked version changed and no reinstall/upgrade was performed during resume. Installed vendor source is authoritative for the exact APIs. Primary reference snapshots are in the parent `primary-references/` directory (Laravel 13 authentication/encryption, OTPHP documentation index).

## Verification evidence

Run only from the isolated stage with the parent safe runner:

```sh
python /root/.hermes/cache/scratch/sbg-v25/run-backend-tests.py
```

The runner generates an ephemeral random APP_KEY and forces testing, SQLite `:memory:`, array cache/mail/session and sync queue; it avoids production config cache and enforces a 60-second subprocess timeout. Database-driver tests switch only their in-memory test session configuration. It writes no `.env`.

RED logs are `backend-resume-red.log`, `backend-red-pin.log`, `backend-red-totp.log`, `backend-red-login.log`, `backend-red-settings.log`, `backend-red-disable.log`, `backend-red-no-leak.log`, `backend-red-sessions.log`, `backend-red-photo.log`, `backend-red-enrollment-rotation.log`, `backend-red-bounded-password.log`, `backend-red-current-session.log`, `backend-red-input-types.log` in the parent directory. They demonstrate missing routes/policies, absent once-only transport, current-session regression and malformed-input defects before fixes. Prior original RED evidence remains `backend-red-profile-password.log`.

Dedicated AccountSecurityTest covers profile restrictions; both password aliases; PIN lifecycle/hash/lockout; real local-QR setup and encrypted secret; genuine full login/challenge and remember-cookie gating; replayed TOTP/recovery; expiry/cancel/lockouts; password/factor reauthentication on regeneration/disable; safe read-only settings; encrypted once-only recovery and secret flash exclusions; real DB session list/deletion/isolation; current-session survival with a fresh guard; validated/decoded/reencoded/private photos; and migration up/down. Final machine-readable results/hashes are in parent `backend-result.json`; green test output is `backend-green.log`.

No browser was launched. Existing `tests/Feature/Auth/PasswordUpdateTest.php` still assumes the weak `new-password` fixture, successful `/profile` redirect and `updatePassword` error bag. Those assertions conflict with the deliberate shared v2.5 strength policy/settings redirect/field-error contract; they were not edited or executed by this focused backend task. Parent integration must reconcile those intended contract changes explicitly rather than weakening an alias or treating them as unexplained regressions.

No production sign-in, multi-process MySQL concurrency run, full-suite run, or visual/accessibility certification is claimed by this backend task. Those integration gates remain with the parent; preserve the five known obsolete ProfileTest 404 identities rather than restoring profile deletion routes.
