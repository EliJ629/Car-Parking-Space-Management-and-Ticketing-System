<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Services\DriverData;
use App\Services\FeeCalculator;
use App\Services\Sweeper;
use App\Support\Lot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function home(Request $request, DriverData $data, FeeCalculator $fees, Sweeper $sweeper): View
    {
        try {
            $sweeper->run();
        } catch (\Throwable $e) {
            report($e);
        }

        $user = $request->user();
        $session = $data->openSession($user);
        $hour = (int) Lot::now()->format('G');

        return view('driver.app.home', [
            'user' => $user,
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'),
            'hasVehicle' => $user->vehicles()->exists(),
            'session' => $session,
            'estimate' => $session && $session->status === 'active' ? $fees->estimate($session) : null,
            'reservation' => $data->upcomingReservation($user),
            'lot' => $data->lot(),
        ]);
    }
}
