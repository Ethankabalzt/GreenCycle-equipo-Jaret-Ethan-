<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\V1\UseInventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Http\Resources\TreeResource;
use App\Models\ActiveEffect;
use App\Models\InventoryItem;
use App\Models\ShopItem;
use App\Models\Tree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $items = request()->user()
            ->inventoryItems()
            ->with('shopItem')
            ->where('quantity', '>', 0)
            ->get();

        return InventoryItemResource::collection($items);
    }

    public function useItem(UseInventoryItemRequest $request, InventoryItem $inventoryItem): JsonResponse
    {
        Gate::authorize('consume', $inventoryItem);

        return DB::transaction(function () use ($request, $inventoryItem) {
            $inventory = InventoryItem::with('shopItem')
                ->lockForUpdate()
                ->findOrFail($inventoryItem->id);

            $tree = Tree::with('seedType')
                ->lockForUpdate()
                ->findOrFail($request->validated('tree_id'));

            Gate::authorize('view', $tree);

            if ($inventory->quantity < 1) {
                return response()->json(['error' => 'No te quedan unidades de este ítem.'], 409);
            }

            if ($tree->status !== Tree::ACTIVE) {
                return response()->json([
                    'error' => 'Solo se pueden aplicar mejoras a árboles activos.',
                ], 409);
            }

            $failure = match ($inventory->shopItem->type) {
                ShopItem::ACCELERATOR => $this->applyAccelerator($tree, $inventory->shopItem),
                ShopItem::FERTILIZER => $this->applyFertilizer($tree, $inventory->shopItem),
                ShopItem::PROTECTOR => $this->applyProtector($tree, $inventory->shopItem),
                default => response()->json(['error' => 'El ítem no tiene un efecto configurado.'], 409),
            };

            if ($failure instanceof JsonResponse) {
                return $failure;
            }

            $inventory->quantity--;
            if ($inventory->quantity <= 0) {
                $inventory->delete();
            } else {
                $inventory->save();
            }

            return response()->json([
                'message' => 'Ítem aplicado con éxito',
                'tree' => (new TreeResource($tree->load('seedType')))->resolve(),
                'inventory' => $inventory->exists
                    ? (new InventoryItemResource($inventory))->resolve()
                    : null,
            ], 200);
        });
    }

    /**
     * Reduce el cooldown pendiente del árbol.
     */
    private function applyAccelerator(Tree $tree, ShopItem $item): ?JsonResponse
    {
        if (! $tree->next_care_at?->isFuture()) {
            return response()->json([
                'error' => 'El árbol ya está listo para recibir cuidados.',
            ], 409);
        }

        $minutes = $item->effect_value ?? Tree::COOLDOWN_MINUTES;
        $nextCare = $tree->next_care_at->copy()->subMinutes($minutes);

        $tree->next_care_at = $nextCare->isFuture() ? $nextCare : null;
        $tree->save();

        return null;
    }

    /**
     * Suma progreso y aplica las subidas de nivel correspondientes.
     */
    private function applyFertilizer(Tree $tree, ShopItem $item): ?JsonResponse
    {
        $gained = $item->effect_value ?? 1;
        $caresByLevel = max(1, $tree->seedType->cares_by_level);

        $tree->progress += $gained;

        while ($tree->progress >= $caresByLevel && $tree->level < Tree::MAX_LEVEL) {
            $tree->progress -= $caresByLevel;
            $tree->level++;
        }

        if ($tree->level >= Tree::MAX_LEVEL) {
            $tree->level = Tree::MAX_LEVEL;
            $tree->progress = 0;
            $tree->status = Tree::MATURE;
            $tree->next_care_at = null;
        }

        $tree->save();

        return null;
    }

    /**
     * Activa un protector contra el deterioro durante su duración.
     */
    private function applyProtector(Tree $tree, ShopItem $item): ?JsonResponse
    {
        if ($tree->hasActiveProtector()) {
            return response()->json([
                'error' => 'El árbol ya tiene un protector activo.',
            ], 409);
        }

        $now = now();

        // Un solo protector vigente por árbol.
        $tree->activeEffects()
            ->where('active_flag', true)
            ->update(['active_flag' => false]);

        ActiveEffect::create([
            'tree_id' => $tree->id,
            'shop_item_id' => $item->id,
            'activated_at' => $now,
            'expires_at' => $now->copy()->addMinutes($item->effect_duration_min ?? 1440),
            'active_flag' => true,
        ]);

        return null;
    }
}
