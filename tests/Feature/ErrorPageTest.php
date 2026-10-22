<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);

        Route::middleware('web')
            ->get('/_error-page-test/{status}', fn (int $status) => abort($status))
            ->whereNumber('status');
    }

    #[DataProvider('errorPages')]
    public function test_browser_errors_use_the_branded_prism_page(
        int $status,
        string $title,
        string $primaryAction,
    ): void {
        $this->get("/_error-page-test/{$status}")
            ->assertStatus($status)
            ->assertSee("Error {$status}")
            ->assertSee($title)
            ->assertSee($primaryAction)
            ->assertSee('Disciplinary Case Management System')
            ->assertSee('assets/vector1_left.png', false)
            ->assertSee('assets/vector2_right.png', false)
            ->assertDontSee('Stack trace')
            ->assertDontSee('vendor/laravel');
    }

    public static function errorPages(): array
    {
        return [
            'authentication required' => [401, 'Sign in required', 'Go to Sign In'],
            'forbidden' => [403, 'Access restricted', 'Return to PRISM'],
            'not found' => [404, 'Page not found', 'Return to PRISM'],
            'expired session' => [419, 'Session expired', 'Sign In Again'],
            'rate limited' => [429, 'Too many requests', 'Return to PRISM'],
            'server error' => [500, 'Something went wrong', 'Return to PRISM'],
            'maintenance' => [503, 'Temporarily unavailable', 'Try Again'],
            'generic client error' => [418, 'Request could not be completed', 'Return to PRISM'],
            'generic server error' => [501, 'PRISM is temporarily unavailable', 'Return to PRISM'],
        ];
    }
}
