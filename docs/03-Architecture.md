# CampusFind — Architecture

**Version:** 1.0 · **Date:** 2026-11-11

Technical architecture of CampusFind and how the systems work together.
Stack: **PHP 8 (procedural), MySQL/PDO, Bootstrap 5, vanilla JS** — designed
to run on a single XAMPP-class Apache host with zero paid dependencies
configured by default.

---

## 1. System overview

```
Browser (Bootstrap 5 + vanilla JS, polling)
   │  HTTP / JSON
   ▼
Apache + PHP  ──  page scripts (marketplace.php, messages.php, …)
   │                admin/ (role-gated pages)
   │                api/  (JSON: chat, M-Pesa callback)
   │                oauth/ (Google OAuth start/callback)
   │
   ├── includes/  shared layer (config, db, auth, domain modules)
   │
   ├── MySQL (campusfind, 20 tables)
   │
   ├── storage/   sms/ · mail/ · chat/ · verifications/  (HTTP-denied)
   ├── uploads/   listing images (served statically, scripts blocked)
   └── External (optional): Africa's Talking SMS · SMTP · Google OAuth · M-Pesa Daraja
```

Principles: **no framework** (small procedural codebase, every include is
explicit), **no ORM** (one PDO wrapper, prepared statements everywhere),
**feature-offline-by-default** (every external integration degrades to a
labelled simulated mode).

---

## 2. Request lifecycle

1. Every page/`api` script starts by requiring `includes/auth.php` (which
   loads config → db) and, as needed, `functions.php` and a domain module.
2. `auth.php` starts the session, exposes CSRF helpers, `current_user()`,
   `require_login()`, `require_admin()`, `flash()`/`redirect()`.
3. POST handlers call `csrf_check()` / `require_post()` **first**, then
   validate with domain functions, then act via `q()` inside a transaction
   where state changes span multiple writes.
4. Rendering uses `includes/header.php` / `footer.php`; all output passes
   through `e()` (`htmlspecialchars`).
5. `api/chat.php` and `api/mpesa-callback.php` short-circuit: JSON headers,
   no layout. `api/mpesa-callback.php` is the **only** unauthenticated entry
   point, gated by the shared callback token (see §7).

---

## 3. Directory map

| Path | Responsibility |
| --- | --- |
| `includes/config.php` | All constants; loads `config.local.php` (gitignored env overrides) first |
| `includes/db.php` | PDO singleton `db()` + `q()` wrapper |
| `includes/auth.php` | Session, CSRF, login gates, flash/redirect |
| `includes/functions.php` | Shared helpers: `e()`, `url()`, `clean()`, pricing, uploads, contact links |
| `includes/otp.php` | Phone normalize/display, SMS adapter, OTP issue/verify, student-verification lookup |
| `includes/mailer.php` | SMTP send via PHPMailer; simulated `storage/mail/` mode |
| `includes/trust.php` | Badges, seller stats, trusted-seller rules, `audit_log()`, moderation history helpers |
| `includes/chat.php` | Conversations, messages, blocks, unread/read, attachment validation |
| `includes/transactions.php` | Transaction state machine, actions, completion, reviews |
| `includes/mpesa.php` | Daraja token, STK push, status query, callback handler, paid-apply |
| `includes/listing_form.php`, `card.php` | Shared listing form + listing card renderers |
| `admin/` | Role-gated admin pages (`require_admin()`), audit-logged actions |
| `api/` | `chat.php` (poll/send/conversations JSON), `mpesa-callback.php` |
| `oauth/` | Google OAuth start + callback |
| `attachment.php` | Authenticated attachment streaming from `storage/chat/` |
| `database/` | Full schema `campusfind.sql` + additive `migrations/` |
| `docs/` | PRD, design system, this document, feature specs |

---

## 4. Configuration model

`includes/config.php` defines constants via `getenv('CF_*')` so **no secret
is ever hardcoded**:

- `includes/config.local.php` (gitignored) sets real values with
  `putenv('CF_...')` — copy from `config.local.example.php`.
- Live integrations: `CF_SMS_PROVIDER/ENV/USERNAME/API_KEY/SENDER`,
  `CF_MPESA_*`, `CF_UNIVERSITY_DOMAINS`, Google OAuth IDs.
