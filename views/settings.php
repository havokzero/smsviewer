<?php
if (!defined('FREEPBX_IS_AUTH')) {
    die('No direct script access allowed');
}

/** @var string $tabs */
/** @var string $csrf_token */
/** @var array $settings */
/** @var string $webhookUrl */
/** @var array|null $notice */
?>
<link rel="stylesheet" href="admin/modules/smsviewer/assets/css/smsviewer.css">

<div class="container-fluid">
    <?php echo $tabs; ?>

    <?php if (!empty($notice) && !empty($notice['message'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars($notice['type'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($notice['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-sm-8">
            <div class="smsviewer-panel">
                <h3>Settings</h3>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="smsviewer_action" value="save_settings">

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="webhook_enabled" value="1" <?php echo ($settings['webhook_enabled'] === '1') ? 'checked' : ''; ?>>
                            Enable Webhook
                        </label>
                    </div>

                    <div class="form-group">
                        <label>Webhook Token</label>
                        <input type="text" class="form-control" name="webhook_token" value="<?php echo htmlspecialchars($settings['webhook_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <p class="help-block">Send this in X-SMSViewer-Token or Authorization: Bearer &lt;token&gt;</p>
                    </div>

                    <div class="form-group">
                        <label>Allowed Source IPs</label>
                        <textarea class="form-control" name="allowed_ips" rows="5"><?php echo htmlspecialchars($settings['allowed_ips'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <p class="help-block">One IP per line. Leave blank to allow any IP with a valid token.</p>
                    </div>

                    <div class="form-group">
                        <label>Retention Days</label>
                        <input type="number" min="1" class="form-control" name="retention_days" value="<?php echo (int)$settings['retention_days']; ?>">
                    </div>

                    <div class="form-group">
                        <label>Inbox Page Size</label>
                        <input type="number" min="10" max="1000" class="form-control" name="page_size" value="<?php echo (int)$settings['page_size']; ?>">
                    </div>

                    <hr>

                    <h4>Flowroute API</h4>

                    <div class="form-group">
                        <label>Flowroute Access Key</label>
                        <input type="text" class="form-control" name="flowroute_access_key" value="<?php echo htmlspecialchars($settings['flowroute_access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Flowroute Secret Key</label>
                        <input type="password" class="form-control" name="flowroute_secret_key" value="<?php echo htmlspecialchars($settings['flowroute_secret_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label>SMS Callback URL</label>
                        <input type="text" class="form-control" name="flowroute_sms_callback_url" value="<?php echo htmlspecialchars($settings['flowroute_sms_callback_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label>MMS Callback URL</label>
                        <input type="text" class="form-control" name="flowroute_mms_callback_url" value="<?php echo htmlspecialchars($settings['flowroute_mms_callback_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label>SMS DLR Callback URL</label>
                        <input type="text" class="form-control" name="flowroute_sms_dlr_callback_url" value="<?php echo htmlspecialchars($settings['flowroute_sms_dlr_callback_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label>MMS DLR Callback URL</label>
                        <input type="text" class="form-control" name="flowroute_mms_dlr_callback_url" value="<?php echo htmlspecialchars($settings['flowroute_mms_dlr_callback_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>

            <div class="smsviewer-panel">
                <h3>Flowroute Webhook Push</h3>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="smsviewer_action" value="push_flowroute_callbacks">

                    <input type="hidden" name="webhook_enabled" value="<?php echo htmlspecialchars($settings['webhook_enabled'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="webhook_token" value="<?php echo htmlspecialchars($settings['webhook_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="allowed_ips" value="<?php echo htmlspecialchars($settings['allowed_ips'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="retention_days" value="<?php echo htmlspecialchars($settings['retention_days'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="page_size" value="<?php echo htmlspecialchars($settings['page_size'], ENT_QUOTES, 'UTF-8'); ?>">

                    <input type="hidden" name="flowroute_access_key" value="<?php echo htmlspecialchars($settings['flowroute_access_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="flowroute_secret_key" value="<?php echo htmlspecialchars($settings['flowroute_secret_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="flowroute_sms_callback_url" value="<?php echo htmlspecialchars($settings['flowroute_sms_callback_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="flowroute_mms_callback_url" value="<?php echo htmlspecialchars($settings['flowroute_mms_callback_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="flowroute_sms_dlr_callback_url" value="<?php echo htmlspecialchars($settings['flowroute_sms_dlr_callback_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="flowroute_mms_dlr_callback_url" value="<?php echo htmlspecialchars($settings['flowroute_mms_dlr_callback_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                    <p class="help-block">
                        This will push any non-empty callback URLs to Flowroute using the account API.
                    </p>

                    <div class="form-group">
                        <button type="submit" class="btn btn-success">Update Flowroute Webhooks</button>
                    </div>
                </form>
            </div>

            <div class="smsviewer-panel">
                <h3>Webhook</h3>
                <p><strong>Recommended public endpoint:</strong></p>
                <code><?php echo htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8'); ?></code>

                <p class="top-space"><strong>Expected JSON:</strong></p>
                <pre>{
  "data": {
    "attributes": {
      "body": "Final test",
      "direction": "inbound",
      "from": "12067392634",
      "is_mms": false,
      "message_callback_url": "https://your-public-webhook.example/smsviewer-hook.php",
      "message_encoding": 0,
      "message_type": "longcode",
      "status": "delivered",
      "timestamp": "2017-11-16T23:45:29.37Z",
      "to": "12012673227"
    },
    "id": "mdr2-3a353498cb2811e7afa11ade1e71c584",
    "type": "message"
  }
}</pre>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="smsviewer-panel">
                <h3>Maintenance</h3>
                <form method="post" onsubmit="return confirm('Purge messages older than the retention period?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="smsviewer_action" value="purge_old">
                    <button type="submit" class="btn btn-danger">Purge Old Messages</button>
                </form>
            </div>
        </div>
    </div>
</div>