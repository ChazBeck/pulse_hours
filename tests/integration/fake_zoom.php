<?php
/**
 * Test-only stand-in for Zoom's Incoming Webhook (run with php -S by run.sh).
 * Appends each request to /t/zoom-requests.jsonl and answers like Zoom: a bare
 * "OK" — or HTTP 500 while /t/zoom-mode says "down".
 */
file_put_contents('/t/zoom-requests.jsonl', json_encode([
    'uri' => $_SERVER['REQUEST_URI'],
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? '',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    'body' => json_decode((string) file_get_contents('php://input'), true),
], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

if (trim((string) @file_get_contents('/t/zoom-mode')) === 'down') {
    http_response_code(500);
    echo 'zoom is down';
    return;
}
echo 'OK';
