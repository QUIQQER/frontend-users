<?php

namespace QUI\FrontendUsers\Rest\Routes;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface as SlimResponse;
use Psr\Http\Message\ServerRequestInterface as SlimRequest;

use QUI;
use QUI\FrontendUsers\Exception;

use function boolval;
use function explode;
use function json_encode;

class GetRegisterRequiredFields
{
    /**
     * To be called by the REST Server (Slim)
     *
     * @param SlimRequest $Request
     * @param SlimResponse $Response
     * @param array $args
     *
     * @return SlimResponse
     */
    public static function call(SlimRequest $Request, SlimResponse $Response, array $args): SlimResponse
    {
        $ResponseFactory = new QUI\REST\ResponseFactory();

        try {
            $requiredFields = QUI\FrontendUsers\Rest\RegistrationData::getRequiredFields();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return $ResponseFactory->createResponse(500);
        }

        return new \GuzzleHttp\Psr7\Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode($requiredFields)
        );
    }
}
