<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\SaveBankAccountRequest;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\PaymentNotification;
use App\Services\Shop\ShopSettings;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tanımlamalar → Banka Hesapları: havale / EFT için gösterilen hesaplar.
 */
class BankAccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('panel/tools/definitions/bank-accounts/index', [
            'accounts' => BankAccount::query()->ordered()->orderBy('bank_name')->get()->map(fn (BankAccount $a) => [
                'id' => $a->id,
                'bank_name' => $a->bank_name,
                'branch' => $a->branch,
                'account_holder' => $a->account_holder,
                'iban' => $a->formattedIban(),
                'account_number' => $a->account_number,
                'currency_code' => $a->currency_code,
                'status' => $a->status,
                'sort_order' => $a->sort_order,
            ])->all(),
            'currencies' => Currency::query()->where('status', Status::Active->value)->orderBy('sort_order')
                ->get(['code', 'name'])
                ->map(fn (Currency $c) => ['value' => $c->code, 'label' => __(':code — :name', ['code' => $c->code, 'name' => $c->name])])
                ->all(),
            'transferEnabled' => ShopSettings::bool('bank_transfer_enabled'),
            'discountRate' => Money::round(ShopSettings::get('bank_transfer_discount_rate')),
        ]);
    }

    /**
     * Havale indirimi (%): ödeme ekranında havale seçilince KDV dahil toplamdan düşülür.
     */
    public function discount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
        ], [], ['discount_rate' => __('havale indirimi')]);

        $rate = Money::round((string) ($data['discount_rate'] ?? 0));
        ShopSettings::save(['bank_transfer_discount_rate' => Money::str($rate)]);

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', __('havale indirimini %:rate yaptı.', ['rate' => Money::number($rate)]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Havale indirimi kaydedildi.')]);
    }

    /**
     * Havale / EFT ödeme seçeneğini açar / kapatır (aktif hesap yoksa açık olsa da sunulmaz).
     */
    public function payment(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        ShopSettings::save(['bank_transfer_enabled' => $enabled]);

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', $enabled ? __('havale / EFT ödemesini açtı.') : __('havale / EFT ödemesini kapattı.'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __($enabled ? __('Havale / EFT ödemesi açıldı.') : __('Havale / EFT ödemesi kapatıldı.'))]);
    }

    public function store(SaveBankAccountRequest $request): RedirectResponse
    {
        $account = BankAccount::query()->create([
            ...$this->attributes($request),
            'status' => Status::Active,
        ]);

        CatalogChangedEvent::dispatch($account, 'bank_account', 'created', __(':bank_name banka hesabını ekledi.', ['bank_name' => $account->bank_name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':bank_name hesabı eklendi.', ['bank_name' => $account->bank_name])]);
    }

    public function update(SaveBankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $bankAccount->update($this->attributes($request));

        CatalogChangedEvent::dispatch($bankAccount, 'bank_account', 'updated', __(':bank_name banka hesabını güncelledi.', ['bank_name' => $bankAccount->bank_name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':bank_name hesabı güncellendi.', ['bank_name' => $bankAccount->bank_name])]);
    }

    public function status(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        // Hedef durum zaten geçerliyse (çift tıklama / iki yönetici) değişiklik yapılmaz.
        if ($bankAccount->hasStatus($request->input('status'))) {
            return back();
        }

        $bankAccount->update(['status' => $bankAccount->isActive() ? Status::Passive : Status::Active]);

        CatalogChangedEvent::dispatch($bankAccount, 'bank_account', 'status', $bankAccount->isActive()
            ? __(':bank_name banka hesabını aktifleştirdi.', ['bank_name' => $bankAccount->bank_name])
            : __(':bank_name banka hesabını pasifleştirdi.', ['bank_name' => $bankAccount->bank_name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => $bankAccount->isActive() ? __(':bank_name hesabı aktifleştirildi.', ['bank_name' => $bankAccount->bank_name]) : __(':bank_name hesabı pasifleştirildi.', ['bank_name' => $bankAccount->bank_name])]);
    }

    public function destroy(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        // Havale bildirimlerinde kullanılan hesap silinmez (bildirimin banka bilgisi kaybolurdu).
        if (PaymentNotification::query()->withoutGlobalScopes()->where('bank_account_id', $bankAccount->id)->exists()) {
            throw new ShopException(__(':bank_name hesabı havale bildirimlerinde kullanılıyor; silmek yerine pasifleştirin.', ['bank_name' => $bankAccount->bank_name]));
        }

        $bankAccount->delete();

        CatalogChangedEvent::dispatch(null, 'bank_account', 'deleted', __(':bank_name banka hesabını sildi.', ['bank_name' => $bankAccount->bank_name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':bank_name hesabı silindi.', ['bank_name' => $bankAccount->bank_name])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SaveBankAccountRequest $request): array
    {
        $data = $request->validated();

        return [
            'bank_name' => $data['bank_name'],
            'branch' => $data['branch'] ?? null,
            'account_holder' => $data['account_holder'],
            'iban' => $data['iban'],
            'account_number' => $data['account_number'] ?? null,
            'currency_code' => $data['currency_code'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
