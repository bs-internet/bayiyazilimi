<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Enums\Status;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\BankAccount;
use App\Models\Contract;
use App\Models\User;
use App\Services\Shop\ShopSettings;
use App\Support\ContentPlaceholders;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();
    // Kurulumla gelen taslaklar pasif; testler kendi sözleşmesini kurar.
    Contract::query()->delete();
});

function makeContract(array $attributes = []): Contract
{
    return Contract::query()->create([
        'title' => 'Satış Sözleşmesi', 'slug' => 'satis', 'content' => '<p>Metin</p><script>x()</script>',
        'show_on_checkout' => false, 'show_on_registration' => false, 'status' => Status::Active, 'sort_order' => 1,
        ...$attributes,
    ]);
}

it('creates a contract passive under definitions and toggles it from the list', function () {
    $this->actingAs($this->admin)->post(route('panel.shop.contracts.store'), [
        'title' => 'Satış Şartları', 'content' => '<p>Şartlar</p>', 'show_on_checkout' => '1',
    ])->assertSessionHasNoErrors();

    $contract = Contract::query()->where('title', 'Satış Şartları')->firstOrFail();
    expect($contract)->status->toBe(Status::Passive)->slug->toBe('satis-sartlari')
        ->show_on_checkout->toBeTrue()->show_on_registration->toBeFalse();

    $this->actingAs($this->admin)->patch(route('panel.shop.contracts.status', $contract), ['status' => 'active']);
    expect($contract->fresh()->status)->toBe(Status::Active);

    // Aynı hedef durum ikinci kez gelirse geri dönmez (çift tıklama).
    $this->actingAs($this->admin)->patch(route('panel.shop.contracts.status', $contract), ['status' => 'active']);
    expect($contract->fresh()->status)->toBe(Status::Active);

    $this->actingAs($this->admin)->get(route('panel.shop.contracts.index'))
        ->assertInertia(fn (Assert $page) => $page->component('panel/tools/definitions/contracts/index')->has('contracts', 1));

    $this->actingAs($this->admin)->delete(route('panel.shop.contracts.destroy', $contract))->assertRedirect(route('panel.shop.contracts.index'));
    expect(Contract::query()->count())->toBe(0);
});

it('offers the registration form option only while the site is public', function () {
    $contract = makeContract(['show_on_registration' => true]);
    $payload = ['title' => 'Satış Sözleşmesi', 'show_on_registration' => '0'];

    // B2B çekirdek: üyelik formu yok; kutu gizli, kayıtlı değere dokunulmaz.
    hooks()->filter('site.public_access', fn () => false, PHP_INT_MAX);
    $this->actingAs($this->admin)->get(route('panel.shop.contracts.edit', $contract))
        ->assertInertia(fn (Assert $page) => $page->where('registration', false));
    $this->actingAs($this->admin)->put(route('panel.shop.contracts.update', $contract), $payload)->assertSessionHasNoErrors();
    expect($contract->fresh()->show_on_registration)->toBeTrue();

    // Site herkese açıkken (vitrin) üyelik formu vardır.
    hooks()->filter('site.public_access', fn () => true, PHP_INT_MAX);
    $this->actingAs($this->admin)->get(route('panel.shop.contracts.edit', $contract))
        ->assertInertia(fn (Assert $page) => $page->where('registration', true));
    $this->actingAs($this->admin)->put(route('panel.shop.contracts.update', $contract), $payload)->assertSessionHasNoErrors();
    expect($contract->fresh()->show_on_registration)->toBeFalse();
});

it('offers only active contracts for the matching form, with sanitized content', function () {
    makeContract(['show_on_checkout' => true]);
    makeContract(['title' => 'KVKK', 'slug' => 'kvkk', 'show_on_checkout' => true, 'show_on_registration' => true, 'sort_order' => 2]);
    makeContract(['title' => 'Taslak', 'slug' => 'taslak', 'show_on_checkout' => true, 'status' => Status::Passive]);
    makeContract(['title' => 'Genel', 'slug' => 'genel']);

    expect(collect(Contract::forForm(Contract::CHECKOUT))->pluck('title')->all())->toBe(['Satış Sözleşmesi', 'KVKK'])
        ->and(collect(Contract::forForm(Contract::REGISTRATION))->pluck('title')->all())->toBe(['KVKK'])
        ->and(Contract::forForm(Contract::CHECKOUT)[0]['content'])->toBe('<p>Metin</p>');
});

it('requires a separate agreement for each active checkout contract', function () {
    expect(collect((new CheckoutRequest)->rules())->keys()->filter(fn ($k) => str_starts_with($k, 'agreements.')))->toBeEmpty();

    makeContract(['show_on_checkout' => true]);
    makeContract(['title' => 'KVKK', 'slug' => 'kvkk', 'show_on_checkout' => true, 'sort_order' => 2]);
    makeContract(['title' => 'Taslak', 'slug' => 'taslak', 'show_on_checkout' => true, 'status' => Status::Passive]);

    $request = new CheckoutRequest;
    expect($request->rules())
        ->toHaveKey('agreements.satis', ['accepted'])
        ->toHaveKey('agreements.kvkk', ['accepted'])
        ->not->toHaveKey('agreements.taslak')
        ->and($request->rules()['marketing_consent'])->toBe(['nullable', 'boolean'])
        ->and($request->attributes()['agreements.kvkk'])->toBe('KVKK');
});

