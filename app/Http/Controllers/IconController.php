<?php

namespace App\Http\Controllers;

use App\Services\IconService;

class IconController extends Controller
{
    public function __construct(
        protected IconService $iconService,
    ) {}

    public function index()
    {
        return response()->json($this->iconService->listActive());
    }
}
