<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->useSqliteInMemory();
        $this->createReportTables();
        $this->seedUsers();

        config()->set('app.url', 'http://localhost');
        config()->set('reports.rate_limit_per_minute', 10);
        config()->set('reports.rate_limit_per_day', 100);
        config()->set('reports.rate_limit_target_per_hour', 2);
        config()->set('reports.allow_self_report', false);
        config()->set('reports.captcha_required', false);
        config()->set('services.captcha.provider', null);
    }

    public function test_report_submission_creates_database_entry_with_snapshot_and_reporter_metadata(): void
    {
        $this->actingAs(User::query()->findOrFail(301));

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.1'])
            ->post('/report', [
                'id' => 300,
                'url' => 'http://localhost/target-user',
                'report-type' => __('messages.hate_speech'),
                'message' => 'Offensive profile text.',
            ]);

        $response->assertStatus(302);
        $this->assertStringContainsString('/report?id=300', (string) $response->headers->get('Location'));

        $row = DB::table('page_reports')->where('reported_user_id', 300)->first();
        $this->assertNotNull($row);
        $this->assertSame('target-user', $row->reported_page_name_snapshot);
        $this->assertStringContainsString('target-user', (string) $row->reported_page_url_snapshot);
        $this->assertSame(1, (int) $row->report_count);
        $this->assertSame('open', $row->status);
        $this->assertSame(301, (int) $row->last_reporter_user_id);
        $this->assertNotEmpty($row->last_reporter_ip_hash);
        $this->assertSame(64, strlen((string) $row->last_reporter_ip_hash));

        $event = DB::table('page_report_events')->where('page_report_id', $row->id)->first();
        $this->assertNotNull($event);
        $this->assertSame(301, (int) $event->reporter_user_id);
        $this->assertSame(__('messages.hate_speech'), $event->report_type);
        $this->assertSame('Offensive profile text.', $event->message);
    }

    public function test_duplicate_report_increments_counter_reopens_record_and_stores_latest_description(): void
    {
        $now = now();
        DB::table('page_reports')->insert([
            'id' => 1,
            'reported_user_id' => 300,
            'reported_page_name_snapshot' => 'target-user',
            'reported_page_url_snapshot' => 'http://localhost/target-user',
            'report_count' => 2,
            'first_reported_at' => $now->copy()->subDay(),
            'last_reported_at' => $now->copy()->subHour(),
            'last_report_type' => __('messages.fraud_scams'),
            'last_report_message' => 'Old note',
            'last_reporter_user_id' => 301,
            'last_reporter_ip_hash' => str_repeat('a', 64),
            'status' => 'processed',
            'processed_at' => $now->copy()->subMinutes(15),
            'processed_by_user_id' => 302,
            'moderator_comment' => 'checked',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs(User::query()->findOrFail(302));

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.2'])
            ->post('/report', [
                'id' => 300,
                'url' => 'http://localhost/target-user',
                'report-type' => __('messages.other_specify'),
                'message' => 'Another report.',
            ]);

        $response->assertStatus(302);
        $this->assertStringContainsString('/report?id=300', (string) $response->headers->get('Location'));

        $row = DB::table('page_reports')->where('id', 1)->first();
        $this->assertNotNull($row);
        $this->assertSame(3, (int) $row->report_count);
        $this->assertSame('open', $row->status);
        $this->assertNull($row->processed_at);
        $this->assertNull($row->processed_by_user_id);
        $this->assertSame(__('messages.other_specify'), $row->last_report_type);
        $this->assertSame('Another report.', $row->last_report_message);

        $event = DB::table('page_report_events')->where('page_report_id', 1)->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame(302, (int) $event->reporter_user_id);
        $this->assertSame(__('messages.other_specify'), $event->report_type);
        $this->assertSame('Another report.', $event->message);
    }

    public function test_report_target_rate_limit_blocks_after_threshold(): void
    {
        $this->actingAs(User::query()->findOrFail(301));

        $payload = [
            'id' => 300,
            'url' => 'http://localhost/target-user',
            'report-type' => __('messages.hate_speech'),
            'message' => 'Repeated report',
        ];

        $first = $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.3'])->post('/report', $payload);
        $second = $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.3'])->post('/report', $payload);
        $third = $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.3'])->post('/report', $payload);

        $first->assertStatus(302);
        $second->assertStatus(302);
        $this->assertStringContainsString('/report?id=300', (string) $first->headers->get('Location'));
        $this->assertStringContainsString('/report?id=300', (string) $second->headers->get('Location'));
        $third->assertStatus(429);

        $this->assertSame(2, (int) DB::table('page_reports')->where('reported_user_id', 300)->value('report_count'));
    }

    public function test_report_daily_ip_rate_limit_blocks_spam_across_targets(): void
    {
        config()->set('reports.rate_limit_per_day', 2);
        config()->set('reports.rate_limit_target_per_hour', 10);

        $this->actingAs(User::query()->findOrFail(301));

        $first = $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.9'])->post('/report', [
            'id' => 300,
            'url' => 'http://localhost/target-user',
            'report-type' => __('messages.hate_speech'),
            'message' => 'First report.',
        ]);

        $second = $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.9'])->post('/report', [
            'id' => 303,
            'url' => 'http://localhost/admin1',
            'report-type' => __('messages.fraud_scams'),
            'message' => 'Second report.',
        ]);

        $third = $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.9'])->post('/report', [
            'id' => 304,
            'url' => 'http://localhost/extra-target-a',
            'report-type' => __('messages.impersonation'),
            'message' => 'Third report.',
        ]);

        $first->assertStatus(302);
        $second->assertStatus(302);
        $third->assertStatus(429);
        $this->assertSame(2, DB::table('page_reports')->count());
    }

    public function test_report_submission_rejects_overlong_message(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->post('/report', [
                'id' => 300,
                'url' => 'http://localhost/target-user',
                'report-type' => __('messages.hate_speech'),
                'message' => str_repeat('x', 1001),
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('message');
        $this->assertSame(0, DB::table('page_reports')->count());
    }

    public function test_report_submission_rejects_unresolvable_slug_like_sql_injection_payload(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.11'])
            ->post('/report', [
                'url' => 'http://localhost/target-user%27%20OR%201=1',
                'report-type' => __('messages.hate_speech'),
                'message' => 'Injection-like slug.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error', __('messages.report_error'));
        $this->assertSame(0, DB::table('page_reports')->count());
    }

    public function test_report_turnstile_is_required_when_configured(): void
    {
        config()->set('reports.captcha_required', true);
        config()->set('services.captcha.provider', 'turnstile');
        config()->set('services.captcha.sitekeys.report', 'report-site-key');
        config()->set('services.captcha.secrets.report', 'report-secret');

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.12'])
            ->post('/report', [
                'id' => 300,
                'url' => 'http://localhost/target-user',
                'report-type' => __('messages.hate_speech'),
                'message' => 'Missing captcha.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('captcha');
        $this->assertSame(0, DB::table('page_reports')->count());
        $this->assertSame(0, DB::table('page_report_events')->count());
    }

    public function test_report_turnstile_allows_submission_with_valid_token(): void
    {
        config()->set('reports.captcha_required', true);
        config()->set('services.captcha.provider', 'turnstile');
        config()->set('services.captcha.sitekeys.report', 'report-site-key');
        config()->set('services.captcha.secrets.report', 'report-secret');

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
                'action' => 'report',
            ], 200),
        ]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.13', 'HTTP_HOST' => 'localhost'])
            ->post('/report', [
                'id' => 300,
                'url' => 'http://localhost/target-user',
                'report-type' => __('messages.hate_speech'),
                'message' => 'Captcha ok.',
                'cf-turnstile-response' => 'valid-token',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertSame(1, DB::table('page_reports')->count());
        $this->assertSame(1, DB::table('page_report_events')->count());

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                && $request['secret'] === 'report-secret'
                && $request['response'] === 'valid-token';
        });
    }

    public function test_report_form_posts_to_canonical_app_url(): void
    {
        config()->set('app.url', 'https://wayvio.example.test');

        $response = $this->get('https://wayvio.example.test/report?id=300');

        $response->assertStatus(200);
        $response->assertSee('action="https://wayvio.example.test/report"', false);
        $response->assertDontSee('action="http://target.example.test/report"', false);
        $response->assertDontSee('action="https://target.example.test/report"', false);
    }

    public function test_report_form_redirects_to_canonical_app_url_on_custom_domain_request(): void
    {
        config()->set('app.url', 'https://wayvio.example.test');

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'target.example.test'])
            ->get('/report?id=300');

        $response->assertRedirect('https://wayvio.example.test/report?id=300');
    }

    public function test_report_submission_accepts_agency_custom_domain_root_url(): void
    {
        config()->set('app.url', 'https://wayvio.example.test');

        DB::table('user_custom_domains')->insert([
            'user_id' => 300,
            'page_id' => null,
            'domain' => 'target.example.test',
            'status' => 'verified',
            'lifecycle_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.4', 'HTTP_HOST' => 'target.example.test'])
            ->post('/report', [
                'url' => 'https://target.example.test',
                'report-type' => __('messages.privacy_violation'),
                'message' => 'Custom domain root report.',
            ]);

        $response->assertStatus(302);
        $this->assertSame('https://wayvio.example.test/report?id=300', (string) $response->headers->get('Location'));
        $this->assertSame(1, (int) DB::table('page_reports')->where('reported_user_id', 300)->value('report_count'));
    }

    public function test_report_submission_accepts_canonical_at_slug_url(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.5'])
            ->post('/report', [
                'url' => 'http://localhost/@target-user',
                'report-type' => __('messages.impersonation'),
                'message' => 'Canonical URL report.',
            ]);

        $response->assertStatus(302);
        $this->assertStringContainsString('/report?id=300', (string) $response->headers->get('Location'));
        $this->assertSame(1, (int) DB::table('page_reports')->where('reported_user_id', 300)->value('report_count'));
    }

    public function test_report_submission_accepts_admin_like_profile_slug_from_other_user(): void
    {
        $this->actingAs(User::query()->findOrFail(301));

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.7'])
            ->post('/report', [
                'id' => 303,
                'url' => 'http://localhost/admin1',
                'report-type' => __('messages.hate_speech'),
                'message' => 'Admin-like slug report.',
            ]);

        $response->assertStatus(302);
        $this->assertStringContainsString('/report?id=303', (string) $response->headers->get('Location'));
        $this->assertSame(1, (int) DB::table('page_reports')->where('reported_user_id', 303)->value('report_count'));
    }

    public function test_self_report_returns_specific_error_message(): void
    {
        $this->actingAs(User::query()->findOrFail(303));

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.8'])
            ->post('/report', [
                'id' => 303,
                'url' => 'http://localhost/admin1',
                'report-type' => __('messages.hate_speech'),
                'message' => 'Self report.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error', __('messages.report_self_error'));
        $this->assertSame(0, DB::table('page_reports')->count());
    }

    public function test_report_submission_ignores_inactive_custom_domain_mapping(): void
    {
        DB::table('user_custom_domains')->insert([
            'user_id' => 300,
            'page_id' => null,
            'domain' => 'inactive.example.test',
            'status' => 'verified',
            'lifecycle_status' => 'inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.6', 'HTTP_HOST' => 'inactive.example.test'])
            ->post('/report', [
                'url' => 'https://inactive.example.test',
                'report-type' => __('messages.fraud_scams'),
                'message' => 'Inactive domain report.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertSame(0, DB::table('page_reports')->count());
    }

    public function test_report_mod_commands_can_list_and_manage_entries(): void
    {
        $now = now();
        DB::table('page_reports')->insert([
            [
                'id' => 10,
                'reported_user_id' => 300,
                'reported_page_name_snapshot' => 'target-user',
                'reported_page_url_snapshot' => 'http://localhost/target-user',
                'report_count' => 5,
                'first_reported_at' => $now->copy()->subDays(2),
                'last_reported_at' => $now->copy()->subHours(2),
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 11,
                'reported_user_id' => 301,
                'reported_page_name_snapshot' => 'reporter-user',
                'reported_page_url_snapshot' => 'http://localhost/reporter-user',
                'report_count' => 1,
                'first_reported_at' => $now->copy()->subDays(4),
                'last_reported_at' => $now->copy()->subDay(),
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Artisan::call('report:list', ['--status' => 'all']);
        $listOutput = Artisan::output();
        $this->assertNotFalse(strpos($listOutput, 'report_id=10'));
        $this->assertNotFalse(strpos($listOutput, 'report_id=11'));
        $this->assertTrue(strpos($listOutput, 'report_id=10') < strpos($listOutput, 'report_id=11'));

        $this->artisan('report:comment', ['report_id' => 10, 'comment' => 'needs check'])->assertExitCode(0);
        $this->assertSame('needs check', DB::table('page_reports')->where('id', 10)->value('moderator_comment'));

        $this->artisan('report:processed', ['report_id' => 10, '--by-user' => 302])->assertExitCode(0);
        $this->assertSame('processed', DB::table('page_reports')->where('id', 10)->value('status'));
        $this->assertSame(302, (int) DB::table('page_reports')->where('id', 10)->value('processed_by_user_id'));

        $this->artisan('report:delete', ['report_id' => 10])->assertExitCode(0);
        $this->assertNotNull(DB::table('page_reports')->where('id', 10)->value('deleted_at'));
    }

    public function test_report_comment_command_rejects_comments_over_40_chars(): void
    {
        $now = now();
        DB::table('page_reports')->insert([
            'id' => 20,
            'reported_user_id' => 300,
            'reported_page_name_snapshot' => 'target-user',
            'reported_page_url_snapshot' => 'http://localhost/target-user',
            'report_count' => 1,
            'first_reported_at' => $now,
            'last_reported_at' => $now,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->artisan('report:comment', [
            'report_id' => 20,
            'comment' => str_repeat('x', 41),
        ])->assertExitCode(1);
    }

    private function useSqliteInMemory(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    private function createReportTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('littlelink_name')->nullable()->unique();
            $table->string('role')->default('user');
            $table->string('block')->default('no');
            $table->string('password')->nullable();
            $table->rememberToken()->nullable();
            $table->timestamps();
        });

        Schema::create('page_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('reported_user_id')->unique();
            $table->string('reported_page_name_snapshot', 191);
            $table->string('reported_page_url_snapshot', 2048);
            $table->unsignedInteger('report_count')->default(1);
            $table->timestamp('first_reported_at');
            $table->timestamp('last_reported_at');
            $table->string('last_report_type', 120)->nullable();
            $table->text('last_report_message')->nullable();
            $table->unsignedBigInteger('last_reporter_user_id')->nullable();
            $table->string('last_reporter_ip_hash', 64)->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by_user_id')->nullable();
            $table->string('moderator_comment', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('page_report_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('page_report_id');
            $table->unsignedBigInteger('reporter_user_id')->nullable();
            $table->string('reporter_ip_hash', 64)->nullable();
            $table->string('report_type', 120)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('user_custom_domains', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('domain')->unique();
            $table->string('status')->default('pending');
            $table->string('lifecycle_status')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_hubs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedBigInteger('managed_user_id');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    private function seedUsers(): void
    {
        $now = now();
        DB::table('users')->insert([
            [
                'id' => 300,
                'name' => 'Target User',
                'email' => 'target@example.test',
                'littlelink_name' => 'target-user',
                'role' => 'user',
                'block' => 'no',
                'password' => Hash::make('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 301,
                'name' => 'Reporter User',
                'email' => 'reporter@example.test',
                'littlelink_name' => 'reporter-user',
                'role' => 'user',
                'block' => 'no',
                'password' => Hash::make('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 302,
                'name' => 'Moderator User',
                'email' => 'moderator@example.test',
                'littlelink_name' => 'moderator-user',
                'role' => 'admin',
                'block' => 'no',
                'password' => Hash::make('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 303,
                'name' => 'Admin One',
                'email' => 'admin1@example.test',
                'littlelink_name' => 'admin1',
                'role' => 'admin',
                'block' => 'no',
                'password' => Hash::make('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 304,
                'name' => 'Extra Target A',
                'email' => 'extra-a@example.test',
                'littlelink_name' => 'extra-target-a',
                'role' => 'user',
                'block' => 'no',
                'password' => Hash::make('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
