<?php
// tests/Feature/Tenant/Portal/UserSoftDeleteAndActiveTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSoftDeleteAndActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_defaults_to_active(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_user_soft_deletes(): void
    {
        $user = User::factory()->create();
        $id = $user->id;
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $id]);
        $this->assertNull(User::find($id));
        $this->assertNotNull(User::withTrashed()->find($id));
    }
}
