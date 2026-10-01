<?php

namespace App\Models;

use Database\Factories\FaqCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FaqCategory extends Model
{
    /** @use HasFactory<FaqCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'order',
    ];

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class, 'category_id')
            ->orderBy('order')
            ->orderBy('id');
    }

    public function publishedFaqs(): HasMany
    {
        return $this->faqs()->where('is_published', true);
    }

    public static function makeSlug(string $name): string
    {
        $slug = Str::slug($name);

        $count = static::query()
            ->where('slug', 'like', $slug.'%')
            ->count();

        return $count > 0 ? "{$slug}-{$count}" : $slug;
    }
}
