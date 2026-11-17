# CampusFind — Product Requirements Document (PRD)

**Version:** 1.0 · **Date:** 2026-11-11 · **Status:** Live (feature-complete, iterating)

---

## 1. Overview

CampusFind is a campus-only marketplace where students buy and sell used items
and student services. It solves a concrete problem: students scattered across
WhatsApp groups, notice boards and word-of-mouth have no trusted, searchable,
single place to trade on campus — and no way to know whether the person on the
other end is a real student or a scammer.

CampusFind replaces that with one app: searchable listings, in-app messaging,
verified identities, transparent trust signals, and an optional M-Pesa payment
path that records exactly what is verified and what is only claimed.

### Product promise

> Your campus. Your marketplace. — Trade with people you can actually verify.

### Design principles

1. **Trust is earned, never asserted.** Public badges come from completed
   checks (real SMS, university email, admin-reviewed student status, or
   transaction history) — never from user claims.
2. **Honest about money.** CampusFind never marks a payment successful itself;
   a payment is *verified* only when Safaricom says so. Everything else is
   labelled "reported".
3. **Private by default.** Phone numbers, registration numbers, evidence
   documents and identity details are never public.
4. **Works without integrations.** No SMS provider, no OAuth, no Daraja
   account? The app still runs — in clearly labelled simulated mode.
5. **Safe by construction.** Every write is POST + CSRF, every query is a
   prepared statement, every output is escaped, and ownership is checked
   server-side on every request.

---

## 2. Problem statement

- Student trading today happens in fragmented WhatsApp groups and campus
  Facebook pages: listings are unsearchable, unverifiable and quickly buried.
- Buyers cannot tell whether a seller is a real student, whether the item
  exists, or whether the price is fair.
- Sellers have no safe channel: sharing a personal phone number publicly
  invites spam and scams.
- Cash-on-delivery disputes have no record: no agreed price, no inspection
  step, no neutral reference for "who completed what".
- University administration has no visibility into scams targeting students.

## 3. Goals and non-goals

### Goals

- A student can list an item or service in under two minutes and have it live
  after a quick admin check.
- A buyer can find what they need (search, filter, sort), judge the seller's
  trustworthiness from public badges, and open a private conversation in-app.
- Two users can record a transaction, pay via M-Pesa when configured, and
  leave each other reviews — with an admin-backed dispute path.
- Admins can moderate listings, review student evidence, resolve reports and
  audit every privileged action.
- The whole system runs on a single XAMPP-class server with zero paid
  dependencies configured by default.

### Non-goals

- CampusFind is **not** an escrow service and does not hold money.
- It is **not** a shipping platform — pickup/delivery is arranged by the
  parties.
- It is **not** multi-university SaaS in v1; one deployment serves one campus
  (`CAMPUS_NAME`), though university-email verification accepts multiple
  domains.
- No native mobile app, no real-time websockets (polling is deliberate — see
  Architecture).

---

## 4. Users and roles

| Role | Description | Key permissions |
| --- | --- | --- |
| **Guest** | Not logged in | Browse public listings; register/login |
| **Student** (default role) | Registered user | CRUD own listings, message, transact, verify phone/email/student status, report |
| **Verified student** | Student with approved `student_verifications` row | Public Student Verified badge; counts toward trust rules |
| **Seller with badges** | Any user with earned badges | Badges shown on listings and public profile |
| **Admin** | `role = admin` | Approve/reject/remove/restore listings, review evidence, manage users (deactivate/suspend), handle reports/disputes, view audit log and reports |

Suspension/deactivation mapping is preserved in history; suspended accounts
cannot log in or act.

---

## 5. Functional requirements

### 5.1 Accounts and authentication

- **FR-A1** Register with full name, email, password (hashed with
  `password_hash`). Duplicate emails rejected.
- **FR-A2** Email verification code (6 digits, 10-minute TTL) sent via SMTP;
  while SMTP is unconfigured the message is written to `storage/mail/` and
  clearly labelled.
- **FR-A3** Login with email + password; session-based auth.
- **FR-A4** Google OAuth login (Google only; the callback URI is fixed at
  `http://localhost/CampusFind/oauth/callback.php`). Facebook was removed.
- **FR-A5** Profile page: full name, avatar, password change. Deactivation by
  the user; suspension only by admin.
- **FR-A6** Every write is POST + CSRF token; sessions hold auth, role and
  flash messages.

### 5.2 Listings (products and services)

- **FR-L1** One listings table for products and services (`listing_type`),
  with categories, title, description, price (KSh), condition and up to 3
  images (JPG/PNG/WebP, 2 MB each, content-checked).
- **FR-L2** Create/edit flows; both go to `pending` and require **admin
  inspection before approval** (admin opens the actual listing before acting).
- **FR-L3** Owner-only actions: edit, delete, mark sold, close, reopen —
  enforced server-side on every request.
- **FR-L4** Marketplace discovery: keyword search, category, type
  (product/service), price range, sort (newest/price), pagination (12 per
  page) via GET params so results are shareable.
- **FR-L5** Listing detail: image gallery, price, seller card with public
  badges, contact links (login required to reveal), and "message seller".
- **FR-L6** Report listing (reason + note) feeding the admin abuse queue.

### 5.3 Verification and trust

