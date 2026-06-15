<?php

namespace Tests\Feature\Logging;

use App\Logging\ActivityLogger;
use App\Logging\ErrorLogger;
use App\Models\ActivityLog;
use App\Models\ErrorLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use RuntimeException;
use Tests\TestCase;

/**
 * ActivityLogger ve ErrorLogger entegrasyon testleri.
 * sqlite :memory: kullanır, RefreshDatabase ile her test temiz başlar.
 */
class LoggerServicesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // ActivityLogger testleri
    // -------------------------------------------------------------------------

    public function test_activity_logger_writes_row_with_correct_fields(): void
    {
        $logger = app(ActivityLogger::class);

        $log = $logger->log('user.login', 'Kullanıcı giriş yaptı', [
            'module' => 'auth',
            'level'  => 'info',
        ]);

        $this->assertInstanceOf(ActivityLog::class, $log);
        $this->assertDatabaseHas('activity_logs', [
            'action'      => 'user.login',
            'description' => 'Kullanıcı giriş yaptı',
            'module'      => 'auth',
            'level'       => 'info',
        ]);
    }

    public function test_activity_logger_sanitizes_properties(): void
    {
        $logger = app(ActivityLogger::class);

        $log = $logger->log('test.action', 'test', [
            'module'     => 'test',
            'properties' => [
                'password' => 'supersecret',
                'email'    => 'ahmet@gmail.com',
                'name'     => 'Ahmet',
            ],
        ]);

        // password → DENY (tamamen kaldırılır)
        $this->assertArrayNotHasKey('password', $log->properties);

        // email → maskelenir (ahmet@gmail.com → a***@g***.com)
        $this->assertArrayHasKey('email', $log->properties);
        $this->assertStringNotContainsString('ahmet@gmail.com', $log->properties['email']);
        $this->assertStringContainsString('@', $log->properties['email']);

        // name → değişmez
        $this->assertSame('Ahmet', $log->properties['name']);
    }

    public function test_activity_logger_infers_module_from_subject_model(): void
    {
        $logger = app(ActivityLogger::class);

        // Product modeli Modules\Product\... namespace'inde → module: 'product'
        $product = new Product();

        $log = $logger->log('product.viewed', 'Ürün görüntülendi', [
            'subject' => $product,
        ]);

        $this->assertSame('product', $log->module);
        $this->assertSame(Product::class, $log->subject_type);
    }

    // -------------------------------------------------------------------------
    // ErrorLogger testleri
    // -------------------------------------------------------------------------

    public function test_error_logger_writes_row_with_correct_fields(): void
    {
        $logger = app(ErrorLogger::class);

        try {
            throw new RuntimeException('boom');
        } catch (RuntimeException $e) {
            $logger->capture($e, ['extra' => 'ctx']);
        }

        $this->assertDatabaseHas('error_logs', [
            'message'         => 'boom',
            'exception_class' => RuntimeException::class,
            'level'           => 'error',
        ]);

        $row = ErrorLog::first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->fingerprint);
        $this->assertSame('boom', $row->message);
        $this->assertSame(RuntimeException::class, $row->exception_class);
        $this->assertNotNull($row->file);
        $this->assertIsInt($row->line);
    }

    public function test_error_logger_swallows_internal_failure(): void
    {
        // ErrorLogger hiçbir zaman exception fırlatmamalı — bu test sadece çalışması gerekir
        $logger = app(ErrorLogger::class);

        // Normal bir exception ile çağır — throw olmamalı
        $this->expectNotToPerformAssertions();

        $logger->capture(new RuntimeException('safe swallow test'));
    }
}
