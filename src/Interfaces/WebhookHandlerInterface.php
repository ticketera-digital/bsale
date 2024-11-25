<?php

namespace ticketeradigital\bsale\Interfaces;

use ticketeradigital\bsale\Events\WebhookHit;

interface WebhookHandlerInterface
{
    public static function handleWebhook(WebhookHit $resource): void;
}
