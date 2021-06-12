<?php declare(strict_types = 1);

namespace Medusa\StorageGateway;

use Medusa\Http\Simple\Curl;
use Medusa\Http\Simple\Request;
use Medusa\StorageGateway\Exception\DbGatewayException;
use function in_array;
use function json_encode;
use const PHP_EOL;

/**
 * Class StorageGateway
 * @package Medusa\StorageGateway
 */
class StorageGateway
{

    protected const GATEWAY_SOCKET = '/tmp/nginx_db-gateway.sock';

    public static function get(string $alias, $parameters)
    {

        static $curl;
        $curl ??= self::createCurl();

        $response = $curl->send(json_encode([
            'alias' => $alias,
            'parameters' => $parameters,
        ]));

        if (
            ($_SERVER['HTTP_X_MEDUSA_DEBUG_CHALLENGE'] ?? false)
            && in_array($_SERVER['HTTP_X_MEDUSA_DEBUG_CHALLENGE'], $response->getHeader('X-Medusa-Debug-Challenge'))
        ) {
            die($response->getBody());
        }

        $body = $response->getParsedBody();

        if ($response->getStatusCode() === 500) {
            throw new DbGatewayException(
                'REMOTE EXCEPTION: ' .
                $response->getStatusCode() . ' ' . $response->getReasonPhrase() . PHP_EOL .
                ($body['message'] ?? 'Unknown error') . PHP_EOL .
                ($body['trace'] ?? ''),
                $body['code'] ?? 0
            );
        } elseif ($response->getStatusCode() === 404) {
            throw new DbGatewayException($response->getStatusCode() . ' ' . $response->getReasonPhrase());
        }

        return $body;
    }

    /**
     * @return Curl
     */
    private static function createCurl(): Curl
    {
        $request = new Request(
            [
                'X-Medusa-Debug-Challenge' => $_SERVER['HTTP_X_MEDUSA_DEBUG_CHALLENGE'] ?? '',
                'Content-Type' => 'application/json',
            ],
            null,
            'POST',
            'storage-gateway.medusa',
            ''
        );

        $curl = Curl::createForRequest($request);
        $curl->setSocketPath($_SERVER['MEDUSA_STORAGE_GATEWAY_SOCKET']);
        return $curl;
    }
}
