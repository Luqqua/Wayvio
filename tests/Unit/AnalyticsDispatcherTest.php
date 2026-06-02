<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Analytics\AnalyticsDispatcher;
use App\Services\Analytics\AnalyticsRequestDataExtractor;
use App\Services\Analytics\AnalyticsTierResolver;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class AnalyticsDispatcherTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_record_page_view_skips_iframe_requests(): void
    {
        $client = Mockery::mock(AnalyticsClient::class);
        $client->shouldNotReceive('sendEvent');

        $tierResolver = Mockery::mock(AnalyticsTierResolver::class);
        $tierResolver->shouldNotReceive('tierLevel');

        $extractor = Mockery::mock(AnalyticsRequestDataExtractor::class);
        $extractor->shouldNotReceive('buildContext');

        $dispatcher = new AnalyticsDispatcher($client, $tierResolver, $extractor);

        $request = Request::create('/alice', 'GET', [], [], [], [
            'HTTP_SEC_FETCH_DEST' => 'iframe',
        ]);

        $owner = new User();
        $owner->id = 1001;
        $owner->littlelink_name = 'alice';

        $result = $dispatcher->recordPageView($request, $owner, [
            'page_slug' => 'alice',
            'page_id' => 1001,
        ]);

        $this->assertFalse($result);
    }

    public function test_record_page_view_tracks_non_iframe_requests(): void
    {
        $client = Mockery::mock(AnalyticsClient::class);
        $client->shouldReceive('sendEvent')
            ->once()
            ->with(Mockery::on(static function (array $payload): bool {
                return ($payload['event_type'] ?? null) === 'view'
                    && ($payload['site_id'] ?? null) === 1001
                    && (($payload['metadata']['ip'] ?? null) === '127.0.0.1');
            }))
            ->andReturn(true);

        $tierResolver = Mockery::mock(AnalyticsTierResolver::class);
        $tierResolver->shouldReceive('tierLevel')->once()->andReturn('business');
        $tierResolver->shouldReceive('allowsEvent')->once()->with('business', 'view')->andReturn(true);
        $tierResolver->shouldReceive('featuresFor')->once()->with('business')->andReturn(['utm', 'heatmaps']);

        $extractor = Mockery::mock(AnalyticsRequestDataExtractor::class);
        $extractor->shouldReceive('buildContext')->once()->andReturn([
            'ip' => '127.0.0.1',
        ]);

        $dispatcher = new AnalyticsDispatcher($client, $tierResolver, $extractor);

        $request = Request::create('/alice', 'GET');

        $owner = new User();
        $owner->id = 1001;
        $owner->littlelink_name = 'alice';

        $result = $dispatcher->recordPageView($request, $owner, [
            'page_slug' => 'alice',
            'page_id' => 1001,
        ]);

        $this->assertTrue($result);
    }
}
