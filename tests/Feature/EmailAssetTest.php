<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class EmailAssetTest extends TestCase
{
    public function test_poppins_font_is_publicly_available_with_email_safe_headers(): void
    {
        $this->get(route('email-assets.poppins', 'regular'))
            ->assertOk()
            ->assertHeader('content-type', 'font/ttf')
            ->assertHeader('access-control-allow-origin', '*')
            ->assertHeader('cache-control', 'immutable, max-age=31536000, public');
    }

    public function test_unknown_email_font_weight_is_not_available(): void
    {
        $this->get('/email-assets/fonts/poppins/black')->assertNotFound();
    }
}
