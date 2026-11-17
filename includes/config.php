<?php
// Local overrides live in includes/config.local.php (gitignored). Use
// putenv('CF_...=...') there; never put real credentials in this file.
if (is_file(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';

// Edit these for your environment (XAMPP defaults shown).
define('DB_HOST', 'localhost');
define('DB_NAME', 'campusfind');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'CampusFind');
define('CAMPUS_NAME', 'Main Campus');   // shown on cards and seller details
define('MAX_IMAGES', 3);
define('MAX_IMAGE_BYTES', 2 * 1024 * 1024);
define('PER_PAGE', 12);

// ---- Google social login (OAuth) -------------------------------------------
// Leave empty to keep the button disabled. The redirect/callback URL to
// register is: http://localhost/CampusFind/oauth/callback.php
// (it follows BASE_URL below, so match the host you actually browse with).
//
// Google -> https://console.cloud.google.com/apis/credentials
// Set CF_GOOGLE_CLIENT_ID / CF_GOOGLE_CLIENT_SECRET in config.local.php.
define('GOOGLE_CLIENT_ID', getenv('CF_GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('CF_GOOGLE_CLIENT_SECRET') ?: '');

// ---- Email verification (SMTP) ---------------------------------------------
// Verification codes are emailed through SMTP. While these are empty the app
// writes the message to storage/mail/ instead, which is handy on XAMPP where
// PHP cannot send mail. For Gmail, enable 2-Step Verification and create an
// App password (myaccount.google.com/apppasswords); use that as SMTP_PASS.
// Any transactional provider (Brevo, Resend, Mailtrap) works the same way.
define('SMTP_HOST', getenv('CF_SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('CF_SMTP_PORT') ?: 587)); // 587 = STARTTLS, 465 = SSL
define('SMTP_SECURE', getenv('CF_SMTP_SECURE') ?: 'tls'); // 'tls', 'ssl' or '' for none
define('SMTP_USER', getenv('CF_SMTP_USER') ?: '');        // full mailbox address
define('SMTP_PASS', getenv('CF_SMTP_PASS') ?: '');        // app password, never the account password
define('SMTP_FROM', SMTP_USER ?: 'no-reply@campusfind.local');
define('SMTP_FROM_NAME', APP_NAME);

// ---- Seller verification ----------------------------------------------------
// University email domains. Set CF_UNIVERSITY_DOMAINS as a comma-separated
// list (e.g. "students.uonbi.ac.ke,student.mmu.ac.ke"). When empty, any
// subdomain of .ac.ke is accepted; that fallback is not a claim of affiliation.
define('UNIVERSITY_EMAIL_DOMAINS', array_values(array_filter(array_map(
    static fn(string $d): string => strtolower(trim($d)),
    explode(',', getenv('CF_UNIVERSITY_DOMAINS') ?: '')
))));
define('UNIVERSITY_EMAIL_SUFFIX', '.ac.ke');
define('STUDENT_EVIDENCE_BYTES', 3 * 1024 * 1024);

define('OTP_TTL', 600);          // code lifetime, seconds
define('OTP_RESEND_WAIT', 60);   // seconds between sends
define('OTP_MAX_ATTEMPTS', 5);
define('OTP_MAX_SENDS', 5);      // per hour

// ---- SMS (Africa's Talking HTTP API) ---------------------------------------
// Leave CF_SMS_PROVIDER empty to run phone verification in simulated mode:
// the code is written to storage/sms/ and the UI says so. A simulated phone
// is never shown as publicly verified.
//
// Required for real delivery: CF_SMS_PROVIDER=africastalking, CF_SMS_USERNAME,
// CF_SMS_API_KEY. Set CF_SMS_ENV=sandbox while testing (uses the sandbox host),
// then production. CF_SMS_SENDER is your approved sender ID or short code.
// Put these in includes/config.local.php (gitignored) or real environment vars.
define('SMS_PROVIDER', strtolower(getenv('CF_SMS_PROVIDER') ?: ''));
define('SMS_ENV', strtolower(getenv('CF_SMS_ENV') ?: 'production'));
define('SMS_API_KEY', getenv('CF_SMS_API_KEY') ?: '');
define('SMS_USERNAME', getenv('CF_SMS_USERNAME') ?: '');
define('SMS_SENDER', getenv('CF_SMS_SENDER') ?: '');
define('SMS_ENDPOINT', getenv('CF_SMS_ENDPOINT') ?: (SMS_ENV === 'sandbox'
    ? 'https://api.sandbox.africastalking.com/version1/messaging'
    : 'https://api.africastalking.com/version1/messaging'));

// ---- M-Pesa Daraja ---------------------------------------------------------
// Credentials must come from environment variables; nothing is hardcoded.
// Sandbox: https://developer.safaricom.co.ke/  (shortcode 174379 + test passkey)
define('MPESA_ENV', getenv('CF_MPESA_ENV') ?: 'sandbox');
define('MPESA_CONSUMER_KEY', getenv('CF_MPESA_CONSUMER_KEY') ?: '');
define('MPESA_CONSUMER_SECRET', getenv('CF_MPESA_CONSUMER_SECRET') ?: '');
define('MPESA_SHORTCODE', getenv('CF_MPESA_SHORTCODE') ?: '');
define('MPESA_PASSKEY', getenv('CF_MPESA_PASSKEY') ?: '');
define('MPESA_TRANSACTION_TYPE', getenv('CF_MPESA_TRANSACTION_TYPE') ?: 'CustomerPayBillOnline');
// Shared secret appended to the callback URL. When empty, callbacks are rejected.
define('MPESA_CALLBACK_TOKEN', getenv('CF_MPESA_CALLBACK_TOKEN') ?: '');

// ---- Trust rules (transparent, shown on seller profiles) -------------------
define('TRUSTED_MIN_COMPLETED', 3);
define('TRUSTED_MIN_RATING_COUNT', 3);
define('TRUSTED_MIN_RATING', 4.0);

// ---- Chat -----------------------------------------------------------------
define('CHAT_IMAGE_BYTES', 2 * 1024 * 1024);
define('CHAT_RATE_LIMIT', 10);   // messages per minute per user

// URL path of the project (auto-detected, e.g. "/CampusFind" or "" on a domain root).
$__root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$__proj = realpath(__DIR__ . '/..') ?: '';
define('BASE_URL', $__root && strpos($__proj, $__root) === 0
    ? str_replace('\\', '/', substr($__proj, strlen($__root))) : '');

// Absolute base URL used in "Contact seller" messages and link previews.
// Leave empty to use the address in the browser (http://localhost/...).
// Set a reachable address when sharing, e.g. an ngrok tunnel or LAN IP:
//   define('SHARE_BASE_URL', 'https://abcd.ngrok-free.app/CampusFind');
define('SHARE_BASE_URL', '');
