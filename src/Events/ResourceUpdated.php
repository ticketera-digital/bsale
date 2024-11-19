<?php

namespace ticketeradigital\bsale\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResourceUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public string $topic,
        public string $resourceId,
        public string $link,
        public array $others
    ) {
        Log::debug('Bsale resource updated', ['topic' => $this->topic, 'resourceId' => $this->resourceId]);
    }
}
