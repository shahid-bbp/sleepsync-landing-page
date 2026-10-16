<?php
declare(strict_types=1);

final class ApiProblem extends RuntimeException
{
    public function __construct(public int $status, public string $apiCode, string $message, public int $retryAfter = 0)
    {
        parent::__construct($message);
    }
}

function privatePath(string $path): bool
{
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $resolved = realpath($path);
    if ($root === false || $resolved === false) {
        return false;
    }
    $root = strtolower(str_replace('\\', '/', $root));
    $resolved = strtolower(str_replace('\\', '/', $resolved));
    return $resolved !== $root && !str_starts_with($resolved, rtrim($root, '/') . '/');
}

function enquiryConfig(): array
{
    $base = dirname(__DIR__, 3);
    $config = [
        'smtp_host' => 'smtp.hostinger.com', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
        'smtp_username' => 'web@thesleepsync.in', 'smtp_password' => '',
        'mail_to' => 'web@thesleepsync.in', 'mail_from' => 'web@thesleepsync.in',
        'mail_from_name' => 'SleepSync Franchise Enquiries',
        'allowed_origins' => ['https://shahid-bbp.github.io', 'https://thesleepsync.in'],
        'storage_path' => $base . '/data', 'rate_limit_max' => 5,
        'rate_limit_window' => 900, 'global_rate_limit_max' => 100, 'daily_mail_limit' => 100,
    ];
    $path = getenv('SLEEPSYNC_CONFIG_PATH') ?: $base . '/config/sleepsync-config.php';
    if (is_file($path)) {
        if (!privatePath($path)) {
            throw new RuntimeException('Private config location invalid.');
        }
        $loaded = require $path;
        if (!is_array($loaded)) {
            throw new RuntimeException('Invalid configuration.');
        }
        $config = array_replace($config, $loaded);
    } elseif (getenv('SLEEPSYNC_CONFIG_PATH')) {
        throw new RuntimeException('Configured file unavailable.');
    }
    foreach (['SMTP_HOST', 'SMTP_PORT', 'SMTP_ENCRYPTION', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'MAIL_TO', 'MAIL_FROM', 'MAIL_FROM_NAME', 'STORAGE_PATH'] as $name) {
        $value = getenv($name);
        if ($value !== false) {
            $config[strtolower($name)] = $name === 'SMTP_PORT' ? (int)$value : $value;
        }
    }
    if (getenv('ALLOWED_ORIGINS') !== false) {
        $config['allowed_origins'] = array_map('trim', explode(',', getenv('ALLOWED_ORIGINS')));
    }
    if (!is_array($config['allowed_origins']) || !$config['allowed_origins']) {
        throw new RuntimeException('Missing origins.');
    }
    foreach ($config['allowed_origins'] as $origin) {
        if (!is_string($origin) || !preg_match('~\Ahttps://[a-z0-9.-]+(?::[0-9]{1,5})?\z~', $origin)) {
            throw new RuntimeException('Invalid origin configuration.');
        }
    }
    return $config;
}

function checkMailConfig(array $config): void
{
    if ($config['smtp_host'] !== 'smtp.hostinger.com' || (int)$config['smtp_port'] !== 465 ||
        $config['smtp_encryption'] !== 'ssl' || $config['smtp_username'] !== 'web@thesleepsync.in' ||
        $config['mail_from'] !== 'web@thesleepsync.in' || $config['mail_to'] !== 'web@thesleepsync.in' ||
        !is_string($config['smtp_password']) || $config['smtp_password'] === '' ||
        str_starts_with($config['smtp_password'], 'REPLACE_') || !extension_loaded('openssl')) {
        throw new ApiProblem(503, 'SERVICE_UNAVAILABLE', 'The enquiry service is unavailable. Please contact web@thesleepsync.in.');
    }
    foreach (['rate_limit_max', 'rate_limit_window', 'global_rate_limit_max', 'daily_mail_limit'] as $key) {
        if (!is_int($config[$key]) || $config[$key] < 1 || $config[$key] > 86400) {
            throw new RuntimeException('Invalid limit.');
        }
    }
}

