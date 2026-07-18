<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

putenv("QUIQQER_OTHER_AUTOLOADERS=KEEP");

require_once __DIR__ . '/stubs/QUI/ERP/Api/AbstractErpProvider.php';
require_once __DIR__ . '/stubs/QUI/REST/ProviderInterface.php';
require_once __DIR__ . '/../../../../bootstrap.php';

require_once __DIR__ . '/stubs/Slim/Routing/RouteCollector.php';
require_once __DIR__ . '/stubs/Slim/Routing/RouteCollectorProxy.php';
require_once __DIR__ . '/stubs/Slim/App.php';
require_once __DIR__ . '/stubs/Slim/Factory/AppFactory.php';
require_once __DIR__ . '/stubs/QUI/REST/Server.php';
