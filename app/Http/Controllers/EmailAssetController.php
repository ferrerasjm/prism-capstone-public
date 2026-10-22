<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmailAssetController extends Controller
{
    private const POPPINS_FILES = [
        'regular' => 'Poppins-Regular.ttf',
        'medium' => 'Poppins-Medium.ttf',
        'semibold' => 'Poppins-SemiBold.ttf',
        'bold' => 'Poppins-Bold.ttf',
    ];

    public function poppins(string $weight): BinaryFileResponse
    {
        abort_unless(isset(self::POPPINS_FILES[$weight]), 404);

        return response()->file(
            public_path('assets/fonts/'.self::POPPINS_FILES[$weight]),
            [
                'Content-Type' => 'font/ttf',
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ],
        );
    }
}
