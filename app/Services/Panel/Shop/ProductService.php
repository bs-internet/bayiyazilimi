<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop;

use App\Enums\Shop\StockTracking;
use App\Events\Shop\CatalogChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Media;
use App\Models\Product;
use App\Models\User;
use App\Services\Support\MediaService;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly MediaService $media,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $causer): Product
    {
        return DB::transaction(function () use ($data, $causer) {
            $data = $this->normalize($data);
            $data['slug'] = $this->catalog->uniqueSlug(Product::class, $data['slug'] ?? null, $data['name']);
            // Stok yalnızca Stok ekranından (StockService) değişir; yeni ürün 0 ile başlar.
            $data['stock'] = 0;

            $product = Product::create($data);
            // Modüllerin ürün formuna eklediği alanlar (bkz. `shop.products.rules`).
            hooks()->do('shop.product.saved', product: $product, data: $data);

            CatalogChangedEvent::dispatch($product, 'product', 'created', __(':sku - :name ürününü oluşturdu.', ['sku' => $product->sku, 'name' => $product->name]), $causer);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data, User $causer): Product
    {
        return DB::transaction(function () use ($product, $data, $causer) {
            $data = $this->normalize($data);
            $data['slug'] = $this->catalog->uniqueSlug(Product::class, $data['slug'] ?? null, $data['name'], $product->id);
            unset($data['stock']);

            // Takip türü (yok / lot / seri), stok satırları buna göre tutulduğu için
            // stok ya da lot kaydı varken değişmez (lotlu satırlar yetim kalır, seri sınırı bozulur).
            if (isset($data['tracking']) && ($data['tracking'] instanceof StockTracking ? $data['tracking'] : StockTracking::from((string) $data['tracking'])) !== $product->tracking
                && ($product->locationStocks()->where('quantity', '!=', 0)->exists() || $product->lots()->exists())) {
                throw new ShopException(__('Stoğu ya da lot / seri kaydı olan ürünün takip türü değiştirilemez; önce stoğu sıfırlayın.'));
            }

            $product->update($data);
            hooks()->do('shop.product.saved', product: $product, data: $data);
            $product->syncStockTotal();

            CatalogChangedEvent::dispatch($product, 'product', 'updated', __(':sku - :name ürününü güncelledi.', ['sku' => $product->sku, 'name' => $product->name]), $causer);

            return $product;
        });
    }

    public function delete(Product $product, User $causer): void
    {
        DB::transaction(function () use ($product, $causer) {
            // Stoğu olan ürün silinirse stok ekranlarından düşer ve geri getirilemez.
            if ($product->stocks()->where('quantity', '!=', 0)->exists()) {
                throw new ShopException(__(':sku ürününün stoğu var; silmeden önce stoğu sıfırlayın ya da ürünü pasifleştirin.', ['sku' => $product->sku]));
            }

            $product->delete();

            CatalogChangedEvent::dispatch($product, 'product', 'deleted', __(':sku - :name ürününü sildi.', ['sku' => $product->sku, 'name' => $product->name]), $causer);
        });
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function addImages(Product $product, array $files, User $causer): int
    {
        $count = 0;

        foreach ($files as $file) {
            $this->media->attach($product, $file, Product::IMAGE_COLLECTION, private: false);
            $count++;
        }

        CatalogChangedEvent::dispatch($product, 'product', 'updated', __(':sku ürününe :count görsel ekledi.', ['sku' => $product->sku, 'count' => $count]), $causer);

        return $count;
    }

    public function removeImage(Product $product, Media $media, User $causer): void
    {
        if ($media->mediable_id !== $product->id || $media->mediable_type !== $product->getMorphClass()) {
            throw new ShopException(__('Görsel bu ürüne ait değil.'));
        }

        $this->media->detach($media);

        CatalogChangedEvent::dispatch($product, 'product', 'updated', __(':sku ürününden bir görsel sildi.', ['sku' => $product->sku]), $causer);
    }

    /**
     * @param  array<int, string>  $orderedIds
     */
    public function reorderImages(Product $product, array $orderedIds): void
    {
        $this->media->reorder($product, Product::IMAGE_COLLECTION, $orderedIds);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (array_key_exists('highlights', $data)) {
            $lines = is_array($data['highlights'])
                ? $data['highlights']
                : preg_split('/\r\n|\r|\n/', (string) $data['highlights']);

            $data['highlights'] = array_values(array_filter(array_map('trim', $lines ?: []), fn ($l) => $l !== ''));
        }

        if (($data['compare_price'] ?? null) === '' || ! Money::decimal($data['compare_price'] ?? 0)->isPositive()) {
            $data['compare_price'] = null;
        }

        return $data;
    }
}
