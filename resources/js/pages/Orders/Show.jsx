import { Link, router, usePage } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { useEffect, useState } from 'react';
import AppLayout from '@/components/AppLayout';
import OrderStatusBadge from '@/components/OrderStatusBadge';
import { formatDate, rupiah } from '@/lib/format';

export default function Show({ order }) {
    const { flash } = usePage().props;
    const pending = order.status === 'pending';
    const paid = order.status === 'paid';
    const activePayment = order.payments.find((payment) => payment.status === 'pending');

    return (
        <AppLayout>
            <div className="mb-8">
                <Link href="/orders" className="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                    ← Kembali ke Tiket Saya
                </Link>
            </div>

            {flash.success && (
                <div className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="mb-6 rounded-lg border border-rose-200 bg-rose-50/60 px-4 py-3 text-sm text-rose-700">
                    {flash.error}
                </div>
            )}

            <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="font-mono text-sm text-slate-400">No. Pesanan</p>
                        <h1 className="text-2xl font-semibold text-slate-900">{order.order_code}</h1>
                        <p className="mt-1 text-sm text-slate-500">Dibuat {order.created_at}</p>
                    </div>
                    <OrderStatusBadge status={order.status} />
                </div>

                {pending && (
                    <div className="mt-6 rounded-lg border border-amber-200 bg-amber-50/60 px-4 py-3">
                        <p className="text-sm font-medium text-amber-700">
                            Segera selesaikan pembayaran.
                        </p>
                        <Countdown from={order.expires_at} />
                    </div>
                )}
            </div>

            {pending && (
                <div className="mt-6 bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-6">
                    <h2 className="font-semibold text-slate-900">Pembayaran</h2>

                    {activePayment ? (
                        <ActivePayment order={order} payment={activePayment} />
                    ) : (
                        <div>
                            <p className="mt-1 text-sm text-slate-500">
                                Pilih metode pembayaran untuk melanjutkan (simulasi mock gateway).
                            </p>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                <MethodButton label="Transfer Bank" hint="Simulasi transfer manual" onClick={() => selectMethod(order, 'bank_transfer')} />
                                <MethodButton label="QRIS" hint="Simulasi scan QR" onClick={() => selectMethod(order, 'qris')} />
                            </div>
                        </div>
                    )}
                </div>
            )}

            {paid && (
                <div className="mt-6 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3">
                    <p className="text-sm font-medium text-emerald-700">
                        Pembayaran lunas {order.paid_at ? `pada ${order.paid_at}` : ''}.
                    </p>
                    <p className="mt-1 text-sm text-emerald-600">
                        {order.tickets.length} e-tiket telah diterbitkan.
                    </p>
                </div>
            )}

            {order.tickets.length > 0 && (
                <div className="mt-8">
                    <h2 className="mb-4 font-semibold text-slate-900">E-Tiket</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {order.tickets.map((ticket) => (
                            <div
                                key={ticket.id}
                                className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm"
                            >
                                <div className="border-b border-slate-200/80 px-5 py-4 flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-mono text-xs text-slate-400">{ticket.ticket_code}</p>
                                        <h3 className="mt-0.5 font-semibold text-slate-900">{ticket.event_title}</h3>
                                        <p className="mt-0.5 text-sm text-slate-500">
                                            {ticket.category} · {formatDate(ticket.event_date)} · {order.order_code}
                                        </p>
                                    </div>
                                    {ticket.status === 'active' && (
                                        <span className="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs px-2.5 py-1 rounded-md font-medium">
                                            Aktif
                                        </span>
                                    )}
                                </div>
                                <div className="px-5 py-5 flex items-center gap-5">
                                    <QRCodeSVG value={ticket.qr_payload} size={112} level="M" className="rounded-lg" />
                                    <p className="text-sm text-slate-500">
                                        Tunjukkan QR ini kepada petugas saat masuk venue untuk validasi.
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="mt-6 bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm">
                <div className="divide-y divide-slate-200/80">
                    {order.items.map((item) => (
                        <div key={item.id} className="px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-medium text-slate-900">{item.category}</p>
                                {item.event && (
                                    <Link href={`/events/${item.event.slug}`} className="text-sm text-indigo-600 hover:text-indigo-700">
                                        {item.event.title} · {formatDate(item.event.event_date)}
                                    </Link>
                                )}
                            </div>
                            <div className="text-right">
                                <p className="text-sm text-slate-500">
                                    {rupiah(item.price_per_unit)} × {item.quantity}
                                </p>
                                <p className="font-semibold text-slate-900">{rupiah(item.subtotal)}</p>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="px-6 py-4 bg-slate-50 border-t border-slate-200/80 space-y-1 text-sm">
                    <Row label="Subtotal" value={rupiah(order.total_amount)} />
                    <Row label="Biaya Layanan" value={rupiah(order.admin_fee)} />
                    <div className="flex items-center justify-between pt-2 text-base font-semibold text-slate-900">
                        <span>Total</span>
                        <span>{rupiah(order.grand_total)}</span>
                    </div>
                </div>
            </div>

            {pending && (
                <div className="mt-6 flex justify-end">
                    <button
                        type="button"
                        onClick={() => {
                            if (window.confirm('Batalkan pesanan ini? Stok tiket akan dikembalikan.')) {
                                router.post(`/orders/${order.order_code}/cancel`, {}, { preserveScroll: true });
                            }
                        }}
                        className="bg-white hover:bg-rose-50 text-rose-600 border border-rose-300 font-medium py-2.5 px-5 rounded-lg transition-all"
                    >
                        Batalkan Pesanan
                    </button>
                </div>
            )}
        </AppLayout>
    );
}

function selectMethod(order, method) {
    router.post(`/orders/${order.order_code}/pay`, { method }, { preserveScroll: true });
}

function MethodButton({ label, hint, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="group text-left bg-white border border-slate-300 rounded-xl p-4 hover:border-indigo-600 hover:shadow-sm transition-all"
        >
            <p className="font-medium text-slate-900 group-hover:text-indigo-700">{label}</p>
            <p className="mt-0.5 text-sm text-slate-500">{hint}</p>
        </button>
    );
}

function ActivePayment({ order, payment }) {
    return (
        <div className="mt-4 rounded-xl border border-slate-200/80 divide-y divide-slate-200/80">
            <div className="px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm text-slate-500">{payment.method_label}</p>
                    <p className="font-mono text-sm text-slate-900">{payment.transaction_id}</p>
                </div>
                <p className="text-sm font-medium text-slate-700">Total {rupiah(payment.gross_amount)}</p>
            </div>

            <div className="px-4 py-3">
                <p className="text-sm text-amber-700">
                    Ini gateway mock: pilih hasil pembayaran untuk melanjutkan.
                </p>
                <div className="mt-3 flex flex-wrap gap-3">
                    <button
                        type="button"
                        onClick={() => router.post(`/payments/${payment.id}/settle`, {}, { preserveScroll: true })}
                        className="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm"
                    >
                        Simulasi: Bayar Berhasil
                    </button>
                    <button
                        type="button"
                        onClick={() => router.post(`/payments/${payment.id}/expire`, {}, { preserveScroll: true })}
                        className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2.5 px-5 rounded-lg transition-all"
                    >
                        Simulasi: Kadaluarsa
                    </button>
                </div>
            </div>
        </div>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex items-center justify-between text-slate-600">
            <span>{label}</span>
            <span>{value}</span>
        </div>
    );
}

function Countdown({ from }) {
    const [remaining, setRemaining] = useState(remainingSeconds(from));

    useEffect(() => {
        const timer = setInterval(() => setRemaining(remainingSeconds(from)), 1000);
        return () => clearInterval(timer);
    }, [from]);

    useEffect(() => {
        if (remaining === 0) {
            router.reload({ only: ['order'] });
        }
    }, [remaining]);

    if (remaining <= 0) {
        return <p className="mt-1 text-sm text-rose-600">Batas waktu pembayaran telah habis.</p>;
    }

    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;

    return (
        <p className="mt-1 text-sm text-amber-700">
            Sisa waktu: <span className="font-mono font-semibold">{String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}</span>
        </p>
    );
}

function remainingSeconds(expiresAtIso) {
    const diff = new Date(expiresAtIso).getTime() - Date.now();
    return Math.max(0, Math.floor(diff / 1000));
}