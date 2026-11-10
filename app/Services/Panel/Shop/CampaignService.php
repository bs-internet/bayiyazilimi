<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Kampanya kayıt işlemleri; her değişiklik etkinlik kaydına düşer.
 */
final class CampaignService
{
    private const LOG_NAME = 'campaign';

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?Campaign $campaign, array $data, User $causer): Campaign
    {
        $isNew = $campaign === null;
        // Yeni kampanya pasif başlar; ayarlar kontrol edilip yayına alınır.
        $campaign ??= new Campaign(['status' => Status::Passive]);
        $combinable = $data['combinable'] ?? null;
        unset($data['combinable']);

        DB::transaction(function () use ($campaign, $data, $combinable): void {
            $campaign->fill($data)->save();

            if (is_array($combinable)) {
                $this->syncCombinations($campaign, $combinable);
            }
        });

        CatalogChangedEvent::dispatch($campaign, self::LOG_NAME, $isNew ? 'created' : 'updated',
            $isNew ? __(':name kampanyasını oluşturdu.', ['name' => $campaign->name]) : __(':name kampanyasını güncelledi.', ['name' => $campaign->name]), $causer);

        return $campaign;
    }

    /**
     * Birlikte uygulanabilen kampanyaları simetrik yazar: A, B'yi seçerse B de
     * A'yı seçmiş sayılır; listeden çıkarılan kampanyanın karşı satırı da silinir.
     *
     * @param  array<int, string>  $ids
     */
    private function syncCombinations(Campaign $campaign, array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id !== $campaign->id)));
        $previous = $campaign->combinables()->pluck('campaigns.id')->all();

        $campaign->combinables()->sync($ids);

        foreach (array_diff($previous, $ids) as $removed) {
            DB::table('campaign_combinations')->where('campaign_id', $removed)->where('other_id', $campaign->id)->delete();
        }

        foreach ($ids as $id) {
            DB::table('campaign_combinations')->insertOrIgnore(['campaign_id' => $id, 'other_id' => $campaign->id]);
        }
    }

    public function toggleStatus(Campaign $campaign, User $causer): void
    {
        $active = $campaign->isActive();
        $campaign->update(['status' => $active ? Status::Passive : Status::Active]);

        CatalogChangedEvent::dispatch($campaign, self::LOG_NAME, $active ? 'deactivated' : 'activated',
            $active ? __(':name kampanyasını pasifleştirdi.', ['name' => $campaign->name]) : __(':name kampanyasını aktifleştirdi.', ['name' => $campaign->name]), $causer);
    }

    public function delete(Campaign $campaign, User $causer): void
    {
        $name = $campaign->name;
        $campaign->delete();

        CatalogChangedEvent::dispatch(null, self::LOG_NAME, 'deleted', __(':name kampanyasını sildi.', ['name' => $name]), $causer);
    }
}
