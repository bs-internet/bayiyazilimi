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
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            // Üyelik formunda onaylatılan sözleşme varsa onay zorunlu.
            'agreement' => Contract::query()->active()->where('show_on_registration', true)->exists() ? ['accepted'] : ['nullable'],
        ], attributes: ['agreement' => __('sözleşme onayı')])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'status' => UserStatus::Draft,
            'user_type' => UserType::Member,
        ]);
    }
}
