<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

require_once __DIR__ . '/stubs/QUI/ERP/Api/AbstractErpProvider.php';
require_once __DIR__ . '/../../../../bootstrap.php';
require_once __DIR__ . '/Support/DatabaseTestCase.php';

QUI\System\TestCleanup::register();
