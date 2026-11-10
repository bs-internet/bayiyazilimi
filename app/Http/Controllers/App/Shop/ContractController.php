<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bayi paneli › Sözleşmeler: aktif sözleşmeler (Tanımlamalar → Sözleşmeler).
 * Ayrı bir bilgi sayfası kaydı yoktur; ödeme / üyelik formunda onaylatılan
 * ve genel sözleşmeler burada okunur.
 */
class ContractController extends Controller
{
    public function index(): RedirectResponse|Response
    {
        $first = $this->contracts()->first();

        if ($first === null) {
            return Inertia::render('app/shop/contracts/show', ['contract' => null, 'items' => []]);
        }

        return to_route('app.shop.contracts.show', $first->slug);
    }

    public function show(string $slug): Response
    {
        $contracts = $this->contracts()->get();
        $contract = $contracts->firstWhere('slug', $slug) ?? abort(404);

        return Inertia::render('app/shop/contracts/show', [
            'contract' => [
                'title' => $contract->title,
                'content' => HtmlSanitizer::clean($contract->content),
                'updated_at' => $contract->updated_at?->toIso8601String(),
            ],
            'items' => $this->items($contracts),
        ]);
    }

    /**
     * İç menü; göreli adres (etkin öğe vurgusu için).
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array{title: string, href: string}>
     */
    private function items(Collection $contracts): array
    {
        return $contracts->map(fn (Contract $c) => [
            'title' => $c->title,
            'href' => route('app.shop.contracts.show', $c->slug, false),
        ])->values()->all();
    }

    /**
     * @return Builder<Contract>
     */
    private function contracts(): Builder
    {
        return Contract::query()->active()->ordered()->orderBy('title');
    }
}
