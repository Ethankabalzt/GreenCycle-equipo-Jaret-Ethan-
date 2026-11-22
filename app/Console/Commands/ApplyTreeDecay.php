<?php

namespace App\Console\Commands;

use App\Models\Tree;
use Illuminate\Console\Command;

class ApplyTreeDecay extends Command
{
    protected $signature = 'trees:apply-decay';

    protected $description = 'Aplica el deterioro pendiente a los árboles activos sin protector.';

    public function handle(): int
    {
        $interval = Tree::DECAY_INTERVAL_HOURS;
        $affected = 0;

        Tree::query()
            ->where('status', Tree::ACTIVE)
            ->chunkById(100, function ($trees) use ($interval, &$affected) {
                foreach ($trees as $tree) {
                    if ($tree->hasActiveProtector()) {
                        // El tiempo protegido no acumula deterioro.
                        $tree->last_decay_at = now();
                        $tree->save();

                        continue;
                    }

                    $anchor = $tree->last_decay_at ?? $tree->planted_at ?? now();
                    $hours = (int) $anchor->diffInHours(now(), true);
                    $periods = intdiv($hours, $interval);

                    if ($periods < 1) {
                        continue;
                    }

                    $tree->health = max(0, $tree->health - Tree::DECAY_HEALTH * $periods);
                    $tree->last_decay_at = $anchor->copy()->addHours($periods * $interval);

                    if ($tree->health === 0) {
                        $tree->status = Tree::DEAD;
                        $tree->next_care_at = null;
                    }

                    $tree->save();
                    $affected++;
                }
            });

        $this->info("Deterioro aplicado a {$affected} árbol(es).");

        return self::SUCCESS;
    }
}
