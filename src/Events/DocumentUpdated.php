<?php

namespace ticketeradigital\bsale\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ticketeradigital\bsale\Models\BsaleDocument;

class DocumentUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public BsaleDocument $document)
    {
        Log::debug('Bsale DocumentUpdated updated (event)', ['id' => $this->document->id]);
    }
}
