import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/components/AppLayout';
import { rupiah } from '@/lib/format';

export default function Categories({ event, event_days, categories }) {
    const { flash } = usePage().props;
    const [editing, setEditing] = useState(null);
    const showingForm = editing !== null;

    return (
        <AppLayout>
            <div className="mb-8">
                <Link
                    href="/organizer/events"
                    className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                >
                    ← Kembali ke Event Saya
                </Link>
                <h1 className="mt-2 text-2xl font-semibold text-slate-900">Kategori Tiket</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Kelola harga, stok, dan batas pemesanan tiket untuk <span className="font-medium">{event.title}</span>
                    {' '}({event.city} · {event.event_date}).
                </p>
            </div>

            {flash.success && (
                <div className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700">
                    {flash.success}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-4">
                    {categories.length === 0 ? (
                        <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm p-10 text-center">
                            <p className="text-sm font-medium text-slate-700">Belum ada kategori tiket.</p>
                            <p className="mt-1 text-sm text-slate-500">Tambahkan kategori pertama Anda.</p>
                        </div>
                    ) : (
                        categories.map((category) => (
                            <CategoryCard
                                key={category.id}
                                category={category}
                                onEdit={() => setEditing(category)}
                                onDelete={() => {
                                    if (window.confirm(`Hapus kategori "${category.name}"?`)) {
                                        router.delete(
                                            `/organizer/events/${event.id}/categories/${category.id}`,
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            />
                        ))
                    )}
                </div>

                <div>
                    {showingForm ? (
                        <CategoryForm
                            key={editing.id}
                            event={event}
                            event_days={event_days}
                            category={editing}
                            onCancel={() => setEditing(null)}
                        />
                    ) : (
                        <CategoryForm event={event} event_days={event_days} />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function CategoryCard({ category, onEdit, onDelete }) {
    return (
        <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <h3 className="font-semibold text-slate-900">{category.name}</h3>
                        {category.access_type === 'SINGLE_DAY' ? (
                            <span className="rounded-full bg-cyan-50 px-2 py-0.5 text-[11px] font-medium text-cyan-700 border border-cyan-100">
                                {category.event_day_label ?? '1 Hari'}
                            </span>
                        ) : (
                            <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 border border-indigo-100">
                                Semua Hari
                            </span>
                        )}
                    </div>
                    {category.description && <p className="mt-0.5 text-sm text-slate-500">{category.description}</p>}
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        onClick={onEdit}
                        className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-xs font-medium px-3 py-1.5 rounded-lg transition-all"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        onClick={onDelete}
                        className="bg-white hover:bg-rose-50 text-rose-600 border border-rose-300 text-xs font-medium px-3 py-1.5 rounded-lg transition-all"
                    >
                        Hapus
                    </button>
                </div>
            </div>

            <div className="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                <Metric label="Harga" value={rupiah(category.price)} />
                <Metric label="Total Stok" value={category.total_quantity.toLocaleString('id-ID')} />
                <Metric label="Terjual" value={category.sold.toLocaleString('id-ID')} />
                <Metric label="Sisa" value={category.available_quantity.toLocaleString('id-ID')} />
                <div className="col-span-2 sm:col-span-2">
                    <Metric label="Maks. per Order" value={`${category.max_per_order} tiket`} />
                </div>
            </div>

            <div className="mt-4">
                <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div
                        className={`h-full rounded-full transition-all ${category.sold_out ? 'bg-rose-500' : 'bg-indigo-600'}`}
                        style={{
                            width: `${category.total_quantity > 0
                                ? Math.min(100, Math.round((category.sold / category.total_quantity) * 100))
                                : 0}%`,
                        }}
                    />
                </div>
                <p className="mt-1.5 text-xs text-slate-400">
                    {category.sold_out
                        ? 'Habis terjual'
                        : `${Math.round((category.sold / category.total_quantity) * 100)}% terisi`}
                </p>
            </div>
        </div>
    );
}

function Metric({ label, value }) {
    return (
        <div>
            <p className="text-xs text-slate-400">{label}</p>
            <p className="font-medium text-slate-800 tabular-nums">{value}</p>
        </div>
    );
}

function CategoryForm({ event, event_days = [], category, onCancel }) {
    const editing = Boolean(category);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: category?.name ?? '',
        description: category?.description ?? '',
        price: category?.price ?? '',
        total_quantity: category?.total_quantity ?? '',
        max_per_order: category?.max_per_order ?? 5,
        access_type: category?.access_type ?? 'ALL_DAYS',
        event_day_id: category?.event_day_id ?? '',
    });

    function submit(formEvent) {
        formEvent.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => !editing && reset() };
        if (editing) {
            put(`/organizer/events/${event.id}/categories/${category.id}`, options);
        } else {
            post(`/organizer/events/${event.id}/categories`, options);
        }
    }

    return (
        <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-5">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="font-semibold text-slate-900">{editing ? 'Edit Kategori' : 'Tambah Kategori'}</h2>
                {onCancel && (
                    <button
                        type="button"
                        onClick={onCancel}
                        className="text-sm text-slate-500 hover:text-slate-700"
                    >
                        Tutup
                    </button>
                )}
            </div>

            <form onSubmit={submit} className="space-y-4">
                <Field label="Nama Kategori" value={data.name} onChange={(value) => setData('name', value)} error={errors.name} placeholder="cth: VIP" />
                <label className="block space-y-1.5">
                    <span className="block text-sm font-medium text-slate-700">Deskripsi</span>
                    <textarea
                        value={data.description}
                        onChange={(formChange) => setData('description', formChange.target.value)}
                        rows={2}
                        placeholder="Fasilitas atau ketentuan area"
                        className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                    />
                    {errors.description && <p className="text-sm text-rose-600">{errors.description}</p>}
                </label>

                <div className="space-y-2">
                    <p className="text-sm font-medium text-slate-700">Tipe Akses</p>
                    {[
                        { value: 'ALL_DAYS', label: 'Semua Hari', description: 'Berlaku di seluruh hari event (mis. 3-Day Pass)' },
                        { value: 'SINGLE_DAY', label: 'Satu Hari', description: 'Tiket hanya berlaku pada satu hari tertentu' },
                    ].map((option) => (
                        <label
                            key={option.value}
                            className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-all ${
                                data.access_type === option.value
                                    ? 'border-indigo-600 bg-indigo-50/60 ring-1 ring-indigo-600'
                                    : 'border-slate-300 bg-white hover:border-slate-400'
                            }`}
                        >
                            <input
                                type="radio"
                                name="access_type"
                                value={option.value}
                                checked={data.access_type === option.value}
                                onChange={() => setData('access_type', option.value)}
                                className="mt-1 h-4 w-4 accent-indigo-600"
                            />
                            <span>
                                <span className="block text-sm font-medium text-slate-800">{option.label}</span>
                                <span className="block text-xs text-slate-500">{option.description}</span>
                            </span>
                        </label>
                    ))}
                    {errors.access_type && <p className="text-sm text-rose-600">{errors.access_type}</p>}
                </div>

                {data.access_type === 'SINGLE_DAY' && (
                    <FieldSelect
                        label="Hari Berlaku"
                        value={data.event_day_id}
                        onChange={(value) => setData('event_day_id', value)}
                        error={errors.event_day_id}
                        options={event_days.map((day) => ({
                            value: day.id,
                            label: `Day ${day.day_number} · ${day.date}`,
                        }))}
                        placeholder={event_days.length === 0 ? 'Belum ada jadwal hari' : 'Pilih hari'}
                    />
                )}

                <div className="grid grid-cols-2 gap-3">
                    <Field label="Harga (Rp)" type="number" min="0" step="1000" value={data.price} onChange={(value) => setData('price', value)} error={errors.price} placeholder="150000" />
                    <Field label="Total Stok" type="number" min="1" value={data.total_quantity} onChange={(value) => setData('total_quantity', value)} error={errors.total_quantity} placeholder="100" hint="Stok dapat disesuaikan tanpa mengubah jumlah terjual" />
                    <Field label="Maks. Per Order" type="number" min="1" max="5" value={data.max_per_order} onChange={(value) => setData('max_per_order', value)} error={errors.max_per_order} placeholder="5" hint="Maksimal 5 tiket per transaksi" />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm disabled:opacity-60"
                >
                    {processing ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Tambah Kategori'}
                </button>
            </form>
        </div>
    );
}

function FieldSelect({ label, value, onChange, error, options, placeholder }) {
    return (
        <label className="block">
            <span className="block text-sm font-medium text-slate-700">{label}</span>
            <select
                value={value}
                onChange={(formChange) => onChange(formChange.target.value)}
                className="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
            >
                <option value="">{placeholder}</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error && <p className="text-sm text-rose-600">{error}</p>}
        </label>
    );
}

function Field({ label, value, onChange, error, min, max, step, type = 'text', placeholder, hint }) {
    return (
        <label className="block">
            <span className="block text-sm font-medium text-slate-700">{label}</span>
            <input
                type={type}
                min={min}
                max={max}
                step={step}
                value={value}
                onChange={(formChange) => onChange(formChange.target.value)}
                placeholder={placeholder}
                className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
            />
            {hint && <span className="mt-1 block text-xs text-slate-400">{hint}</span>}
            {error && <p className="text-sm text-rose-600">{error}</p>}
        </label>
    );
}