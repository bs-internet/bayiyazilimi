<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\UpdateCustomerTypeRequest;
use App\Models\User;
use App\Services\Panel\Shop\DealerService;
use Illuminate\Http\RedirectResponse;

class CustomerTypeController extends Controller
{
    public function update(UpdateCustomerTypeRequest $request, User $user, DealerService $dealers): RedirectResponse
    {
        abort_unless($user->user_type->isMember(), 404);

        $account = $dealers->setCustomerType($user, $request->validated(), $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => $account->isDealer() ? __('Bayi ayarları kaydedildi.') : __('Hesap müşteri olarak kaydedildi.'),
        ]);
    }
}
