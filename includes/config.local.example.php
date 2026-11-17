<?php
// Copy this file to includes/config.local.php and fill in your own values.
// config.local.php is gitignored and loaded before the app constants.
// Never commit real credentials or paste them into tracked files.

// Google social login (callback: http://localhost/CampusFind/oauth/callback.php)
putenv('CF_GOOGLE_CLIENT_ID=your-google-client-id');
putenv('CF_GOOGLE_CLIENT_SECRET=your-google-client-secret');

// SMTP for verification codes (Gmail: use a 16-character App Password)
putenv('CF_SMTP_HOST=smtp.gmail.com');
putenv('CF_SMTP_PORT=587');                           // 587 = STARTTLS, 465 = SSL
putenv('CF_SMTP_SECURE=tls');
putenv('CF_SMTP_USER=you@gmail.com');
putenv('CF_SMTP_PASS=your-app-password');

// Africa's Talking
putenv('CF_SMS_PROVIDER=africastalking');
putenv('CF_SMS_ENV=sandbox');                         // sandbox | production
putenv('CF_SMS_USERNAME=your-africastalking-username');
putenv('CF_SMS_API_KEY=your-africastalking-api-key');
putenv('CF_SMS_SENDER=your-sender-id');               // approved sender ID or short code

// Optional university-domain allowlist (empty accepts any .ac.ke address)
// putenv('CF_UNIVERSITY_DOMAINS=students.uonbi.ac.ke,student.mmu.ac.ke');
