<?php

namespace FreePBX\modules\Smsviewer\Api;

class Webhook
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    public function handle($rawBody, array $headers = array(), $remoteIp = '')
    {
        if (!$this->module->isWebhookEnabled()) {
            return $this->response(403, array(
                'status' => 'error',
                'message' => 'Webhook disabled',
            ));
        }

        if (!$this->isIpAllowed($remoteIp)) {
            return $this->response(403, array(
                'status' => 'error',
                'message' => 'Source IP not allowed',
            ));
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            return $this->response(400, array(
                'status' => 'error',
                'message' => 'Invalid JSON payload',
            ));
        }

        if (
            !$this->isTokenValid($headers) &&
            !$this->isQueryTokenValid() &&
            !$this->looksLikeFlowroutePayload($data)
        ) {
            return $this->response(403, array(
                'status' => 'error',
                'message' => 'Invalid token',
            ));
        }

        if ($this->looksLikeFlowroutePayload($data)) {
            if ($this->looksLikeFlowrouteDlr($data)) {
                return $this->handleFlowrouteStatusCallback($data, $rawBody);
            }

            return $this->handleFlowrouteInboundMessage($data, $rawBody);
        }

        if ($this->looksLikeLegacyStatusCallback($data)) {
            return $this->handleLegacyStatusCallback($data, $rawBody);
        }

        return $this->handleLegacyInboundMessage($data, $rawBody);
    }

    private function handleFlowrouteInboundMessage(array $data, $rawBody)
    {
        $normalized = $this->normalizeFlowrouteInboundPayload($data);
        if (!$normalized['ok']) {
            return $this->response(400, array(
                'status' => 'error',
                'message' => $normalized['error'],
            ));
        }

        $id = $this->module->insertInboundMessage(
            $normalized['sender'],
            $normalized['receiver'],
            $normalized['message'],
            $normalized['provider_ref'],
            $rawBody,
            $normalized['status']
        );

        $this->sendDiscordNotification(
            $normalized['sender'],
            $normalized['receiver'],
            $normalized['message'],
            $normalized['provider_ref'],
            'flowroute'
        );

        return $this->response(200, array(
            'status' => 'ok',
            'message_id' => $id,
            'provider' => 'flowroute',
        ));
    }

    private function handleFlowrouteStatusCallback(array $data, $rawBody)
    {
        $normalized = $this->normalizeFlowrouteStatusPayload($data);
        if (!$normalized['ok']) {
            return $this->response(400, array(
                'status' => 'error',
                'message' => $normalized['error'],
            ));
        }

        $updated = $this->module->updateDeliveryStatus(
            $normalized['provider_ref'],
            $normalized['provider_status'],
            $rawBody
        );

        return $this->response(200, array(
            'status' => 'ok',
            'updated_rows' => $updated,
            'provider_ref' => $normalized['provider_ref'],
            'new_status' => $normalized['provider_status'],
            'provider' => 'flowroute',
        ));
    }

    private function handleLegacyInboundMessage(array $data, $rawBody)
    {
        $normalized = $this->normalizeLegacyInboundPayload($data);
        if (!$normalized['ok']) {
            return $this->response(400, array(
                'status' => 'error',
                'message' => $normalized['error'],
            ));
        }

        $id = $this->module->insertInboundMessage(
            $normalized['sender'],
            $normalized['receiver'],
            $normalized['message'],
            $normalized['provider_ref'],
            $rawBody,
            'received'
        );

        $this->sendDiscordNotification(
            $normalized['sender'],
            $normalized['receiver'],
            $normalized['message'],
            $normalized['provider_ref'],
            'generic'
        );

        return $this->response(200, array(
            'status' => 'ok',
            'message_id' => $id,
            'provider' => 'generic',
        ));
    }

    private function handleLegacyStatusCallback(array $data, $rawBody)
    {
        $providerRef = isset($data['RefId']) ? trim((string)$data['RefId']) : '';
        $providerStatus = $this->extractLegacyStatus($data);

        if ($providerRef === '' || $providerStatus === '') {
            return $this->response(400, array(
                'status' => 'error',
                'message' => 'Missing RefId or status',
            ));
        }

        $updated = $this->module->updateDeliveryStatus($providerRef, $providerStatus, $rawBody);

        return $this->response(200, array(
            'status' => 'ok',
            'updated_rows' => $updated,
            'provider_ref' => $providerRef,
            'new_status' => $providerStatus,
            'provider' => 'generic',
        ));
    }

    private function looksLikeFlowroutePayload(array $data)
    {
        return isset($data['data']) && is_array($data['data']);
    }

    private function looksLikeFlowrouteDlr(array $data)
    {
        $attributes = $this->getFlowrouteAttributes($data);

        if (empty($attributes)) {
            return false;
        }

        return isset($attributes['delivery_receipts']);
    }

    private function looksLikeLegacyStatusCallback(array $data)
    {
        return isset($data['Status']) || isset($data['MessageStatus']) || isset($data['DeliveryStatus']);
    }

    private function normalizeFlowrouteInboundPayload(array $data)
    {
        $attributes = $this->getFlowrouteAttributes($data);
        $providerRef = isset($data['data']['id']) ? trim((string)$data['data']['id']) : '';

        $from = isset($attributes['from']) ? trim((string)$attributes['from']) : '';
        $to = isset($attributes['to']) ? trim((string)$attributes['to']) : '';
        $message = isset($attributes['body']) ? trim((string)$attributes['body']) : '';
        $status = isset($attributes['status']) ? trim((string)$attributes['status']) : 'received';

        if ($from === '') {
            return array('ok' => false, 'error' => 'Missing Flowroute from');
        }

        if ($to === '') {
            return array('ok' => false, 'error' => 'Missing Flowroute to');
        }

        if ($message === '') {
            return array('ok' => false, 'error' => 'Missing Flowroute body');
        }

        if ($providerRef === '') {
            $providerRef = null;
        }

        return array(
            'ok' => true,
            'sender' => $from,
            'receiver' => $to,
            'message' => $message,
            'provider_ref' => $providerRef,
            'status' => ($status !== '' ? $status : 'received'),
        );
    }

    private function normalizeFlowrouteStatusPayload(array $data)
    {
        $attributes = $this->getFlowrouteAttributes($data);
        $providerRef = isset($data['data']['id']) ? trim((string)$data['data']['id']) : '';
        $providerStatus = '';

        if (!empty($attributes['delivery_receipts']) && is_array($attributes['delivery_receipts'])) {
            $lastReceipt = end($attributes['delivery_receipts']);
            if (is_array($lastReceipt)) {
                if (isset($lastReceipt['status'])) {
                    $providerStatus = trim((string)$lastReceipt['status']);
                } elseif (isset($lastReceipt['message_state'])) {
                    $providerStatus = trim((string)$lastReceipt['message_state']);
                }
            }
            reset($attributes['delivery_receipts']);
        }

        if ($providerStatus === '' && isset($attributes['status'])) {
            $providerStatus = trim((string)$attributes['status']);
        }

        if ($providerRef === '') {
            return array('ok' => false, 'error' => 'Missing Flowroute message id');
        }

        if ($providerStatus === '') {
            return array('ok' => false, 'error' => 'Missing Flowroute status');
        }

        return array(
            'ok' => true,
            'provider_ref' => $providerRef,
            'provider_status' => $providerStatus,
        );
    }

    private function normalizeLegacyInboundPayload(array $data)
    {
        $from = isset($data['From']) ? trim((string)$data['From']) : '';
        $to = '';
        $message = isset($data['Message']) ? urldecode((string)$data['Message']) : '';
        $providerRef = isset($data['RefId']) ? trim((string)$data['RefId']) : null;

        if (isset($data['To'])) {
            if (is_array($data['To']) && isset($data['To'][0])) {
                $to = trim((string)$data['To'][0]);
            } elseif (!is_array($data['To'])) {
                $to = trim((string)$data['To']);
            }
        }

        if ($from === '') {
            return array('ok' => false, 'error' => 'Missing From');
        }

        if ($to === '') {
            return array('ok' => false, 'error' => 'Missing To');
        }

        if ($message === '') {
            return array('ok' => false, 'error' => 'Missing Message');
        }

        return array(
            'ok' => true,
            'sender' => $from,
            'receiver' => $to,
            'message' => $message,
            'provider_ref' => $providerRef,
        );
    }

    private function extractLegacyStatus(array $data)
    {
        foreach (array('Status', 'MessageStatus', 'DeliveryStatus') as $key) {
            if (isset($data[$key])) {
                return trim((string)$data[$key]);
            }
        }

        return '';
    }

    private function getFlowrouteAttributes(array $data)
    {
        if (empty($data['data']) || !is_array($data['data'])) {
            return array();
        }

        if (empty($data['data']['attributes']) || !is_array($data['data']['attributes'])) {
            return array();
        }

        return $data['data']['attributes'];
    }

    private function isTokenValid(array $headers)
    {
        $expected = trim((string)$this->module->getWebhookToken());
        if ($expected === '') {
            return false;
        }

        foreach ($headers as $key => $value) {
            $normalizedKey = strtolower((string)$key);
            if ($normalizedKey !== 'x-smsviewer-token' && $normalizedKey !== 'authorization') {
                continue;
            }

            $candidate = trim((string)$value);
            if (stripos($candidate, 'Bearer ') === 0) {
                $candidate = trim(substr($candidate, 7));
            }

            if ($candidate !== '' && hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function isQueryTokenValid()
    {
        $expected = trim((string)$this->module->getWebhookToken());
        if ($expected === '') {
            return false;
        }

        $candidate = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
        if ($candidate === '') {
            return false;
        }

        return hash_equals($expected, $candidate);
    }

    private function isIpAllowed($remoteIp)
    {
        $allowed = $this->module->getAllowedIps();

        if (empty($allowed)) {
            return true;
        }

        foreach ($allowed as $ip) {
            if ($ip === $remoteIp) {
                return true;
            }
        }

        return false;
    }

    private function sendDiscordNotification($from, $to, $message, $providerRef = null, $provider = 'sms')
    {
        if (!method_exists($this->module, 'getAllSettings')) {
            return;
        }

        $settings = $this->module->getAllSettings();
        $discordWebhook = isset($settings['discord_webhook_url']) ? trim((string)$settings['discord_webhook_url']) : '';

        if ($discordWebhook === '') {
            return;
        }

        $content = "**Inbound SMS Received**\n"
            . "**From:** " . $from . "\n"
            . "**To:** " . $to . "\n"
            . "**Provider:** " . $provider . "\n";

        if (!empty($providerRef)) {
            $content .= "**Ref:** " . $providerRef . "\n";
        }

        $content .= "**Message:**\n" . $message;

        $payload = json_encode(array(
            'content' => $content,
        ));

        $ch = curl_init($discordWebhook);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        curl_close($ch);
    }

    private function response($status, array $body)
    {
        return array(
            'status' => (int)$status,
            'body' => $body,
        );
    }
}