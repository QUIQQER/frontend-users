<?php

namespace QUI\FrontendUsers\Rest\Routes;

use Exception;
use Psr\Http\Message\ResponseInterface as SlimResponse;
use Psr\Http\Message\ServerRequestInterface as SlimRequest;
use QUI;

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
        if (!class_exists('QUI\REST\ResponseFactory')) {
            throw new QUI\Exception('Class "QUI\REST\ResponseFactory" not found.');
        }

        $ResponseFactory = new QUI\REST\ResponseFactory();

        try {
            $requiredFields = QUI\FrontendUsers\Rest\RegistrationData::getRequiredFields();
        } catch (Exception $Exception) {
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
