<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasSortOrder;
use App\Concerns\HasStatus;
use App\Enums\Status;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Sözleşme / yasal metin (Tanımlamalar → Sözleşmeler). Nerede onaylatılacağı
 * işaretlerle belirlenir: ödeme formu, üyelik formu; işaretsiz olanlar
 * "genel"dir. Aktif sözleşmelerin hepsi bayi panelinde (Sözleşmeler) okunur.
 */
#[Fillable([
    'title',
    'slug',
    'content',
    'show_on_checkout',
    'show_on_registration',
    'status',
    'sort_order',
])]
class Contract extends Model
{
    use HasSortOrder, HasStatus, HasUuids;

    public const CHECKOUT = 'checkout';

    public const REGISTRATION = 'registration';

    /**
     * Formda onaylatılacak aktif sözleşmeler (içerik temizlenmiş).
     *
     * @param  self::CHECKOUT|self::REGISTRATION  $form
     * @return array<int, array{title: string, slug: string, content: string}>
     */
    public static function forForm(string $form): array
    {
        return static::query()->active()
            ->where($form === self::CHECKOUT ? 'show_on_checkout' : 'show_on_registration', true)
            ->ordered()->orderBy('title')->get()
            ->map(fn (self $c) => [
                'title' => $c->title,
                'slug' => $c->slug,
                'content' => HtmlSanitizer::clean($c->content),
            ])->values()->all();
    }

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'show_on_checkout' => 'boolean',
            'show_on_registration' => 'boolean',
        ];
    }
}
