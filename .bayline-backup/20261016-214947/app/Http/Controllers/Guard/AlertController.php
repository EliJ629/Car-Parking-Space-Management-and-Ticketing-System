<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Services\GuardAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /** Polled by the console to refresh the Alerts button and show a toast for a new alert. */
    public function index(GuardAlerts $alerts): JsonResponse
    {
        $items = $alerts->items();

        return response()->json(['count' => $alerts->count($items), 'items' => $items->map(fn (array $i) => collect($i)->except('sort'))->values()]);
    }

    public function readAll(Request $request, GuardAlerts $alerts): JsonResponse|RedirectResponse
    {
        $alerts->markRead();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
