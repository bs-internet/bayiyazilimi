<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Enums\Shop\AddressType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\ResolvesBasketArea;
use App\Http\Requests\Shop\QuoteRequest;
use App\Http\Resources\Shop\BasketPresenter;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\Shop\Basket;
use App\Services\Shop\CurrencyManager;
use App\Services\Shop\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teklif listesinin ortak kuralları: ürünler sepete benzer biçimde toplanır,
 * form ile teklif talebi gönderilir. Alan (sayfa, bildirim, talep sonrası
 * yönlendirme) alt sınıfta: bayi paneli App\Http\Controllers\App\Shop\QuoteListController,
 * vitrin modülünde kendi QuoteListController'ı.
 */
abstract class QuoteListController extends Controller
{
    use ResolvesBasketArea;

    /**
     * Teklif listesi sayfasının Inertia adı.
     */
    abstract protected function page(): string;

    /**
     * Ürün listeye eklendikten sonraki yanıt.
     */
    abstract protected function added(Product $product): RedirectResponse;

    /**
     * Talep oluştuktan sonraki yanıt.
     */
    abstract protected function submitted(Quote $quote, Request $request): RedirectResponse;

    public function index(Request $request, CurrencyManager $currencies): Response
    {
        $user = $request->user();
        $currency = $currencies->current();

        return Inertia::render($this->page(), [
            'lines' => BasketPresenter::lines(Basket::quote()->lines($currency), $currency),
            // Üye: iletişim bilgileri hesaptan alınır, formda yalnız not kalır.
            'member' => $user !== null,
            'prefill' => $user !== null ? self::memberContact($user) : [
                'customer_name' => null, 'email' => null, 'company' => null, 'phone' => null, 'city' => null,
            ],
        ]);
    }

    /**
     * Üyenin teklif iletişim bilgileri: ad, e-posta, firma ve varsayılan fatura
     * (yoksa herhangi bir) adresindeki telefon / şehir.
     *
     * @return array{customer_name: string, email: string, company: ?string, phone: ?string, city: ?string}
     */
    public static function memberContact(User $user): array
    {
        $user->loadMissing('account');

        $address = $user->account_id !== null
            ? CustomerAddress::query()
                ->where('account_id', $user->account_id)
                ->with('city:id,name')
                ->orderByRaw('case when type = ? then 0 else 1 end', [AddressType::Billing->value])
                ->orderByDesc('is_default_billing')
                ->orderByDesc('is_default_shipping')
                ->first()
            : null;

        return [
            'customer_name' => $user->name,
            'email' => $user->email,
            'company' => $user->account?->title,
            'phone' => $address?->phone,
            'city' => $address?->city?->name,
        ];
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $product = Product::query()->visible()->findOrFail($data['product_id']);
        Basket::quote()->add($product, max((int) ($data['quantity'] ?? 1), 1));

        return $this->added($product);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['array'],
            'quantities.*' => ['integer', 'min:0', 'max:100000'],
        ]);

        $list = Basket::quote();
        $products = Product::query()->visible()->whereIn('id', array_keys($data['quantities'] ?? []))->get()->keyBy('id');

        foreach ($data['quantities'] ?? [] as $id => $qty) {
            $product = $products->get($id);
            $product ? $list->set($product, (int) $qty) : $list->remove((string) $id);
        }

        $redirect = to_route($this->basketRoute('quote-list.index'));

        // Adet kutusundan anlık güncelleme (auto=1) bildirim göstermez.
        return $request->boolean('auto') ? $redirect : $redirect->with('success', __('Teklif listeniz güncellendi.'));
    }

    public function remove(string $product): RedirectResponse
    {
        Basket::quote()->remove($product);

        return to_route($this->basketRoute('quote-list.index'))->with('success', __('Ürün teklif listesinden çıkarıldı.'));
    }

    public function submit(QuoteRequest $request, QuoteService $quotes, CurrencyManager $currencies): RedirectResponse
    {
        $quote = $quotes->request(
            Basket::quote(),
            $request->validated(),
            $request->user(),
            $currencies->current(),
            $request->ip(),
        );

        return $this->submitted($quote, $request);
    }
}
