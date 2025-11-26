<?php

namespace ticketeradigital\bsale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ticketeradigital\bsale\Bsale;
use ticketeradigital\bsale\BsaleException;
use ticketeradigital\bsale\Events\VariantUpdated;
use ticketeradigital\bsale\Events\WebhookHit;
use ticketeradigital\bsale\Interfaces\WebhookHandlerInterface;

class BsaleVariant extends Model implements WebhookHandlerInterface
{
    protected $fillable = [
        'data',
        'costs',
    ];

    protected $casts = [
        'data' => 'array',
        'costs' => 'array',
    ];

    protected $dispatchesEvents = [
        'saved' => VariantUpdated::class,
        'updated' => VariantUpdated::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(BsaleProduct::class, 'product_id', 'internal_id');
    }

    /* Not to be used: a variant can have stocks as offices */
    public function stock(): HasOne
    {
        return $this->hasOne(BsaleStock::class, 'variant_id', 'internal_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(BsaleStock::class, 'variant_id', 'internal_id');
    }

    public function price(): HasOne
    {
        return $this->prices()->one()->latestOfMany();
    }

    public function prices(): HasMany
    {
        return $this->hasMany(BsalePrice::class, 'variant_id', 'internal_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(BsaleDetail::class, 'variant_id', 'internal_id');
    }

    /**
     * @throws \Throwable
     */
    public function consume(string $note, ?int $officeId = null, int $quantity = 1): array
    {
        $_officeId = $officeId ?? config('bsale.default_office_id');
        throw_if(! $_officeId, \Exception::class, 'Office id not set');
        $params = [
            'note' => $note,
            'officeId' => $_officeId,
            'details' => [[
                'quantity' => $quantity,
                'variantId' => $this->internal_id,
            ]],
        ];

        return Bsale::makeRequest('/v1/stocks/consumptions.json', $params, 'POST');
    }

    public static function fetchForProduct(BsaleProduct|int $product)
    {
        $productId = $product instanceof BsaleProduct ? $product->internal_id : $product;
        self::fetchAll(['productid' => $productId]);
    }

    public function fetch(): array
    {
        $id = $this->internal_id;
        $response = Bsale::makeRequest("/v1/variants/$id.json");
        $difference = array_diff(Arr::dot($this->data), Arr::dot($response));
        if ($difference) {
            $this->update(['data' => $response]);
        }

        return $response;
    }

    /**
     * @throws \Throwable
     */
    public function fetchCosts(): void
    {
        $response = Bsale::makeRequest("/v1/variants/$this->internal_id/costs.json");
        $this->update(['costs' => $response]);
    }

    public static function upsertMany(array $items): void
    {
        $variants = self::whereIn('internal_id', array_column($items, 'id'))->get();
        DB::transaction(function () use ($variants, $items) {
            foreach ($items as $item) {
                $price = $variants->firstWhere('internal_id', $item['id']);
                data_set($item, 'description', Str::of($item['description'])
                    ->before("\n")
                    ->trim()
                    ->limit(252)
                );
                if ($price) {
                    $difference = array_diff(Arr::dot($item), Arr::dot($price->data));
                    $price->data = $difference ? $item : $price->data;
                } else {
                    $price = self::make(['data' => $item]);
                }
                $price->dispatchesEvents = [];
                $price->save();
            }
        });
    }

    /**
     * @throws BsaleException
     */
    public static function fetchAll(array $params = []): void
    {
        Bsale::fetchAllAndCallback('/v1/variants.json', [self::class, 'upsertMany'], $params);
    }

    public static function fetchOne(int $id): self
    {
        $data = Bsale::makeRequest('/v1/variants/'.$id.'.json');

        return self::updateOrCreate(
            ['internal_id' => $id],
            ['data' => $data]
        );
    }

    /**
     * Whether the variant controls stock. Will be false whenever unlimitedStock is true or allowNegativeStock is true.
     * unlimitedStock is the opposite of the variant's product stockControl.
     */
    public function getControlsStockAttribute(): bool
    {
        return ! ((bool) $this->data['unlimitedStock'] || $this->data['allowNegativeStock']);
    }

    public function getEnabledAttribute()
    {
        return ! $this->data['state'];
    }

    public static function handleWebhook(WebhookHit $resource): void
    {
        self::fetchOne($resource->resourceId);
    }
}
