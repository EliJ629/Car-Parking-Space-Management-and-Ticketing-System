<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

abstract class ManagerController extends Controller
{
    protected function managerPage(string $title, string $activePage, View $content): View
    {
        return view()->file(resource_path('views/layouts/manager.dashboard.php'), [
            'title' => $title,
            'activePage' => $activePage,
            'content' => new HtmlString($content->render()),
        ]);
    }
}
