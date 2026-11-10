<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\AccountTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * REST API cari hareket gösterimi (ana para biriminde).
 *
 * @mixin AccountTransaction
 */
class AccountTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccountTransaction $transaction */
        $transaction = $this->resource;

        return [
            'id' => $transaction->id,
            'reference' => $transaction->external_ref,
            'type' => $transaction->type->value,
            'type_label' => $transaction->type->label(),
            'direction' => $transaction->direction->value,
            'amount' => (string) $transaction->amount,
            'currency' => $transaction->currency_code,
            'date' => $transaction->transaction_date?->toDateString(),
            'due_date' => $transaction->due_date?->toDateString(),
            'description' => $transaction->description,
            'reversed' => $transaction->reversed_at !== null,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }
}
