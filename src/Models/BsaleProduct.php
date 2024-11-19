<?php

namespace ticketeradigital\bsale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use ticketeradigital\bsale\Bsale;
use ticketeradigital\bsale\BsaleException;
use ticketeradigital\bsale\Events\ProductUpdated;
use ticketeradigital\bsale\Events\ResourceUpdated;
use ticketeradigital\bsale\Interfaces\WebhookHandlerInterface;

class BsaleProduct extends Model implements WebhookHandlerInterface
{
    protected $fillable = [
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    protected $dispatchesEvents = [
        'saved' => ProductUpdated::class,
        'updated' => ProductUpdated::class,
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(BsaleVariant::class, 'product_id', 'internal_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BsaleProductType::class, 'product_type_id', 'internal_id');
    }

    public function fetch(): array
    {
        $id = $this->internal_id;
        $response = Bsale::makeRequest("/v1/products/$id.json");
        $difference = array_diff(Arr::dot($this->data), Arr::dot($response));
        if ($difference) {
            $this->update(['data' => $response]);
        }

        return $response;
    }

    public static function upsertMany(array $items): void
    {
        foreach ($items as $item) {
            $product = self::firstOrNew([
                'internal_id' => $item['id'],
            ]);
            $difference = array_diff(Arr::dot($item), Arr::dot($product->data ?? []));
            $product->data = $difference ? $item : $product->data;
            $product->dispatchesEvents = [];
            $product->save();
        }
    }

    /**
     * @throws BsaleException
     */
    public static function fetchAll(): void
    {
        Bsale::fetchAllAndCallback('/v1/products.json', [self::class, 'upsertMany']);
    }

    public function getControlsStockAttribute()
    {
        return (bool) $this->data['stockControl'];
    }

    public function getEnabledAttribute()
    {
        return ! $this->data['state'];
    }

    public static function fetchOne(string|int $id): self
    {
        $data = Bsale::makeRequest("/v1/products/$id.json");

        return self::updateOrCreate(
            ['internal_id' => $id],
            ['data' => $data]
        );
    }

    public static function handleWebhook(ResourceUpdated $resource): void
    {
        $product = self::fetchOne($resource->resourceId);

        info('BsaleProducts updated/created', ['id' => $product->id]);
    }
}
