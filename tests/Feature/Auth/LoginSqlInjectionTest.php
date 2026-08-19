<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LoginRequest::authenticate() Auth::attempt() -> EloquentUserProvider üzerinden
 * çalışır; bu her zaman parametreli sorgu (query builder bindings) kullanır, ham
 * SQL string birleştirme yoktur. Ayrıca 'email' alanı 'email' validasyon kuralına
 * tabi olduğundan tipik SQLi payload'ları (') zaten format doğrulamasında elenir.
 * Bu test, teoriyi gerçek HTTP isteğiyle doğrular.
 */
class LoginSqlInjectionTest extends TestCase
{
    use RefreshDatabase;

    public static function payloadProvider(): array
    {
        return [
            "classic tautology"      => ["' OR '1'='1", "' OR '1'='1"],
            "comment terminator"     => ["admin'--", "x"],
            "union select"           => ["' UNION SELECT * FROM users--", "x"],
            "stacked query"          => ["'; DROP TABLE users;--", "x"],
            "boolean blind"          => ["' OR 1=1--", "' OR 1=1--"],
        ];
    }

    #[DataProvider('payloadProvider')]
    public function test_sql_injection_payload_in_email_field_does_not_authenticate(string $emailPayload, string $passwordPayload): void
    {
        User::factory()->create([
            'email'    => 'victim@example.com',
            'password' => bcrypt('gercek-sifre'),
        ]);

        $response = $this->post('/login', [
            'email'    => $emailPayload,
            'password' => $passwordPayload,
        ]);

        // 'email' formatı geçersiz olduğu için çoğu payload 422/redirect+validation error alır;
        // hiçbir durumda oturum açılmamalı.
        $this->assertGuest();
        $response->assertSessionHasErrors();
    }

    public function test_sql_injection_payload_in_password_field_with_valid_looking_email_does_not_authenticate(): void
    {
        User::factory()->create([
            'email'    => 'victim@example.com',
            'password' => bcrypt('gercek-sifre'),
        ]);

        $response = $this->post('/login', [
            'email'    => 'victim@example.com',
            'password' => "' OR '1'='1",
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_table_survives_stacked_query_attempt(): void
    {
        User::factory()->create(['email' => 'victim@example.com']);

        $this->post('/login', [
            'email'    => "x@x.com'; DROP TABLE users;--",
            'password' => 'irrelevant',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'victim@example.com']);
    }
}
