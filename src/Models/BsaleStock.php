<?php

namespace ticketeradigital\bsale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;
use ticketeradigital\bsale\Bsale;
use ticketeradigital\bsale\BsaleException;
use ticketeradigital\bsale\Entities\Offices;
use ticketeradigital\bsale\Events\StockUpdated;
use ticketeradigital\bsale\Events\WebhookHit;
use ticketeradigital\bsale\Interfaces\WebhookHandlerInterface;

class BsaleStock extends Model implements WebhookHandlerInterface
{
    protected $fillable = [
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    protected $dispatchesEvents = [
        'saved' => StockUpdated::class,
        'updated' => StockUpdated::class,
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(BsaleVariant::class, 'variant_id', 'internal_id');
    }

    /**
     * @throws Throwable
     */
    public function fetch(): array
    {
        $id = $this->internal_id;
        $response = Bsale::makeRequest("/v1/stocks/$id.json");
        $this->data = $response;
        $this->save();

        return $response;
    }

    public static function upsertMany(array $items): void
    {
        $stocks = self::whereIn('internal_id', array_column($items, 'id'))->get();
        DB::transaction(function () use ($stocks, $items) {
            foreach ($items as $item) {
                $stock = $stocks->firstWhere('internal_id', $item['id']);
                if ($stock) {
                    $difference = array_diff(Arr::dot($item), Arr::dot($stock->data));
                    $stock->data = $difference ? $item : $stock->data;
                } else {
                    $stock = self::make(['data' => $item]);
                }
                $stock->dispatchesEvents = [];
                $stock->save();
            }
        });
    }

    /**
     * @throws BsaleException
     */
    public static function fetchAll(array $params = []): void
    {
        Bsale::fetchAllAndCallback('/v1/stocks.json', [self::class, 'upsertMany'], null, $params);
    }

    public static function fetchForVariant(BsaleVariant|string|int $variant): void
    {
        $variantId = $variant instanceof BsaleVariant ? $variant->internal_id : $variant;
        self::fetchAll(['variantid' => $variantId]);
    }

    public static function fetchOne($id): self
    {
        $data = Bsale::makeRequest("/v1/stocks/$id.json");

        return self::updateOrCreate(
            ['internal_id' => $id],
            ['data' => $data]
        );
    }

    /**
     * @throws Throwable
     */
    public static function handleWebhook(WebhookHit $resource): void
    {
        $response = Bsale::makeRequest('/v1/stocks.json', [
            'variantid' => $resource->resourceId,
            'officeid' => $resource->others['officeId'],
        ]);
        $data = $response['items'][0];
        self::updateOrCreate(['internal_id' => $data['id']], [
            'data' => $data,
        ]);
    }

    public function getOfficeAttribute(): ?array
    {
        return Offices::find((int) data_get($this->data, 'office.id', 0));
    }
}
