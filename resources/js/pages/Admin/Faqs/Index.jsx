import { router, useForm, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/components/AppLayout';

const ICON_OPTIONS = ['', 'Ticket', 'ShieldCheck', 'QrCode', 'CreditCard', 'CalendarCheck'];

export default function Index({ categories }) {
    const { flash } = usePage().props;
    const [categoryModal, setCategoryModal] = useState(null);
    const [faqModal, setFaqModal] = useState(null);

    const showAddCategory = () => setCategoryModal({ category: null });
    const showEditCategory = (category) => setCategoryModal({ category });
    const showAddFaq = (categoryId) => setFaqModal({ faq: null, defaultCategoryId: categoryId });
    const showEditFaq = (faq) => setFaqModal({ faq, defaultCategoryId: faq.category_id });

    function moveCategory(index, direction) {
        const next = swap(categories, index, direction);
        router.post('/admin/faqs/categories/reorder', { ids: next.map((category) => category.id) }, { preserveScroll: true });
    }

    function moveFaq(category, index, direction) {
        const next = swap(category.faqs, index, direction);
        router.post('/admin/faqs/reorder', { category_id: category.id, ids: next.map((faq) => faq.id) }, { preserveScroll: true });
    }

    function removeCategory(category) {
        if (window.confirm(`Hapus kategori "${category.name}" beserta ${category.faqs.length} pertanyaannya?`)) {
            router.delete(`/admin/faqs/categories/${category.id}`, { preserveScroll: true });
        }
    }

    function removeFaq(faq) {
        if (window.confirm(`Hapus pertanyaan "${faq.question}"?`)) {
            router.delete(`/admin/faqs/${faq.id}`, { preserveScroll: true });
        }
    }

    function togglePublish(faq) {
        router.post(`/admin/faqs/${faq.id}/toggle`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-slate-900">Kelola FAQ</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Atur kategori dan pertanyaan yang tampil di halaman Pusat Bantuan (FAQ).
                </p>
            </div>

            {flash.success && <Flash type="success">{flash.success}</Flash>}
            {flash.error && <Flash type="error">{flash.error}</Flash>}

            <div className="flex flex-wrap gap-3">
                <button
                    type="button"
                    onClick={showAddCategory}
                    className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-indigo-700"
                >
                    <Plus aria-hidden="true" className="h-4 w-4" />
                    Tambah Kategori
                </button>
                {categories.length > 0 && (
                    <button
                        type="button"
                        onClick={showAddFaq}
                        className="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-100"
                    >
                        <Plus aria-hidden="true" className="h-4 w-4" />
                        Tambah Pertanyaan
                    </button>
                )}
            </div>

            {categories.length === 0 ? (
                <div className="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                    <h2 className="text-lg font-semibold text-slate-900">Belum ada kategori FAQ</h2>
                    <p className="mx-auto mt-1 max-w-sm text-sm leading-6 text-slate-500">
                        Tambahkan kategori pertama, lalu isi dengan pertanyaan yang sering diajukan.
                    </p>
                </div>
            ) : (
                <div className="mt-6 flex flex-col gap-6">
                    {categories.map((category, categoryIndex) => (
                        <section
                            key={category.id}
                            className="rounded-xl border border-slate-200/80 bg-white shadow-sm"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                                <div className="flex min-w-0 items-center gap-3">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                        {category.icon ? category.icon.slice(0, 2) : 'KB'}
                                    </span>
                                    <div className="min-w-0">
                                        <h2 className="truncate font-semibold text-slate-900">{category.name}</h2>
                                        <p className="text-xs text-slate-500">
                                            {category.faqs.length} pertanyaan · {category.faqs.filter((faq) => faq.is_published).length} tampil
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        onClick={() => showAddFaq(category.id)}
                                        className="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-2 text-xs font-medium text-indigo-600 transition-colors hover:bg-indigo-100"
                                        title="Tambah pertanyaan di kategori ini"
                                    >
                                        <Plus aria-hidden="true" className="h-4 w-4" />
                                        Pertanyaan
                                    </button>
                                    <MoveButton
                                        direction="up"
                                        disabled={categoryIndex === 0}
                                        onClick={() => moveCategory(categoryIndex, -1)}
                                        label="Naikkan kategori"
                                    />
                                    <MoveButton
                                        direction="down"
                                        disabled={categoryIndex === categories.length - 1}
                                        onClick={() => moveCategory(categoryIndex, 1)}
                                        label="Turunkan kategori"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => showEditCategory(category)}
                                        className="rounded-lg border border-slate-200 p-2 text-slate-500 transition-colors hover:bg-slate-50 hover:text-indigo-600"
                                        aria-label={`Edit kategori ${category.name}`}
                                        title="Edit kategori"
                                    >
                                        <Pencil aria-hidden="true" className="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => removeCategory(category)}
                                        className="rounded-lg border border-slate-200 p-2 text-slate-500 transition-colors hover:bg-rose-50 hover:text-rose-600"
                                        aria-label={`Hapus kategori ${category.name}`}
                                        title="Hapus kategori"
                                    >
                                        <Trash2 aria-hidden="true" className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div className="divide-y divide-slate-100">
                                {category.faqs.length === 0 ? (
                                    <p className="px-5 py-6 text-center text-sm text-slate-500">
                                        Belum ada pertanyaan di kategori ini. Gunakan tombol "Pertanyaan" di atas.
                                    </p>
                                ) : (
                                    category.faqs.map((faq, faqIndex) => (
                                        <div
                                            key={faq.id}
                                            className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                <MoveButton
                                                    direction="up"
                                                    disabled={faqIndex === 0}
                                                    onClick={() => moveFaq(category, faqIndex, -1)}
                                                    label="Naikkan pertanyaan"
                                                />
                                                <MoveButton
                                                    direction="down"
                                                    disabled={faqIndex === category.faqs.length - 1}
                                                    onClick={() => moveFaq(category, faqIndex, 1)}
                                                    label="Turunkan pertanyaan"
                                                />
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-slate-800" title={faq.question}>
                                                        {faq.question}
                                                    </p>
                                                    <p className="truncate text-xs text-slate-500" title={faq.answer}>
                                                        {faq.answer}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <PublishBadge published={faq.is_published} />
                                                <button
                                                    type="button"
                                                    onClick={() => togglePublish(faq)}
                                                    className="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
                                                >
                                                    {faq.is_published ? 'Sembunyikan' : 'Terbitkan'}
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => showEditFaq(faq)}
                                                    className="rounded-lg border border-slate-200 p-2 text-slate-500 transition-colors hover:bg-slate-50 hover:text-indigo-600"
                                                    aria-label={`Edit pertanyaan ${faq.question}`}
                                                    title="Edit pertanyaan"
                                                >
                                                    <Pencil aria-hidden="true" className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => removeFaq(faq)}
                                                    className="rounded-lg border border-slate-200 p-2 text-slate-500 transition-colors hover:bg-rose-50 hover:text-rose-600"
                                                    aria-label={`Hapus pertanyaan ${faq.question}`}
                                                    title="Hapus pertanyaan"
                                                >
                                                    <Trash2 aria-hidden="true" className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>
                    ))}
                </div>
            )}

            {categoryModal && (
                <CategoryModal
                    categories={categories}
                    category={categoryModal.category}
                    onClose={() => setCategoryModal(null)}
                />
            )}

            {faqModal && (
                <FaqModal
                    categories={categories}
                    faq={faqModal.faq}
                    defaultCategoryId={faqModal.defaultCategoryId}
                    onClose={() => setFaqModal(null)}
                />
            )}
        </AppLayout>
    );
}

function MoveButton({ direction, disabled, onClick, label }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className="rounded-lg border border-slate-200 p-2 text-slate-500 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            aria-label={label}
            title={label}
        >
            {direction === 'up' ? (
                <ChevronUp aria-hidden="true" className="h-4 w-4" />
            ) : (
                <ChevronDown aria-hidden="true" className="h-4 w-4" />
            )}
        </button>
    );
}

function PublishBadge({ published }) {
    return published ? (
        <span className="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
            Tampil
        </span>
    ) : (
        <span className="rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
            Tersembunyi
        </span>
    );
}

function CategoryModal({ categories, category, onClose }) {
    const isEdit = category !== null;
    const form = useForm({
        name: category?.name ?? '',
        icon: category?.icon ?? '',
    });

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.put(`/admin/faqs/categories/${category.id}`);
        } else {
            form.post('/admin/faqs/categories');
        }
    }

    return (
        <Modal title={isEdit ? 'Edit Kategori' : 'Tambah Kategori'} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Nama Kategori" error={form.errors.name}>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        className={inputClass(!!form.errors.name)}
                        placeholder="Contoh: Pembelian Tiket"
                    />
                </Field>
                <Field label="Ikon (nama Lucide)" error={form.errors.icon}>
                    <select
                        value={form.data.icon}
                        onChange={(event) => form.setData('icon', event.target.value)}
                        className={inputClass(!!form.errors.icon)}
                    >
                        {ICON_OPTIONS.map((icon) => (
                            <option key={icon} value={icon}>
                                {icon === '' ? 'Tanpa ikon' : icon}
                            </option>
                        ))}
                    </select>
                </Field>
                <p className="text-xs text-slate-500">
                    Slug dibuat otomatis dari nama. Urutan tampil dapat diatur dengan tombol atas/bawah.
                </p>
                <ModalActions onClose={onClose} processing={form.processing} submitLabel={isEdit ? 'Simpan Perubahan' : 'Tambah Kategori'} />
            </form>
        </Modal>
    );
}

