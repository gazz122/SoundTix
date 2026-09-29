<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TicketValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatekeeperScanController extends Controller
{
    public function scan(Request $request, TicketValidationService $service): JsonResponse
    {
        $payload = $request->validate([
            'payload' => ['required', 'string', 'max:512'],
        ])['payload'];

        return response()->json($service->validate($payload, $request->user()));
    }
}
