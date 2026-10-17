<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

// No user data, SMTP transcripts, credentials or exception messages in responses/logs.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL);
umask(0077);
ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Vary: Origin');

function respond(int $status, string $code, string $message, array $extra = []): never
{
    if (ob_get_length() !== false) ob_clean();
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $status === 200, 'code' => $code, 'message' => $message], $extra), JSON_THROW_ON_ERROR);
    exit;
}
set_error_handler(static function ($severity, $message, $file, $line): never {
    throw new ErrorException('Internal operation failed.', 0, $severity, $file, $line);
});

$reference = null;
try {
    require_once __DIR__ . '/lib/security.php';
    require_once __DIR__ . '/lib/validation.php';
    require_once __DIR__ . '/lib/email-template.php';
    // HTTPS must be asserted by the web server, never by arbitrary proxy headers.
    if (strtolower((string)($_SERVER['HTTPS'] ?? '')) !== 'on' &&
        (string)($_SERVER['HTTPS'] ?? '') !== '1' && (int)($_SERVER['SERVER_PORT'] ?? 0) !== 443) {
        respond(403, 'HTTPS_REQUIRED', 'Please use HTTPS for enquiry submissions.');
    }
    $config = enquiryConfig();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!in_array($origin, $config['allowed_origins'], true)) {
        respond(403, 'FORBIDDEN_ORIGIN', 'This website origin is not permitted.');
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Expose-Headers: Retry-After');
    header('Access-Control-Max-Age: 600');
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'OPTIONS') {
        if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? '') !== 'POST') {
            respond(405, 'METHOD_NOT_ALLOWED', 'Only POST submissions are accepted.');
        }
        $requested = array_filter(array_map('trim', explode(',', strtolower($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? ''))));
        if (array_diff($requested, ['content-type', 'accept'])) {
            respond(403, 'FORBIDDEN_HEADERS', 'The requested headers are not permitted.');
        }
        http_response_code(204);
        exit;
    }
    if ($method !== 'POST') {
        header('Allow: POST, OPTIONS');
        respond(405, 'METHOD_NOT_ALLOWED', 'Only POST submissions are accepted.');
    }
    checkMailConfig($config);
    limitAttempts($config);
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json' ||
        !empty($_SERVER['HTTP_CONTENT_ENCODING'])) {
        respond(415, 'UNSUPPORTED_MEDIA_TYPE', 'Send an uncompressed application/json request.');
    }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
        respond(413, 'PAYLOAD_TOO_LARGE', 'The enquiry exceeds the 32 KiB request limit.');
    }
    // Bound the actual read as well as Content-Length, including chunked requests.
    $raw = file_get_contents('php://input', false, null, 0, 32769);
    if ($raw === false || strlen($raw) > 32768) {
        respond(413, 'PAYLOAD_TOO_LARGE', 'The enquiry exceeds the 32 KiB request limit.');
    }
    try {
        $decoded = json_decode($raw, false, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        respond(400, 'INVALID_JSON', 'The enquiry must be a valid JSON object.');
    }
    if (!$decoded instanceof stdClass) {
        respond(400, 'INVALID_JSON', 'The enquiry must be a valid JSON object.');
    }
    $data = (array)$decoded;
    if (!isset($data['website']) || !is_string($data['website']) || $data['website'] !== '') {
        respond(422, 'SUBMISSION_REJECTED', 'The enquiry could not be accepted. Please reload the page and try again.');
    }
    [$clean, $errors] = validateEnquiry($data);
    if ($errors) {
        respond(422, 'VALIDATION_FAILED', 'Please check the highlighted field and try again.', ['errors' => $errors]);
    }
    if (is_file(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    } else {
        require_once __DIR__ . '/PHPMailer/Exception.php';
        require_once __DIR__ . '/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/SMTP.php';
    }
    $reference = 'SS-' . strtoupper(bin2hex(random_bytes(8)));
    $fingerprint = hash_hmac('sha256', json_encode($clean, JSON_THROW_ON_ERROR), $config['smtp_password']);
    $previous = claimEnquiry($config, $fingerprint, $reference);
    if ($previous !== null) {
        respond(200, 'SMTP_ACCEPTED', 'Your enquiry has already been submitted successfully.', ['reference' => $previous['reference']]);
    }
    [$html, $plain] = enquiryEmail($clean, $reference, gmdate('Y-m-d H:i:s') . ' UTC');
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->Port = 465;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAuth = true;
        $mail->SMTPDebug = 0;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->Timeout = 15;
        $mail->getSMTPInstance()->Timelimit = 30;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('web@thesleepsync.in', 'SleepSync Franchise Enquiries');
        $mail->addAddress('web@thesleepsync.in');
        $mail->addReplyTo($clean['email'], $clean['fullName']);
        $mail->isHTML(true);
        $mail->Subject = 'SleepSync franchise enquiry';
        $mail->Body = $html;
        $mail->AltBody = $plain;
        if (!$mail->send()) throw new RuntimeException('Mail not accepted.');
    } catch (Throwable $error) {
        // Keep the pending reservation for 15 minutes: a lost SMTP acknowledgement
        // can mean delivery happened. Never automatically resend in this window.
        error_log('SleepSync EMAIL_SEND_FAILED ' . $reference);
        respond(502, 'EMAIL_SEND_FAILED', 'We could not confirm email submission. Your details are retained on this page. Please contact web@thesleepsync.in before retrying.');
    }
    finishEnquiry($config, $fingerprint);
    respond(200, 'SMTP_ACCEPTED', 'Thank you! Your franchise enquiry has been submitted successfully.', ['reference' => $reference]);
} catch (ApiProblem $error) {
    if ($error->retryAfter > 0) header('Retry-After: ' . $error->retryAfter);
    respond($error->status, $error->apiCode, $error->getMessage());
} catch (Throwable $error) {
    error_log('SleepSync SERVICE_UNAVAILABLE' . ($reference ? ' ' . $reference : ''));
    respond(503, 'SERVICE_UNAVAILABLE', 'The enquiry service is unavailable. Please contact web@thesleepsync.in.');
}
