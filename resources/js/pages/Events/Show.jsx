import { Link, router, useForm, usePage, usePoll } from '@inertiajs/react';
import { Bookmark } from 'lucide-react';
import { useMemo, useState } from 'react';
import CartDrawer from '@/components/CartDrawer';
import PublicLayout from '@/components/PublicLayout';
import { formatDate, rupiah } from '@/lib/format';

const MAX_TICKETS_PER_ORDER = 5;

function formatGate(value) {
    const [datePart, timePart] = value.split(' ');
    return `${formatDate(datePart)} ${timePart} WIB`;
}

export default function Show({ event }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const isCustomer = user?.role === 'customer';
    const hasTickets = event.ticket_categories.length > 0;
    const [shared, setShared] = useState(false);
    const [saved, setSaved] = useState(Boolean(event.is_saved));

    function toggleSave() {
        setSaved((current) => !current);
        if (saved) {
            router.delete(`/events/${event.slug}/save`, { preserveScroll: true });
        } else {
            router.post(`/events/${event.slug}/save`, {}, { preserveScroll: true });
        }
    }

    async function handleShare() {
        const payload = {
            title: event.title,
            text: `Jangan lupa ${event.title} di ${event.venue_name}!`,
            url: window.location.href,
        };
        try {
            if (navigator.share) {
                await navigator.share(payload);
                return;
            }
            await navigator.clipboard.writeText(window.location.href);
            setShared(true);
            setTimeout(() => setShared(false), 1500);
        } catch {
        }
    }

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
                                {event.duration_type === 'multi_day' && event.event_days?.length
                                    ? event.event_days.map((day) => `Day ${day.day_number} · ${formatDate(day.date)}`).join(' · ')
                                    : formatDate(event.event_date)}
                                {' · Acara mulai '}{event.start_time}{event.end_time ? ` - ${event.end_time}` : ''}
                                <span className="mx-2">·</span>
                                {event.address}
                            </p>
                            {event.duration_type === 'multi_day' ? (
                                event.event_days?.some((day) => day.open_gate || day.close_gate) && (
                                    <div className="mt-2 space-y-1 text-sm font-medium text-indigo-600">
                                        {event.event_days.map((day) => (day.open_gate || day.close_gate) && (
                                            <p key={day.day_number}>
                                                Day {day.day_number} · {formatDate(day.date)}
                                                {day.open_gate && ` · Pintu buka ${formatGate(day.open_gate)}`}
                                                {day.close_gate && ` · tutup ${formatGate(day.close_gate)}`}
                                            </p>
                                        ))}
                                    </div>
                                )
                            ) : event.open_gate ? (
                                <p className="mt-1 text-sm font-medium text-indigo-600">
                                    Pintu dibuka {formatGate(event.open_gate)} · ditutup {formatGate(event.close_gate)}
                                </p>
                            ) : null}
                            <p className="text-sm text-slate-400">
                                Dipromosikan oleh{' '}
                                <span className="font-medium text-slate-600">{event.organizer.name}</span>
                            </p>
                        </div>

                        {hasTickets && (
                            <div className="flex items-start gap-3">
                                {user && (
                                    <button
                                        type="button"
                                        onClick={toggleSave}
                                        aria-label={saved ? `Batalkan simpan ${event.title}` : `Simpan ${event.title}`}
                                        aria-pressed={saved}
                                        className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border transition-colors ${
                                            saved
                                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-600'
                                        }`}
                                    >
                                        <Bookmark aria-hidden="true" className="h-5 w-5" fill={saved ? 'currentColor' : 'none'} />
                                    </button>
                                )}
                                <button type="button" onClick={handleShare} aria-label="Bagikan event" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-colors hover:bg-slate-50">
                                    {shared ? (
                                        <svg aria-hidden="true" className="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="m5 13 4 4L19 7" />
                                        </svg>
                                    ) : (
                                        <svg aria-hidden="true" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                        </svg>
                                    )}
                                </button>
                                <TicketCta user={user} />
                            </div>
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

                    {event.rundowns?.length > 0 && <RundownSection event={event} />}

                    <div className="mt-8 scroll-mt-40" id="order">
                        <h2 className="text-lg font-semibold text-slate-900">Kategori Tiket</h2>
                        {isCustomer ? (
                            <TicketOrder event={event} />
                        ) : (
                                <TicketTable categories={event.ticket_categories} event={event} />
                        )}
                    </div>
                </div>
            </section>

            {hasTickets && (
                <div
                    className={`fixed inset-x-0 z-30 border-t border-slate-200/80 bg-white/95 px-4 py-3 backdrop-blur-lg lg:hidden ${
                        user ? 'bottom-[4.5rem]' : 'bottom-0'
                    }`}
                    style={{ paddingBottom: user ? 'calc(env(safe-area-inset-bottom) + 0.75rem)' : 'env(safe-area-inset-bottom)' }}
                >
                    <div className="mx-auto flex max-w-md items-center justify-between gap-4">
                        <div>
                            <p className="text-sm text-slate-500">Mulai dari</p>
                            <p className="text-lg font-semibold tabular-nums text-slate-900">
                                {event.starting_price ? rupiah(event.starting_price) : 'Harga menyusul'}
                            </p>
                        </div>
                        <a
                            href="#order"
                            className="shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg shadow-sm transition-all"
                        >
                            Beli Tiket
                        </a>
                    </div>
                </div>
            )}
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
    const [drawerOpen, setDrawerOpen] = useState(false);
    const ticketGroups = useMemo(() => {
        const groups = new Map();

        categories.forEach((category, index) => {
            const day = category.event_day;
            const label = day
                ? `Day ${day.day_number}`
                : category.access_type === 'SINGLE_DAY'
                    ? 'Hari belum ditentukan'
                    : event.duration_type === 'multi_day' && category.access_type === 'ALL_DAYS'
                        ? 'Semua Hari'
                        : null;
            const key = label ?? 'tickets';

            if (!groups.has(key)) {
                groups.set(key, {
                    key,
                    label,
                    date: day
                        ? formatDate(day.date)
                        : label === 'Semua Hari'
                            ? event.event_days.map((eventDay) => formatDate(eventDay.date)).join(' · ')
                            : null,
                    dayNumber: day?.day_number ?? (label === 'Semua Hari' ? 0 : Number.MAX_SAFE_INTEGER),
                    categories: [],
                });
            }

            groups.get(key).categories.push({ category, index });
        });

        return [...groups.values()].sort((first, second) => first.dayNumber - second.dayNumber);
    }, [categories, event.duration_type, event.event_days]);

    const { data, setData, post, processing, errors, transform } = useForm({
        items: categories.map((category) => ({ category_id: category.id, quantity: 0 })),
    });

    transform((formData) => ({
        items: formData.items
            .filter((item) => Number(item.quantity) > 0)
            .map((item) => ({ category_id: item.category_id, quantity: Number(item.quantity) })),
    }));

    const selectedItems = useMemo(
        () =>
            data.items
                .filter((item) => Number(item.quantity) > 0)
                .map((item) => {
                    const category = categories.find((entry) => entry.id === item.category_id);
                    return category ? { ...category, quantity: Number(item.quantity) } : null;
                })
                .filter(Boolean),
        [data.items, categories],
    );

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
        eventSubmit?.preventDefault?.();
        post(`/events/${event.slug}/order`, { preserveScroll: true });
    }

    function openDrawer() {
        if (totalQuantity > 0 && !overLimit) {
            setDrawerOpen(true);
        }
    }

    return (
        <>
            <form onSubmit={submit} className="mt-3 grid gap-4 lg:grid-cols-3">
                <div className="lg:col-span-2 rounded-xl border border-slate-200/80 overflow-hidden">
                    <div className="divide-y divide-slate-200/80 bg-white">
                        {ticketGroups.map((group) => (
                            <div key={group.key}>
                                {group.label && (
                                    <h3 className="border-b border-slate-200/80 bg-slate-50 px-4 py-2.5">
                                        <span className="block text-sm font-semibold text-slate-800">{group.label}</span>
                                        {group.date && <span className="block text-xs text-slate-500">{group.date}</span>}
                                    </h3>
                                )}
                                {group.categories.map(({ category, index }) => {
                                    const cap = Math.min(category.max_per_order, category.available_quantity);
                                    const quantity = data.items.find((item) => item.category_id === category.id)?.quantity ?? 0;

                                    return (
                                        <div key={category.id} className="border-b border-slate-200/80 px-4 py-3 last:border-b-0">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="font-medium text-slate-900">{category.name}</p>
                                                    <p className="text-xs text-slate-400">
                                                        {category.available_quantity === 0
                                                            ? 'Habis terjual'
                                                            : `Sisa ${category.available_quantity} · maks. ${category.max_per_order} per transaksi`}
                                                    </p>
                                                </div>
                                                <p className="text-indigo-600 font-semibold tabular-nums">{rupiah(category.price)}</p>
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
                        ))}
                    </div>
                </div>

                <div className="bg-cyan-400 border border-cyan-400 rounded-xl shadow-sm p-5 h-fit">
                    <h3 className="font-semibold text-slate-900">Ringkasan Pesanan</h3>
                    <ul className="mt-3 space-y-2 text-sm">
                        {data.items.map((item) => {
                            const category = categories.find((entry) => entry.id === item.category_id);
                            if (!category || Number(item.quantity) === 0) return null;

                            return (
                                <li key={item.category_id} className="flex items-center justify-between gap-2 text-slate-700">
                                    <span>
                                        {category.name} x {item.quantity}
                                    </span>
                                    <span className="text-slate-900 font-medium tabular-nums">
                                        {rupiah(category.price * Number(item.quantity))}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>

                    <div className="mt-4 flex items-center justify-between border-t border-slate-300/80 pt-3">
                        <span className="text-sm font-medium text-slate-700">Total</span>
                        <span className="text-xl font-semibold text-slate-900 tabular-nums">{rupiah(grandTotal)}</span>
                    </div>

                    {overLimit && (
                        <p className="mt-2 text-sm text-rose-600">Maksimal 5 tiket per transaksi.</p>
                    )}
                    {totalQuantity === 0 && (
                        <p className="mt-2 text-sm text-slate-700">Pilih minimal satu tiket untuk memesan.</p>
                    )}
                    {errors.items && <p className="mt-2 text-sm text-rose-600">{errors.items}</p>}

                    <button
                        type="button"
                        onClick={openDrawer}
                        disabled={totalQuantity === 0 || overLimit}
                        className="mt-4 w-full bg-slate-900 hover:bg-slate-800 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        Lanjut ke Pembayaran
                    </button>
                    <p className="mt-2 text-xs text-slate-700">
                        Pesanan berlaku 15 menit. Batas 5 tiket per transaksi.
                    </p>
                </div>
            </form>

            <CartDrawer
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                event={event}
                items={selectedItems}
                processing={processing}
                onCheckout={submit}
                onExpire={() => setDrawerOpen(false)}
            />
        </>
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

function TicketTable({ categories, event }) {
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
                            <td className="px-4 py-3">
                                <p className="font-medium text-slate-900">{category.name}</p>
                                {category.event_day ? (
                                    <p className="mt-0.5 text-xs font-medium text-indigo-600">
                                        Day {category.event_day.day_number} · {formatDate(category.event_day.date)}
                                    </p>
                                ) : category.access_type === 'SINGLE_DAY' ? (
                                    <p className="mt-0.5 text-xs font-medium text-amber-700">Hari belum ditentukan</p>
                                ) : event.duration_type === 'multi_day' && category.access_type === 'ALL_DAYS' ? (
                                    <p className="mt-0.5 text-xs font-medium text-indigo-600">Semua Hari</p>
                                ) : null}
                            </td>
                            <td className="px-4 py-3 text-slate-500 hidden sm:table-cell">{category.description}</td>
                            <td className="px-4 py-3 text-right text-indigo-600 font-semibold tabular-nums">
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

const wibFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

function wibNow() {
    const parts = Object.fromEntries(wibFormatter.formatToParts(new Date()).map((part) => [part.type, part.value]));

    return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` };
}

function wibClock(iso) {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(new Date(iso));
}

function RundownPoller() {
    usePoll(30000, { only: ['event'], preserveScroll: true });

    return null;
}

function RundownSection({ event }) {
    const isMultiDay = event.duration_type === 'multi_day';
    const days = event.event_days ?? [];
    const now = wibNow();

    const groups = isMultiDay
        ? days.map((day) => ({
              key: day.id,
              date: day.date,
              label: `Day ${day.day_number} \u00b7 ${formatDate(day.date)}`,
              items: event.rundowns.filter((item) => item.event_day_id === day.id),
          }))
        : [{ key: 'single', date: event.event_date, label: null, items: event.rundowns }];

    const isEventDay = groups.some((group) => group.date === now.date);
    const lastUpdated = event.rundowns.reduce(
        (latest, item) => (item.updated_at && item.updated_at > latest ? item.updated_at : latest),
        '',
    );

    return (
        <div className="mt-8">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-lg font-semibold text-slate-900">Rundown Acara</h2>
                {lastUpdated && (
                    <p className="text-xs text-slate-400">Jadwal diperbarui {wibClock(lastUpdated)} WIB</p>
                )}
            </div>

            {isEventDay && <RundownPoller />}

            <div className="mt-3 space-y-6">
                {groups.map((group) => (
                    <div key={group.key}>
                        {group.label && <p className="mb-2 text-sm font-semibold text-indigo-600">{group.label}</p>}
                        <ol className="space-y-2">
                            {group.items.map((item) => {
                                const isLive =
                                    group.date === now.date &&
                                    item.start_time <= now.time &&
                                    (!item.end_time || now.time < item.end_time);

                                return (
                                    <li
                                        key={item.id}
                                        className={`flex gap-4 rounded-xl border p-4 ${
                                            isLive ? 'border-indigo-300 bg-indigo-50/60' : 'border-slate-200/80 bg-white'
                                        }`}
                                    >
                                        <div className="shrink-0 text-center">
                                            <p className="text-sm font-bold text-indigo-600">{item.start_time}</p>
                                            {item.end_time && <p className="text-xs text-slate-400">- {item.end_time}</p>}
                                        </div>
                                        <div className="min-w-0">
                                            {isLive && (
                                                <span className="mb-1 inline-block rounded-md bg-indigo-600 px-2 py-0.5 text-[11px] font-semibold text-white">
                                                    Sedang tampil
                                                </span>
                                            )}
                                            <p className="font-medium text-slate-900">
                                                {item.performer?.name ?? item.title}
                                            </p>
                                            {item.performer && item.title && (
                                                <p className="text-sm text-slate-600">{item.title}</p>
                                            )}
                                            {item.performer?.genre && (
                                                <span className="mt-1 inline-block rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-600">
                                                    {item.performer.genre}
                                                </span>
                                            )}
                                            {item.description && (
                                                <p className="mt-1 text-sm text-slate-500">{item.description}</p>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    </div>
                ))}
            </div>
        </div>
    );
}