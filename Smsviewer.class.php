<?php

namespace FreePBX\modules;

use PDO;
use Exception;
use FreePBX\modules\Smsviewer\Api\Webhook;

class Smsviewer implements \BMO
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $moduleDir;

    public function __construct($freepbx = null)
    {
        $this->db = \FreePBX::Database();
        $this->moduleDir = __DIR__;
    }

    public function install()
    {
        $this->createTables();
        $this->migrateSchema();
        $this->seedDefaultSettings();
    }

    public function uninstall()
    {
        // Preserve data by default.
    }

    public function backup()
    {
        return array(
            'settings' => $this->getAllSettings(),
        );
    }

    public function restore($backup)
    {
        if (!is_array($backup) || empty($backup['settings']) || !is_array($backup['settings'])) {
            return;
        }

        foreach ($backup['settings'] as $key => $value) {
            $this->setSetting($key, $value);
        }
    }

    public function doConfigPageInit($page)
    {
        if ($page !== 'smsviewer') {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $this->assertCsrf();

        $action = isset($_POST['smsviewer_action']) ? trim((string)$_POST['smsviewer_action']) : '';

        switch ($action) {
            case 'delete_selected':
                $ids = isset($_POST['delete_ids']) && is_array($_POST['delete_ids']) ? $_POST['delete_ids'] : array();
                $this->deleteSelectedMessages($ids);
                break;

            case 'delete_sender':
                $sender = isset($_POST['sender']) ? trim((string)$_POST['sender']) : '';
                if ($sender !== '') {
                    $this->deleteAllFromSender($sender);
                }
                break;

            case 'save_settings':
                $this->saveSettings($_POST);
                break;

            case 'push_flowroute_callbacks':
                $this->saveSettings($_POST);
                $this->pushFlowrouteCallbacks();
                break;

            case 'purge_old':
                $this->purgeOldMessages();
                break;
        }
    }

    public function renderPage()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $tab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : 'main';
        $activeSender = isset($_GET['sender']) ? trim((string)$_GET['sender']) : '';

        $filters = $this->getFiltersFromRequest();

        $data = array(
            'tabs'         => $this->renderTabs($tab, $activeSender),
            'csrf_token'   => $this->getCsrfToken(),
            'senders'      => $this->getSenders($filters['sender_search']),
            'activeSender' => $activeSender,
            'messages'     => $activeSender !== '' ? $this->getMessagesBySender($activeSender, $filters, $this->getPageSize()) : array(),
            'stats'        => $this->getStats(),
            'settings'     => $this->getAllSettings(),
            'webhookUrl'   => $this->guessWebhookUrl(),
            'filters'      => $filters,
            'notice'       => $this->getNotice(),
        );

        if ($tab === 'settings') {
            return load_view($this->moduleDir . '/views/settings.php', $data);
        }

        return load_view($this->moduleDir . '/views/main.php', $data);
    }

    public function handleWebhook($rawBody, array $headers = array(), $remoteIp = '')
    {
        $handler = new Webhook($this);
        return $handler->handle($rawBody, $headers, $remoteIp);
    }

    public function getSenders($senderSearch = '')
    {
        if ($senderSearch !== '') {
            $stmt = $this->db->prepare("
                SELECT sender, COUNT(*) AS cnt, MAX(created_at) AS last_seen
                FROM smsviewer_messages
                WHERE sender LIKE :sender_search
                GROUP BY sender
                ORDER BY last_seen DESC
            ");
            $stmt->execute(array(':sender_search' => '%' . $senderSearch . '%'));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->query("
            SELECT sender, COUNT(*) AS cnt, MAX(created_at) AS last_seen
            FROM smsviewer_messages
            GROUP BY sender
            ORDER BY last_seen DESC
        ");

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    public function getMessagesBySender($sender, array $filters = array(), $limit = 100)
    {
        $limit = (int)$limit;
        if ($limit < 10) {
            $limit = 100;
        }

        $where = array("sender = :sender");
        $params = array(':sender' => $sender);

        if (!empty($filters['q'])) {
            $where[] = "message LIKE :q";
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['receiver'])) {
            $where[] = "receiver LIKE :receiver";
            $params[':receiver'] = '%' . $filters['receiver'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $sql = "
            SELECT
                id, created_at, sender, receiver, message, direction,
                provider_ref, status, provider_status, status_updated_at
            FROM smsviewer_messages
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC, id DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertInboundMessage($sender, $receiver, $message, $providerRef = null, $rawPayload = null, $status = 'received')
    {
        $stmt = $this->db->prepare("
            INSERT INTO smsviewer_messages
            (sender, receiver, message, direction, provider_ref, raw_payload, status, provider_status)
            VALUES
            (:sender, :receiver, :message, 'inbound', :provider_ref, :raw_payload, :status, :provider_status)
        ");

        $stmt->execute(array(
            ':sender'          => $sender,
            ':receiver'        => $receiver,
            ':message'         => $message,
            ':provider_ref'    => $providerRef,
            ':raw_payload'     => $rawPayload,
            ':status'          => $status,
            ':provider_status' => $status,
        ));

        return (int)$this->db->lastInsertId();
    }

    public function updateDeliveryStatus($providerRef, $providerStatus, $rawPayload = null)
    {
        $stmt = $this->db->prepare("
            UPDATE smsviewer_messages
            SET provider_status = :provider_status,
                status = :status,
                status_updated_at = NOW(),
                raw_payload = CASE
                    WHEN :raw_payload IS NULL OR :raw_payload = '' THEN raw_payload
                    ELSE :raw_payload
                END
            WHERE provider_ref = :provider_ref
        ");

        $stmt->execute(array(
            ':provider_status' => $providerStatus,
            ':status'          => $providerStatus,
            ':raw_payload'     => $rawPayload,
            ':provider_ref'    => $providerRef,
        ));

        return $stmt->rowCount();
    }

    public function deleteSelectedMessages(array $ids)
    {
        $clean = array();

        foreach ($ids as $id) {
            if (ctype_digit((string)$id)) {
                $clean[] = (int)$id;
            }
        }

        if (empty($clean)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($clean), '?'));
        $stmt = $this->db->prepare("DELETE FROM smsviewer_messages WHERE id IN ($placeholders)");
        $stmt->execute($clean);

        return $stmt->rowCount();
    }

    public function deleteAllFromSender($sender)
    {
        $stmt = $this->db->prepare("DELETE FROM smsviewer_messages WHERE sender = :sender");
        $stmt->execute(array(':sender' => $sender));

        return $stmt->rowCount();
    }

    public function purgeOldMessages()
    {
        $days = (int)$this->getSetting('retention_days', '365');
        if ($days < 1) {
            $days = 365;
        }

        $stmt = $this->db->prepare("
            DELETE FROM smsviewer_messages
            WHERE created_at < (NOW() - INTERVAL :days DAY)
        ");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function getStats()
    {
        $row = $this->db->query("
            SELECT
                COUNT(*) AS total_messages,
                COUNT(DISTINCT sender) AS unique_senders,
                MAX(created_at) AS last_message_at
            FROM smsviewer_messages
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return array(
                'total_messages' => 0,
                'unique_senders' => 0,
                'last_message_at' => null,
            );
        }

        return $row;
    }

    public function saveSettings(array $post)
    {
        $enabled = isset($post['webhook_enabled']) ? '1' : '0';
        $retention = isset($post['retention_days']) ? (int)$post['retention_days'] : 365;
        $pageSize = isset($post['page_size']) ? (int)$post['page_size'] : 100;
        $allowedIps = isset($post['allowed_ips']) ? trim((string)$post['allowed_ips']) : '';
        $token = isset($post['webhook_token']) ? trim((string)$post['webhook_token']) : '';

        $flowrouteAccessKey = isset($post['flowroute_access_key']) ? trim((string)$post['flowroute_access_key']) : '';
        $flowrouteSecretKey = isset($post['flowroute_secret_key']) ? trim((string)$post['flowroute_secret_key']) : '';
        $flowrouteSmsCallbackUrl = isset($post['flowroute_sms_callback_url']) ? trim((string)$post['flowroute_sms_callback_url']) : '';
        $flowrouteMmsCallbackUrl = isset($post['flowroute_mms_callback_url']) ? trim((string)$post['flowroute_mms_callback_url']) : '';
        $flowrouteSmsDlrCallbackUrl = isset($post['flowroute_sms_dlr_callback_url']) ? trim((string)$post['flowroute_sms_dlr_callback_url']) : '';
        $flowrouteMmsDlrCallbackUrl = isset($post['flowroute_mms_dlr_callback_url']) ? trim((string)$post['flowroute_mms_dlr_callback_url']) : '';

        if ($retention < 1) {
            $retention = 365;
        }

        if ($pageSize < 10 || $pageSize > 1000) {
            $pageSize = 100;
        }

        if ($token === '') {
            $token = $this->generateToken();
        }

        $this->setSetting('webhook_enabled', $enabled);
        $this->setSetting('retention_days', (string)$retention);
        $this->setSetting('page_size', (string)$pageSize);
        $this->setSetting('allowed_ips', $allowedIps);
        $this->setSetting('webhook_token', $token);

        $this->setSetting('flowroute_access_key', $flowrouteAccessKey);
        $this->setSetting('flowroute_secret_key', $flowrouteSecretKey);
        $this->setSetting('flowroute_sms_callback_url', $flowrouteSmsCallbackUrl);
        $this->setSetting('flowroute_mms_callback_url', $flowrouteMmsCallbackUrl);
        $this->setSetting('flowroute_sms_dlr_callback_url', $flowrouteSmsDlrCallbackUrl);
        $this->setSetting('flowroute_mms_dlr_callback_url', $flowrouteMmsDlrCallbackUrl);

        $this->setNotice('Settings saved.');
    }

    public function getAllSettings()
    {
        $stmt = $this->db->query("SELECT `key`, `value` FROM smsviewer_settings");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();

        $settings = array(
            'webhook_enabled'                 => '1',
            'retention_days'                  => '365',
            'page_size'                       => '100',
            'allowed_ips'                     => '',
            'webhook_token'                   => '',
            'flowroute_access_key'            => '',
            'flowroute_secret_key'            => '',
            'flowroute_sms_callback_url'      => '',
            'flowroute_mms_callback_url'      => '',
            'flowroute_sms_dlr_callback_url'  => '',
            'flowroute_mms_dlr_callback_url'  => '',
        );

        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        if ($settings['webhook_token'] === '') {
            $settings['webhook_token'] = $this->generateToken();
            $this->setSetting('webhook_token', $settings['webhook_token']);
        }

        return $settings;
    }

    public function getWebhookToken()
    {
        return (string)$this->getSetting('webhook_token', '');
    }

    public function isWebhookEnabled()
    {
        return $this->getSetting('webhook_enabled', '1') === '1';
    }

    public function getAllowedIps()
    {
        $raw = trim((string)$this->getSetting('allowed_ips', ''));
        if ($raw === '') {
            return array();
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $ips = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $ips[] = $line;
            }
        }

        return $ips;
    }

    public function getPageSize()
    {
        $pageSize = (int)$this->getSetting('page_size', '100');
        if ($pageSize < 10 || $pageSize > 1000) {
            return 100;
        }

        return $pageSize;
    }

    public function guessWebhookUrl()
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'pbx.local';

        return $scheme . '://' . $host . '/smsviewer-hook.php';
    }

    private function pushFlowrouteCallbacks()
    {
        $accessKey = trim((string)$this->getSetting('flowroute_access_key', ''));
        $secretKey = trim((string)$this->getSetting('flowroute_secret_key', ''));

        if ($accessKey === '' || $secretKey === '') {
            $this->setNotice('Flowroute access key and secret key are required.', 'danger');
            return;
        }

        $callbacks = array(
            'sms_callback'     => trim((string)$this->getSetting('flowroute_sms_callback_url', '')),
            'mms_callback'     => trim((string)$this->getSetting('flowroute_mms_callback_url', '')),
            'sms_dlr_callback' => trim((string)$this->getSetting('flowroute_sms_dlr_callback_url', '')),
            'mms_dlr_callback' => trim((string)$this->getSetting('flowroute_mms_dlr_callback_url', '')),
        );

        $results = array();

        foreach ($callbacks as $callbackType => $callbackUrl) {
            if ($callbackUrl === '') {
                continue;
            }

            $results[] = $this->flowroutePutCallback($callbackType, $callbackUrl, $accessKey, $secretKey);
        }

        $failed = array_filter($results, function ($row) {
            return empty($row['ok']);
        });

        if (!empty($failed)) {
            $messages = array();
            foreach ($failed as $row) {
                $messages[] = $row['callback_type'] . ': ' . $row['message'];
            }
            $this->setNotice('Flowroute update failed: ' . implode(' | ', $messages), 'danger');
            return;
        }

        $this->setNotice('Flowroute webhooks updated successfully.');
    }

    private function flowroutePutCallback($callbackType, $callbackUrl, $accessKey, $secretKey)
    {
        $url = 'https://api.flowroute.com/v2.1/messages/' . rawurlencode($callbackType);

        $payload = json_encode(array(
            'data' => array(
                'attributes' => array(
                    'callback_url' => $callbackUrl,
                ),
            ),
        ));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $accessKey . ':' . $secretKey);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json',
            'Content-Length: ' . strlen($payload),
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            return array(
                'ok' => false,
                'callback_type' => $callbackType,
                'message' => $curlError,
            );
        }

        if ($httpCode === 204) {
            return array(
                'ok' => true,
                'callback_type' => $callbackType,
                'message' => 'Updated',
            );
        }

        $decoded = json_decode((string)$body, true);
        $message = 'HTTP ' . $httpCode;

        if (is_array($decoded) && !empty($decoded['errors'][0]['detail'])) {
            $message = $decoded['errors'][0]['detail'];
        }

        return array(
            'ok' => false,
            'callback_type' => $callbackType,
            'message' => $message,
        );
    }

    private function getFiltersFromRequest()
    {
        return array(
            'sender_search' => isset($_GET['sender_search']) ? trim((string)$_GET['sender_search']) : '',
            'q'             => isset($_GET['q']) ? trim((string)$_GET['q']) : '',
            'receiver'      => isset($_GET['receiver']) ? trim((string)$_GET['receiver']) : '',
            'status'        => isset($_GET['status']) ? trim((string)$_GET['status']) : '',
            'date_from'     => isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '',
            'date_to'       => isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '',
        );
    }

    private function createTables()
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS smsviewer_messages (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sender VARCHAR(64) NOT NULL,
                receiver VARCHAR(64) NOT NULL,
                message TEXT NOT NULL,
                direction ENUM('inbound','outbound') NOT NULL DEFAULT 'inbound',
                provider_ref VARCHAR(128) DEFAULT NULL,
                raw_payload MEDIUMTEXT DEFAULT NULL,
                status VARCHAR(32) DEFAULT NULL,
                provider_status VARCHAR(64) DEFAULT NULL,
                status_updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_sender (sender),
                KEY idx_receiver (receiver),
                KEY idx_created_at (created_at),
                KEY idx_provider_ref (provider_ref),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS smsviewer_settings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `key` VARCHAR(64) NOT NULL,
                `value` TEXT NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_key (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function migrateSchema()
    {
        $columns = array();

        $stmt = $this->db->query("SHOW COLUMNS FROM smsviewer_messages");
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $columns[$col['Field']] = true;
            }
        }

        if (!isset($columns['provider_status'])) {
            $this->db->exec("ALTER TABLE smsviewer_messages ADD COLUMN provider_status VARCHAR(64) DEFAULT NULL");
        }

        if (!isset($columns['status_updated_at'])) {
            $this->db->exec("ALTER TABLE smsviewer_messages ADD COLUMN status_updated_at DATETIME DEFAULT NULL");
        }
    }

    private function seedDefaultSettings()
    {
        $defaults = array(
            'webhook_enabled'                 => '1',
            'retention_days'                  => '365',
            'page_size'                       => '100',
            'allowed_ips'                     => '',
            'webhook_token'                   => $this->generateToken(),
            'flowroute_access_key'            => '',
            'flowroute_secret_key'            => '',
            'flowroute_sms_callback_url'      => '',
            'flowroute_mms_callback_url'      => '',
            'flowroute_sms_dlr_callback_url'  => '',
            'flowroute_mms_dlr_callback_url'  => '',
        );

        foreach ($defaults as $key => $value) {
            if ($this->getSetting($key, null) === null) {
                $this->setSetting($key, $value);
            }
        }
    }

    private function getSetting($key, $default = null)
    {
        $stmt = $this->db->prepare("SELECT `value` FROM smsviewer_settings WHERE `key` = :key LIMIT 1");
        $stmt->execute(array(':key' => $key));
        $value = $stmt->fetchColumn();

        return ($value === false) ? $default : $value;
    }

    private function setSetting($key, $value)
    {
        $stmt = $this->db->prepare("
            INSERT INTO smsviewer_settings (`key`, `value`)
            VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");

        $stmt->execute(array(
            ':key'   => $key,
            ':value' => (string)$value,
        ));
    }

    private function renderTabs($activeTab, $activeSender = '')
    {
        $mainUrl = 'config.php?display=smsviewer&tab=main';
        if ($activeSender !== '') {
            $mainUrl .= '&sender=' . urlencode($activeSender);
        }

        $tabs = array(
            array(
                'label' => 'Inbox',
                'url'   => $mainUrl,
                'active'=> ($activeTab === 'main'),
            ),
            array(
                'label' => 'Settings',
                'url'   => 'config.php?display=smsviewer&tab=settings',
                'active'=> ($activeTab === 'settings'),
            ),
        );

        $html = '<div class="smsviewer-tabs">';
        foreach ($tabs as $tab) {
            $class = $tab['active'] ? 'smsviewer-tab active' : 'smsviewer-tab';
            $html .= '<a class="' . $class . '" href="' . htmlspecialchars($tab['url'], ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8')
                . '</a>';
        }
        $html .= '</div>';

        return $html;
    }

    private function getCsrfToken()
    {
        if (empty($_SESSION['smsviewer_csrf'])) {
            $_SESSION['smsviewer_csrf'] = $this->generateToken(16);
        }

        return $_SESSION['smsviewer_csrf'];
    }

    private function assertCsrf()
    {
        $posted = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        $actual = $this->getCsrfToken();

        if ($posted === '' || !hash_equals($actual, $posted)) {
            throw new Exception('Invalid CSRF token');
        }
    }

    private function setNotice($message, $type = 'success')
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION['smsviewer_notice'] = array(
            'type'    => $type,
            'message' => $message,
        );
    }

    private function getNotice()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (empty($_SESSION['smsviewer_notice'])) {
            return null;
        }

        $notice = $_SESSION['smsviewer_notice'];
        unset($_SESSION['smsviewer_notice']);

        return $notice;
    }

    private function generateToken($bytes = 24)
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($bytes));
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($bytes));
        }

        return substr(hash('sha256', uniqid((string)mt_rand(), true)), 0, $bytes * 2);
    }
}
