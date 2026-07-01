<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\ActivityLog;
use App\Models\ErrorLog;
use Modules\Atelier\Models\MaterialMovement;
use Modules\Finance\Models\BankTransaction;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Veritabanı bakım / retention zamanlamaları
|--------------------------------------------------------------------------
| Bu komutlar tabloların soft-delete kalıntısı ve framework çöpüyle (eski
| job/batch/şifre-sıfırlama kayıtları) şişmesini önler. `php artisan schedule:list`
| ile görülebilir, `php artisan schedule:test` ile tek tek denenebilir.
| Çalışması için sunucuda `php artisan schedule:run` cron'unun dakikada bir
| tetiklenmesi gerekir (Laravel zamanlayıcı kurulumu).
*/

// Eşikten (30 gün) eski soft-delete edilmiş model kayıtlarını kalıcı sil.
// Modeller modüllerde olduğundan FQCN'ler açıkça verilir (otomatik keşif app/Models'a bakar).
Schedule::command('model:prune', [
    '--model' => [
        ActivityLog::class,
        ErrorLog::class,
        ProductImage::class,
        Stock::class,
        StockMovement::class,
        PriceList::class,
        MaterialMovement::class,
        BankTransaction::class,
    ],
])->daily()->onOneServer()->runInBackground();

// Eski başarısız iş kayıtları (7 günden eski).
Schedule::command('queue:prune-failed --hours=168')->weekly();

// Tamamlanmış kuyruk batch kayıtları (48 saatten eski).
Schedule::command('queue:prune-batches --hours=48')->daily();

// Süresi geçmiş şifre sıfırlama tokenları.
Schedule::command('auth:clear-resets')->daily();

// Süresi geçmiş etiketli cache kayıtları (yalnızca destekleyen sürücülerde etkili).
Schedule::command('cache:prune-stale-tags')->hourly();

// Geçerlilik tarihi dolmuş proforma faturaları expired yapar (Finance modülü).
Schedule::command('finance:expire-proformas')->daily();
