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
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'message' => 'Unhandled exception'
    ));
}