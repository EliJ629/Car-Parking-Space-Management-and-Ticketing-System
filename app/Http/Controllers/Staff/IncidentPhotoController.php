<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\IncidentPhoto;
use App\Services\IncidentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentPhotoController extends Controller
{
    public function show(IncidentPhoto $photo, IncidentService $incidents): StreamedResponse
    {
        return $incidents->photoResponse($photo);
    }
}
