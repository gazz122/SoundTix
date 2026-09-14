<?php

namespace App\Http\Controllers;

use App\Services\TicketValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GatekeeperScannerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Gatekeeper/Scanner');
    }

    public function validate(Request $request, TicketValidationService $service): RedirectResponse
    {
        $payload = $request->validate([
            'payload' => ['required', 'string', 'max:512'],
        ])['payload'];

        $result = $service->validate($payload, $request->user());

        return redirect()->back()->with('scan_result', $result);
    }
}
