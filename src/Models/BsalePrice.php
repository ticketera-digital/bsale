<?php

namespace ticketeradigital\bsale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ticketeradigital\bsale\Bsale;
use ticketeradigital\bsale\BsaleException;
use ticketeradigital\bsale\Entities\PriceLists;
use ticketeradigital\bsale\Events\PriceUpdated;
use ticketeradigital\bsale\Events\WebhookHit;
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
        Bsale::fetchAllAndCallback(
            "/v1/price_lists/$priceListId/details.json",
            [self::class, 'upsertMany'],
            $priceListId,
            $params
        );
    }

    /**
     * @throws BsaleException
     */
    public static function fetchForVariant(string|int $priceListId, BsaleVariant|string|int $variant): void
    {
        $variantId = $variant instanceof BsaleVariant ? $variant->internal_id : $variant;
        self::fetchPriceList($priceListId, ['variantid' => $variantId]);
    }

    public static function fetchAll(): void
    {
        $priceLists = PriceLists::all();
        foreach ($priceLists as $priceList) {
            self::fetchPriceList($priceList['id']);
        }
    }

    /**
     * @throws \Throwable
     */
    public static function handleWebhook(WebhookHit $resource): void
    {
        $url = parse_url($resource->link);
        parse_str($url['query'], $params);
        $response = Bsale::makeRequest($url['path'], $params);
        $data = $response['data'][0];
        $priceId = Str::of($data['href'])->after('details/')->before('.json')->toInteger();

        $data['id'] = $priceId;
        $data['variant']['id'] = $data['variantId'];
        $price = self::updateOrCreate(['internal_id' => $priceId], [
            'price_list_id' => $resource->others['priceListId'],
            'data' => $data,
        ]);
        info("Created/Updated Price $price->id");
    }

    public function getListAttribute(): ?array
    {
        return PriceLists::find($this->price_list_id);
    }
}
