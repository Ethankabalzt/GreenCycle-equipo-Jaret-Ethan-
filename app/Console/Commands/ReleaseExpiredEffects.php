<?php

namespace App\Console\Commands;

use App\Models\ActiveEffect;
use Illuminate\Console\Command;

class ReleaseExpiredEffects extends Command
{
    protected $signature = 'effects:release-expired';

    protected $description = 'Desactiva los efectos cuya duración ya terminó.';

    public function handle(): int
    {
        $released = ActiveEffect::query()
            ->where('active_flag', true)
            ->where('expires_at', '<=', now())
            ->update(['active_flag' => false]);

        $this->info("Efectos liberados: {$released}.");

        return self::SUCCESS;
    }
}
