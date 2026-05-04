<?php

namespace App\Http\Controllers;

use App\Services\MediaService;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(
        private MediaService $mediaService
    ) {}

    /**
     * Body must include: { "confirm": "DELETE_ALL_MEDIA" }
     */
    public function destroyAll(Request $request)
    {
        $request->validate([
            'confirm' => 'required|string|in:DELETE_ALL_MEDIA',
        ]);

        return response()->json($this->mediaService->deleteAllMedia());
    }
}
