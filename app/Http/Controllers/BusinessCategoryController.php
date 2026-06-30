<?php

namespace App\Http\Controllers;

use App\Services\BusinessCategoryService;
use Illuminate\Http\Request;

class BusinessCategoryController extends Controller
{
    public function __construct(
        protected BusinessCategoryService $businessCategoryService,
    ) {}

    public function index(Request $request)
    {
        return response()->json(
            $this->businessCategoryService->listPublic($request->query('type'))
        );
    }
}
