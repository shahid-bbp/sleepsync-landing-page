<?php
// LOCAL TEST HARNESS ONLY. Never deploy tests/ or use this router in production.
namespace PHPMailer\PHPMailer {
    function stream_socket_client($address, &$errno, &$errstr, $timeout, $flags, $context) {
        if ($address !== 'ssl://smtp.hostinger.com:465') throw new \RuntimeException('Unexpected SMTP destination');
        // Real PHPMailer and TLS, redirected to the local test SMTP server only.
        \stream_context_set_option($context, 'ssl', 'cafile', getenv('TEST_CERT'));
        \stream_context_set_option($context, 'ssl', 'peer_name', 'localhost');
        return \stream_socket_client('ssl://127.0.0.1:' . getenv('TEST_SMTP_PORT'), $errno, $errstr, 2, $flags, $context);
    }
    function stream_select(&$read, &$write, &$except, $seconds, $microseconds = null) {
        if (trim(file_get_contents(getenv('TEST_MODE'))) === 'timeout') return 0;
        return \stream_select($read, $write, $except, $seconds, $microseconds);
    }
}
namespace {
    if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit;
    }
    // Emulate the web server's HTTPS flag. No such bypass exists in the endpoint.
    $_SERVER['HTTPS'] = isset($_SERVER['HTTP_X_TEST_HTTP']) ? 'off' : 'on';
    require dirname(__DIR__) . '/backend-php/public_html/api/submit-enquiry.php';
}
