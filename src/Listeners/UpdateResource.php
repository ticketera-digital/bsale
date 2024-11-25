<?php

namespace ticketeradigital\bsale\Listeners;

use Illuminate\Support\Facades\Log;
use ticketeradigital\bsale\Events\WebhookHit;
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
    public function handle(WebhookHit $resource)
    {
        $handler = self::getWebhookHandler($resource->topic);
        Log::debug('UpdateResource (WebhookHit listener)', ['resource' => $handler]);
        $handler::handleWebHook($resource);
    }
}
