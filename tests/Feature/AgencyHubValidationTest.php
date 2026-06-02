<?php

namespace Tests\Feature;

use App\Http\Controllers\AgencyHubController;
use App\Models\AgencyHub;
use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use App\Services\Agency\AgencyHubQuotaManager;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Hubs\HubPublicationService;
use App\Services\Lifecycle\AccountLifecycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class AgencyHubValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_accepts_display_name_and_slug_at_limits(): void
    {
        $displayName = str_repeat('a', 30);
        $slug = 'slug-12345678901234567890';

        $hub = new AgencyHub([
            'managed_user_id' => 3002,
            'display_name' => $displayName,
        ]);
        $hub->setRelation('managedUser', new User(['littlelink_name' => $slug]));

        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('isAgencyAccount')->once()->andReturn(true);
        $agencyContext->shouldReceive('createHub')
            ->once()
            ->with(
                Mockery::type(User::class),
                Mockery::on(function (array $payload) use ($displayName, $slug): bool {
                    return ($payload['display_name'] ?? null) === $displayName
                        && ($payload['littlelink_name'] ?? null) === $slug;
                }),
                Mockery::type(Request::class)
            )
            ->andReturn($hub);

        $audit = Mockery::mock(ComplianceAuditService::class);
        $audit->shouldReceive('record')->once();

        $response = $this->controller($agencyContext, $audit)->store($this->request([
            'display_name' => $displayName,
            'littlelink_name' => $slug,
        ]));

        $this->assertSame(30, strlen($displayName));
        $this->assertSame(25, strlen($slug));
        $this->assertTrue($response->isRedirect(route('agency.hubs.index')));
    }

    public function test_store_rejects_display_name_over_30_characters(): void
    {
        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('isAgencyAccount')->once()->andReturn(true);
        $agencyContext->shouldNotReceive('createHub');

        try {
            $this->controller($agencyContext)->store($this->request([
                'display_name' => str_repeat('a', 31),
                'littlelink_name' => 'valid-hub',
            ]));
            $this->fail('Expected validation to reject the display name.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('display_name', $e->errors());
        }
    }

    public function test_store_rejects_slug_over_25_characters(): void
    {
        $agencyContext = Mockery::mock(AgencyHubContext::class);
        $agencyContext->shouldReceive('isAgencyAccount')->once()->andReturn(true);
        $agencyContext->shouldNotReceive('createHub');

        try {
            $this->controller($agencyContext)->store($this->request([
                'display_name' => 'Valid Hub',
                'littlelink_name' => str_repeat('a', 26),
            ]));
            $this->fail('Expected validation to reject the hub slug.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('littlelink_name', $e->errors());
        }
    }

    private function request(array $payload): Request
    {
        $request = Request::create('/agency/hubs', 'POST', $payload);
        $request->setUserResolver(function (): User {
            $user = new User();
            $user->id = 3001;
            $user->locale = 'en';

            return $user;
        });
        $request->setLaravelSession(app('session.store'));
        app('session.store')->start();

        return $request;
    }

    private function controller(
        AgencyHubContext $agencyContext,
        ?ComplianceAuditService $complianceAudit = null
    ): AgencyHubController {
        return new AgencyHubController(
            $agencyContext,
            Mockery::mock(AgencyHubQuotaManager::class),
            $complianceAudit ?: Mockery::mock(ComplianceAuditService::class),
            Mockery::mock(AccountLifecycleService::class),
            app(HubPublicationService::class),
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
        Schema::create('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('littlelink_name')->nullable()->unique();
            $table->timestamps();
        });
    }
}
