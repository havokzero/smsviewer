<?php

header('Content-Type: application/json');

if (!file_exists('/etc/freepbx.conf')) {
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'message' => 'FreePBX bootstrap not found'
    ));
    exit;
}

require_once '/etc/freepbx.conf';

$raw = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : array();
$remoteIp = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';

file_put_contents(
    '/tmp/smsviewer-webhook.log',
    date('c') . ' IP=' . $remoteIp . ' RAW=' . $raw . PHP_EOL,
    FILE_APPEND
);

try {
    $result = \FreePBX::Smsviewer()->handleWebhook($raw, $headers, $remoteIp);

    $status = isset($result['status']) ? (int)$result['status'] : 500;
    $body = isset($result['body']) && is_array($result['body'])
        ? $result['body']
        : array(
            'status' => 'error',
            'message' => 'Invalid webhook response'
        );

    http_response_code($status);
    echo json_encode($body);
} catch (\Throwable $e) {
    file_put_contents(
        '/tmp/smsviewer-webhook-error.log',
        date('c')
        . ' MESSAGE=' . $e->getMessage()
        . ' FILE=' . $e->getFile()
        . ' LINE=' . $e->getLine()
        . PHP_EOL
        . $e->getTraceAsString()
        . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ));
}