function FaqModal({ categories, faq, defaultCategoryId, onClose }) {
    const isEdit = faq !== null;
    const form = useForm({
        category_id: faq?.category_id ?? defaultCategoryId ?? categories[0]?.id ?? '',
        question: faq?.question ?? '',
        answer: faq?.answer ?? '',
        is_published: faq?.is_published ?? true,
    });

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.put(`/admin/faqs/${faq.id}`);
        } else {
            form.post('/admin/faqs');
        }
    }

    return (
        <Modal title={isEdit ? 'Edit Pertanyaan' : 'Tambah Pertanyaan'} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Kategori" error={form.errors.category_id}>
                    <select
                        value={form.data.category_id}
                        onChange={(event) => form.setData('category_id', event.target.value)}
                        className={inputClass(!!form.errors.category_id)}
                    >
                        {categories.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Pertanyaan" error={form.errors.question}>
                    <input
                        type="text"
                        value={form.data.question}
                        onChange={(event) => form.setData('question', event.target.value)}
                        className={inputClass(!!form.errors.question)}
                        placeholder="Contoh: Bagaimana cara membeli tiket?"
                    />
                </Field>
                <Field label="Jawaban" error={form.errors.answer}>
                    <textarea
                        rows={5}
                        value={form.data.answer}
                        onChange={(event) => form.setData('answer', event.target.value)}
                        className={inputClass(!!form.errors.answer)}
                        placeholder="Tulis jawaban yang jelas dan mudah dipahami..."
                    />
                </Field>
                <Field label="Status Publikasi">
                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={form.data.is_published}
                            onChange={(event) => form.setData('is_published', event.target.checked)}
                            className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600"
                        />
                        Tampilkan di halaman publik
                    </label>
                </Field>
                <ModalActions onClose={onClose} processing={form.processing} submitLabel={isEdit ? 'Simpan Perubahan' : 'Tambah Pertanyaan'} />
            </form>
        </Modal>
    );
}

