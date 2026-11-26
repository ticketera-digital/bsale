<?php

namespace ticketeradigital\bsale;

use Illuminate\Http\Client\Response;

class BsaleException extends \Exception
{
    public function __construct(public readonly Response $response)
    {
        $message = $response->json('error') ?? $response->reason() ?: "HTTP {$response->status()}";
        parent::__construct($message, $response->status());
    }

    public function __toString()
    {
        return __CLASS__.": {$this->message}\n";
    }
}
