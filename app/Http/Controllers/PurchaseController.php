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
            $idempotencyKey = $request->validated('idempotency_key');

            if ($idempotencyKey !== null) {
                $existing = Purchase::where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    $inventory = InventoryItem::with('shopItem')
                        ->where('user_id', $user->id)
                        ->where('shop_item_id', $existing->shop_item_id)
                        ->first();

                    return response()->json([
                        'message' => 'La compra ya había sido procesada.',
                        'coins' => $user->coins,
                        'purchase' => [
                            'id' => $existing->id,
                            'quantity' => $existing->quantity,
                            'total_cost' => $existing->total_cost,
                            'purchased_at' => $existing->purchased_at->utc()->toIso8601ZuluString(),
                        ],
                        'inventory' => $inventory
                            ? (new InventoryItemResource($inventory))->resolve()
                            : null,
                    ], 200);
                }
            }

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
                'idempotency_key' => $idempotencyKey,
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