function Modal({ title, onClose, children }) {
    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
            onClick={onClose}
            role="dialog"
            aria-modal="true"
            aria-label={title}
        >
            <div
                className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl"
                onClick={(event) => event.stopPropagation()}
            >
                <div className="mb-5 flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Tutup"
                    >
                        <X aria-hidden="true" className="h-5 w-5" />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

function ModalActions({ onClose, processing, submitLabel }) {
    return (
        <div className="flex justify-end gap-3 pt-2">
            <button
                type="button"
                onClick={onClose}
                className="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
            >
                Batal
            </button>
            <button
                type="submit"
                disabled={processing}
                className="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-indigo-700 disabled:opacity-50"
            >
                {processing ? 'Menyimpan...' : submitLabel}
            </button>
        </div>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="block">
            <span className="text-sm font-medium text-slate-700">{label}</span>
            <div className="mt-1.5">{children}</div>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </label>
    );
}

function inputClass(hasError) {
    return [
        'w-full bg-white border text-slate-900 rounded-lg px-3 py-2',
        'focus:outline-none focus:ring-1 transition-all',
        hasError
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600',
    ].join(' ');
}

function swap(list, index, direction) {
    const next = [...list];
    const target = index + direction;
    if (target < 0 || target >= next.length) return next;
    [next[index], next[target]] = [next[target], next[index]];
    return next;
}

function Flash({ type, children }) {
    const styles = {
        success: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
        error: 'border-rose-200 bg-rose-50/60 text-rose-700',
    };
    return <div className={`mb-6 rounded-lg border px-4 py-3 text-sm ${styles[type]}`}>{children}</div>;
}