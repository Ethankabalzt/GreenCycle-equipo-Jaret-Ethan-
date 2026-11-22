<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShopItemResource;
use App\Models\ShopItem;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShopItemController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ShopItemResource::collection(
            ShopItem::orderBy('cost')->get()
        );
    }
}
