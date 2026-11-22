<?php

namespace App\Http\Controllers;

use App\Http\Resources\SeedTypeResource;
use App\Models\SeedType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SeedTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SeedTypeResource::collection(
            SeedType::orderBy('cares_by_level')->get()
        );
    }
}
