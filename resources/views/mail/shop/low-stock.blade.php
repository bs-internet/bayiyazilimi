<x-mail::message>
# {{ $product->stock <= 0 ? 'Stok tükendi' : 'Kritik stok seviyesi' }}

{{ __('**Ürün:** :name', ['name' => $product->name]) }}<br>
{{ __('**Stok kodu:** :sku', ['sku' => $product->sku]) }}<br>
{{ __('**Satılabilir stok:** :stock :unit', ['stock' => $product->stock, 'unit' => $product->unit]) }}<br>
{{ __('**Kritik eşik:** :low_stock_threshold', ['low_stock_threshold' => $product->lowStockThreshold()]) }}

@if ($product->stock <= 0 && $product->allow_backorder)
{{ __('Bu ürün ön siparişe açık; stok eksiye düşebilir.') }}
@endif

<x-mail::button :url="route('panel.shop.stock.index', ['q' => $product->sku])">
{{ __('Stok Durumunda Aç') }}
</x-mail::button>
</x-mail::message>
