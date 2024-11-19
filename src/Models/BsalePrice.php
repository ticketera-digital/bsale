<?php

namespace ticketeradigital\bsale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ticketeradigital\bsale\Bsale;
use ticketeradigital\bsale\BsaleException;
use ticketeradigital\bsale\Events\PriceUpdated;
use ticketeradigital\bsale\Events\ResourceUpdated;
use ticketeradigital\bsale\Interfaces\WebhookHandlerInterface;

class BsalePrice extends Model implements WebhookHandlerInterface
{
    protected $fillable = [
        'data',
        'price_list_id',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    protected $dispatchesEvents = [
        'saved' => PriceUpdated::class,
        'updated' => PriceUpdated::class,
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(BsaleVariant::class, 'variant_id', 'internal_id');
    }

    public function fetch(): array
    {
        $id = $this->internal_id;
        $list = $this->price_list_id;
        $response = Bsale::makeRequest("/v1/price_lists/$list/details/$id.json");
        $difference = array_diff(Arr::dot($this->data), Arr::dot($response));
        if ($difference) {
            $this->update(['data' => $response]);
        }

        return $response;
    }

    /**
     * @throws \Throwable
     */
    public static function upsertMany(array $items, $priceListId): void
    {
        $prices = self::whereIn('internal_id', array_column($items, 'id'))
            ->where('price_list_id', $priceListId)
            ->get();
        DB::transaction(function () use ($prices, $items, $priceListId) {
            foreach ($items as $item) {
                $price = $prices->firstWhere('internal_id', $item['id']);
                if ($price) {
                    $difference = array_diff(Arr::dot($item), Arr::dot($price->data));
                    $price->data = $difference ? $item : $price->data;
                    $price->price_list_id = $priceListId;
                } else {
                    $price = self::make(['data' => $item, 'price_list_id' => $priceListId]);
                }
                $price->dispatchesEvents = [];
                $price->save();
            }
        });
    }

    /**
     * @throws BsaleException
     */
    public static function fetchPriceList(int $priceListId, array $params = []): void
    {
        Bsale::fetchAllAndCallback("/v1/price_lists/$priceListId/details.json", [self::class, 'upsertMany'], $priceListId, $params);
    }

    public static function fetchPriceLists(): void
    {
        Cache::forget('bsale_price_lists');
        Bsale::fetchAllAndCallback('/v1/price_lists.json', [self::class, 'savePriceLists']);
    }

    public static function savePriceLists(array $priceLists): void
    {
        $currentPriceList = cache('bsale_price_lists', []);
        foreach ($priceLists as $priceList) {
            $key = $priceList['id'];
            $currentPriceList[$key] = $priceList;
        }
        cache()->forever('bsale.price_lists', $currentPriceList);
    }

    public static function getPriceLists(): array
    {
        return Cache::get('bsale.price_lists', function () {
            self::fetchPriceLists();

            return cache('bsale_price_lists');
        });
    }

    /**
     * @throws BsaleException
     */
    public static function fetchForVariant(string|int $priceListId, BsaleVariant|string|int $variant): void
    {
        $variantId = $variant instanceof BsaleVariant ? $variant->internal_id : $variant;
        self::fetchPriceList($priceListId, ['variantid' => $variantId]);
    }

    public static function handleWebhook(ResourceUpdated $resource): void
    {
        $priceListId = $resource->others['priceListId'];
        self::fetchForVariant($priceListId, $resource->resourceId);
        info('BsalePrice updated/created', ['variantId' => $resource->resourceId, 'priceList' => $priceListId]);
    }
}
