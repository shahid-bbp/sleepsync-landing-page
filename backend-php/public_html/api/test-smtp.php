<?php
// Retired public diagnostic. Upload this inert replacement over any older copy.
http_response_code(404);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
echo '{"ok":false,"code":"NOT_FOUND","message":"Not found."}';
