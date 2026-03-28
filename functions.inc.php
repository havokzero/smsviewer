<?php
if (!defined('FREEPBX_IS_AUTH')) {
    die('No direct script access allowed');
}

function smsviewer_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}