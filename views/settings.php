<?php
if (!defined('FREEPBX_IS_AUTH')) {
    die('No direct script access allowed');
}

/** @var string $tabs */
/** @var string $csrf_token */
/** @var array $settings */
/** @var string $webhookUrl */
?>
<link rel="stylesheet" href="admin/modules/smsviewer/assets/css/smsviewer.css">

<div class="container-fluid">
    <?php echo $tabs; ?>

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

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>

            <div class="smsviewer-panel">
                <h3>Webhook</h3>
                <p><strong>Recommended public endpoint:</strong></p>
                <code><?php echo htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8'); ?></code>

                <p class="top-space"><strong>Expected JSON:</strong></p>
                <pre>{
  "RefId": "21973666",
  "From": "15078655603",
  "To": ["15053001004"],
  "Message": "%5BTikTok%5D+881966+is+your+verification+code"
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