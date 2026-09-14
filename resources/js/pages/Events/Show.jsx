import { Link, useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import PublicLayout from '@/components/PublicLayout';
import { formatDate, rupiah } from '@/lib/format';

const MAX_TICKETS_PER_ORDER = 5;

export default function Show({ event }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const isCustomer = user?.role === 'customer';
    const hasTickets = event.ticket_categories.length > 0;

    return (
        <PublicLayout>
            <section className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
                <div className="h-40 bg-slate-100 sm:h-48">
                    {event.banner_url ? (
                        <img
                            src={event.banner_url}
                            alt={`Poster ${event.title}`}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="h-full flex items-end p-6">
                            <span className="text-sm font-medium text-slate-500">{formatDate(event.event_date)}</span>
                        </div>
                    )}
                </div>

                <div className="p-6 sm:p-8">
                    <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                        <div>
                            <h1 className="text-2xl sm:text-3xl font-semibold text-slate-900">{event.title}</h1>
                            <p className="mt-2 text-slate-500">
                                {event.city} · {event.venue_name}
                            </p>
                            <p className="mt-1 text-sm text-slate-400">
                                {formatDate(event.event_date)} · {event.start_time} – {event.end_time || 'selesai'}
                                <span className="mx-2">·</span>
                                {event.address}
                            </p>
                            <p className="text-sm text-slate-400">
                                Dipromosikan oleh{' '}
                                <span className="font-medium text-slate-600">{event.organizer.name}</span>
                            </p>
                        </div>

                        {hasTickets && (
                            <TicketCta user={user} />
                        )}
                    </div>

                    <div className="mt-8 prose-sm max-w-none whitespace-pre-line text-slate-600">{event.description}</div>

                    <div className="mt-8">
                        <h2 className="text-lg font-semibold text-slate-900">Line-up Pengisi Acara</h2>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {event.performers.map((performer) => (
                                <div
                                    key={performer.id}
                                    className="bg-slate-50 border border-slate-200/80 rounded-xl p-4"
                                >
                                    <p className="font-medium text-slate-900">{performer.name}</p>
                                    <span className="mt-1 inline-block bg-white border border-slate-200 text-xs px-2.5 py-0.5 rounded-md font-medium text-slate-600">
                                        {performer.genre}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="mt-8" id="order">
                        <h2 className="text-lg font-semibold text-slate-900">Kategori Tiket</h2>
                        {isCustomer ? (
                            <TicketOrder event={event} />
                        ) : (
                            <TicketTable categories={event.ticket_categories} />
                        )}
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

function TicketCta({ user }) {
    if (user?.role === 'customer') {
        return (
            <a
                href="#order"
                className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-8 rounded-lg shadow-sm transition-all inline-block text-center"
            >
                Pesan Tiket
            </a>
        );
    }

    if (!user) {
        return (
            <Link
                href="/login"
                className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-8 rounded-lg shadow-sm transition-all inline-block text-center"
            >
                Masuk untuk Memesan
            </Link>
        );
    }

    return null;
}

function TicketOrder({ event }) {
    const categories = event.ticket_categories;

    const { data, setData, post, processing, errors, transform } = useForm({
        items: categories.map((category) => ({ category_id: category.id, quantity: 0 })),
    });

    transform((formData) => ({
        items: formData.items
            .filter((item) => Number(item.quantity) > 0)
            .map((item) => ({ category_id: item.category_id, quantity: Number(item.quantity) })),
    }));

    const totalQuantity = useMemo(
        () => data.items.reduce((sum, item) => sum + Number(item.quantity), 0),
        [data.items],
    );

    const grandTotal = useMemo(
        () =>
            data.items.reduce((sum, item) => {
                const category = categories.find((entry) => entry.id === item.category_id);
                return sum + (category?.price ?? 0) * Number(item.quantity);
            }, 0),
        [data.items, categories],
    );

    const overLimit = totalQuantity > MAX_TICKETS_PER_ORDER;

    function changeQuantity(categoryId, next) {
        const category = categories.find((entry) => entry.id === categoryId);
        const cap = Math.min(category.max_per_order, category.available_quantity);

        setData(
            'items',
            data.items.map((item) =>
                item.category_id === categoryId
                    ? { ...item, quantity: Math.max(0, Math.min(next, cap)) }
                    : item,
            ),
        );
    }

    function submit(eventSubmit) {
        eventSubmit.preventDefault();
        post(`/events/${event.slug}/order`, { preserveScroll: true });
    }

    const submitDisabled = processing || totalQuantity === 0 || overLimit;

    return (
        <form onSubmit={submit} className="mt-3 grid gap-4 lg:grid-cols-3">
            <div className="lg:col-span-2 rounded-xl border border-slate-200/80 overflow-hidden">
                <div className="divide-y divide-slate-200/80 bg-white">
                    {categories.map((category, index) => {
                        const cap = Math.min(category.max_per_order, category.available_quantity);
                        const quantity = data.items.find((item) => item.category_id === category.id)?.quantity ?? 0;

                        return (
                            <div key={category.id} className="px-4 py-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-medium text-slate-900">{category.name}</p>
                                        <p className="text-xs text-slate-400">
                                            {category.available_quantity === 0
                                                ? 'Habis terjual'
                                                : `Sisa ${category.available_quantity} · maks. ${category.max_per_order} per transaksi`}
                                        </p>
                                    </div>
                                    <p className="text-indigo-600 font-semibold">{rupiah(category.price)}</p>
                                    <Stepper
                                        value={Number(quantity)}
                                        disabled={category.available_quantity === 0}
                                        onChange={(next) => changeQuantity(category.id, next)}
                                        cap={cap}
                                    />
                                </div>
                                <p className="mt-1 text-sm text-rose-600">
                                    {errors[`items.${index}.quantity`]}
                                </p>
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm p-5 h-fit">
                <h3 className="font-semibold text-slate-900">Ringkasan Pesanan</h3>
                <ul className="mt-3 space-y-2 text-sm">
                    {data.items.map((item) => {
                        const category = categories.find((entry) => entry.id === item.category_id);
                        if (!category || Number(item.quantity) === 0) return null;

                        return (
                            <li key={item.category_id} className="flex items-center justify-between gap-2 text-slate-600">
                                <span>
                                    {category.name} × {item.quantity}
                                </span>
                                <span className="text-slate-900 font-medium">
                                    {rupiah(category.price * Number(item.quantity))}
                                </span>
                            </li>
                        );
                    })}
                </ul>

                <div className="mt-4 flex items-center justify-between border-t border-slate-200/80 pt-3">
                    <span className="text-sm font-medium text-slate-700">Total</span>
                    <span className="text-xl font-semibold text-slate-900">{rupiah(grandTotal)}</span>
                </div>

                {overLimit && (
                    <p className="mt-2 text-sm text-rose-600">Maksimal 5 tiket per transaksi.</p>
                )}
                {totalQuantity === 0 && (
                    <p className="mt-2 text-sm text-slate-400">Pilih minimal satu tiket untuk memesan.</p>
                )}
                {errors.items && <p className="mt-2 text-sm text-rose-600">{errors.items}</p>}

                <button
                    type="submit"
                    disabled={submitDisabled}
                    className="mt-4 w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    {processing ? 'Memproses...' : 'Pesan Sekarang'}
                </button>
                <p className="mt-2 text-xs text-slate-400">
                    Pesanan berlaku 15 menit. Batas 5 tiket per transaksi.
                </p>
            </div>
        </form>
    );
}

function Stepper({ value, onChange, cap, disabled }) {
    return (
        <div className="flex items-center gap-2">
            <button
                type="button"
                onClick={() => onChange(value - 1)}
                disabled={disabled || value === 0}
                className="h-8 w-8 rounded-lg border border-slate-300 bg-white text-slate-700 font-medium hover:bg-slate-100 disabled:opacity-40 transition-all"
            >
                −
            </button>
            <span className="w-8 text-center font-medium text-slate-900">{value}</span>
            <button
                type="button"
                onClick={() => onChange(value + 1)}
                disabled={disabled || value >= cap}
                className="h-8 w-8 rounded-lg border border-slate-300 bg-white text-slate-700 font-medium hover:bg-slate-100 disabled:opacity-40 transition-all"
            >
                +
            </button>
        </div>
    );
}

function TicketTable({ categories }) {
    return (
        <div className="mt-3 rounded-xl border border-slate-200/80 overflow-hidden">
            <table className="w-full text-sm">
                <thead className="bg-slate-50 text-slate-600">
                    <tr>
                        <th className="text-left px-4 py-3 font-medium">Kategori</th>
                        <th className="text-left px-4 py-3 font-medium hidden sm:table-cell">Keterangan</th>
                        <th className="text-right px-4 py-3 font-medium">Harga</th>
                        <th className="text-right px-4 py-3 font-medium">Stok</th>
                    </tr>
                </thead>
                <tbody className="bg-white divide-y divide-slate-200/80">
                    {categories.map((category) => (
                        <tr key={category.id}>
                            <td className="px-4 py-3 font-medium text-slate-900">{category.name}</td>
                            <td className="px-4 py-3 text-slate-500 hidden sm:table-cell">{category.description}</td>
                            <td className="px-4 py-3 text-right text-indigo-600 font-semibold">
                                {rupiah(category.price)}
                            </td>
                            <td className="px-4 py-3 text-right">
                                {category.available_quantity === 0 ? (
                                    <span className="bg-slate-100 text-slate-500 border border-slate-200 text-xs px-2.5 py-1 rounded-md font-medium">
                                        Habis
                                    </span>
                                ) : (
                                    <span className="text-slate-500 text-xs">
                                        {category.available_quantity} sisa
                                    </span>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}