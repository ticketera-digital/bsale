<?php

namespace ticketeradigital\bsale\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ticketeradigital\bsale\Models\BsalePrice;

class PriceUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public BsalePrice $price)
    {
        Log::debug('BsalePrice updated (event)', ['id' => $this->price->id]);
    }
}
