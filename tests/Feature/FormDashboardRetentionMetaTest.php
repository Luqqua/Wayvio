<?php

namespace Tests\Feature;

use App\Http\Controllers\FormDashboardController;
use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use App\Services\Forms\FormCatalog;
use App\Services\Forms\FormsAccess;
use App\Services\Forms\FormsClient;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class FormDashboardRetentionMetaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();

        config()->set('billing.lifecycle.downgrade_retention_days', 30);
        config()->set('billing.lifecycle.pending_deletion_grace_days', 1);
        Carbon::setTestNow(Carbon::parse('2026-05-13 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_retention_meta_uses_downgrade_window_when_forms_are_lifecycle_suspended(): void
    {
        DB::table('forms_resource_states')->insert([
            'user_id' => 130564,
            'status' => 'suspended',
            'reason' => 'downgrade',
            'suspended_at' => '2026-05-13 09:23:39',
        ]);

        $controller = $this->controller();
        $hub = new User();
        $hub->setAttribute('id', 130564);

        $meta = $this->invokeResolveRetentionMeta($controller, $hub, false, 7);

        $this->assertSame(30, $meta['days']);
        $this->assertSame('lifecycle_suspended', $meta['mode']);
        $this->assertSame(1, $meta['grace_days']);
        $this->assertSame('2026-06-12T09:23:39+00:00', $meta['pending_deletion_at']);
        $this->assertSame('2026-06-13T09:23:39+00:00', $meta['delete_after_at']);
        $this->assertSame(30, $meta['days_until_pending_deletion']);
        $this->assertSame(31, $meta['days_until_delete']);
        $this->assertTrue($meta['pending_deletion_estimated']);
        $this->assertTrue($meta['delete_after_estimated']);
    }

    public function test_resolve_retention_meta_returns_plan_mode_when_forms_allowed_and_no_lifecycle_state(): void
    {
        $controller = $this->controller();
        $hub = new User();
        $hub->setAttribute('id', 999001);

        $meta = $this->invokeResolveRetentionMeta($controller, $hub, true, 90);

        $this->assertSame(90, $meta['days']);
        $this->assertSame('plan', $meta['mode']);
        $this->assertNull($meta['pending_deletion_at']);
        $this->assertNull($meta['delete_after_at']);
        $this->assertNull($meta['days_until_pending_deletion']);
        $this->assertNull($meta['days_until_delete']);
        $this->assertFalse($meta['pending_deletion_estimated']);
        $this->assertFalse($meta['delete_after_estimated']);
    }

    public function test_resolve_retention_meta_returns_locked_mode_when_forms_not_allowed(): void
    {
        $controller = $this->controller();
        $hub = new User();
        $hub->setAttribute('id', 999002);

        $meta = $this->invokeResolveRetentionMeta($controller, $hub, false, 90);

        $this->assertSame(90, $meta['days']);
        $this->assertSame('locked', $meta['mode']);
        $this->assertNull($meta['pending_deletion_at']);
        $this->assertNull($meta['delete_after_at']);
        $this->assertFalse($meta['pending_deletion_estimated']);
        $this->assertFalse($meta['delete_after_estimated']);
    }

    public function test_resolve_retention_meta_uses_final_grace_window_when_pending_deletion(): void
    {
        DB::table('forms_resource_states')->insert([
            'user_id' => 130564,
            'status' => 'pending_deletion',
            'reason' => 'non_payment',
            'pending_deletion_at' => '2026-06-12 00:00:00',
            'delete_after_at' => '2026-06-13 00:00:00',
        ]);

        $controller = $this->controller();
        $hub = new User();
        $hub->setAttribute('id', 130564);

        $meta = $this->invokeResolveRetentionMeta($controller, $hub, false, 7);

        $this->assertSame(1, $meta['days']);
        $this->assertSame('lifecycle_pending_deletion', $meta['mode']);
        $this->assertSame(1, $meta['grace_days']);
        $this->assertSame('2026-06-12T00:00:00+00:00', $meta['pending_deletion_at']);
        $this->assertSame('2026-06-13T00:00:00+00:00', $meta['delete_after_at']);
        $this->assertSame(30, $meta['days_until_pending_deletion']);
        $this->assertSame(31, $meta['days_until_delete']);
        $this->assertFalse($meta['pending_deletion_estimated']);
        $this->assertFalse($meta['delete_after_estimated']);
    }

    /**
     * @return array{
     *     days:int,
     *     mode:string,
     *     grace_days:int,
     *     pending_deletion_at:?string,
     *     delete_after_at:?string,
     *     days_until_pending_deletion:?int,
     *     days_until_delete:?int,
     *     pending_deletion_estimated:bool,
     *     delete_after_estimated:bool
     * }
     */
    private function invokeResolveRetentionMeta(
        FormDashboardController $controller,
        User $hub,
        bool $formsAllowed,
        int $defaultRetentionDays
    ): array {
        $method = new \ReflectionMethod(FormDashboardController::class, 'resolveRetentionMetaForHub');
        $method->setAccessible(true);

        /** @var array{days:int,mode:string,grace_days:int} $meta */
        $meta = $method->invoke($controller, $hub, $formsAllowed, $defaultRetentionDays);

        return $meta;
    }

    private function controller(): FormDashboardController
    {
        return new FormDashboardController(
            Mockery::mock(AgencyHubContext::class),
            Mockery::mock(FormCatalog::class),
            Mockery::mock(FormsAccess::class),
            Mockery::mock(FormsClient::class),
        );
    }

    private function useSqliteInMemory(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    private function createTables(): void
    {
        Schema::create('forms_resource_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('status')->nullable();
            $table->string('reason')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('pending_deletion_at')->nullable();
            $table->dateTime('delete_after_at')->nullable();
        });
    }
}