- Read `docs/marketplace-v2.md` for the full variable table.

On Apache, `SetEnv` in the vhost is the equivalent production mechanism.
XAMPP dev default: simulated SMS and simulated mail.

---

## 5. Data layer

**`db.php`** — one connection per request, real prepares
(`EMULATE_PREPARES => false`), exceptions on, assoc fetch. **`q($sql,
$params)`** is the single query path; there is no string-built SQL anywhere.

**Schema (20 tables), grouped:**

| Group | Tables |
| --- | --- |
| Identity | `users`, `email_verifications`, `user_oauth` |
| Catalog | `categories`, `listings`, `listing_images`, `listing_reports`, `moderation_logs` |
| Verification | `verification_otps`, `student_verifications` |
| Messaging | `conversations`, `messages`, `message_attachments`, `blocked_users` |
| Commerce | `transactions`, `payments`, `payment_events`, `transaction_reviews` |
| Governance | `user_reports`, `admin_audit_logs` |

Rules:
- Fresh installs import `database/campusfind.sql`; **live databases only ever
  receive additive migrations** (`database/migrations/…`) — never drops,
  resets or deletes.
- Money is stored as decimal; `payments.payment_events` is the append-only
  record of every Daraja callback/query received.
- OTP codes are stored hashed (`password_hash`), never in plaintext.

---

## 6. Module interactions and key flows

### 6.1 Authentication & sessions
`auth.php` owns the session (`auth`, `role`, `flash`, `csrf`). Password login,
email-verification gating at register, and Google OAuth (`oauth/start.php` →
Google → `oauth/callback.php` → `user_oauth` link-or-create → `login_user()`).
One OAuth provider by design; the exact localhost callback URI is registered
in the Google console.

### 6.2 Verification pipeline (`includes/otp.php`)
- `otp_issue(userId, purpose, destination)` — rate limits read **database
  time** (`TIMESTAMPDIFF` vs `NOW()`) so PHP/MySQL clock skew can't bypass
  cooldowns; cooldown 60 s, 5 sends/hour, TTL 600 s; codes hashed at rest.
- Delivery by purpose: `phone` → `sms_send()`; `university_email` →
  `mailer.php`.
- **SMS adapter:** Africa's Talking-compatible HTTP POST (`apiKey` header,
  `username/to/message/from` form fields). Three honest modes:
  - *dev* — no provider: writes labelled `SIMULATED SMS` files to
    `storage/sms/`.
  - *sandbox* — AT sandbox: real HTTP call, but delivery lands in the AT
    simulator only.
  - *sms* — production: real delivery.
  `sms_send()` fails closed on partial config (provider set but missing
  username/key → error, no fake success).
- `otp_verify()` — constant-time code check, attempt cap, duplicate-number
  guard inside a DB transaction, then marks `phone_verified_mode`:
  `sms` only when `sms_live()` (provider **and** `CF_SMS_ENV=production`);
  otherwise `dev`/`sandbox`. **Simulated and sandbox phones can never earn
  the public badge** — this is enforced in `otp_verify`, `trust.php` badge
  computation, and the admin views.
- `student_verifications` — evidence to `storage/verifications/` (HTTP-denied,
  admin-only viewing via `admin/evidence.php`); approve/reject sets
  `users.student_verified_at` and triggers `trust_refresh()`.

### 6.3 Trust computation (`includes/trust.php`)
`trust_refresh(userId)` recomputes seller stats (completed transactions,
rating count, average) and derives badges. **Trusted Seller** requires
≥ 3 completed + ≥ 3 reviews + ≥ 4.0 average (`TRUSTED_MIN_*` constants).
`audit_log()` writes every admin action to `admin_audit_logs`.

### 6.4 Messaging (`includes/chat.php` + `api/chat.php` + `messages.php`)
- Conversation is created per listing (`chat_start`), participants =
  buyer + seller; uniqueness enforced in the table.
- Frontend polls `api/chat.php?action=poll&c=…&after=…` every few seconds;
  the same call marks messages read, yielding read receipts (`read_upto`).
