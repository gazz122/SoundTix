<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\FaqCategory;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(): Response
    {
        $categories = FaqCategory::query()
            ->orderBy('order')
            ->with(['publishedFaqs' => fn ($query) => $query->orderBy('order')->orderBy('id')])
            ->get()
            ->filter(fn (FaqCategory $category) => $category->publishedFaqs->isNotEmpty())
            ->values()
            ->map(fn (FaqCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'faqs' => $category->publishedFaqs->map(fn (Faq $faq) => [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category_name' => $category->name,
                ])->all(),
            ])
            ->all();

        return Inertia::render('FAQ', [
            'categories' => $categories,
        ]);
    }
}