- **FR-V1** **Phone OTP**: 6-digit code by SMS (Africa's Talking-compatible
  HTTP API). Rate limits: 60 s resend cooldown, 5 sends/hour, 5 attempts,
  10-minute TTL; times come from the database clock so PHP/MySQL skew cannot
  be exploited. One number per account.
- **FR-V2** Simulated mode: with no SMS provider configured, codes are written
  to `storage/sms/` and the UI says so. A simulated phone **never** earns the
  public badge (`phone_verified_mode = 'dev'`).
- **FR-V3** Sandbox honesty: AT sandbox deliveries go to a simulator, so
  sandbox verification also never earns the badge (`mode = 'sandbox'`). Only
  a production-configured provider yields `mode = 'sms'`.
- **FR-V4** **University email OTP**: 6-digit code by SMTP, restricted to
  approved domains (configurable allowlist, default any `.ac.ke`).
- **FR-V5** **Student status**: full name + registration number + university +
  optional evidence (PDF/JPG/PNG ≤ 3 MB). Admin reviews approve/reject with a
  note. Registration numbers and evidence are never public.
- **FR-V6** Public badges: Verified Phone (real SMS only), Verified Email,
  Verified Student, and **Trusted Seller** — earned when
  ≥ 3 completed transactions AND ≥ 3 reviews AND average rating ≥ 4.0.
- **FR-V7** Public seller profiles: badges, rating, review history, listings.

### 5.4 Messaging

- **FR-M1** Per-listing conversations between exactly two participants
  (buyer + seller), started from the listing page.
- **FR-M2** Polling-based chat (JSON API): new messages, read receipts
  (`read_upto`), unread counts.
- **FR-M3** Image attachments (JPG/PNG/WebP, 2 MB), stored in `storage/chat/`
  (denied over HTTP) and served through an authenticated endpoint that checks
  participant-or-admin on every request.
- **FR-M4** Rate limit: 10 messages/minute per user.
- **FR-M5** Block user (blocks both messaging and conversation), report user.

### 5.5 Transactions and payments

- **FR-T1** A transaction records: listing, buyer, seller, agreed amount,
  inspection state. States flow through inspection → completion (two-sided) →
  review; disputes open an admin path.
- **FR-T2** **Direct payment**: buyer reports cash/other payment; it is stored
  as *reported* — never verified.
- **FR-T3** **M-Pesa STK Push** (Daraja): initiate push; only a validated
  callback or status query marks the payment **verified**. An empty
  `CF_MPESA_CALLBACK_TOKEN` means callbacks are rejected outright (fail
  closed).
- **FR-T4** Reviews are only possible after a completed transaction, one per
  side per transaction.
- **FR-T5** Users see their transaction history; admins see all payments and
  events.

### 5.6 Administration

- **FR-D1** Admin dashboard: counts, queues (pending listings, pending student
  verifications, open reports).
- **FR-D2** Listing moderation: approve/reject/remove/restore with mandatory
  inspection and a moderation log entry.
- **FR-D3** User management: list, search, view, deactivate, suspend, restore.
- **FR-D4** Verification review: view evidence, approve/reject with note.
- **FR-D5** Abuse queues: listing reports and user reports.
- **FR-D6** Transactions/payments views, dispute handling.
- **FR-D7** Audit log: every admin action (who, what, when, target) recorded
  in `admin_audit_logs`.
- **FR-D8** Reports with date filter and print.

---

## 6. Non-functional requirements

| Category | Requirement |
| --- | --- |
| **Security** | CSRF on all writes; prepared statements only; escaped output; server-side ownership/role checks; hashed OTP codes (`password_hash`); rate limits on OTP and chat; authenticated file serving; `.htaccess` denies on `storage/`; fail-closed payment callbacks |
| **Privacy** | Phone visible only after login; registration numbers and evidence never public; attachments private |
| **Performance** | Pagination on all lists; indexed columns on hot paths; chat polling is lightweight JSON; images size-capped |
| **Availability of features** | Core marketplace works with zero external integrations; each integration degrades to a labelled simulated mode |
| **Accessibility** | Focus-visible rings, labelled form controls, aria labels on filter toolbar, sufficient contrast in both themes |
| **Responsive** | Desktop, tablet (≤ 991 px) and mobile (≤ 575 px) layouts verified; messenger, filter bar and cards adapt |
| **Compatibility** | PHP 8 + MySQL (XAMPP default); Chrome/Firefox/Edge current |

---

## 7. Release status and roadmap

**Shipped:** everything in section 5, plus light/dark theming, seeded demo
sellers/listings, and headless-Chrome QA scripts.

**Parked / next candidates:**

- Real SMS delivery pending valid Africa's Talking production credentials
  (current key rejected with HTTP 401; sandbox-only today).
- Seller payout analytics and price-history hints.
- Listing "boosts" or category pinning (requires moderation policy first).
- Email digests (unread messages, expiring listings).

---

## 8. Open decisions

| Decision | Current answer | Revisit when |
| --- | --- | --- |
| Real-time chat transport | JSON polling (~few s interval) | Traffic grows or typing indicators are demanded |
| Multi-campus | Single campus per deployment | A second campus actually onboards |
| Payment scope | STK Push + callback verification | Refunds/API reversals become a real need |
