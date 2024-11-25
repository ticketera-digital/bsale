<?php

namespace ticketeradigital\bsale\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WebhookHit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public string $topic,
        public string $resourceId,
        public string $link,
        public array $others
    ) {
        Log::debug('Bsale WebhookHit event', ['topic' => $this->topic, 'resourceId' => $this->resourceId]);
    }
}
