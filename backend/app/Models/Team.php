<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['nombre', 'category_id', 'temporada'])]
class Team extends Model
{
    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Si el equipo tiene publicaciones o partidos, no se puede borrar
     * sin perder la clasificación de ese material.
     */
    public function estaEnUso(): bool
    {
        return $this->posts()->exists()
            || DB::table('matches')->where('team_id', $this->id)->exists();
    }
}
