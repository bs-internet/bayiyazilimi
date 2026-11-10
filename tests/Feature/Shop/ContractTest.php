<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Enums\Status;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\Contract;
use App\Models\User;
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

it('requires the checkout agreement only when a checkout contract is active', function () {
    expect((new CheckoutRequest)->rules()['agreement'])->toBe(['nullable']);

    makeContract(['show_on_checkout' => true]);

    expect((new CheckoutRequest)->rules()['agreement'])->toBe(['accepted']);
});

it('requires the registration agreement only when a registration contract is active', function () {
    $input = ['name' => 'Yeni Üye', 'email' => 'yeni@ornek.com', 'password' => 'Gizli.Sifre-2026', 'password_confirmation' => 'Gizli.Sifre-2026'];

    makeContract(['show_on_registration' => true]);

    expect(fn () => app(CreateNewUser::class)->create($input))->toThrow(ValidationException::class);

    expect(app(CreateNewUser::class)->create([...$input, 'agreement' => '1']))->email->toBe('yeni@ornek.com');
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
