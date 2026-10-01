import { useEffect, useMemo, useRef, useState } from 'react';
import { formatDate, rupiah } from '@/lib/format';

export default function CartDrawer({
    open,
    onClose,
    event,
    items = [],
    serviceFeeRate = 0,
    minutes = 10,
    processing = false,
    onCheckout,
    onExpire,
}) {
    const [secondsLeft, setSecondsLeft] = useState(minutes * 60);
    const expiredRef = useRef(false);

    useEffect(() => {
        if (!open) return;

        setSecondsLeft(minutes * 60);
        expiredRef.current = false;

        const timer = setInterval(() => {
            setSecondsLeft((seconds) => Math.max(0, seconds - 1));
        }, 1000);

        return () => clearInterval(timer);
    }, [open, minutes]);

    const expired = secondsLeft === 0;

    useEffect(() => {
        if (!open || !expired || expiredRef.current) return;
        expiredRef.current = true;
        onExpire?.();
    }, [open, expired, onExpire]);

    useEffect(() => {
        if (!open) return;

        function handleKeyDown(event) {
            if (event.key === 'Escape') onClose?.();
        }

        window.addEventListener('keydown', handleKeyDown);
        document.body.style.overflow = 'hidden';

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    const subtotal = useMemo(
        () => items.reduce((sum, item) => sum + Number(item.price) * Number(item.quantity), 0),
        [items],
    );
    const serviceFee = useMemo(() => Math.round(subtotal * Number(serviceFeeRate || 0)), [subtotal, serviceFeeRate]);
    const total = subtotal + serviceFee;
    const totalQuantity = items.reduce((sum, item) => sum + Number(item.quantity), 0);

    const canCheckout = totalQuantity > 0 && !expired && !processing;

    return (
        <>
            <div
                onClick={onClose}
                aria-hidden="true"
                className={`fixed inset-0 z-40 bg-slate-950/20 backdrop-blur-md transition-opacity duration-300 ${
                    open ? 'opacity-100' : 'pointer-events-none opacity-0'
                }`}
            />

            <aside
                role="dialog"
                aria-modal="true"
                aria-label="Keranjang Tiket"
                aria-hidden={!open}
                className="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border border-slate-200/80 bg-slate-50/95 shadow-xl shadow-slate-900/10 backdrop-blur-xl transition-[transform,visibility] duration-300 ease-out"
                style={{
                    transform: open ? 'translateX(0)' : 'translateX(100%)',
                    visibility: open ? 'visible' : 'hidden',
                }}
            >
                <header className="flex items-center justify-between gap-3 border-b border-slate-200/80 px-6 py-4">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">Keranjang Tiket</h2>
                        <p className="text-xs text-slate-400">{formatDate(event?.event_date)}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <TimerBadge secondsLeft={secondsLeft} expired={expired} />
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label="Tutup keranjang"
                            className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
                        >
                            <svg aria-hidden="true" className="h-4 w-4" fill="none" stroke="currentColor" strokeLinecap="round" strokeWidth={2} viewBox="0 0 24 24">
                                <path d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </div>
                </header>

                <div className="flex-1 overflow-y-auto px-6 py-5">
                    <EventSummary event={event} />

                    {totalQuantity === 0 ? (
                        <EmptyState />
                    ) : (
                        <ul className="mt-5 space-y-3">
                            {items
                                .filter((item) => Number(item.quantity) > 0)
                                .map((item) => (
                                    <li
                                        key={item.category_id}
                                        className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="font-medium text-slate-900">{item.name}</p>
                                                <p className="mt-0.5 text-sm text-slate-500">
                                                    {rupiah(item.price)} x {item.quantity}
                                                </p>
                                            </div>
                                            <p className="shrink-0 font-semibold text-slate-900">
                                                {rupiah(Number(item.price) * Number(item.quantity))}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                        </ul>
                    )}
                </div>

                <footer className="border-t border-slate-200/80 px-6 py-5">
                    {expired && (
                        <p className="mb-3 rounded-lg border border-rose-200 bg-rose-50/60 px-4 py-2.5 text-sm text-rose-700">
                            Waktu pemesanan telah habis. Silakan pilih kembali tiket Anda.
                        </p>
                    )}

                    <dl className="space-y-2 text-sm">
                        <div className="flex items-center justify-between text-slate-600">
                            <dt>Subtotal</dt>
                            <dd className="tabular-nums">{rupiah(subtotal)}</dd>
                        </div>
                        <div className="flex items-center justify-between text-slate-600">
                            <dt>Biaya Layanan</dt>
                            <dd className="tabular-nums">{rupiah(serviceFee)}</dd>
                        </div>
                        <div className="flex items-center justify-between border-t border-slate-200/80 pt-3 text-base font-semibold text-slate-900">
                            <dt>Total Pembayaran</dt>
                            <dd className="tabular-nums">{rupiah(total)}</dd>
                        </div>
                    </dl>

                    <button
                        type="button"
                        onClick={() => onCheckout?.({
                            items: items
                                .filter((item) => Number(item.quantity) > 0)
                                .map((item) => ({
                                    category_id: item.category_id,
                                    quantity: Number(item.quantity),
                                })),
                        })}
                        disabled={!canCheckout}
                        className="mt-5 w-full bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98] text-white font-medium py-3 px-5 rounded-xl transition-all duration-200 shadow-sm disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {processing ? 'Memproses...' : 'Lanjut ke Pembayaran'}
                    </button>

                    <div className="mt-3 flex items-center justify-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50/70 px-3 py-2">
                        <ShieldCheckIcon />
                        <span className="text-xs font-medium text-emerald-800">
                            Garansi aman - transaksi terenkripsi
                        </span>
                    </div>
                </footer>
            </aside>
        </>
    );
}

function ShieldCheckIcon() {
    return (
        <svg
            aria-hidden="true"
            className="h-4 w-4 shrink-0 text-emerald-600"
            fill="none"
            stroke="currentColor"
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            viewBox="0 0 24 24"
        >
            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
            <path d="m9 12 2 2 4-4" />
        </svg>
    );
}

function EventSummary({ event }) {
    if (!event) return null;

    return (
        <div className="flex items-center gap-4 rounded-xl border border-indigo-100 bg-indigo-50/60 p-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-lg font-bold text-white">
                {event.title.charAt(0)}
            </div>
            <div className="min-w-0">
                <p className="truncate font-semibold text-slate-900">{event.title}</p>
                <p className="truncate text-sm text-slate-500">
                    {event.city} · {event.venue_name}
                </p>
            </div>
        </div>
    );
}

function TimerBadge({ secondsLeft, expired }) {
    if (expired) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-semibold tabular-nums text-rose-700">
                <svg aria-hidden="true" className="h-4 w-4" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} viewBox="0 0 24 24">
                    <circle cx="12" cy="13" r="8" />
                    <path d="M12 9v4l2 2M9 2h6" />
                </svg>
                Waktu habis
            </span>
        );
    }

    const minutes = Math.floor(secondsLeft / 60);
    const seconds = secondsLeft % 60;

    return (
        <span className="inline-flex animate-pulse items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-semibold tabular-nums text-amber-700">
            <svg aria-hidden="true" className="h-4 w-4" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} viewBox="0 0 24 24">
                <circle cx="12" cy="13" r="8" />
                <path d="M12 9v4l2 2M9 2h6" />
            </svg>
            {String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}
        </span>
    );
}

function EmptyState() {
    return (
        <div className="mt-5 rounded-xl border border-slate-200/80 bg-slate-50 p-10 text-center">
            <p className="text-sm font-medium text-slate-700">Keranjang Anda masih kosong.</p>
            <p className="mt-1 text-sm text-slate-500">Pilih kategori tiket untuk mulai memesan.</p>
        </div>
    );
}