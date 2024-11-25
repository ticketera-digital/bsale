<?php

namespace ticketeradigital\bsale\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ticketeradigital\bsale\Models\BsaleProduct;

class ProductUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public BsaleProduct $product)
    {
        Log::debug('Bsale ProductUpdated updated (observer)', ['id' => $this->product->id]);
    }
}
