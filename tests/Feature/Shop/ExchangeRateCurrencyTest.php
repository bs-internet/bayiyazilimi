<?php

declare(strict_types=1);

use App\Enums\Shop\RateSource;
use App\Enums\Status;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

const TCMB_XML = '<?xml version="1.0" encoding="UTF-8"?><Tarih_Date>'
    .'<Currency CurrencyCode="USD"><Unit>1</Unit><Isim>ABD DOLARI</Isim><ForexBuying>40.1</ForexBuying><ForexSelling>40.2</ForexSelling></Currency>'
    .'<Currency CurrencyCode="EUR"><Unit>1</Unit><Isim>EURO</Isim><ForexBuying>48.1</ForexBuying><ForexSelling>48.2</ForexSelling></Currency>'
    .'<Currency CurrencyCode="JPY"><Unit>100</Unit><Isim>JAPON YENİ</Isim><ForexBuying>27</ForexBuying><ForexSelling>27.5</ForexSelling></Currency>'
    .'</Tarih_Date>';

function rateCurrency(string $code, float $rate = 1): Currency
{
    return Currency::query()->updateOrCreate(['code' => $code], [
        'name' => $code,
        'symbol' => $code,
        'decimal_places' => 2,
        'thousands_separator' => '.',
        'decimal_separator' => ',',
        'exchange_rate' => $rate,
        'rate_source' => RateSource::Manual,
        'status' => Status::Active,
        'sort_order' => 1,
    ]);
}

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
    Currency::query()->withTrashed()->where('code', '!=', 'TRY')->forceDelete();
    $try = rateCurrency('TRY');
    Setting::query()->updateOrCreate(['key' => 'default_currency_id'], ['value' => $try->id, 'group' => 'defaults']);
    Http::fake(['*' => Http::response(TCMB_XML)]);
});

test('available tcmb currencies exclude added ones and mark deleted as restorable', function () {
    rateCurrency('EUR', 30);
    rateCurrency('USD', 30)->delete();

    $this->actingAs($this->admin)
        ->get(route('panel.shop.exchange-rates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('available')
            ->reloadOnly('available', fn (Assert $reload) => $reload->where('available', [
                ['code' => 'JPY', 'name' => 'JAPON YENİ', 'unit' => 100, 'restorable' => false],
                ['code' => 'USD', 'name' => 'ABD DOLARI', 'unit' => 1, 'restorable' => true],
            ])));
});

test('admin can add a tcmb currency and its rate is fetched', function () {
    $this->actingAs($this->admin)
        ->post(route('panel.shop.exchange-rates.store'), ['code' => 'jpy'])
        ->assertSessionHasNoErrors();

    $jpy = Currency::query()->where('code', 'JPY')->firstOrFail();

    expect($jpy->rate_source)->toBe(RateSource::Tcmb)
        ->and($jpy->status)->toBe(Status::Active)
        ->and($jpy->exchange_rate)->toEqualDecimal('0.275')
        ->and($jpy->symbol)->toBe('¥');
});

test('adding a deleted currency restores it', function () {
    $usd = rateCurrency('USD', 30);
    $usd->delete();

    $this->actingAs($this->admin)
        ->post(route('panel.shop.exchange-rates.store'), ['code' => 'USD'])
        ->assertSessionHasNoErrors();

    $usd->refresh();

    expect($usd->trashed())->toBeFalse()
        ->and($usd->rate_source)->toBe(RateSource::Tcmb)
        ->and($usd->exchange_rate)->toEqualDecimal('40.2')
        ->and(Currency::query()->where('code', 'USD')->count())->toBe(1);
});

test('adding an existing or unknown currency fails', function () {
    rateCurrency('EUR', 30);

    $this->actingAs($this->admin)
        ->post(route('panel.shop.exchange-rates.store'), ['code' => 'EUR'])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('panel.shop.exchange-rates.store'), ['code' => 'XYZ'])
        ->assertRedirect();

    expect(Currency::query()->where('code', 'EUR')->count())->toBe(1)
        ->and(Currency::query()->where('code', 'XYZ')->exists())->toBeFalse();
});
