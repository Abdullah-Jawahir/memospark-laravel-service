<?php

namespace Tests\Feature;

use App\Services\FastApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FastApiServiceIamAuthTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    Cache::flush();
  }

  public function test_it_attaches_identity_token_when_iam_auth_is_enabled(): void
  {
    config([
      'services.fastapi.url' => 'https://memospark-fastapi-service-abc-uc.a.run.app',
      'services.fastapi.iam_auth_enabled' => true,
      'services.fastapi.iam_audience' => null,
      'services.fastapi.iam_metadata_url' => 'http://metadata/computeMetadata/v1/instance/service-accounts/default/identity',
      'services.fastapi.iam_token_cache_seconds' => 3000,
    ]);

    Http::fake([
      'http://metadata/computeMetadata/v1/instance/service-accounts/default/identity*' => Http::response('test-identity-token', 200),
      'https://memospark-fastapi-service-abc-uc.a.run.app/api/v1/search-flashcards/topics' => function (Request $request) {
        $this->assertSame('Bearer test-identity-token', $request->header('Authorization')[0] ?? null);

        return Http::response(['topics' => ['python']], 200);
      },
    ]);

    $result = app(FastApiService::class)->getSuggestedTopics();

    $this->assertSame(['topics' => ['python']], $result);

    Http::assertSent(function (Request $request) {
      return str_contains($request->url(), 'metadata/computeMetadata')
        && (($request->header('Metadata-Flavor')[0] ?? null) === 'Google')
        && str_contains($request->url(), 'audience=' . urlencode('https://memospark-fastapi-service-abc-uc.a.run.app'));
    });
  }

  public function test_it_does_not_send_identity_token_when_iam_auth_is_disabled(): void
  {
    config([
      'services.fastapi.url' => 'https://memospark-fastapi-service-abc-uc.a.run.app',
      'services.fastapi.iam_auth_enabled' => false,
      'services.fastapi.iam_audience' => null,
      'services.fastapi.iam_metadata_url' => 'http://metadata/computeMetadata/v1/instance/service-accounts/default/identity',
      'services.fastapi.iam_token_cache_seconds' => 3000,
    ]);

    Http::fake([
      'https://memospark-fastapi-service-abc-uc.a.run.app/api/v1/search-flashcards/topics' => function (Request $request) {
        $this->assertNull($request->header('Authorization')[0] ?? null);

        return Http::response(['topics' => ['math']], 200);
      },
    ]);

    $result = app(FastApiService::class)->getSuggestedTopics();

    $this->assertSame(['topics' => ['math']], $result);

    Http::assertNotSent(function (Request $request) {
      return str_contains($request->url(), 'metadata/computeMetadata');
    });
  }

  public function test_it_reuses_cached_identity_token_across_requests(): void
  {
    config([
      'services.fastapi.url' => 'https://memospark-fastapi-service-abc-uc.a.run.app',
      'services.fastapi.iam_auth_enabled' => true,
      'services.fastapi.iam_audience' => 'https://custom-fastapi-audience.run.app',
      'services.fastapi.iam_metadata_url' => 'http://metadata/computeMetadata/v1/instance/service-accounts/default/identity',
      'services.fastapi.iam_token_cache_seconds' => 3000,
    ]);

    Http::fake([
      'http://metadata/computeMetadata/v1/instance/service-accounts/default/identity*' => Http::response('cached-token', 200),
      'https://memospark-fastapi-service-abc-uc.a.run.app/api/v1/search-flashcards/topics' => Http::sequence()
        ->push(['topics' => ['first']], 200)
        ->push(['topics' => ['second']], 200),
    ]);

    $service = app(FastApiService::class);

    $first = $service->getSuggestedTopics();
    $second = $service->getSuggestedTopics();

    $this->assertSame(['topics' => ['first']], $first);
    $this->assertSame(['topics' => ['second']], $second);

    Http::assertSentCount(3);
  }
}
