<?php

namespace ticketeradigital\bsale\Entities;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use ticketeradigital\bsale\Bsale;
use ticketeradigital\bsale\BsaleException;

abstract class BaseEntity
{
    public static function getName(): Stringable
    {
        $reflection = (new \ReflectionClass(get_called_class()))->getShortName();

        return Str::of($reflection)->after('Bsale')->snake();
    }

    public static function getCacheKey(): string
    {
        return self::getName()->prepend('bsale.');
    }

    public static function forget(): void
    {
        Cache::forget(self::getCacheKey());
    }

    public static function getEndpoint(): string
    {
        $name = self::getName();

        return "/v1/$name.json";
    }

    /**
     * @throws \Exception
     */
    private static function saveItems(array $items): void
    {
        $currentValue = cache(self::getCacheKey(), []);
        foreach ($items as $item) {
            $currentValue[$item['id']] = $item;
        }
        cache()->forever(self::getCacheKey(), $currentValue);
    }

    /**
     * @throws BsaleException
     */
    public static function fetchAll(): void
    {
        self::forget();
        Bsale::fetchAllAndCallback(self::getEndpoint(), fn (array $i) => self::saveItems($i));
    }

    public static function all(): array
    {
        return Cache::get(self::getCacheKey(), function () {
            self::fetchAll();

            return cache(self::getCacheKey(), []);
        });
    }

    public static function find(int $id): ?array
    {
        return self::all()[$id] ?? null;
    }
}
