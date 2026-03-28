<?php
if (!defined('FREEPBX_IS_AUTH')) {
    die('No direct script access allowed');
}

function smsviewer_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function smsviewer_mask_secret($value, $visible = 4)
{
    $value = (string)$value;
    $length = strlen($value);

    if ($length <= $visible) {
        return str_repeat('*', $length);
    }

    return str_repeat('*', $length - $visible) . substr($value, -$visible);
}