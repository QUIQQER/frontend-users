<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

require_once __DIR__ . '/../../../../bootstrap.php';

$optionalClassStubs = [
    QUI\ERP\Api\AbstractErpProvider::class
        => 'QUI/ERP/Api/AbstractErpProvider.php'
];

foreach ($optionalClassStubs as $className => $stubFile) {
    if (!class_exists($className)) {
        require_once __DIR__ . '/stubs/' . $stubFile;
    }
}

require_once __DIR__ . '/Support/DatabaseTestCase.php';

QUI\System\TestCleanup::register();
