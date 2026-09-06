<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudinaryImageService
{
    private int $clockOffset = 0;

    public function upload(string $path): string
    {
        $cloud = (string) config('services.cloudinary.cloud_name');
        $key = (string) config('services.cloudinary.api_key');
        $secret = (string) config('services.cloudinary.api_secret');
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $cloud) || $key === '' || $secret === '') {
            throw new RuntimeException('Cloudinary configuration is missing.');
        }
        if (! is_readable($path)) {
            throw new RuntimeException('Image file is unreadable.');
        }
        $parameters = [
            'overwrite' => 'false',
            'public_id' => 'exploremy/'.hash_file('sha256', $path),
        ];
        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $parameters['timestamp'] = (string) (time() + $this->clockOffset);
                ksort($parameters);
                $signature = sha1(urldecode(http_build_query($parameters, '', '&', PHP_QUERY_RFC3986)).$secret);
                $file = fopen($path, 'rb');
                try {
                    $response = Http::acceptJson()->connectTimeout(15)->timeout(90)
                        ->withOptions(['allow_redirects' => false])
                        ->attach('file', $file, basename($path))
                        ->post("https://api.cloudinary.com/v1_1/{$cloud}/image/upload", $parameters + [
                            'api_key' => $key, 'signature' => $signature,
                        ]);
                } finally {
                    fclose($file);
                }

                $message = strtolower((string) $response->json('error.message', ''));
                $serverTime = strtotime($response->header('Date'));
                if ($attempt === 0 && $response->status() === 401 && $serverTime !== false
                    && (str_contains($message, 'timestamp') || str_contains($message, 'stale'))) {
                    // Use only the Date header from this verified HTTPS endpoint.
                    // Retry once with a fresh signature, without changing Windows time.
                    $this->clockOffset = $serverTime - time();
                    continue;
                }
                break;
            }
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            throw new RuntimeException('Cloudinary connection failed. Retry the command to resume.');
        }
        if (! $response->successful()) {
            // Classify the error without printing the provider body, which may
            // include credentials, signatures or signed request parameters.
            $message = strtolower((string) $response->json('error.message', ''));
            $hint = match (true) {
                str_contains($message, 'timestamp'), str_contains($message, 'stale') => ' Check your computer clock; the upload timestamp was rejected.',
                str_contains($message, 'signature') => ' The signature was rejected. Verify the API secret belongs to this API key and cloud.',
                str_contains($message, 'api_key'), str_contains($message, 'api key') => ' The API key was rejected. Verify it is active and belongs to this cloud.',
                str_contains($message, 'cloud name'), str_contains($message, 'cloud_name') => ' The cloud name was rejected.',
                str_contains($message, 'permission') => ' Cloudinary reports insufficient permissions for this endpoint.',
                str_contains($message, 'disabled') => ' Cloudinary reports that this feature or account is disabled.',
                default => '',
            };
            if ($hint === '' && $response->status() === 403 && $message !== '') {
                $safeMessage = str_replace([$secret, $key, base64_encode($key.':'.$secret)], '[redacted]', (string) $response->json('error.message'));
                $safeMessage = preg_replace('/https?:\/\/\S+|[A-Za-z0-9_+\/=.-]{20,}/', '[redacted]', $safeMessage);
                $hint = ' Provider message: '.substr(preg_replace('/[\r\n\x00-\x1f]/', ' ', $safeMessage), 0, 250);
            }
            throw new RuntimeException('Cloudinary upload failed (HTTP '.$response->status().').'.$hint);
        }
        $url = $response->json('secure_url');
        if (! is_string($url) || ! str_starts_with($url, 'https://res.cloudinary.com/'.$cloud.'/')) {
            throw new RuntimeException('Cloudinary returned no valid image URL.');
        }

        return $url;
    }
}
