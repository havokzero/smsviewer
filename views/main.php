<?php
if (!defined('FREEPBX_IS_AUTH')) {
    die('No direct script access allowed');
}

/** @var string $tabs */
/** @var string $csrf_token */
/** @var array $senders */
/** @var string $activeSender */
/** @var array $messages */
/** @var array $stats */
/** @var array $filters */
?>
<link rel="stylesheet" href="admin/modules/smsviewer/assets/css/smsviewer.css">
<script src="admin/modules/smsviewer/assets/js/smsviewer.js"></script>

<div class="container-fluid">
    <?php echo $tabs; ?>

    <div class="row smsviewer-summary-row">
        <div class="col-sm-4">
            <div class="smsviewer-stat-card">
                <div class="smsviewer-stat-label">Total Messages</div>
                <div class="smsviewer-stat-value"><?php echo (int)$stats['total_messages']; ?></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="smsviewer-stat-card">
                <div class="smsviewer-stat-label">Unique Senders</div>
                <div class="smsviewer-stat-value"><?php echo (int)$stats['unique_senders']; ?></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="smsviewer-stat-card">
                <div class="smsviewer-stat-label">Last Message</div>
                <div class="smsviewer-stat-value small">
                    <?php echo $stats['last_message_at'] ? htmlspecialchars($stats['last_message_at'], ENT_QUOTES, 'UTF-8') : 'N/A'; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row smsviewer-layout">
        <div class="col-sm-3">
            <div class="smsviewer-panel">
                <h3>Senders</h3>

                <form method="get" class="smsviewer-filter-form smsviewer-sender-search">
                    <input type="hidden" name="display" value="smsviewer">
                    <input type="hidden" name="tab" value="main">
                    <?php if ($activeSender !== ''): ?>
                        <input type="hidden" name="sender" value="<?php echo htmlspecialchars($activeSender, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>
                    <input type="text" class="form-control" name="sender_search" placeholder="Search sender" value="<?php echo htmlspecialchars($filters['sender_search'], ENT_QUOTES, 'UTF-8'); ?>">
                </form>

                <?php if (!empty($senders)): ?>
                    <div class="list-group">
                        <?php foreach ($senders as $row): ?>
                            <?php
                            $senderValue = $row['sender'];
                            $class = ($activeSender === $senderValue) ? 'list-group-item active' : 'list-group-item';
                            $url = 'config.php?display=smsviewer&tab=main&sender=' . urlencode($senderValue)
                                . '&sender_search=' . urlencode($filters['sender_search'])
                                . '&q=' . urlencode($filters['q'])
                                . '&receiver=' . urlencode($filters['receiver'])
                                . '&status=' . urlencode($filters['status'])
                                . '&date_from=' . urlencode($filters['date_from'])
                                . '&date_to=' . urlencode($filters['date_to']);
                            ?>
                            <a class="<?php echo $class; ?>" href="<?php echo $url; ?>">
                                <span class="smsviewer-sender"><?php echo htmlspecialchars($senderValue, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="badge"><?php echo (int)$row['cnt']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No messages yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-sm-9">
            <div class="smsviewer-panel">
                <?php if ($activeSender !== ''): ?>
                    <div class="smsviewer-header-bar">
                        <h3>Messages from <?php echo htmlspecialchars($activeSender, ENT_QUOTES, 'UTF-8'); ?></h3>

                        <form method="post" class="inline-form" onsubmit="return confirm('Delete all messages from this sender?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="smsviewer_action" value="delete_sender">
                            <input type="hidden" name="sender" value="<?php echo htmlspecialchars($activeSender, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-danger">Delete All From Sender</button>
                        </form>
                    </div>

                    <form method="get" class="smsviewer-filter-form smsviewer-message-filters">
                        <input type="hidden" name="display" value="smsviewer">
                        <input type="hidden" name="tab" value="main">
                        <input type="hidden" name="sender" value="<?php echo htmlspecialchars($activeSender, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="sender_search" value="<?php echo htmlspecialchars($filters['sender_search'], ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="row">
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="q" placeholder="Search message text" value="<?php echo htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-sm-3">
                                <input type="text" class="form-control" name="receiver" placeholder="Receiver" value="<?php echo htmlspecialchars($filters['receiver'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-sm-2">
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-sm-2">
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-sm-1">
                                <button type="submit" class="btn btn-primary btn-block">Go</button>
                            </div>
                        </div>

                        <div class="row top-space">
                            <div class="col-sm-3">
                                <select class="form-control" name="status">
                                    <option value="">Any Status</option>
                                    <?php foreach (array('received','queued','sent','delivered','failed','undelivered') as $statusOpt): ?>
                                        <option value="<?php echo $statusOpt; ?>" <?php echo ($filters['status'] === $statusOpt) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($statusOpt); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-2">
                                <a class="btn btn-default btn-block" href="config.php?display=smsviewer&tab=main&sender=<?php echo urlencode($activeSender); ?>">Reset</a>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($messages)): ?>
                        <form method="post" id="smsviewer-delete-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="smsviewer_action" value="delete_selected">

                            <?php foreach ($messages as $msg): ?>
                                <div class="smsviewer-message">
                                    <div class="smsviewer-checkbox">
                                        <input type="checkbox" name="delete_ids[]" value="<?php echo (int)$msg['id']; ?>">
                                    </div>

                                    <div class="smsviewer-message-body">
                                        <div class="smsviewer-message-meta">
                                            <span><strong>Date:</strong> <?php echo htmlspecialchars($msg['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span><strong>To:</strong> <?php echo htmlspecialchars($msg['receiver'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span><strong>Direction:</strong> <?php echo htmlspecialchars($msg['direction'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if (!empty($msg['provider_ref'])): ?>
                                                <span><strong>Ref:</strong> <?php echo htmlspecialchars($msg['provider_ref'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($msg['provider_status'])): ?>
                                                <span><strong>Status:</strong> <?php echo htmlspecialchars($msg['provider_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($msg['status_updated_at'])): ?>
                                                <span><strong>Status Updated:</strong> <?php echo htmlspecialchars($msg['status_updated_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="smsviewer-message-text">
                                            <?php echo nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="smsviewer-actions">
                                <button type="submit" class="btn btn-warning" onclick="return confirm('Delete selected messages?');">
                                    Delete Selected
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">No messages for this sender and filter set.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">Select a sender from the left to view messages.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>