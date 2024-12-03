<?php

if (file_exists(__DIR__ . '/../../config/config.inc.php')) {
    require_once __DIR__ . '/../../config/config.inc.php';
}

if (file_exists(__DIR__ . '/../../init.php')) {
    require_once __DIR__ . '/../../init.php';
}

if (!defined('_PS_VERSION_')) {
    exit;
}