it('fills placeholders from corporate info and bank accounts when a contract is shown', function () {
    ShopSettings::save(['store_name' => 'Örnek <Ticaret> A.Ş.', 'tax_office' => 'Kadıköy', 'tax_number' => '1234567890', 'mersis_number' => '0123456789000015']);
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'branch' => 'Merkez', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    BankAccount::query()->create(['bank_name' => 'Kapalı Banka', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR000000000000000000000000', 'status' => 'passive']);

    makeContract([
        'show_on_checkout' => true,
        'content' => '<p>Satıcı: {unvan}, {vergi_dairesi} V.D. {vergi_no}, MERSİS {mersis_no} {bilinmeyen}</p><p>{banka_hesaplari}</p>',
    ]);

    $content = Contract::forForm(Contract::CHECKOUT)[0]['content'];

    expect($content)
        ->toContain('Satıcı: Örnek &lt;Ticaret&gt; A.Ş., Kadıköy V.D. 1234567890, MERSİS 0123456789000015 {bilinmeyen}')
        ->toContain('<table>')->not->toContain('<p><table>')
        ->toContain('TR12 0006 4000 0011 2345 6789 01')->toContain('Merkez')
        ->not->toContain('Kapalı Banka');

    // Bayi panelindeki Sözleşmeler de doldurulmuş metni gösterir.
    $this->actingAs($this->member)->get(route('app.shop.contracts.show', 'satis'))
        ->assertInertia(fn (Assert $page) => $page->where('contract.content', fn (string $html) => str_contains($html, 'Kadıköy V.D. 1234567890')));
});

it('gives the contract editor the raw text and the placeholder list', function () {
    $contract = makeContract(['content' => '<p>{unvan}</p>']);

    $this->actingAs($this->admin)->get(route('panel.shop.contracts.edit', $contract))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contract.content', fn (string $html) => str_contains($html, '{unvan}'))
            ->where('placeholders', fn ($items) => collect($items)->pluck('token')->contains('{banka_hesaplari}')));
});

it('leaves the bank account placeholder empty when no account is active', function () {
    expect(ContentPlaceholders::fill('<p>Hesaplar:</p><p>{banka_hesaplari}</p>'))->toBe('<p>Hesaplar:</p>');
});

it('asks each registration contract separately and records the optional commercial message consent', function () {
    $input = ['name' => 'Yeni Üye', 'email' => 'yeni@ornek.com', 'password' => 'Gizli.Sifre-2026', 'password_confirmation' => 'Gizli.Sifre-2026'];

    // Onaylatılan sözleşme yokken kutu da yok; ileti onayı verilmezse zaman boş.
    expect(app(CreateNewUser::class)->create($input)->marketing_consent_at)->toBeNull();

    makeContract(['title' => 'Üyelik Sözleşmesi', 'slug' => 'uyelik', 'show_on_registration' => true]);
    makeContract(['title' => 'KVKK', 'slug' => 'kvkk', 'show_on_registration' => true, 'sort_order' => 2]);
    $input['email'] = 'ikinci@ornek.com';

    try {
        app(CreateNewUser::class)->create([...$input, 'agreements' => ['uyelik' => '1']]);
        $this->fail('KVKK onayı olmadan kayıt açılmamalı.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('agreements.kvkk')->not->toHaveKey('agreements.uyelik');
    }

    $user = app(CreateNewUser::class)->create([...$input, 'agreements' => ['uyelik' => '1', 'kvkk' => '1'], 'marketing_consent' => '1']);

    expect($user->email)->toBe('ikinci@ornek.com')
        ->and($user->fresh()->marketing_consent_at)->not->toBeNull();

    $this->actingAs($this->admin)->get(route('panel.members.show', $user))
        ->assertInertia(fn (Assert $page) => $page->where('user.data.marketing_consent_at', fn ($value) => $value !== null));
});

it('publishes a contract on the site only while the site is public', function () {
    $contract = makeContract();
    $payload = ['title' => 'Satış Sözleşmesi', 'show_on_site' => '1'];

    hooks()->filter('site.public_access', fn () => false, PHP_INT_MAX);
    $this->actingAs($this->admin)->put(route('panel.shop.contracts.update', $contract), $payload)->assertSessionHasNoErrors();
    expect($contract->fresh()->show_on_site)->toBeFalse();

    hooks()->filter('site.public_access', fn () => true, PHP_INT_MAX);
    $this->actingAs($this->admin)->put(route('panel.shop.contracts.update', $contract), $payload)->assertSessionHasNoErrors();
    expect($contract->fresh()->show_on_site)->toBeTrue();

    $this->actingAs($this->admin)->get(route('panel.shop.contracts.edit', $contract))
        ->assertInertia(fn (Assert $page) => $page->where('contract.show_on_site', true));
});

it('lists active contracts in the dealer panel', function () {
    makeContract(['show_on_checkout' => true]);
    makeContract(['title' => 'Garanti Şartları', 'slug' => 'garanti', 'sort_order' => 2]);
    $hidden = makeContract(['title' => 'Taslak', 'slug' => 'taslak', 'status' => Status::Passive]);

    $this->actingAs($this->member)->get(route('app.shop.contracts.index'))->assertRedirect(route('app.shop.contracts.show', 'satis'));

    $this->actingAs($this->member)->get(route('app.shop.contracts.show', 'satis'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/contracts/show')
            ->where('contract.content', '<p>Metin</p>')
            ->where('items.1.title', 'Garanti Şartları')
            ->has('items', 2));

    $this->actingAs($this->member)->get(route('app.shop.contracts.show', $hidden->slug))->assertNotFound();
});

it('shows an empty contract list when nothing is active', function () {
    $this->actingAs($this->member)->get(route('app.shop.contracts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('contract', null)->where('items', []));
});
