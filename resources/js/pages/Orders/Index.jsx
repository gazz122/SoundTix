import { Link } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';
import OrderStatusBadge from '@/components/OrderStatusBadge';
import { formatDate, rupiah } from '@/lib/format';

export default function Index({ orders }) {
    return (
        <AppLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-slate-900">Tiket Saya</h1>
                <p className="mt-1 text-sm text-slate-500">Riwayat pesanan tiket Anda.</p>
            </div>

            {orders.length === 0 ? (
                <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm p-10 text-center">
                    <p className="text-sm font-medium text-slate-700">Belum ada pesanan.</p>
                    <Link
                        href="/"
                        className="mt-4 inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm"
                    >
                        Cari Event
                    </Link>
                </div>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    {orders.map((order) => {
                        const event = order.items[0]?.event;

                        return (
                            <Link
                                key={order.order_code}
                                href={`/orders/${order.order_code}`}
                                className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm hover:shadow-md hover:border-slate-300 transition-all p-5 block"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-mono text-xs text-slate-400">{order.order_code}</p>
                                        <h2 className="mt-1 font-semibold text-slate-900 truncate">
                                            {event?.title ?? 'Pesanan'}
                                        </h2>
                                        {event && (
                                            <p className="mt-0.5 text-sm text-slate-500">
                                                {formatDate(event.event_date)}
                                            </p>
                                        )}
                                    </div>
                                    <OrderStatusBadge status={order.status} />
                                </div>

                                <div className="mt-4 flex items-end justify-between gap-3 border-t border-slate-200/80 pt-3">
                                    <p className="text-sm text-slate-500">
                                        {order.items.length} kategori ·{' '}
                                        {order.items.reduce((sum, item) => sum + item.quantity, 0)} tiket
                                    </p>
                                    <p className="font-semibold text-slate-900">{rupiah(order.grand_total)}</p>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            )}
        </AppLayout>
    );
}