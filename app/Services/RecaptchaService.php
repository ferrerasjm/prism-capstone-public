<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side reCAPTCHA verification. Fails closed by design — when
 * reCAPTCHA is enabled but the secret is missing or the token is rejected,
 * verification returns false and the submission is refused rather than let
 * through.
 */
class RecaptchaService
{
    public function enabled(): bool
    {
        return (bool) config('services.recaptcha.enabled');
    }

    /**
     * Verifies a reCAPTCHA token against Google's siteverify endpoint.
     *
     * Returns `! enabled()` rather than throwing when reCAPTCHA is off,
     * the secret is unset, or the token is empty - so disabling
     * reCAPTCHA in .env genuinely disables it everywhere this is
     * called, instead of every caller needing its own bypass.
     */
    public function verify(string $token, ?string $ipAddress): bool
    {
        $secret = (string) config('services.recaptcha.secret_key');

        if (! $this->enabled() || $secret === '' || $token === '') {
            return ! $this->enabled();
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post((string) config('services.recaptcha.verify_url'), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ]);

            if (! $response->successful() || ! $response->json('success')) {
                Log::warning('reCAPTCHA verification rejected.', [
                    'http_status' => $response->status(),
                    'error_codes' => (array) $response->json('error-codes', []),
                    'token_length' => strlen($token),
                ]);

                return false;
            }

            $allowedHostnames = array_filter(array_map(
                'trim',
                explode(',', (string) config('services.recaptcha.hostname'))
            ));
            $actualHostname = trim((string) $response->json('hostname', ''));

            if ($allowedHostnames !== []) {
                $matches = $actualHostname !== '' && array_reduce(
                    $allowedHostnames,
                    fn (bool $carry, string $expected): bool => $carry || hash_equals($expected, $actualHostname),
                    false
                );

                if (! $matches) {
                    Log::warning('reCAPTCHA hostname verification failed.', [
                        'expected_hostnames' => $allowedHostnames,
                        'actual_hostname' => $actualHostname,
                    ]);

                    return false;
                }
            }

            return true;
        } catch (Throwable $exception) {
            $failOpen = $this->shouldFailOpen($exception);

            Log::warning('reCAPTCHA verification request failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'fail_open' => $failOpen,
            ]);

            return $failOpen;
        }
    }

    private function shouldFailOpen(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            && app()->environment('local')
            && (bool) config('services.recaptcha.fail_open_on_connection_error', false);
    }
}
