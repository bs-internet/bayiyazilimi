<?php

declare(strict_types=1);

namespace App\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;

/**
 * Excel satırı. OpenSpout `=` ile başlayan metni formül yazar; dışa aktarılan
 * veride kullanıcının girdiği metin (bayi ünvanı, açıklama) de bulunduğundan
 * bunlar düz metin hücresi olur, açan kişinin Excel'inde çalışmaz.
 */
final class SpreadsheetRow
{
    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function of(array $values, ?Style $style = null): Row
    {
        return new Row(array_values(array_map(
            fn (mixed $value): Cell => is_string($value) && str_starts_with($value, '=')
                ? new StringCell($value, $style)
                : Cell::fromValue($value, $style),
            $values,
        )));
    }
}
