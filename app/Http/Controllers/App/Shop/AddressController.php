<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Enums\Shop\AddressType;
use App\Http\Controllers\App\Shop\Concerns\ResolvesMemberArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Shop\SaveAddressRequest;
use App\Models\CustomerAddress;
use App\Services\Shop\AddressService;
use App\Support\RegionOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Üye alanı: Hesabım → Adreslerim.
 */
class AddressController extends Controller
{
    use ResolvesMemberArea;

    public function index(): Response
    {
        // Teslimat ve fatura adresleri arayüzde ayrı bölümlerde listelenir; her bölümde varsayılan önce.
        $addresses = CustomerAddress::query()
            ->with(['city:id,name', 'district:id,name'])
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('is_default_billing')
            ->latest()
            ->get();

        return Inertia::render($this->page('addresses/index'), [
            'addresses' => $addresses->map(fn (CustomerAddress $a) => self::present($a))->values(),
            'cities' => RegionOptions::cities(),
            'types' => AddressType::options(),
        ]);
    }

    public function store(SaveAddressRequest $request, AddressService $service): RedirectResponse
    {
        $also = $request->boolean('also_as_other');
        $service->save($request->user()->account, $request->addressData(), alsoAsOther: $also);

        return back()->with('toast', ['type' => 'success', 'message' => $also ? __('Teslimat ve fatura adresi ayrı ayrı kaydedildi.') : __('Adres kaydedildi.')]);
    }

    public function update(SaveAddressRequest $request, CustomerAddress $address, AddressService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $address);
        $service->save($request->user()->account, $request->addressData(), $address, $request->boolean('also_as_other'));

        return back()->with('toast', ['type' => 'success', 'message' => __('Adres güncellendi.')]);
    }

    public function makeDefault(Request $request, CustomerAddress $address, AddressService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $address);
        // Varsayılan bayrak adresin kendi türüne göre belirlenir (teslimat ya da fatura).
        $service->setDefault($address);

        return back()->with('toast', ['type' => 'success', 'message' => $address->type === AddressType::Billing ? __('Varsayılan fatura adresi güncellendi.') : __('Varsayılan teslimat adresi güncellendi.')]);
    }

    public function destroy(Request $request, CustomerAddress $address, AddressService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $address);
        $service->delete($address);

        return back()->with('toast', ['type' => 'success', 'message' => __('Adres silindi.')]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(CustomerAddress $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'type' => $a->type->value,
            'type_label' => $a->type->label(),
            'name' => $a->name,
            'company' => $a->company,
            'tax_office' => $a->tax_office,
            'tax_number' => $a->tax_number,
            'identity_number' => $a->identity_number,
            'phone' => $a->phone,
            'city_id' => $a->city_id,
            'city' => $a->city?->name,
            'district_id' => $a->district_id,
            'district' => $a->district?->name,
            'address' => $a->address,
            'postal_code' => $a->postal_code,
            'is_default_shipping' => $a->is_default_shipping,
            'is_default_billing' => $a->is_default_billing,
        ];
    }

    /**
     * Rota bağlama hesap kapsamından önce çalışabilir; sahipliği açıkça doğrula.
     */
    private function authorizeOwner(Request $request, CustomerAddress $address): void
    {
        abort_unless($address->account_id === $request->user()?->account_id, 404);
    }
}