/** Short, exclusive read/modify/write transaction. SMTP never runs under this lock. */
function enquiryState(array $config, callable $change): mixed
{
    $dir = $config['storage_path'];
    if (!is_string($dir) || (!is_dir($dir) && !mkdir($dir, 0700, true)) || !privatePath($dir)) {
        throw new RuntimeException('Private storage unavailable.');
    }
    $file = $dir . '/submission-state.json';
    $handle = fopen($dir . '/submission-state.lock', 'c+');
    if ($handle === false) {
        throw new RuntimeException('Storage unavailable.');
    }
    try {
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            throw new ApiProblem(503, 'SERVICE_BUSY', 'The enquiry service is busy. Please try again shortly.', 5);
        }
        $exists = is_file($file);
        $raw = $exists ? file_get_contents($file, false, null, 0, 1048577) : '';
        if ($raw === false || strlen($raw) > 1048576) {
            throw new RuntimeException('Invalid storage size.');
        }
        $state = !$exists ? ['ips' => [], 'attempts' => [], 'mailAttempts' => [], 'submissions' => []] : json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        foreach (['ips', 'attempts', 'mailAttempts', 'submissions'] as $key) {
            if (!isset($state[$key]) || !is_array($state[$key])) {
                throw new RuntimeException('Invalid storage state.');
            }
        }
        $now = time();
        foreach ($state['ips'] as $key => $times) {
            $state['ips'][$key] = array_values(array_filter($times, fn($t) => $t > $now - $config['rate_limit_window']));
            if (!$state['ips'][$key]) unset($state['ips'][$key]);
        }
        $state['attempts'] = array_values(array_filter($state['attempts'], fn($t) => $t > $now - $config['rate_limit_window']));
        $state['mailAttempts'] = array_values(array_filter($state['mailAttempts'], fn($t) => $t > $now - 86400));
        foreach ($state['submissions'] as $key => $entry) {
            if ($entry['expires'] <= $now) unset($state['submissions'][$key]);
        }
        $result = $change($state, $now);
        $encoded = json_encode($state, JSON_THROW_ON_ERROR);
        // Replace atomically under a separate stable lock; a crash cannot leave a
        // truncated state file that silently resets rate limits/deduplication.
        if (strlen($encoded) > 1048576 || file_put_contents($file . '.tmp', $encoded) !== strlen($encoded) ||
            !rename($file . '.tmp', $file)) {
            throw new RuntimeException('Storage write failed.');
        }
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function limitAttempts(array $config): void
{
    // REMOTE_ADDR only: never trust a caller-supplied X-Forwarded-For header.
    $ip = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $config['smtp_password']);
    enquiryState($config, function (&$state, $now) use ($config, $ip) {
        $times = $state['ips'][$ip] ?? [];
        if (count($times) >= $config['rate_limit_max'] || count($state['attempts']) >= $config['global_rate_limit_max']) {
            $start = count($times) >= $config['rate_limit_max'] ? min($times) : min($state['attempts']);
            throw new ApiProblem(429, 'RATE_LIMITED', 'Too many attempts. Please wait before trying again.', max(1, $start + $config['rate_limit_window'] - $now));
        }
        $state['ips'][$ip][] = $now;
        $state['attempts'][] = $now;
    });
}

function claimEnquiry(array $config, string $fingerprint, string $reference): ?array
{
    return enquiryState($config, function (&$state, $now) use ($config, $fingerprint, $reference) {
        if (isset($state['submissions'][$fingerprint])) {
            $previous = $state['submissions'][$fingerprint];
            if ($previous['status'] === 'sent') return $previous;
            throw new ApiProblem(409, 'SUBMISSION_PENDING', 'This enquiry is processing or its result is uncertain. Please contact web@thesleepsync.in before resubmitting.', max(1, $previous['expires'] - $now));
        }
        if (count($state['mailAttempts']) >= $config['daily_mail_limit']) {
            throw new ApiProblem(429, 'RATE_LIMITED', 'The enquiry service has reached its daily limit. Please contact web@thesleepsync.in.', max(1, min($state['mailAttempts']) + 86400 - $now));
        }
        $state['mailAttempts'][] = $now;
        $state['submissions'][$fingerprint] = ['status' => 'pending', 'reference' => $reference, 'expires' => $now + 900];
        return null;
    });
}

function finishEnquiry(array $config, string $fingerprint): void
{
    enquiryState($config, function (&$state, $now) use ($fingerprint) {
        $state['submissions'][$fingerprint]['status'] = 'sent';
        $state['submissions'][$fingerprint]['expires'] = $now + 86400;
    });
}
