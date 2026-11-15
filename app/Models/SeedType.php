<?php

namespace App\Models;

use Database\Factories\SeedTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeedType extends Model
{
    /** @use HasFactory<SeedTypeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['name', 'cares_by_level', 'harvest_coins', 'description'];

    public function trees(): HasMany
    {
        return $this->hasMany(Tree::class);
    }
}
