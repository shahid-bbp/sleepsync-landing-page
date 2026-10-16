<?php
declare(strict_types=1);
// Copy ONLY on Hostinger to <parent of public_html>/config/sleepsync-config.php.
// Never commit the private copy. See docs/HOSTINGER-PHP-DEPLOYMENT.md.
return [
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl',
    'smtp_username' => 'web@thesleepsync.in',
    'smtp_password' => 'REPLACE_WITH_PRIVATE_MAILBOX_PASSWORD',
    'mail_to' => 'web@thesleepsync.in',
    'mail_from' => 'web@thesleepsync.in',
    'mail_from_name' => 'SleepSync Franchise Enquiries',
    // Exact origins: no paths, wildcards or trailing slashes.
    'allowed_origins' => ['https://shahid-bbp.github.io', 'https://thesleepsync.in'],
    'storage_path' => dirname(__DIR__) . '/data',
    'rate_limit_max' => 5,
    'rate_limit_window' => 900,
    'global_rate_limit_max' => 100,
    'daily_mail_limit' => 100,
];
