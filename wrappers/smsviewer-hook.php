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
$remoteIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

try {
    $result = \FreePBX::Smsviewer()->handleWebhook($raw, $headers, $remoteIp);
    http_response_code((int)$result['status']);
    echo json_encode($result['body']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'message' => 'Unhandled exception'
    ));
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'message' => 'Unhandled exception'
    ));
}