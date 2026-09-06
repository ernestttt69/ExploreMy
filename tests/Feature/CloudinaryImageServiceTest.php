<?php

namespace Tests\Feature;

use App\Services\CloudinaryImageService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudinaryImageServiceTest extends TestCase
{
    public function test_upload_signs_parameters_and_returns_https_url(): void
    {
        config(['services.cloudinary' => ['cloud_name' => 'test-cloud', 'api_key' => 'test-key', 'api_secret' => 'test-secret']]);
        $body = '';
        Http::fake(function ($request) use (&$body) {
            $body = $request->body();
            return Http::response(['secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/example.jpg']);
        });
        $path = public_path('images/ExploreMy_icon.jpeg');
        $url = app(CloudinaryImageService::class)->upload($path);
        $this->assertSame('https://res.cloudinary.com/test-cloud/image/upload/example.jpg', $url);
        Http::assertSent(function ($request) use ($path, $body) {
            preg_match('/name="timestamp"[^\r]*\r\n(?:[^\r]+\r\n)*\r\n(\d+)/', $body, $matches);
            $expected = sha1('overwrite=false&public_id=exploremy/'.hash_file('sha256', $path).'&timestamp='.($matches[1] ?? '').'test-secret');
            return str_contains($body, $expected)
                && str_contains($body, 'exploremy/'.hash_file('sha256', $path))
                && preg_match('/name="overwrite"[^\r]*\r\n(?:[^\r]+\r\n)*\r\nfalse/', $body)
                && ! $request->hasHeader('Authorization') && ! str_contains($body, 'test-secret');
        });
    }

    public function test_stale_timestamp_is_retried_once_using_server_date(): void
    {
        config(['services.cloudinary' => ['cloud_name' => 'test-cloud', 'api_key' => 'test-key', 'api_secret' => 'test-secret']]);
        $timestamp = time() - 7200;
        $bodies = [];
        Http::fake(function ($request) use (&$bodies, $timestamp) {
            $bodies[] = $request->body();
            if (count($bodies) === 1) {
                return Http::response(['error' => ['message' => 'Stale timestamp']], 401, ['Date' => gmdate('D, d M Y H:i:s', $timestamp).' GMT']);
            }
            return Http::response(['secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/example.jpg']);
        });
        app(CloudinaryImageService::class)->upload(public_path('images/ExploreMy_icon.jpeg'));
        Http::assertSentCount(2);
        $this->assertStringContainsString((string) $timestamp, $bodies[1]);
    }

    public function test_upload_failure_does_not_expose_provider_response(): void
    {
        config(['services.cloudinary' => ['cloud_name' => 'test-cloud', 'api_key' => 'test-key', 'api_secret' => 'test-secret']]);
        Http::fake(['*' => Http::response(['error' => 'private details'], 401)]);
        $this->expectExceptionMessage('Cloudinary upload failed (HTTP 401).');
        app(CloudinaryImageService::class)->upload(public_path('images/ExploreMy_icon.jpeg'));
    }
}
