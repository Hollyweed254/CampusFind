# Marketplace v2: verification, messaging, transactions and M-Pesa

This release adds seller verification, trust badges, internal messaging, recorded
transactions and optional Safaricom Daraja payments to the existing CampusFind
app. Existing authentication, listings, moderation and admin features are
unchanged.

## 1. Database

Existing installations apply the additive migration once:

```bash
mysql -u root campusfind < database/migrations/2026-11-11-marketplace-v2.sql
```

`2026-11-11-marketplace-v2.sql` uses plain `ALTER TABLE`, so it is apply-once.
Record that it ran (for example in your deployment notes) before running it
again. Fresh installations import `database/campusfind.sql`, which already
contains every new table and column.

New tables: `verification_otps`, `student_verifications`, `conversations`,
`messages`, `message_attachments`, `blocked_users`, `transactions`, `payments`,
`payment_events`, `transaction_reviews`, `user_reports`, `admin_audit_logs`.

New `users` columns: `phone_verified_at`, `phone_verified_mode`,
`university_email` (unique), `university_email_verified_at`,
`student_verified_at`, `trusted_seller_at`, `suspended_at`,
`suspension_reason`. The `status` enum gains `suspended`.

To undo the feature set, back up first and run the destructive rollback:

```bash
mysqldump -u root campusfind > campusfind-before-rollback.sql
mysql -u root campusfind < database/migrations/2026-11-11-marketplace-v2-down.sql
```

The rollback drops all v2 tables and columns, including chat and payment
history, and maps suspended accounts to `deactivated`.

## 2. Configuration

All new integrations read environment variables. Nothing below is required for
the rest of the marketplace to work.

| Variable | Purpose |
| --- | --- |
| `CF_UNIVERSITY_DOMAINS` | Comma-separated allowed domains, e.g. `uonbi.ac.ke,strathmore.edu`. When empty, any `.ac.ke` address is accepted. |
| `CF_SMS_PROVIDER` | Set to `africastalking` (or `at`) for real SMS. Leave empty for simulated mode. |
| `CF_SMS_ENV` | `production` (default) or `sandbox`; selects the Africa's Talking host. |
| `CF_SMS_API_KEY` | Africa's Talking API key. |
| `CF_SMS_USERNAME` | Africa's Talking app username. |
| `CF_SMS_SENDER` | Approved sender ID or short code (optional, recommended for production). |
| `CF_SMS_ENDPOINT` | Optional full override of the messaging endpoint. |
| `CF_MPESA_ENV` | `sandbox` (default) or `production`. |
| `CF_MPESA_CONSUMER_KEY` | Daraja app consumer key. |
| `CF_MPESA_CONSUMER_SECRET` | Daraja app consumer secret. |
| `CF_MPESA_SHORTCODE` | Paybill / till short code. Sandbox test value is `174379`. |
| `CF_MPESA_PASSKEY` | Daraja Lipa na M-Pesa passkey. |
| `CF_MPESA_TRANSACTION_TYPE` | Defaults to `CustomerPayBillOnline`. |
| `CF_MPESA_CALLBACK_TOKEN` | Random shared secret appended to the callback URL. Empty means callbacks are rejected. |

For local setup, copy `includes/config.local.example.php` to
`includes/config.local.php` and fill in the values. That file is gitignored and
is loaded before the configuration constants. On shared hosting, use the
control panel's environment variable feature or an equivalent untracked local
include. Never commit real credentials.

### SMS behaviour

Without a provider the app writes clearly labelled `SIMULATED SMS` files to
`storage/sms/`. The UI tells the user that SMS is simulated, and a simulated
phone number **does not** earn the public Phone Verified badge. If a provider
is selected but its username or API key is missing, the app refuses to send
and shows a configuration error instead of silently simulating.

Africa's Talking's sandbox is also simulated: messages appear in the sandbox
simulator, are not delivered to handsets, and never earn the public badge.
Only `CF_SMS_ENV=production` with a live app counts as real SMS.

### Africa's Talking setup

1. Create an account and an app at <https://africastalking.com>.
2. Copy the app **username** and **API key** from the dashboard.
3. For testing, set `CF_SMS_ENV=sandbox` and add your phone as a sandbox test
   recipient. For production, register an alphanumeric sender ID or short code
   and set it as `CF_SMS_SENDER`.
4. Create `includes/config.local.php` from the example file and enter the
   provider, environment, username, API key and sender.
5. Open **Verification** while logged in. In production the simulated notice
   disappears and a phone number is marked verified only after Africa's
   Talking accepts the message and the user enters the correct code. In the
   sandbox, read the code in the Africa's Talking simulator; the number is
   recorded as simulated and does not earn the public badge.

