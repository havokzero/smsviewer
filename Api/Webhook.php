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

        if (!$this->isTokenValid($headers)) {
            return $this->response(403, array(
                'status' => 'error',
                'message' => 'Invalid token',
            ));
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            return $this->response(400, array(
                'status' => 'error',
                'message' => 'Invalid JSON payload',
            ));
        }

        if ($this->looksLikeStatusCallback($data)) {
            return $this->handleStatusCallback($data, $rawBody);
        }

        return $this->handleInboundMessage($data, $rawBody);
    }

    private function handleInboundMessage(array $data, $rawBody)
    {
        $normalized = $this->normalizeInboundPayload($data);
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

        return $this->response(200, array(
            'status'     => 'ok',
            'message_id' => $id,
        ));
    }

    private function handleStatusCallback(array $data, $rawBody)
    {
        $providerRef = isset($data['RefId']) ? trim((string)$data['RefId']) : '';
        $providerStatus = $this->extractStatus($data);

        if ($providerRef === '' || $providerStatus === '') {
            return $this->response(400, array(
                'status' => 'error',
                'message' => 'Missing RefId or status',
            ));
        }

        $updated = $this->module->updateDeliveryStatus($providerRef, $providerStatus, $rawBody);

        return $this->response(200, array(
            'status'       => 'ok',
            'updated_rows' => $updated,
            'provider_ref' => $providerRef,
            'new_status'   => $providerStatus,
        ));
    }

    private function looksLikeStatusCallback(array $data)
    {
        return isset($data['Status']) || isset($data['MessageStatus']) || isset($data['DeliveryStatus']);
    }

    private function extractStatus(array $data)
    {
        foreach (array('Status', 'MessageStatus', 'DeliveryStatus') as $key) {
            if (isset($data[$key])) {
                return trim((string)$data[$key]);
            }
        }

        return '';
    }

    private function normalizeInboundPayload(array $data)
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
            'ok'           => true,
            'sender'       => $from,
            'receiver'     => $to,
            'message'      => $message,
            'provider_ref' => $providerRef,
        );
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

    private function response($status, array $body)
    {
        return array(
            'status' => (int)$status,
            'body'   => $body,
        );
    }
}