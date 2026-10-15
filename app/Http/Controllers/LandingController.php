<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LandingController extends Controller
{
    public function staff(): View
    {
        return view('staff.landing');
    }

    public function driver(): View
    {
        return view('driver.landing');
    }
}
