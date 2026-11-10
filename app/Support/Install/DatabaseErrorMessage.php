<?php

declare(strict_types=1);

namespace App\Support\Install;

use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

/**
 * Kurulumda karşılaşılan veritabanı hatalarını kullanıcının anlayacağı
 * Türkçe iletiye çevirir. Tanınmayan hatalarda teknik ayrıntı sona eklenir
 * (destek için); veritabanı dışı hatalar olduğu gibi döner.
 */
final class DatabaseErrorMessage
{
    public static function for(Throwable $e, ?string $database = null): string
    {
        if (! $e instanceof PDOException && ! $e instanceof QueryException) {
            return $e->getMessage();
        }

        $message = $e->getMessage();
        $database = (string) $database;

        return match (self::code($message)) {
            '1045' => __('Kullanıcı adı ya da şifre hatalı. Veritabanı kullanıcısının adını ve şifresini kontrol edin.'),
            '1044' => __('Bu kullanıcının ":database" veritabanına erişim izni yok.', ['database' => $database]),
            '1049' => __('":database" adında bir veritabanı bulunamadı. Önce boş bir veritabanı oluşturun ya da adını kontrol edin.', ['database' => $database]),
            '2002', '2003', '2005', '2006' => __('Veritabanı sunucusuna ulaşılamadı. Sunucu adresini ve portu kontrol edin; MySQL / MariaDB çalışıyor olmalı.'),
            '1050' => __('Veritabanı boş değil: içinde başka tablolar var. Boş bir veritabanı seçin ya da içindeki tabloları silip yeniden deneyin.'),
            '1142', '1227' => __('Veritabanı kullanıcısının tablo oluşturma yetkisi yok. Kullanıcıya bu veritabanında tüm yetkileri verin.'),
            default => __('Veritabanı işlemi başarısız oldu. Bilgileri kontrol edip yeniden deneyin. (Ayrıntı: :detail)', ['detail' => self::detail($message)]),
        };
    }

    /** MySQL hata numarası: "SQLSTATE[HY000] [1045] …" ya da "SQLSTATE[42S01]: …: 1050 …". */
    private static function code(string $message): ?string
    {
        if (preg_match('/SQLSTATE\[\w+\](?:: [^:]+:)? \[?(\d{4})\]?/', $message, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /** Teknik ayrıntı: SQL sorgusu ve bağlantı bilgisi olmadan, kısa. */
    private static function detail(string $message): string
    {
        $message = (string) preg_replace('/\s*\((Connection|SQL):.*$/s', '', $message);

        return mb_strimwidth($message, 0, 200, '…');
    }
}
