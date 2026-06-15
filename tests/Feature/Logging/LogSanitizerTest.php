<?php

namespace Tests\Feature\Logging;

use App\Logging\LogSanitizer;
use Tests\TestCase;

/**
 * KVKK maskeleme testi — veritabanı gerektirmez.
 * sqlite :memory: yok, RefreshDatabase YOK.
 */
class LogSanitizerTest extends TestCase
{
    private LogSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new LogSanitizer();
    }

    // -------------------------------------------------------------------------
    // DENY: parola / token / secret tamamen kaldırılır
    // -------------------------------------------------------------------------

    public function test_password_key_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['password' => 'secret123', 'name' => 'Ahmet']);

        $this->assertArrayNotHasKey('password', $result);
        $this->assertSame('Ahmet', $result['name']);
    }

    public function test_password_confirmation_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['password_confirmation' => 'secret123']);
        $this->assertArrayNotHasKey('password_confirmation', $result);
    }

    public function test_current_password_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['current_password' => 'old']);
        $this->assertArrayNotHasKey('current_password', $result);
    }

    public function test_new_password_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['new_password' => 'new123']);
        $this->assertArrayNotHasKey('new_password', $result);
    }

    public function test_password_hash_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['password_hash' => '$2y$...']);
        $this->assertArrayNotHasKey('password_hash', $result);
    }

    public function test_underscore_token_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['_token' => 'csrf-value']);
        $this->assertArrayNotHasKey('_token', $result);
    }

    public function test_token_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['token' => 'abcdef']);
        $this->assertArrayNotHasKey('token', $result);
    }

    public function test_access_token_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['access_token' => 'bearer-xyz']);
        $this->assertArrayNotHasKey('access_token', $result);
    }

    public function test_refresh_token_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['refresh_token' => 'rt-xyz']);
        $this->assertArrayNotHasKey('refresh_token', $result);
    }

    public function test_api_key_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['api_key' => 'key-abc']);
        $this->assertArrayNotHasKey('api_key', $result);
    }

    public function test_apikey_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['apikey' => 'key-abc']);
        $this->assertArrayNotHasKey('apikey', $result);
    }

    public function test_secret_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['secret' => 'top-secret']);
        $this->assertArrayNotHasKey('secret', $result);
    }

    public function test_client_secret_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['client_secret' => 'cs-abc']);
        $this->assertArrayNotHasKey('client_secret', $result);
    }

    public function test_cvv_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['cvv' => '123']);
        $this->assertArrayNotHasKey('cvv', $result);
    }

    public function test_cvc_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['cvc' => '456']);
        $this->assertArrayNotHasKey('cvc', $result);
    }

    public function test_card_number_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['card_number' => '4111111111111111']);
        $this->assertArrayNotHasKey('card_number', $result);
    }

    public function test_cardnumber_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['cardnumber' => '4111111111111111']);
        $this->assertArrayNotHasKey('cardnumber', $result);
    }

    public function test_pin_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['pin' => '1234']);
        $this->assertArrayNotHasKey('pin', $result);
    }

    public function test_keys_containing_password_are_removed(): void
    {
        $result = $this->sanitizer->sanitize([
            'user_password'  => 'x',
            'PASSWORD'       => 'x',
            'OldPassword'    => 'x',
        ]);
        $this->assertArrayNotHasKey('user_password', $result);
        $this->assertArrayNotHasKey('PASSWORD', $result);
        $this->assertArrayNotHasKey('OldPassword', $result);
    }

    public function test_keys_containing_token_are_removed(): void
    {
        $result = $this->sanitizer->sanitize([
            'auth_token'   => 'x',
            'TOKEN_VALUE'  => 'x',
            'myTokenField' => 'x',
        ]);
        $this->assertArrayNotHasKey('auth_token', $result);
        $this->assertArrayNotHasKey('TOKEN_VALUE', $result);
        $this->assertArrayNotHasKey('myTokenField', $result);
    }

    public function test_keys_containing_secret_are_removed(): void
    {
        $result = $this->sanitizer->sanitize([
            'my_secret'    => 'x',
            'SECRET_KEY'   => 'x',
            'appSecret'    => 'x',
        ]);
        $this->assertArrayNotHasKey('my_secret', $result);
        $this->assertArrayNotHasKey('SECRET_KEY', $result);
        $this->assertArrayNotHasKey('appSecret', $result);
    }

    // Explicit assertion: NO password value ever survives sanitize()
    public function test_no_password_value_survives_in_any_form(): void
    {
        $data = [
            'password'              => 'hunter2',
            'password_confirmation' => 'hunter2',
            'current_password'      => 'hunter2',
            'new_password'          => 'hunter2',
            'password_hash'         => '$2y$hunter2',
            '_token'                => 'csrf',
            'token'                 => 'tok',
            'access_token'          => 'atk',
            'refresh_token'         => 'rtk',
            'api_key'               => 'apk',
            'apikey'                => 'apk2',
            'secret'                => 'shh',
            'client_secret'         => 'cs',
            'cvv'                   => '123',
            'cvc'                   => '456',
            'card_number'           => '4111',
            'cardnumber'            => '4222',
            'pin'                   => '9999',
            'SomeRandomPasswordKey' => 'p',
            'myTokenField'          => 't',
            'appSecret'             => 's',
            'name'                  => 'Ahmet',
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertSame(['name' => 'Ahmet'], $result);

        // Verify no known-secret value appears anywhere in the encoded output
        $encoded = json_encode($result);
        $this->assertStringNotContainsString('hunter2', $encoded);
        $this->assertStringNotContainsString('csrf', $encoded);
    }

    // -------------------------------------------------------------------------
    // Nested arrays are sanitized recursively
    // -------------------------------------------------------------------------

    public function test_nested_array_is_sanitized(): void
    {
        $data = [
            'user' => [
                'password' => 'secret',
                'email'    => 'ahmet@gmail.com',
                'name'     => 'Ahmet',
            ],
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertArrayNotHasKey('password', $result['user']);
        $this->assertArrayHasKey('email', $result['user']);
        $this->assertNotSame('ahmet@gmail.com', $result['user']['email']);
        $this->assertSame('Ahmet', $result['user']['name']);
    }

    public function test_deeply_nested_array_is_sanitized(): void
    {
        $data = [
            'request' => [
                'headers' => [],
                'body'    => [
                    'credentials' => [
                        'password' => 'topsecret',
                        'api_key'  => 'key-123',
                    ],
                    'user_email' => 'test@example.com',
                ],
            ],
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertArrayNotHasKey('password', $result['request']['body']['credentials']);
        $this->assertArrayNotHasKey('api_key', $result['request']['body']['credentials']);
        $this->assertArrayHasKey('user_email', $result['request']['body']);
        $this->assertNotSame('test@example.com', $result['request']['body']['user_email']);
    }

    // -------------------------------------------------------------------------
    // Normal key is left unchanged
    // -------------------------------------------------------------------------

    public function test_normal_key_is_unchanged(): void
    {
        $result = $this->sanitizer->sanitize(['name' => 'Ahmet', 'age' => 30]);

        $this->assertSame('Ahmet', $result['name']);
        $this->assertSame(30, $result['age']);
    }

    // -------------------------------------------------------------------------
    // MASK: email
    // -------------------------------------------------------------------------

    public function test_email_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['email' => 'ahmet@gmail.com']);

        $this->assertArrayHasKey('email', $result);
        $masked = $result['email'];
        $this->assertStringNotContainsString('ahmet@gmail.com', $masked);
        $this->assertStringContainsString('@', $masked);
        $this->assertStringContainsString('.com', $masked);
    }

    public function test_email_key_containing_email_word_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['user_email' => 'john@example.org']);

        $this->assertArrayHasKey('user_email', $result);
        $this->assertStringNotContainsString('john@example.org', $result['user_email']);
    }

    public function test_maskEmail_first_char_of_local_and_domain_visible(): void
    {
        $sanitizer = new LogSanitizer();
        $masked = $sanitizer->maskEmail('ahmet@gmail.com');

        // Local part: only first char 'a' visible
        $this->assertStringStartsWith('a', $masked);
        // Contains @ separator
        $this->assertStringContainsString('@', $masked);
        // TLD preserved
        $this->assertStringEndsWith('.com', $masked);
        // Not the full email
        $this->assertStringNotContainsString('hmet', $masked);
        $this->assertStringNotContainsString('mail', $masked);
    }

    public function test_maskEmail_handles_no_at_sign(): void
    {
        $sanitizer = new LogSanitizer();
        // Should not throw; just mask the middle
        $result = $sanitizer->maskEmail('notanemail');
        $this->assertIsString($result);
        $this->assertNotSame('notanemail', $result);
    }

    public function test_maskEmail_empty_string_no_throw(): void
    {
        $sanitizer = new LogSanitizer();
        $result = $sanitizer->maskEmail('');
        $this->assertIsString($result);
    }

    // -------------------------------------------------------------------------
    // MASK: phone
    // -------------------------------------------------------------------------

    public function test_phone_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['phone' => '05321234567']);

        $this->assertArrayHasKey('phone', $result);
        $masked = $result['phone'];
        // Middle digits are hidden
        $this->assertStringContainsString('*', $masked);
        // Last 2 visible
        $this->assertStringEndsWith('67', $masked);
        // Full original not preserved
        $this->assertStringNotContainsString('05321234567', $masked);
    }

    public function test_telefon_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['telefon' => '05321234567']);
        $this->assertArrayHasKey('telefon', $result);
        $this->assertStringContainsString('*', $result['telefon']);
    }

    public function test_gsm_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['gsm' => '05321234567']);
        $this->assertArrayHasKey('gsm', $result);
        $this->assertStringContainsString('*', $result['gsm']);
    }

    public function test_maskPhone_keeps_first_4_and_last_2(): void
    {
        $sanitizer = new LogSanitizer();
        $masked = $sanitizer->maskPhone('05321234567');

        $this->assertStringStartsWith('0532', $masked);
        $this->assertStringEndsWith('67', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    public function test_maskPhone_short_string_no_throw(): void
    {
        $sanitizer = new LogSanitizer();
        $result = $sanitizer->maskPhone('123');
        $this->assertIsString($result);
    }

    public function test_maskPhone_empty_string_no_throw(): void
    {
        $sanitizer = new LogSanitizer();
        $result = $sanitizer->maskPhone('');
        $this->assertIsString($result);
    }

    // -------------------------------------------------------------------------
    // MASK: national ID (TC)
    // -------------------------------------------------------------------------

    public function test_tckn_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['tckn' => '12345678901']);
        $this->assertArrayHasKey('tckn', $result);
        $this->assertStringContainsString('*', $result['tckn']);
        $this->assertStringNotContainsString('12345678901', $result['tckn']);
    }

    public function test_national_id_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['national_id' => '12345678901']);
        $this->assertArrayHasKey('national_id', $result);
        $this->assertStringContainsString('*', $result['national_id']);
    }

    public function test_tc_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['tc' => '12345678901']);
        $this->assertArrayHasKey('tc', $result);
        $this->assertStringContainsString('*', $result['tc']);
    }

    public function test_maskNationalId_shows_first_3_and_last_2(): void
    {
        $sanitizer = new LogSanitizer();
        $masked = $sanitizer->maskNationalId('12345678901');

        $this->assertStringStartsWith('123', $masked);
        $this->assertStringEndsWith('01', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    public function test_maskNationalId_empty_no_throw(): void
    {
        $sanitizer = new LogSanitizer();
        $result = $sanitizer->maskNationalId('');
        $this->assertIsString($result);
    }

    // -------------------------------------------------------------------------
    // MASK: IBAN
    // -------------------------------------------------------------------------

    public function test_iban_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['iban' => 'TR330006100519786457841326']);
        $this->assertArrayHasKey('iban', $result);
        $this->assertStringContainsString('*', $result['iban']);
        $this->assertStringNotContainsString('TR330006100519786457841326', $result['iban']);
    }

    public function test_maskIban_shows_first_6_and_last_4(): void
    {
        $sanitizer = new LogSanitizer();
        $masked = $sanitizer->maskIban('TR330006100519786457841326');

        $this->assertStringStartsWith('TR3300', $masked);
        $this->assertStringEndsWith('1326', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    public function test_maskIban_empty_no_throw(): void
    {
        $sanitizer = new LogSanitizer();
        $result = $sanitizer->maskIban('');
        $this->assertIsString($result);
    }

    // -------------------------------------------------------------------------
    // MASK: tax number
    // -------------------------------------------------------------------------

    public function test_tax_no_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['tax_no' => '1234567890']);
        $this->assertArrayHasKey('tax_no', $result);
        $this->assertStringContainsString('*', $result['tax_no']);
    }

    public function test_vergi_no_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['vergi_no' => '1234567890']);
        $this->assertArrayHasKey('vergi_no', $result);
        $this->assertStringContainsString('*', $result['vergi_no']);
    }

    public function test_maskTaxNo_shows_last_3(): void
    {
        $sanitizer = new LogSanitizer();
        $masked = $sanitizer->maskTaxNo('1234567890');

        $this->assertStringEndsWith('890', $masked);
        $this->assertStringContainsString('*', $masked);
        $this->assertStringNotContainsString('1234567', $masked);
    }

    public function test_maskTaxNo_empty_no_throw(): void
    {
        $sanitizer = new LogSanitizer();
        $result = $sanitizer->maskTaxNo('');
        $this->assertIsString($result);
    }

    // -------------------------------------------------------------------------
    // MASK: IP address
    // -------------------------------------------------------------------------

    public function test_ip_address_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['ip_address' => '88.230.45.12']);
        $this->assertArrayHasKey('ip_address', $result);
        // IPv4 maskeleme 'x' kullanır (örn. 88.230.x.x)
        $this->assertStringNotContainsString('88.230.45.12', $result['ip_address']);
        $this->assertStringContainsString('88.230.', $result['ip_address']);
    }

    public function test_ip_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['ip' => '88.230.45.12']);
        $this->assertArrayHasKey('ip', $result);
        // IPv4 maskeleme 'x' kullanır (örn. 88.230.x.x)
        $this->assertStringNotContainsString('88.230.45.12', $result['ip']);
        $this->assertStringContainsString('88.230.', $result['ip']);
    }

    public function test_maskIp_ipv4_keeps_first_two_octets(): void
    {
        $sanitizer = new LogSanitizer();
        $masked = $sanitizer->maskIp('88.230.45.12');

        $this->assertStringStartsWith('88.230.', $masked);
        $this->assertStringContainsString('x', $masked);
    }

    public function test_maskIp_non_ipv4_no_throw(): void
    {
        $sanitizer = new LogSanitizer();
        $result = $sanitizer->maskIp('::1');
        $this->assertIsString($result);
    }

    public function test_maskIp_empty_no_throw(): void
    {
        $sanitizer = new LogSanitizer();
        $result = $sanitizer->maskIp('');
        $this->assertIsString($result);
    }

    // -------------------------------------------------------------------------
    // Empty / non-string values in MASK keys are left as-is (no masking)
    // -------------------------------------------------------------------------

    public function test_mask_key_with_empty_string_is_not_masked(): void
    {
        // Empty string value on a mask key → keep as-is (spec: "only when value is a non-empty string")
        $result = $this->sanitizer->sanitize(['email' => '']);
        $this->assertArrayHasKey('email', $result);
        $this->assertSame('', $result['email']);
    }

    public function test_mask_key_with_null_value_kept(): void
    {
        $result = $this->sanitizer->sanitize(['email' => null]);
        $this->assertArrayHasKey('email', $result);
        $this->assertNull($result['email']);
    }

    public function test_mask_key_with_integer_value_kept(): void
    {
        $result = $this->sanitizer->sanitize(['phone' => 5321234567]);
        $this->assertArrayHasKey('phone', $result);
        $this->assertSame(5321234567, $result['phone']);
    }

    // -------------------------------------------------------------------------
    // Case-insensitive key matching
    // -------------------------------------------------------------------------

    public function test_uppercase_deny_key_is_removed(): void
    {
        $result = $this->sanitizer->sanitize(['PASSWORD' => 'secret', 'Api_Key' => 'key']);
        $this->assertArrayNotHasKey('PASSWORD', $result);
        $this->assertArrayNotHasKey('Api_Key', $result);
    }

    public function test_uppercase_mask_key_is_masked(): void
    {
        $result = $this->sanitizer->sanitize(['EMAIL' => 'test@example.com']);
        $this->assertArrayHasKey('EMAIL', $result);
        $this->assertStringContainsString('*', $result['EMAIL']);
    }

    // -------------------------------------------------------------------------
    // Container resolution
    // -------------------------------------------------------------------------

    public function test_resolvable_via_app_container(): void
    {
        $sanitizer = app(LogSanitizer::class);
        $this->assertInstanceOf(LogSanitizer::class, $sanitizer);

        $result = $sanitizer->sanitize(['password' => 'x', 'name' => 'Y']);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertSame('Y', $result['name']);
    }
}
