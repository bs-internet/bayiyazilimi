<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('an extension can send a user to its own area and keep them out of the panel', function () {
    $rep = User::factory()->admin()->create(['email' => 'temsilci@example.com']);

    hooks()->filter('auth.home', fn (?string $value, User $user) => $user->email === 'temsilci@example.com' ? '/temsilci' : $value);
    hooks()->filter('user.area_access', fn (bool $value, User $user) => $value && $user->email !== 'temsilci@example.com');

    $this->actingAs($rep)->get(route('dashboard'))->assertRedirect('/temsilci');
    $this->actingAs($rep)->get(route('panel.dashboard'))->assertRedirect(route('dashboard'));

    // Diğer yöneticiler etkilenmez.
    $this->flushSession();
    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
    $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('panel.dashboard'));
    $this->actingAs($admin)->get(route('panel.dashboard'))->assertOk();
});
