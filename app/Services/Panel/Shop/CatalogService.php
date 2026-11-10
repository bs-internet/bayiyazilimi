<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Contract;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Support\FileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Panelde kategori / marka / depo / sayfa CRUD iş kuralları.
 * Her değişiklik CatalogChangedEvent ile etkinlik kaydına düşer.
 */
class CatalogService
{
    public function __construct(
        private readonly FileService $files,
    ) {}

    // ----------------------------------------------------------------- Kategori

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCategory(?Category $category, array $data, ?UploadedFile $image, bool $removeImage, User $causer): Category
    {
        return DB::transaction(function () use ($category, $data, $image, $removeImage, $causer) {
            $isNew = $category === null;
            $category ??= new Category;

            if (! $isNew && ($data['parent_id'] ?? null) !== null) {
                if ($data['parent_id'] === $category->id || in_array($data['parent_id'], $category->descendantIds(), true)) {
                    throw new ShopException(__('Kategori kendisinin ya da alt kategorisinin altına taşınamaz.'));
                }
            }

            $data['slug'] = $this->uniqueSlug(Category::class, $data['slug'] ?? null, $data['name'], $category->id);

            $category->fill($data);
            $category->image = $this->handleImage($image, $removeImage, $category->image, 'media/categories', 'category');
            $category->save();

            CatalogChangedEvent::dispatch($category, 'category', $isNew ? 'created' : 'updated', $isNew ? __(':name kategorisini oluşturdu.', ['name' => $category->name]) : __(':name kategorisini güncelledi.', ['name' => $category->name]), $causer);

            return $category;
        });
    }

    public function deleteCategory(Category $category, User $causer): void
    {
        if ($category->children()->exists()) {
            throw new ShopException(__(':name kategorisinin alt kategorileri var; önce onları taşıyın veya silin.', ['name' => $category->name]));
        }

        if ($category->products()->exists()) {
            throw new ShopException(__(':name kategorisinde ürün var; önce ürünleri başka kategoriye taşıyın.', ['name' => $category->name]));
        }

        DB::transaction(function () use ($category, $causer) {
            $this->files->deletePublicFile($category->image);
            $category->delete();

            CatalogChangedEvent::dispatch(null, 'category', 'deleted', __(':name kategorisini sildi.', ['name' => $category->name]), $causer);
        });
    }

    // -------------------------------------------------------------------- Marka

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveBrand(?Brand $brand, array $data, ?UploadedFile $logo, bool $removeLogo, User $causer): Brand
    {
        return DB::transaction(function () use ($brand, $data, $logo, $removeLogo, $causer) {
            $isNew = $brand === null;
            $brand ??= new Brand;

            $data['slug'] = $this->uniqueSlug(Brand::class, $data['slug'] ?? null, $data['name'], $brand->id);

            $brand->fill($data);
            $brand->logo = $this->handleImage($logo, $removeLogo, $brand->logo, 'media/brands', 'brand');
            $brand->save();

            CatalogChangedEvent::dispatch($brand, 'brand', $isNew ? 'created' : 'updated', $isNew ? __(':name markasını oluşturdu.', ['name' => $brand->name]) : __(':name markasını güncelledi.', ['name' => $brand->name]), $causer);

            return $brand;
        });
    }

    public function deleteBrand(Brand $brand, User $causer): void
    {
        if ($brand->products()->exists()) {
            throw new ShopException(__(':name markasına bağlı ürünler var; marka silinemez (pasifleştirebilirsiniz).', ['name' => $brand->name]));
        }

        DB::transaction(function () use ($brand, $causer) {
            $this->files->deletePublicFile($brand->logo);
            $brand->delete();

            CatalogChangedEvent::dispatch(null, 'brand', 'deleted', __(':name markasını sildi.', ['name' => $brand->name]), $causer);
        });
    }

