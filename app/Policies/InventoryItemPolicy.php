<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function consume(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->id === $inventoryItem->user_id;
    }
}
