<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Shop\BulkOrderService;
use App\Services\Shop\CurrencyManager;
use App\Services\Shop\PriceCalculator;
use App\Services\Shop\QuickOrderService;
use App\Services\Shop\StockService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bayi paneli › Sipariş ver: ürün arama (ad / stok kodu + kategori / marka),
 * sepete ekleme, Excel ile toplu ekleme ve önceki siparişi tekrarlama. Bayi vitrine gitmeden
 * sipariş verebilsin diye ürün kartı verisi burada üretilir.
 */
class QuickOrderController extends Controller
{
    public const MIN_SEARCH = 2;

    public function search(Request $request, CurrencyManager $currencies): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $categoryId = (string) $request->query('category_id', '');
        $brandId = (string) $request->query('brand_id', '');

        if (mb_strlen($q) < self::MIN_SEARCH && $categoryId === '' && $brandId === '') {
            return response()->json([]);
        }

        $categoryIds = null;
        if ($categoryId !== '') {
            $category = Category::query()->find($categoryId);
            $categoryIds = $category ? $category->descendantIds() : [];
        }

        $products = Product::query()
            ->visible()
            ->when($q !== '', fn ($query) => $query->search($q)
                ->orderByRaw('case when sku like ? then 0 else 1 end', [$q.'%']))
            ->when($categoryIds !== null, fn ($query) => $query->whereIn('category_id', $categoryIds))
            ->when($brandId !== '', fn ($query) => $query->where('brand_id', $brandId))
            ->with(['images', 'brand:id,name', 'currency'])
            ->orderByDesc('sales_count')
            ->orderBy('name')
            ->limit(40)
            ->get();

        return response()->json(self::rows($products, $currencies->current()));
    }

    /**
     * Sık aldıklarınız: hesabın geçmiş siparişlerinde en çok alınan (hâlâ
     * satışta olan) ilk 12 ürün.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function frequent(Currency $currency): array
    {
        // Hesabın siparişleri alt sorgu olarak (BelongsToAccount kapsamıyla); kimlikler belleğe çekilmez.
        $top = OrderItem::query()
            ->whereIn('order_id', Order::query()->select('id'))
            ->whereNotNull('product_id')
            ->selectRaw('product_id, sum(quantity) as qty')
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->limit(12)
            ->pluck('qty', 'product_id');

        $products = Product::query()
            ->visible()
            ->whereIn('id', $top->keys())
            ->with(['images', 'brand:id,name', 'currency'])
            ->get()
            ->sortByDesc(fn (Product $p) => $top[$p->id] ?? 0)
            ->values();

        return array_map(
            fn (array $row) => [...$row, 'ordered_qty' => (int) ($top[$row['id']] ?? 0)],
            self::rows($products, $currency),
        );
    }

    /**
     * Ürün kartı satırları (arama sonucu ve sık alınanlar ortak).
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    public static function rows(Collection $products, Currency $currency): array
    {
        $prices = app(PriceCalculator::class);

        // Bayinin bölge deposu varsa o depodaki stok ayrıca gösterilir (sipariş
        // önce oradan, yetmezse diğer depolardan karşılanır; satılabilirlik toplamdan).
        $accountId = request()->user()?->account_id;
        $region = $accountId ? Account::query()->with('warehouse')->find($accountId)?->regionWarehouse() : null;
        $regionStock = $region ? app(StockService::class)->sellableMap($products->pluck('id')->all(), $region) : [];

        return $products->map(fn (Product $p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
            'brand' => $p->brand?->name,
            'image' => $p->coverUrl(),
            'unit' => $p->unit,
            'price_formatted' => $p->showsPrice() ? $prices->formatDisplay($p, $currency) : null,
            'stock' => $p->track_stock ? $p->stock : null,
            'region_stock' => $region && $p->track_stock ? ($regionStock[$p->id] ?? 0) : null,
            'min_qty' => $p->min_qty,
            'max_qty' => $p->max_qty,
            // Sepete eklenebilir mi; değilse (yalnız teklif / fiyat yok) teklif listesine eklenir.
            'purchasable' => $p->isPurchasable(),
            'quote_only' => ! $p->sale_mode->allowsCart() || ! Money::decimal($p->price)->isPositive(),
        ])->values()->all();
    }

    public function add(Request $request, QuickOrderService $quick): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'uuid'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $product = Product::query()->visible()->findOrFail($data['product_id']);
        $error = $quick->add($product, (int) ($data['quantity'] ?? 1));

        if ($error !== null) {
            return back()->with('toast', ['type' => 'warning', 'message' => __(':name: :error', ['name' => $product->name, 'error' => $error])]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __(':name sepete eklendi.', ['name' => $product->name])]);
    }

    /**
     * Excel ile toplu sipariş: dosya (.xlsx / .csv) ya da yapıştırılan metin.
     * Sonuç (eklenen sayısı, eklenemeyen satırlar ve nedenleri) bir sonraki
     * "Sipariş ver" sayfasında gösterilir.
     */
    public function import(Request $request, BulkOrderService $bulk): RedirectResponse
    {
        $request->validate([
            'file' => ['nullable', 'required_without:text', 'file', 'max:4096', 'mimes:xlsx,csv,txt'],
            'text' => ['nullable', 'required_without:file', 'string', 'max:100000'],
        ], [], ['file' => __('dosya'), 'text' => __('yapıştırılan satırlar')]);

        $parsed = $request->hasFile('file')
            ? BulkOrderService::parseFile($request->file('file'))
            : BulkOrderService::parseText((string) $request->input('text'));

        $result = $bulk->add($parsed['lines']);
        $failed = [...$parsed['errors'], ...$result['failed']];
        $added = count($result['added']);

        $request->session()->flash('bulk_order', [
            'added' => $added,
            'failed' => array_map(fn (array $row) => ['code' => $row['code'], 'quantity' => $row['quantity'] ?? null, 'reason' => $row['reason']], $failed),
        ]);

        return back()->with('toast', match (true) {
            $added > 0 && $failed === [] => ['type' => 'success', 'message' => __(':added ürün sepete eklendi.', ['added' => $added])],
            $added > 0 => ['type' => 'warning', 'message' => __(':added ürün sepete eklendi, :failed satır eklenemedi.', ['added' => $added, 'failed' => count($failed)])],
            default => ['type' => 'error', 'message' => __('Hiçbir ürün sepete eklenemedi.')],
        });
    }

    /** Toplu sipariş için boş Excel şablonu. */
    public function template(): BinaryFileResponse
    {
        return response()->download(BulkOrderService::template(), 'toplu-siparis.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
