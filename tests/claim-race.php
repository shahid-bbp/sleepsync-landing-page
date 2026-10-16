<?php
// Concurrent-process regression: only one worker may reserve an enquiry.
require dirname(__DIR__) . '/backend-php/public_html/api/lib/security.php';
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__) . '/backend-php/public_html';
try {
    $config = enquiryConfig();
    $previous = claimEnquiry($config, 'fixed-test-fingerprint', 'SS-0123456789ABCDEF');
    echo $previous === null ? 'claimed' : 'duplicate';
} catch (ApiProblem $error) {
    echo (string)$error->status;
}
