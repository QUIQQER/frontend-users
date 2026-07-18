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

require_once __DIR__ . '/stubs/Psr/Http/Message/MessageInterface.php';
require_once __DIR__ . '/stubs/Psr/Http/Message/StreamInterface.php';
require_once __DIR__ . '/stubs/Psr/Http/Message/ResponseInterface.php';
require_once __DIR__ . '/stubs/Psr/Http/Message/ServerRequestInterface.php';
require_once __DIR__ . '/stubs/GuzzleHttp/Psr7/Stream.php';
require_once __DIR__ . '/stubs/GuzzleHttp/Psr7/Response.php';
require_once __DIR__ . '/stubs/GuzzleHttp/Psr7/ServerRequest.php';
require_once __DIR__ . '/stubs/QUI/ERP/Order/Exception.php';
require_once __DIR__ . '/stubs/QUI/REST/ResponseFactory.php';
require_once __DIR__ . '/stubs/Slim/Routing/RouteCollector.php';
require_once __DIR__ . '/stubs/Slim/Routing/RouteCollectorProxy.php';
require_once __DIR__ . '/stubs/Slim/App.php';
require_once __DIR__ . '/stubs/Slim/Factory/AppFactory.php';
require_once __DIR__ . '/stubs/QUI/REST/Server.php';
