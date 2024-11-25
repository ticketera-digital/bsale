<?php

namespace ticketeradigital\bsale\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ticketeradigital\bsale\Models\BsaleVariant;

class VariantUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public BsaleVariant $variant)
    {
        Log::debug('Bsale VariantUpdated updated (observer)', ['id' => $this->variant->id]);
    }
}
