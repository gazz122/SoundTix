<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'genre', 'image_path'])]
class Performer extends Model
{
    use HasFactory;

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_performers');
    }
}
