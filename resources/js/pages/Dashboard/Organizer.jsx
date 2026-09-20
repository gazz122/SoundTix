import { Link } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';
import StatCard from '@/components/ui/StatCard';

export default function Organizer({ stats }) {
    const last7 = stats.sales_by_day.slice(-7);
    const maxRevenue = Math.max(...last7.map((d) => d.revenue), 1);

    return (
        <AppLayout>
            <Header name="Dashboard Event Organizer" description="Ringkasan event, tiket, dan pendapatan Anda." />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Total Event" value={stats.total_events} hint="Semua status" />
                <StatCard label="Event Published" value={stats.published_events} hint="Sedang tayang" />
                <StatCard label="Menunggu Persetujuan" value={stats.pending_events} hint="Status PENDING" />
                <StatCard label="Pendapatan" value={rupiah(stats.revenue)} hint="Dari pesanan PAID" />
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <Panel title="Tren Penjualan 7 Hari Terakhir">
                        {last7.length === 0 ? (
                            <p className="py-6 text-center text-sm text-slate-400">Belum ada data penjualan.</p>
                        ) : (
                            <div className="flex items-end gap-2 pt-4" style={{ height: 160 }}>
                                {last7.map((d) => {
                                    const h = Math.round((d.revenue / maxRevenue) * 120) + 4;
                                    return (
                                        <div key={d.date} className="flex flex-1 flex-col items-center gap-1">
                                            <span className="text-[10px] tabular-nums text-slate-500">
                                                {rupiahShort(d.revenue)}
                                            </span>
                                            <div
                                                className="w-full rounded-t bg-indigo-500/80 transition-all"
                                                style={{ height: h }}
                                                title={`${d.date}\n${rupiah(d.revenue)} (${d.orders} pesanan)`}
                                            />
                                            <span className="text-[10px] text-slate-400">
                                                {new Date(d.date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </Panel>
                </div>

                <Panel title="Kategori Terlaris">
                    {stats.top_categories.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-400">Belum ada data.</p>
                    ) : (
                        <ul className="divide-y divide-slate-100 pt-2">
                            {stats.top_categories.map((c, i) => (
                                <li key={i} className="flex items-start justify-between gap-2 py-2">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-slate-800">{c.event_title}</p>
                                        <p className="truncate text-xs text-slate-500">{c.category_name}</p>
                                    </div>
                                    <div className="text-right whitespace-nowrap">
                                        <p className="text-sm font-semibold text-slate-800">{c.quantity} tiket</p>
                                        <p className="text-xs text-slate-500">{rupiah(c.revenue)}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>

            <Panel title="Isi Kuota Tiket">
                {stats.quota_fill.length === 0 ? (
                    <p className="py-6 text-center text-sm text-slate-400">Belum ada kategori tiket.</p>
                ) : (
                    <div className="space-y-3 pt-2">
                        {stats.quota_fill.map((q, i) => (
                            <div key={i}>
                                <div className="flex items-center justify-between text-xs">
                                    <span className="truncate font-medium text-slate-700">
                                        {q.event_title} — {q.category_name}
                                    </span>
                                    <span className="ml-2 shrink-0 text-slate-500">
                                        {q.sold}/{q.total} ({q.percentage}%)
                                    </span>
                                </div>
                                <div className="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        className="h-full rounded-full bg-indigo-500"
                                        style={{ width: `${Math.min(q.percentage, 100)}%` }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                <div className="mt-4 text-right">
                    <Link
                        href="/organizer/reports"
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                    >
                        Lihat Laporan Lengkap &rarr;
                    </Link>
                </div>
            </Panel>
        </AppLayout>
    );
}

function Header({ name, description }) {
    return (
        <div className="mb-8">
            <h1 className="text-2xl font-semibold text-slate-900">{name}</h1>
            <p className="mt-1 text-sm text-slate-500">{description}</p>
        </div>
    );
}

function Panel({ title, children }) {
    return (
        <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-6">
            {title && <h2 className="mb-4 text-sm font-semibold text-slate-900">{title}</h2>}
            {children}
        </div>
    );
}

function rupiah(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(
        Number(value) || 0,
    );
}

function rupiahShort(value) {
    const n = Number(value) || 0;
    if (n >= 1_000_000_000) return (n / 1_000_000_000).toFixed(1) + 'M';
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'jt';
    if (n >= 1_000) return (n / 1_000).toFixed(0) + 'rb';
    return n.toLocaleString('id-ID');
}