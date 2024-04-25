<?php

namespace ticketeradigital\bsale\Listeners;

use ticketeradigital\bsale\Bsale;
use ticketeradigital\bsale\Events\ResourceUpdated;
use ticketeradigital\bsale\Models\BsaleDocument;
use ticketeradigital\bsale\Models\BsalePrice;
use ticketeradigital\bsale\Models\BsaleProduct;
use ticketeradigital\bsale\Models\BsaleStock;
use ticketeradigital\bsale\Models\BsaleVariant;

class UpdateResource
{
    private const HANDLERS = [
        'document' => BsaleDocument::class,
        'product' => BsaleProduct::class,
        'variant' => BsaleVariant::class,
        'price' => BsalePrice::class,
        'stock' => BsaleStock::class,
    ];

    public static function getWebhookHandler(string $resourceName)
    {
        $className = "ticketeradigital\bsale\Models\Bsale".ucfirst($resourceName);
        throw_unless(class_exists($className), "Handler class $className does not exist.");
        $interfaces = class_implements($className);
        throw_unless(in_array("ticketeradigital\bsale\Interfaces\WebhookHandlerInterface", $interfaces), "Handler $className does not implement WebhookHandlerInterface.");

        return $className;
    }

    /**
     * @throws \Throwable
     */
    public function handle(ResourceUpdated $resource)
    {
        logger('Hola desde el handler.');
        $handler = self::getWebhookHandler($resource->topic);
        $response = Bsale::makeRequest($resource->link);
        $handler::handleWebHook($response, $resource);
    }
}