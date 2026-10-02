<?php

namespace Tests\Feature\Superadmin;

use App\Contracts\TranslationSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\Superadmin\Services\Translations\LangFileStore;
use Modules\Superadmin\Services\Translations\TranslationService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Süper Admin › Dil Dosyaları. Dosya testleri geçici klasörde çalışır (repo'daki lang
 * dosyalarına dokunulmaz); controller testleri sahte bir TranslationSource kullanır,
 * Bagisto'ya giden HTTP Http::fake ile kesilir.
 */
class TranslationEditorTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'translations.manage', 'guard_name' => 'web']);
        Http::fake(['*' => Http::response(['data' => ['groups' => []]])]);

        $this->dir = storage_path('framework/testing/lang-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->dir.'/tr');
        File::ensureDirectoryExists($this->dir.'/en');
        File::put($this->dir.'/tr/shop.php', "<?php\n\n// yorum\nreturn ['title' => 'Mağaza', 'cart' => ['empty' => 'Sepet boş', 'items' => ':count ürün'], 'odd.key' => 'x', 'n' => 5];\n");
        File::put($this->dir.'/en/shop.php', "<?php\n\nreturn ['title' => 'Shop'];\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_store_reads_flattened_rows_across_locales(): void
    {
        $rows = collect((new LangFileStore($this->dir.'/_backups'))->read($this->dir, 'shop', ['tr', 'en']))->keyBy('key');

        $this->assertSame(['title', 'cart.empty', 'cart.items', 'odd.key', 'n'], array_values(array_intersect(['title', 'cart.empty', 'cart.items', 'odd.key', 'n'], $rows->keys()->all())));
        $this->assertSame(['tr' => 'Sepet boş', 'en' => null], $rows['cart.empty']['values']);
        $this->assertTrue($rows['cart.empty']['editable']);
        // noktalı anahtar parçası ve metin olmayan değer nokta yoluyla güvenle yazılamaz
        $this->assertFalse($rows['odd.key']['editable']);
        $this->assertFalse($rows['n']['editable']);
    }

    public function test_store_writes_nested_keys_keeps_data_and_backs_up(): void
    {
        $store = new LangFileStore($this->dir.'/_backups');

        $written = $store->write($this->dir, 'shop', [
            'tr' => ['cart.empty' => 'Sepetiniz boş', 'checkout.title' => "Ödeme 'adımı'"],
            'en' => ['cart.empty' => 'Your cart is empty'],
        ]);

        $this->assertSame(3, $written);

        $tr = include $this->dir.'/tr/shop.php';
        $this->assertSame('Sepetiniz boş', $tr['cart']['empty']);
        $this->assertSame(':count ürün', $tr['cart']['items']);
        $this->assertSame("Ödeme 'adımı'", $tr['checkout']['title']);
        $this->assertSame('x', $tr['odd.key']);
        $this->assertSame(5, $tr['n']);

        $en = include $this->dir.'/en/shop.php';
        $this->assertSame(['title' => 'Shop', 'cart' => ['empty' => 'Your cart is empty']], $en);

        $backups = File::allFiles($this->dir.'/_backups');
        $this->assertCount(2, $backups);
    }

    public function test_store_fills_missing_locales_only_where_key_is_absent(): void
    {
        (new LangFileStore($this->dir.'/_backups'))->write($this->dir, 'shop', ['tr' => ['title' => 'Yeni başlık', 'promo' => 'Kampanya']], ['tr', 'en']);

        $en = include $this->dir.'/en/shop.php';
        $this->assertSame('Shop', $en['title']); // var olan dokunulmaz
        $this->assertSame('Kampanya', $en['promo']); // eksik olan eklenir
    }

    public function test_store_rejects_invalid_and_conflicting_keys(): void
    {
        $store = new LangFileStore($this->dir.'/_backups');

        foreach (['../x', 'a..b', 'bad key', 'title.sub', 'cart'] as $key) {
            try {
                $store->write($this->dir, 'shop', ['tr' => [$key => 'v']]);
                $this->fail("{$key} kabul edilmemeliydi");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_page_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/superadmin/translations')->assertForbidden();
        $this->actingAs($user)->put('/superadmin/translations', [])->assertForbidden();
    }

    public function test_page_renders_selected_file_rows(): void
    {
        $this->useFakeSource();

        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        $this->actingAs($this->editor())
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
            ->get('/superadmin/translations?source=fake&group=g&file=shop&locales=en,tr,xx')
            ->assertOk()
            ->assertJsonPath('component', 'Superadmin::Translations')
            ->assertJsonPath('props.selection.locales', ['en', 'tr'])
            ->assertJsonPath('props.rows.0.key', 'cart.empty')
            ->assertJsonPath('props.isNewFile', false);
    }

    public function test_update_passes_dotted_keys_to_source(): void
    {
        $source = $this->useFakeSource();

        $this->actingAs($this->editor())
            ->from('/superadmin/translations')
            ->putJson('/superadmin/translations', [
                'source' => 'fake',
                'group' => 'g',
                'file' => 'shop',
                'changes' => ['tr' => ['cart.empty' => 'Boş', 'title' => null]],
                'fill_missing_locales' => true,
            ])
            ->assertRedirect('/superadmin/translations')
            ->assertSessionHas('success');

        $this->assertSame([['g', 'shop', ['tr' => ['cart.empty' => 'Boş', 'title' => '']], true]], $source->writes);
    }

    public function test_update_reports_source_validation_errors(): void
    {
        $this->useFakeSource();

        $this->actingAs($this->editor())
            ->from('/superadmin/translations')
            ->putJson('/superadmin/translations', ['source' => 'yok', 'group' => 'g', 'file' => 'shop', 'changes' => ['tr' => ['a' => 'b']]])
            ->assertRedirect('/superadmin/translations')
            ->assertSessionHas('error');
    }

    private function editor(): User
    {
        return tap(User::factory()->create())->givePermissionTo('translations.manage');
    }

    private function useFakeSource(): TranslationSource
    {
        $dir = $this->dir;
        $source = new class($dir) implements TranslationSource
        {
            public array $writes = [];

            public function __construct(private string $dir) {}

            public function key(): string { return 'fake'; }

            public function label(): string { return 'Sahte'; }

            public function allowsNewFiles(): bool { return false; }

            public function groups(): array
            {
                return [['key' => 'g', 'label' => 'G', 'hint' => null, 'locales' => ['en', 'tr'], 'files' => ['shop']]];
            }

            public function read(string $group, string $file, array $locales): array
            {
                return (new LangFileStore($this->dir.'/_backups'))->read($this->dir, $file, $locales);
            }

            public function write(string $group, string $file, array $changes, bool $fillMissingLocales, ?int $actorId): int
            {
                $this->writes[] = [$group, $file, $changes, $fillMissingLocales];

                return array_sum(array_map('count', $changes));
            }
        };

        $this->app->instance(TranslationService::class, new TranslationService([$source]));

        return $source;
    }
}
