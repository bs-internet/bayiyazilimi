<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        // Üyelik formunda onaylatılan her sözleşmenin kendi onay kutusu vardır.
        $contracts = Contract::titlesForForm(Contract::REGISTRATION);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'agreements' => ['nullable', 'array'],
            ...collect($contracts)->mapWithKeys(fn (string $title, string $slug) => ["agreements.{$slug}" => ['accepted']])->all(),
            // Ticari elektronik ileti onayı (6563 s. Kanun): isteğe bağlı, ayrı kutu.
            'marketing_consent' => ['nullable', 'boolean'],
        ], attributes: [
            'marketing_consent' => __('ticari ileti onayı'),
            ...collect($contracts)->mapWithKeys(fn (string $title, string $slug) => ["agreements.{$slug}" => $title])->all(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'status' => UserStatus::Draft,
            'user_type' => UserType::Member,
            'marketing_consent_at' => filter_var($input['marketing_consent'] ?? false, FILTER_VALIDATE_BOOLEAN) ? now() : null,
        ]);
    }
}
