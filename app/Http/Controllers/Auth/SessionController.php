<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function keepAlive(Request $request): JsonResponse
    {
        $request->session()->put('auth.last_activity_at', now()->timestamp);

        return response()->json([
            'expires_in' => (int) config('session.lifetime', 480) * 60,
            'warning_in' => (int) config('session.warning', 300),
        ]);
    }
}
