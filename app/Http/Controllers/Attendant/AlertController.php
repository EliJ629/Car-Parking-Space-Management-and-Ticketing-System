<?php

namespace App\Http\Controllers\Attendant;

use App\Http\Controllers\Controller;
use App\Services\AttendantAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /** Polled by the attendant console to refresh the Alerts button and show toasts. */
    public function index(AttendantAlerts $alerts): JsonResponse
    {
        $items = $alerts->items();

        return response()->json([
            'count' => $alerts->count($items),
            'items' => $items->map(fn (array $i) => collect($i)->except('sort'))->values(),
        ]);
    }

    public function readAll(Request $request, AttendantAlerts $alerts): JsonResponse|RedirectResponse
    {
        $alerts->markRead();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
