<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'genre', 'image_path'])]
class Performer extends Model
{
    use HasFactory;

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_performers');
    }

    public function rundowns(): HasMany
    {
        return $this->hasMany(EventRundown::class);
    }
}