### M-Pesa setup

1. Create an app at <https://developer.safaricom.co.ke/>, select the sandbox,
   and copy the consumer key/secret.
2. Use the sandbox short code `174379` and its test passkey.
3. Set a long random `CF_MPESA_CALLBACK_TOKEN`.
4. Expose the app over HTTPS. Daraja cannot call `localhost`. For local testing
   use a tunnel such as ngrok and set `SHARE_BASE_URL` in
   `includes/config.php` to the tunnel address, e.g.
   `https://abcd.ngrok-free.app/CampusFind`.
5. The generated callback is
   `https://<host>/<base>/api/mpesa-callback.php?token=<CF_MPESA_CALLBACK_TOKEN>`.
6. The buyer enters a Safaricom number on the transaction page. The app sends
   an STK Push, and the transaction becomes paid **only** when Daraja returns
   a successful callback or a successful status query with a matching amount.

Direct (cash / manual) payments stay as *reported* by the buyer or seller.
They are never labelled "verified". M-Pesa payments display "Payment verified
by M-Pesa" and store the receipt.

## 3. Verification and trust

- **Phone verified** — 6-digit SMS code, 10-minute expiry, 60-second resend
  cooldown, 5 sends/hour, 5 attempts. Kenyan numbers are normalised to
  `+2547…` / `+2541…`. One number can be verified on one account.
- **Email verified** — the existing registration email flow.
- **Verified student** — the user verifies a university email, then submits
  full name, registration number, university and optional evidence. An admin
  approves or rejects it. Registration numbers and evidence are never public;
  evidence is served only through the admin-only proxy.
- **Trusted seller** — computed from real activity: at least 3 completed
  transactions, at least 3 reviews, average rating at least 4.0, no open abuse
  reports and no unresolved disputes. The public profile shows these rules.
- New sellers are labelled "New seller". There are no arbitrary scores or
  manually assigned trust levels.

## 4. Messaging

- "Message seller" on a listing opens one conversation per listing and buyer.
- Messages are stored server-side and polled every 4 seconds; there are no
  WebSockets.
- Only the buyer, the seller and (for abuse review) an admin can read a
  conversation. Attachments are served through `attachment.php`, never
  directly from disk.
- Text is limited to 2,000 characters and 10 messages per minute per sender.
  Attachments must be JPG, PNG or WebP, verified by MIME and `getimagesize`,
  and are limited to 2 MB.
- Users can block each other and report a conversation or profile. Blocking
  prevents new messages. Suspended or deactivated participants cannot send.
- `storage/` is denied over HTTP by `.htaccess`. Nginx hosts must add an
  equivalent deny rule.

## 5. Transactions

1. The buyer records an agreed amount from an existing conversation.
2. The buyer confirms inspection; only then does payment open.
3. The buyer either reports a direct payment or starts M-Pesa.
4. Both sides confirm completion. The listing becomes sold and the seller's
   trust is refreshed.
5. Either party can open a dispute with a written reason; admins resolve it as
   completed or cancelled.
6. Reviews open only after completion, one per transaction, 1-5 stars.

## 6. Admin

New admin pages: Verification, Abuse reports, Transactions, Payments and Audit
log, plus account checks on the Users page. All admin actions require the
`admin` role, POST and CSRF, and are written to `admin_audit_logs`.
Administrators can approve/reject student requests, suspend/reinstate
accounts, resolve disputes and recheck pending M-Pesa payments. There is no
button to mark a payment paid.

## 7. Tests and limits

Backend checks (131 assertions against disposable rows, cleaned up afterwards)
cover: phone normalisation and duplicate prevention, OTP cooldown/attempts/
expiry, university-domain rules, simulated-phone badge suppression, student
review, conversation reuse and participant-only access, blocking, rate limits,
attachment rejection, reporting, transaction state transitions, completion,
reviews, trust thresholds, callback token rejection, amount-mismatch
rejection, idempotent callbacks, cancellation/timeout/failure handling and
audit logging. HTTP checks cover login gating, RBAC (403 for non-admins),
admin pages, CSRF rejection, admin approve/suspend/dispute forms,
attachment/evidence access and direct `storage/` access denial.

Browser checks (Chrome at 1440px and 390x844, light and dark themes) cover
marketplace, listing, seller, messages, transaction, verification, profile and
the admin verification, abuse, transactions and payments pages. Admin tables
scroll horizontally on phones; bulk admin work remains desktop-oriented.

Known limits: SMS delivery needs a provider account; M-Pesa needs Daraja
credentials and a public HTTPS callback; direct payments are trust-based
between users; chat is polling-based, not real-time WebSockets.