    // --------------------------------------------------------------------- Depo

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveWarehouse(?Warehouse $warehouse, array $data, User $causer): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data, $causer) {
            $isNew = $warehouse === null;
            $warehouse ??= new Warehouse;

            // Varsayılan depo yalnız Stok › Stok Ayarları'ndan seçilir; ilk depo kendiliğinden varsayılan olur.
            unset($data['is_default']);

            if (! $isNew && $warehouse->is_default && ($data['status'] ?? null) === Status::Passive->value) {
                throw new ShopException(__('Varsayılan depo pasif olamaz; önce Stok Ayarları\'ndan başka bir depoyu varsayılan yapın.'));
            }

            $warehouse->fill($data);
            $warehouse->is_default = $warehouse->is_default || ! Warehouse::query()->where('is_default', true)->exists();
            $warehouse->save();

            $this->syncProductTotals($warehouse);

            CatalogChangedEvent::dispatch($warehouse, 'warehouse', $isNew ? 'created' : 'updated', $isNew ? __(':name deposunu oluşturdu.', ['name' => $warehouse->name]) : __(':name deposunu güncelledi.', ['name' => $warehouse->name]), $causer);

            return $warehouse;
        });
    }

    public function deleteWarehouse(Warehouse $warehouse, User $causer): void
    {
        if ($warehouse->is_default) {
            throw new ShopException(__('Varsayılan depo silinemez.'));
        }

        if ($warehouse->stocks()->where('quantity', '!=', 0)->exists()) {
            throw new ShopException(__(':name deposunda stok bulunuyor; önce stokları transfer edin.', ['name' => $warehouse->name]));
        }

        // Hareket geçmişi denetim izidir; silinmez. Kullanılmış depo pasife alınır.
        if ($warehouse->movements()->exists()) {
            throw new ShopException(__(':name deposunun stok hareket geçmişi var; silmek yerine pasife alın.', ['name' => $warehouse->name]));
        }

        DB::transaction(function () use ($warehouse, $causer) {
            $warehouse->delete();

            CatalogChangedEvent::dispatch(null, 'warehouse', 'deleted', __(':name deposunu sildi.', ['name' => $warehouse->name]), $causer);
        });
    }

    /**
     * Deponun satılabilirlik/durum değişikliği ürün toplam stoklarını etkiler.
     */
    private function syncProductTotals(Warehouse $warehouse): void
    {
        if (! $warehouse->wasChanged(['is_sellable', 'status'])) {
            return;
        }

        ProductStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->with('product')
            ->chunkById(200, function ($stocks) {
                foreach ($stocks as $stock) {
                    $stock->product?->syncStockTotal();
                }
            });
    }

    // ----------------------------------------------------------------- Sözleşme

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveContract(?Contract $contract, array $data, User $causer): Contract
    {
        return DB::transaction(function () use ($contract, $data, $causer) {
            $isNew = $contract === null;
            $contract ??= new Contract;

            $data['slug'] = $this->uniqueSlug(Contract::class, $data['slug'] ?? null, $data['title'], $contract->id);
            $contract->fill($data)->save();

            CatalogChangedEvent::dispatch($contract, 'contract', $isNew ? 'created' : 'updated', $isNew ? __(':name sözleşmesini oluşturdu.', ['name' => $contract->title]) : __(':name sözleşmesini güncelledi.', ['name' => $contract->title]), $causer);

            return $contract;
        });
    }

    public function deleteContract(Contract $contract, User $causer): void
    {
        DB::transaction(function () use ($contract, $causer) {
            $contract->delete();

            CatalogChangedEvent::dispatch(null, 'contract', 'deleted', __(':title sözleşmesini sildi.', ['title' => $contract->title]), $causer);
        });
    }

    // ------------------------------------------------------------------- Durum

    public function toggleStatus(Model $model, string $logName, string $label, User $causer): void
    {
        $active = $model->getAttribute('status') === Status::Active;

        // Kategori ağacı tutarlı kalır: pasif üst kategorinin altı aktif olamaz.
        if ($model instanceof Category && ! $active && $model->parent_id !== null
            && Category::query()->whereKey($model->parent_id)->where('status', Status::Passive->value)->exists()) {
            throw new ShopException(__('Üst kategorisi pasif olan kategori aktifleştirilemez; önce üst kategoriyi aktifleştirin.'));
        }

        DB::transaction(function () use ($model, $active) {
            $model->setAttribute('status', $active ? Status::Passive : Status::Active);
            $model->save();

            // Pasifleşen kategorinin alt kategorileri de pasifleşir (ürünleri sitede gizlenir).
            if ($model instanceof Category && $active) {
                Category::query()->whereKey($model->descendantIds())->where('status', Status::Active->value)->get()
                    ->each(fn (Category $child) => $child->update(['status' => Status::Passive]));
            }
        });

        CatalogChangedEvent::dispatch($model, $logName, $active ? 'deactivated' : 'activated', $active ? __(':name kaydını pasifleştirdi.', ['name' => $label]) : __(':name kaydını aktifleştirdi.', ['name' => $label]), $causer);
    }

    // ---------------------------------------------------------------- Yardımcı

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function uniqueSlug(string $modelClass, ?string $slug, string $fallback, ?string $ignoreId = null): string
    {
        $base = Str::slug($slug !== null && $slug !== '' ? $slug : $fallback) ?: Str::lower(Str::random(8));
        $candidate = $base;
        $i = 2;

        while ($modelClass::query()
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->when(method_exists($modelClass, 'bootSoftDeletes'), fn ($q) => $q->withTrashed())
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }

    public function handleImage(?UploadedFile $file, bool $remove, ?string $current, string $directory, string $prefix): ?string
    {
        if ($file !== null) {
            return $this->files->storePublicImage($file, $directory, $this->ownPath($current), $prefix);
        }

        if ($remove) {
            $this->files->deletePublicFile($this->ownPath($current));

            return null;
        }

        return $current;
    }

    /**
     * Seed edilen harici/asset yollarını (http, /storefront) silmeye çalışmamak için.
     */
    public function ownPath(?string $path): ?string
    {
        if ($path === null || Str::startsWith($path, ['http://', 'https://', '/'])) {
            return null;
        }

        return $path;
    }
}
