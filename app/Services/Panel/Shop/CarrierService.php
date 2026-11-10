<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\User;
use App\Services\Support\FileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Kargo firması CRUD iş kuralları (desi tabloları ShippingService'tedir).
 */
class CarrierService
{
    public function __construct(
        private readonly FileService $files,
        private readonly CatalogService $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?Carrier $carrier, array $data, ?UploadedFile $logo, bool $removeLogo, User $causer): Carrier
    {
        return DB::transaction(function () use ($carrier, $data, $logo, $removeLogo, $causer) {
            $isNew = $carrier === null;
            $carrier ??= new Carrier;

            // Varsayılan firma, işareti kaldırılarak varsayılansız bırakılamaz: başka bir
            // firma varsayılan yapılınca bu kendiliğinden düşer.
            if (! $isNew && $carrier->is_default) {
                $data['is_default'] = true;
            }

            $status = $data['status'] ?? Status::Active->value;

            if (($data['is_default'] ?? false) && ($status instanceof Status ? $status : Status::from((string) $status)) === Status::Passive) {
                throw new ShopException(__('Varsayılan kargo firması pasif olamaz.'));
            }

            $carrier->fill($data);
            $carrier->logo = $this->catalog->handleImage($logo, $removeLogo, $carrier->logo, 'media/carriers', 'carrier');
            $carrier->save();

            if ($carrier->is_default) {
                Carrier::query()->whereKeyNot($carrier->id)->update(['is_default' => false]);
            }

            CatalogChangedEvent::dispatch($carrier, 'carrier', $isNew ? 'created' : 'updated', $isNew ? __(':name kargo firmasını oluşturdu.', ['name' => $carrier->name]) : __(':name kargo firmasını güncelledi.', ['name' => $carrier->name]), $causer);

            return $carrier;
        });
    }

    public function toggleStatus(Carrier $carrier, User $causer): void
    {
        if ($carrier->is_default && $carrier->status === Status::Active) {
            throw new ShopException(__('Varsayılan kargo firması pasifleştirilemez; önce başka bir firmayı varsayılan yapın.'));
        }

        $this->catalog->toggleStatus($carrier, 'carrier', $carrier->name, $causer);
    }

    public function delete(Carrier $carrier, User $causer): void
    {
        if (Order::query()->where('carrier_id', $carrier->id)->exists()) {
            throw new ShopException(__(':name siparişlerde kullanıldığı için silinemez (pasifleştirebilirsiniz).', ['name' => $carrier->name]));
        }

        if ($carrier->is_default) {
            throw new ShopException(__('Varsayılan kargo firması silinemez.'));
        }

        DB::transaction(function () use ($carrier, $causer) {
            $this->files->deletePublicFile($this->catalog->ownPath($carrier->logo));
            $carrier->delete();

            CatalogChangedEvent::dispatch(null, 'carrier', 'deleted', __(':name kargo firmasını sildi.', ['name' => $carrier->name]), $causer);
        });
    }
}
