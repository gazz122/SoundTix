<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminFaqController extends Controller
{
    public function index(): Response
    {
        $categories = FaqCategory::query()
            ->orderBy('order')
            ->with(['faqs' => fn ($query) => $query->orderBy('order')->orderBy('id')])
            ->get()
            ->map(fn (FaqCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'order' => $category->order,
                'faqs' => $category->faqs->map(fn (Faq $faq) => [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'order' => $faq->order,
                    'is_published' => $faq->is_published,
                ])->all(),
            ])
            ->all();

        return Inertia::render('Admin/Faqs/Index', [
            'categories' => $categories,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'icon' => ['nullable', 'string', 'max:60'],
        ]);

        $order = (int) $request->input('order', FaqCategory::query()->max('order') + 1);

        FaqCategory::create([
            'name' => $data['name'],
            'slug' => $this->resolveSlug($data),
            'icon' => $data['icon'] ?: null,
            'order' => $order,
        ]);

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'Kategori FAQ berhasil ditambahkan.');
    }

    public function updateCategory(Request $request, FaqCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'icon' => ['nullable', 'string', 'max:60'],
        ]);

        $category->update([
            'name' => $data['name'],
            'slug' => $this->resolveSlug($data, $category),
            'icon' => $data['icon'] ?: null,
        ]);

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'Kategori FAQ berhasil diperbarui.');
    }

    public function destroyCategory(FaqCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'Kategori beserta pertanyaannya dihapus.');
    }

    public function reorderCategories(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array']])['ids'];

        foreach (array_values($ids) as $index => $id) {
            FaqCategory::whereKey($id)->update(['order' => $index]);
        }

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'Urutan kategori disimpan.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedFaq($request, null);

        Faq::create([...$data, 'order' => $this->nextOrder($data['category_id'])]);

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'Pertanyaan FAQ berhasil ditambahkan.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $data = $this->validatedFaq($request, $faq->id);

        $faq->update($data);

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'Pertanyaan FAQ berhasil diperbarui.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'Pertanyaan FAQ dihapus.');
    }

    public function toggle(Faq $faq): RedirectResponse
    {
        $faq->update(['is_published' => ! $faq->is_published]);

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', $faq->is_published ? 'FAQ dipublikasikan.' : 'FAQ disembunyikan.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:faq_categories,id'],
            'ids' => ['required', 'array'],
        ]);

        foreach (array_values($data['ids']) as $index => $id) {
            Faq::query()
                ->whereKey($id)
                ->where('category_id', $data['category_id'])
                ->update(['order' => $index]);
        }

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'Urutan pertanyaan disimpan.');
    }

    private function nextOrder(int $categoryId): int
    {
        return Faq::query()->where('category_id', $categoryId)->max('order') + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFaq(Request $request, ?int $ignoringId): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:faq_categories,id'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'is_published' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSlug(array $data, ?FaqCategory $category = null): string
    {
        $slug = trim($data['slug'] ?? '') ?: Str::slug($data['name']);

        $excluding = $category !== null ? $category->id : null;

        $query = FaqCategory::query()->where('slug', $slug);
        if ($excluding !== null) {
            $query->whereKeyNot($excluding);
        }

        if (! $query->exists()) {
            return $slug;
        }

        $index = 2;
        do {
            $candidate = "{$slug}-{$index}";
            $index++;
        } while (FaqCategory::query()->where('slug', $candidate)->exists());

        return $candidate;
    }
}
