<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Enums\Status;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Stok giriş / çıkış / sayım (adjust), depolar arası transfer ve depo içi
 * lokasyon değişimi. Lot / seri takipli üründe:
 * - giriş: lot no (seride seri no, miktar 1) yazılır; SKT takipliyse SKT gerekli,
 * - sayım ve lokasyon değişimi: lot seçilmeli,
 * - çıkış ve transfer: lot isteğe bağlı (boşsa SKT'si yakın lot önce).
 */
class StockOperationRequest extends FormRequest
{
    public const OPERATIONS = ['in', 'out', 'count'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isTransfer = $this->routeIs('panel.shop.stock.transfer');
        $lotRules = [
            'lot_id' => ['nullable', 'uuid', Rule::exists('stock_lots', 'id')->where('product_id', $this->input('product_id'))],
            'lot_code' => ['nullable', 'string', 'max:64'],
            'expires_at' => ['nullable', 'date'],
        ];

        if ($this->routeIs('panel.shop.stock.relocate')) {
            return [
                'product_id' => ['required', 'uuid', 'exists:products,id'],
                'from_location_id' => ['required', 'uuid', 'exists:warehouse_locations,id'],
                // Pasif lokasyona stok konmaz (pasiften çıkış serbest: boşaltma).
                'to_location_id' => ['required', 'uuid', Rule::exists('warehouse_locations', 'id')->where('status', Status::Active->value), 'different:from_location_id'],
                'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
                'note' => ['nullable', 'string', 'max:255'],
                ...$lotRules,
            ];
        }

        return [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'operation' => $isTransfer ? ['nullable'] : ['required', Rule::in(self::OPERATIONS)],
            // Pasif depo / lokasyona stok girmez (giriş, sayım, transfer hedefi); pasiften çıkış serbest (boşaltma).
            'warehouse_id' => $isTransfer ? ['nullable'] : ['required', 'uuid', $this->input('operation') === 'out'
                ? Rule::exists('warehouses', 'id')
                : Rule::exists('warehouses', 'id')->where('status', Status::Active->value)],
            'from_warehouse_id' => $isTransfer ? ['required', 'uuid', 'exists:warehouses,id'] : ['nullable'],
            'to_warehouse_id' => $isTransfer ? ['required', 'uuid', Rule::exists('warehouses', 'id')->where('status', Status::Active->value), 'different:from_warehouse_id'] : ['nullable'],
            // Boş: otomatik lokasyon (giriş → ürünün ana lokasyonu, çıkış → toplama sırası).
            'location_id' => ['nullable', 'uuid', $this->input('operation') === 'out'
                ? Rule::exists('warehouse_locations', 'id')->where('warehouse_id', $this->input('warehouse_id'))
                : Rule::exists('warehouse_locations', 'id')->where('warehouse_id', $this->input('warehouse_id'))->where('status', Status::Active->value)],
            // Transfer: boş kaynak lokasyon → toplama sırasıyla düşülür.
            'from_location_id' => $isTransfer
                ? ['nullable', 'uuid', Rule::exists('warehouse_locations', 'id')->where('warehouse_id', $this->input('from_warehouse_id'))]
                : ['nullable'],
            'to_location_id' => ['nullable', 'uuid', Rule::exists('warehouse_locations', 'id')->where('warehouse_id', $this->input('to_warehouse_id'))->where('status', Status::Active->value)],
            'quantity' => ['required', 'integer', $this->input('operation') === 'count' ? 'min:0' : 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
            ...$lotRules,
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $product = Product::query()->find($this->input('product_id'));

                if ($product === null || ! $product->tracksLots()) {
                    return;
                }

                $operation = match (true) {
                    $this->routeIs('panel.shop.stock.relocate') => 'relocate',
                    $this->routeIs('panel.shop.stock.transfer') => 'transfer',
                    default => (string) $this->input('operation'),
                };
                $noun = $product->tracksSerials() ? __('seri numarası') : 'lot';

                if ($operation === 'in') {
                    if (blank($this->input('lot_code')) && blank($this->input('lot_id'))) {
                        $validator->errors()->add('lot_code', __('Bu ürün :noun takipli; :noun girilmelidir.', ['noun' => $noun]));
                    }

                    if ($product->tracksSerials() && (int) $this->input('quantity') !== 1) {
                        $validator->errors()->add('quantity', __('Seri numarası takipli üründe tek seferde 1 adet girilir (çoklu giriş için Mal Kabul).'));
                    }

                    if ($product->tracksExpiry() && blank($this->input('expires_at')) && blank($this->input('lot_id'))) {
                        $validator->errors()->add('expires_at', __('Bu ürün son kullanma tarihi takipli; SKT girilmelidir.'));
                    }
                }

                if (in_array($operation, ['count', 'relocate'], true) && blank($this->input('lot_id'))) {
                    $validator->errors()->add('lot_id', __('Bu ürün :noun takipli; işlem yapılacak :noun seçilmelidir.', ['noun' => $noun]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'to_warehouse_id' => __('hedef depo'),
            'from_warehouse_id' => __('kaynak depo'),
            'location_id' => __('lokasyon'),
            'from_location_id' => __('kaynak lokasyon'),
            'to_location_id' => __('hedef lokasyon'),
            'lot_id' => __('lot'),
            'lot_code' => __('lot / seri no'),
            'expires_at' => __('son kullanma tarihi'),
        ];
    }
}
