<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase / DatabaseMigrations trait'leri burada (setUpTraits) migrate:fresh
     * çalıştırır. Bağlantı bellekteki sqlite değilse hiçbir tabloya dokunmadan dur:
     * phpunit.xml'deki ayar bir şekilde ezilirse (ör. testler canlı .env'yi ortama
     * yazmış bir artisan sürecinden başlatılırsa) canlı veritabanı silinmesin.
     * 2026-09-26'da tam olarak bu yolla canlı PostgreSQL veritabanı silindi.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Testler sadece bellekteki sqlite üzerinde çalışır; şu anki bağlantı: {$connection} ({$database}). "
                .'Canlı veritabanını korumak için test durduruldu.'
            );
        }

        return parent::setUpTraits();
    }
}
