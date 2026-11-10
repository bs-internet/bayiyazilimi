<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop;

use App\Enums\Shop\CustomerType;
use App\Events\Shop\CatalogChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\DealerGroup;
use App\Models\User;
use App\Services\Account\AccountProvisioner;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Bayi grupları ve müşteri türü (müşteri ↔ bayi) yönetimi.
 */
class DealerService
{
    public function __construct(
        private readonly AccountProvisioner $provisioner,
    ) {}

    /**
     * @param  array{name: string, discount_rate: float|string, description?: ?string}  $data
     */
    public function saveGroup(?DealerGroup $group, array $data, User $causer): DealerGroup
    {
        return DB::transaction(function () use ($group, $data, $causer) {
            $isNew = $group === null;
            $group ??= new DealerGroup;
            $group->fill($data)->save();

            CatalogChangedEvent::dispatch(
                $group,
                'dealer',
                $isNew ? 'created' : 'updated',
                $isNew ? __(':name bayi grubunu oluşturdu.', ['name' => $group->name]) : __(':name bayi grubunu güncelledi.', ['name' => $group->name]),
                $causer,
            );

            return $group;
        });
    }

    public function deleteGroup(DealerGroup $group, User $causer): void
    {
        DB::transaction(function () use ($group, $causer) {
            $count = $group->accounts()->count();

            if ($count > 0) {
                throw new ShopException(__(':name grubunda :count bayi var; önce bayileri başka gruba taşıyın.', ['name' => $group->name, 'count' => $count]));
            }

            $group->delete();

            CatalogChangedEvent::dispatch(null, 'dealer', 'deleted', __(':name bayi grubunu sildi.', ['name' => $group->name]), $causer);
        });
    }

    /**
     * Üyenin türünü (müşteri/bayi), bayi grubunu ve bayiye özel indirimini ayarlar.
     * Hesabı henüz oluşmamış üye için hesap açılır.
     *
     * @param  array{type: string, dealer_group_id?: ?string, discount_rate?: float|string|null, warehouse_id?: ?string}  $data
     */
    public function setCustomerType(User $user, array $data, User $causer): Account
    {
        return DB::transaction(function () use ($user, $data, $causer) {
            $account = $this->provisioner->ensureForUser($user);

            if ($account === null) {
                throw new ShopException(__('Yalnızca üyelerin müşteri türü değiştirilebilir.'));
            }

            $type = CustomerType::from($data['type']);
            $wasDealer = $account->isDealer();
            $rate = $data['discount_rate'] ?? null;

            $account->fill([
                'type' => $type,
                'dealer_group_id' => $type === CustomerType::Dealer ? ($data['dealer_group_id'] ?? null) : null,
                'warehouse_id' => $type === CustomerType::Dealer ? (($data['warehouse_id'] ?? null) ?: null) : null,
                'discount_rate' => $type === CustomerType::Dealer && $rate !== null && $rate !== '' ? Money::round((string) $rate) : null,
            ])->save();

            // Bayiye özel cari çalışma şekli (boş = gruptan); müşteriye çevrilince temizlenir.
            $terms = ['payment_terms', 'credit_limit', 'payment_term_days', 'over_limit_action', 'overdue_action', 'allowed_payment_methods'];
            $account->forceFill(array_combine($terms, array_map(
                fn (string $key) => $type === CustomerType::Dealer && array_key_exists($key, $data) ? $data[$key] : ($type === CustomerType::Dealer ? $account->getAttribute($key) : null),
                $terms,
            )))->save();

            $account->load('dealerGroup');

            $message = match (true) {
                $type === CustomerType::Dealer && ! $wasDealer => __(':name müşterisini bayiye çevirdi.', ['name' => $user->name]),
                $type === CustomerType::Customer && $wasDealer => __(':name bayisini müşteriye çevirdi.', ['name' => $user->name]),
                default => __(':name bayi ayarlarını güncelledi.', ['name' => $user->name]),
            };

            CatalogChangedEvent::dispatch($user, 'dealer', 'type', $message, $causer);

            return $account;
        });
    }
}
