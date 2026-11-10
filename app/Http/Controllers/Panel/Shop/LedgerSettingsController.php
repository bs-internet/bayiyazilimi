<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\ChequeStatus;
use App\Events\Shop\CatalogChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Services\Shop\ShopSettings;
use App\Support\Money;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\PaymentRegistry;
use App\Support\Payments\PaymentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tanımlamalar › Cari Ayarları: ödeme yöntemlerinin en düşük
 * sipariş tutarı, vade uyarısı ve çek / senet portföyü (varsayılan kapalı).
 */
class LedgerSettingsController extends Controller
{
    public function edit(PaymentRegistry $payments): Response
    {
        $mins = PaymentRules::minAmounts();

        return Inertia::render('panel/tools/definitions/ledger/index', [
            'currency' => Money::baseCode(),
            'methods' => array_values(array_map(fn (PaymentGateway $g) => [
                'key' => $g->key(),
                'label' => $g->label(),
                'min_amount' => $mins[$g->key()] ?? null,
            ], $payments->all())),
            'dueWarningDays' => ShopSettings::int('due_warning_days'),
            'chequesEnabled' => ShopSettings::bool('cheques_enabled'),
            'chequeWarningDays' => ShopSettings::int('cheque_warning_days'),
        ]);
    }

    public function update(Request $request, PaymentRegistry $payments): RedirectResponse
    {
        $data = $request->validate([
            'min_amounts' => ['nullable', 'array'],
            'min_amounts.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'due_warning_days' => ['required', 'integer', 'min:0', 'max:365'],
            'cheque_warning_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ], [], ['min_amounts.*' => __('en düşük tutar'), 'due_warning_days' => __('vade uyarı süresi'), 'cheque_warning_days' => __('çek / senet uyarı süresi')]);

        // Portföyde (tahsil edilmemiş) çek / senet varken özellik kapatılamaz: kapanınca
        // portföy ekranı gizlenir ve bu evraklar yönetilemez hale gelir.
        if (ShopSettings::bool('cheques_enabled') && ! $request->boolean('cheques_enabled')
            && Cheque::query()->withoutGlobalScopes()->whereIn('status', ChequeStatus::atRisk())->exists()) {
            throw ValidationException::withMessages([
                'cheques_enabled' => __('Portföyde tahsil edilmemiş çek / senet varken bu özellik kapatılamaz; önce tahsil, iade ya da karşılıksız olarak sonuçlandırın.'),
            ]);
        }

        $mins = [];

        foreach ($data['min_amounts'] ?? [] as $key => $value) {
            if ($payments->has((string) $key) && is_numeric($value) && Money::decimal((string) $value)->isPositive()) {
                $mins[$key] = Money::str((string) $value);
            }
        }

        ShopSettings::save([
            'payment_min_amounts' => json_encode($mins, JSON_THROW_ON_ERROR),
            'due_warning_days' => (string) (int) $data['due_warning_days'],
            'cheques_enabled' => $request->boolean('cheques_enabled'),
            'cheque_warning_days' => (string) (int) ($data['cheque_warning_days'] ?? ShopSettings::int('cheque_warning_days')),
        ]);

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', __('cari ayarlarını güncelledi.'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Cari ayarları kaydedildi.')]);
    }
}
