<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Cari hesap listesi: bakiyeler tek sorguda (LedgerService::balances) hesaplanıp verilir.
 */
class AccountCollection extends ResourceCollection
{
    public $collects = AccountResource::class;

    /**
     * @param  array<string, BigDecimal>  $balances
     */
    public function __construct(mixed $resource, private readonly array $balances)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (AccountResource $item): array => $item
                ->withBalance($this->balances[$item->resource->id] ?? Money::round(0))
                ->toArray($request))
            ->all();
    }
}
