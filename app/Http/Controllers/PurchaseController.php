<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\V1\PurchaseRequest;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\ShopItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function store(PurchaseRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $item = ShopItem::findOrFail($request->validated('shop_item_id'));
            $quantity = (int) $request->validated('quantity');
            $total = $item->cost * $quantity;

            if ($user->coins < $total) {
                return response()->json([
                    'error' => 'No tienes monedas suficientes para esta compra.',
                    'coins' => $user->coins,
                    'required' => $total,
                ], 409);
            }

            $user->coins -= $total;
            $user->save();

            $inventory = InventoryItem::firstOrNew([
                'user_id' => $user->id,
                'shop_item_id' => $item->id,
            ]);
            $inventory->quantity = ($inventory->quantity ?? 0) + $quantity;
            $inventory->save();

            $purchase = Purchase::create([
                'user_id' => $user->id,
                'shop_item_id' => $item->id,
                'quantity' => $quantity,
                'total_cost' => $total,
                'purchased_at' => now(),
            ]);

            return response()->json([
                'message' => 'Compra realizada con éxito',
                'coins' => $user->coins,
                'purchase' => [
                    'id' => $purchase->id,
                    'quantity' => $purchase->quantity,
                    'total_cost' => $purchase->total_cost,
                    'purchased_at' => $purchase->purchased_at->utc()->toIso8601ZuluString(),
                ],
                'inventory' => (new InventoryItemResource($inventory->load('shopItem')))->resolve(),
            ], 201);
        });
    }
}
