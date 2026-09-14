<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organizer_id',
    'title',
    'slug',
    'description',
    'banner_path',
    'venue_name',
    'city',
    'address',
    'event_date',
    'start_time',
    'end_time',
    'status',
    'rejection_reason',
])]
class Event extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function performers(): BelongsToMany
    {
        return $this->belongsToMany(Performer::class, 'event_performers');
    }

    public function ticketCategories(): HasMany
    {
        return $this->hasMany(TicketCategory::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('event_date', '>=', now()->subDay()->toDateString());
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilterable(Builder $query, array $filters = []): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $query, string $q) {
                $query->where(function (Builder $query) use ($q) {
                    $pattern = '%'.$q.'%';
                    $query->whereLike('title', $pattern)
                        ->orWhereLike('city', $pattern)
                        ->orWhereLike('venue_name', $pattern);
                });
            })
            ->when($filters['city'] ?? null, fn (Builder $query, string $city) => $query->where('city', $city))
            ->when($filters['date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('event_date', $date))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query
                ->whereHas('ticketCategories', fn (Builder $q) => $q->where('name', $category)));
    }
}
