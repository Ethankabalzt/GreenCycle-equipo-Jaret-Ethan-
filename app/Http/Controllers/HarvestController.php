<?php

namespace App\Http\Controllers;

use App\Http\Resources\TreeResource;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class HarvestController extends Controller
{
    public function __invoke(Tree $tree): JsonResponse
    {
        Gate::authorize('harvest', $tree);

        return DB::transaction(function () use ($tree) {
            $tree = Tree::with('seedType')
                ->lockForUpdate()
                ->findOrFail($tree->id);

            if ($tree->status !== Tree::MATURE || $tree->harvested_at !== null) {
                return response()->json([
                    'error' => 'El árbol no está listo para cosechar.',
                ], 409);
            }

            $user = User::lockForUpdate()->findOrFail($tree->user_id);
            $reward = $tree->seedType->harvest_coins;

            $tree->status = Tree::HARVESTED;
            $tree->harvested_at = now();
            $tree->next_care_at = null;
            $tree->save();

            $user->coins += $reward;
            $user->save();

            return response()->json([
                'message' => 'Cosecha completada con éxito',
                'reward' => $reward,
                'coins' => $user->coins,
                'tree' => (new TreeResource($tree))->resolve(),
            ], 200);
        });
    }
}
