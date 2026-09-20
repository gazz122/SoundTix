<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TicketValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketValidationController extends Controller
{
    public function validate(Request $request, TicketValidationService $service): JsonResponse
    {
        $payload = $request->validate([
            'payload' => ['required', 'string', 'max:512'],
        ])['payload'];

        $result = $service->validate($payload, $request->user());

        return response()->json($result);
    }
}