- `action=send` is POST + CSRF, rate-limited (10/min), attachment validated
  by MIME (`image/jpeg|png|webp`), stored under `storage/chat/` with random
  names, served via `attachment.php` which re-checks participant-or-admin on
  every request. Blocks short-circuit both send and conversation access.

### 6.5 Transactions & M-Pesa (`includes/transactions.php`, `includes/mpesa.php`)
- `transaction_create` records listing + buyer + agreed amount → state
  machine (`transaction_action`) drives inspection, payment reporting,
  two-sided completion (`transaction_finish_if_ready`), disputes, and
  one-per-side reviews.
- **Direct payments** are stored as *reported* — UI and badges distinguish
  "reported" from "verified" everywhere.
- **M-Pesa Daraja:** `mpesa_token()` → `mpesa_stk_push()` for a pending
  `payments` row; the callback URL embeds `CF_MPESA_CALLBACK_TOKEN`.
  `api/mpesa-callback.php` validates the token, then `mpesa_handle_callback()`
  normalizes the payload, appends to `payment_events`, and calls
  `mpesa_apply_paid()` → verifies the payment and releases the transaction.
  `mpesa_query_status()` is the manual fallback that applies the same
  "only Safaricom may verify" rule.
- **A browser action can never mark a payment verified.** Empty callback
  token ⇒ all callbacks rejected (fail closed).

### 6.6 Moderation & admin
New/edited listings land in `pending`; the admin must open the actual
listing (`admin/inspect.php`) before approving — the action UI lives on the
inspection page. All moderation transitions write `moderation_logs` and the
target user-facing state is derived from them (suspended accounts map to
`deactivated`). Reports (listings, users) join the same queues; disputes on
transactions resolve through admin actions. Every privileged action is
POST + CSRF + `audit_log()`.

### 6.7 File storage
| Area | Location | Served via |
| --- | --- | --- |
| Listing images | `uploads/` | Static; `.htaccess` blocks script execution; content-checked, random names |
| Chat attachments | `storage/chat/` | `attachment.php` (auth + participant check) |
| Verification evidence | `storage/verifications/` | `admin/evidence.php` (admin only) |
| Simulated SMS / mail | `storage/sms/`, `storage/mail/` | Never served; `Require all denied` |

---

## 7. Security model

| Control | Where |
| --- | --- |
| CSRF on every write | `csrf_field()` / `csrf_check()`; JSON send uses token from POST |
| SQL injection | 100% prepared statements via `q()` |
| XSS | `e()` on all output; JSON API sets `nosniff` |
| AuthZ | `require_login()` / `require_admin()`; ownership re-checked server-side per request (listings, transactions, attachments, evidence) |
| Rate limiting | OTP (DB-time cooldowns/sends/attempts), chat 10/min |
| Secrets | Env vars only (`CF_*`); `config.local.php` gitignored; never commit credentials |
| Payments | Verified only by validated Daraja callback/query; empty token = reject all callbacks |
| Uploads | MIME check, size caps (2 MB images, 3 MB evidence), random filenames, non-executable only, script-blocking `.htaccess` |
| Sessions/headers | Session-based auth; `no-store` on JSON; attachment streaming with private cache |

Known accepted trade-offs: chat polling instead of websockets (simplicity,
shared-hosting compat); procedural style over a framework (deployment
surface and onboarding cost for a solo-maintainer PHP app).

---

## 8. Testing & operations

- `docs/test-cases.md` — manual QA checklist; headless-Chrome (CDP)
  screenshot/measurement scripts used during development for responsive and
  visual verification at 1440/820/390 widths.
- `test-connection.php` — environment smoke check (**delete before
  deploying**).
- `docs/deployment-guide.md` — XAMPP and shared-hosting deployment;
  `docs/marketplace-v2.md` — integration configuration reference.
- Admin seed: `admin@campusfind.local` / `admin123` — **change before any
  real deployment.**

---

## 9. Evolution notes

- Schema changes are additive migrations dated `YYYY-MM-DD-feature.sql`.
- New integrations must follow the existing pattern: env-var config, a
  `*_configured()` gate, a labelled simulated fallback, and fail-closed
  behaviour on partial configuration.
- UI changes follow `docs/02-Design-System.md` (tokens, both themes,
  responsive breakpoints, flash-alert timing).
