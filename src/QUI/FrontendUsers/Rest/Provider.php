<?php

namespace QUI\FrontendUsers\Rest;

use QUI;
use QUI\REST\Server;
use Slim\Routing\RouteCollectorProxy;

use function file_exists;

/**
 * Class RestProvider
 *
 * @package QUI\Projects
 */
class Provider implements QUI\REST\ProviderInterface
{
    /**
     * @param Server $Server
     */
    public function register(Server $Server)
    {
        $Slim = $Server->getSlim();

        $Slim->group('/frontend-users', function (RouteCollectorProxy $RouteCollector) {
            $RouteCollector->post('/register', 'QUI\FrontendUsers\Rest\Routes\PostRegister::call');
            $RouteCollector->get(
                '/register/required-fields',
                'QUI\FrontendUsers\Rest\Routes\GetRegisterRequiredFields::call'
            );
        });
    }

    public function getOpenApiDefinitionFile()
    {
        try {
            $packageDirectory = QUI::getPackage('quiqqer/frontend-users')->getDir();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        $filePath = $packageDirectory . 'docs/openapi.json';

        if (!file_exists($filePath)) {
            return false;
        }

        return $filePath;
    }

    public function getTitle(QUI\Locale $Locale = null): string
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/frontend-users', 'rest.provider.title');
    }

    public function getName(): string
    {
        return "FrontendUsers";
    }
}
