<?php

namespace ticketeradigital\bsale;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use ticketeradigital\bsale\Models\BsaleDocument;

class Bsale
{
    /**
     * @throws Throwable
     */
    public static function makeRequest(string $endpoint, array $params = [], string $method = 'GET'): array
    {
        $accessToken = config('bsale.access_token');
        $baseUrl = config('bsale.base_url');
        throw_if(! $accessToken, \Exception::class, 'Config access_token not set.');
        throw_if(! $baseUrl, \Exception::class, 'Config base_url not set.');
        $client = Http::withHeaders([
            'access_token' => $accessToken,
        ]);
        $url = $baseUrl.$endpoint;
        if ($method === 'GET') {
            $response = $client->get($url, $params);
        } elseif ($method === 'POST') {
            $response = $client->post($url, $params);
        } else {
            throw new \Exception("Unknown request method $method.");
        }
        Log::debug("Fetching Bsale $method: {$response->effectiveUri()}", $params);
        if ($response->failed()) {
            Log::debug('Bsale request failed', [
                'url' => $url,
                'method' => $method,
                'params' => $params,
            ]);
            throw new BsaleException($response);
        }

        return $response->json();
    }

    public static function createDocument($document): BsaleDocument
    {
        try {
            $response = self::makeRequest('/v1/documents.json', $document, 'POST');
        } catch (BsaleException $e) {
            throw $e;
        } catch (Throwable $e) {
            exit($e->getMessage());
        }

        return BsaleDocument::create([
            'data' => $response,
        ]);
    }

    public static function fetchAllAndCallback(string $endpoint, callable $callback, mixed $callbackArgs = null, array $endPointParams = []): void
    {
        try {
            $limit = 50;
            $offset = 0;
            do {
                $params = compact('limit', 'offset') + $endPointParams;
                $response = self::makeRequest($endpoint, $params);
                $count = $response['count'];
                $offset = $offset + $limit;
                call_user_func($callback, $response['items'] ?? [], $callbackArgs);
            } while ($offset < ($count + $limit));
        } catch (BsaleException $e) {
            throw $e;
        } catch (Throwable $e) {
            exit($e->getMessage());
        }
    }
}
