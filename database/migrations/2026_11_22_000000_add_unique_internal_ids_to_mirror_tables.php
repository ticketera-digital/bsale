<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_KEYS = [
        'bsale_products' => ['internal_id'],
        'bsale_variants' => ['internal_id'],
        'bsale_stocks' => ['internal_id'],
        'bsale_prices' => ['internal_id', 'price_list_id'],
        'bsale_product_types' => ['internal_id'],
    ];

    public function up(): void
    {
        foreach (self::UNIQUE_KEYS as $table => $columns) {
            $this->removeDuplicates($table, $columns);

            Schema::table($table, function (Blueprint $table) use ($columns) {
                $table->unique($columns);
            });
        }
    }

    public function down(): void
    {
        foreach (self::UNIQUE_KEYS as $table => $columns) {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                $table->dropUnique($columns);
            });
        }
    }

    private function removeDuplicates(string $table, array $columns): void
    {
        DB::table($table)
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('count(*) > 1')
            ->get()
            ->each(function (object $group) use ($table) {
                $ids = DB::table($table)
                    ->where((array) $group)
                    ->orderByDesc('updated_at')
                    ->orderBy('id')
                    ->pluck('id');

                DB::table($table)->whereIn('id', $ids->slice(1))->delete();
            });
    }
};
