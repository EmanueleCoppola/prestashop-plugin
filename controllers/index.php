<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

header("Expires: Thu, 01 Jul 1999 09:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

header("Location: ../");

exit;
