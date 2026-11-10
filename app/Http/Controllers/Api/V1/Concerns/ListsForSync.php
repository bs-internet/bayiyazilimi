<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Liste uç noktalarının ortak süzgeçleri: `updated_since` (artımlı eşitleme)
 * ve `per_page` (varsayılan Pagination::PER_PAGE, en çok MAX_PER_PAGE).
 * Liste `updated_at`, sonra `id` sırasıyla artan döner: istemci son gördüğü
 * `updated_at`'i bir sonraki istekte `updated_since` olarak gönderir.
 */
trait ListsForSync
{
    public const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, mixed>  $rules  uç noktaya özgü süzgeç kuralları
     * @return array<string, mixed>
     */
    protected function listFilters(Request $request, array $rules = []): array
    {
        return $request->validate([
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
            ...$rules,
        ], [], [
            'updated_since' => __('güncellenme tarihi'),
            'per_page' => __('sayfa boyutu'),
        ]);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    protected function applySync(Builder $query, array $filters): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->when($filters['updated_since'] ?? null, fn (Builder $q, string $since) => $q->where($table.'.updated_at', '>=', CarbonImmutable::parse($since)->setTimezone((string) config('app.timezone'))))
            ->orderBy($table.'.updated_at')
            ->orderBy($table.'.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function perPage(array $filters): int
    {
        return (int) ($filters['per_page'] ?? Pagination::PER_PAGE);
    }
}
