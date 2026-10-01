import { Link } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';
import StatCard from '@/components/ui/StatCard';

export default function Organizer({ stats }) {
    const last7 = stats.sales_by_day.slice(-7);
    const maxRevenue = Math.max(...last7.map((dataPoint) => dataPoint.revenue), 1);

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
                            <div className="pt-4">
                                <svg
                                    viewBox="0 0 700 190"
                                    role="img"
                                    aria-label="Grafik garis penjualan 7 hari terakhir"
                                    className="h-auto w-full overflow-visible"
                                >
                                    {[0, 1, 2, 3].map((gridLine) => {
                                        const y = 130 - gridLine * 33;

                                        return (
                                            <line
                                                key={gridLine}
                                                x1="24"
                                                x2="676"
                                                y1={y}
                                                y2={y}
                                                className="stroke-slate-100"
                                                strokeWidth="1"
                                            />
                                        );
                                    })}
                                    <polyline
                                        points={last7
                                            .map((dataPoint, index) => {
                                                const x = last7.length === 1 ? 350 : 24 + index * (652 / (last7.length - 1));
                                                const y = 130 - (dataPoint.revenue / maxRevenue) * 100;

                                                return `${x},${y}`;
                                            })
                                            .join(' ')}
                                        fill="none"
                                        className="stroke-indigo-600"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="3"
                                    />
                                    {last7.map((dataPoint, index) => {
                                        const x = last7.length === 1 ? 350 : 24 + index * (652 / (last7.length - 1));
                                        const y = 130 - (dataPoint.revenue / maxRevenue) * 100;

                                        return (
                                            <g key={dataPoint.date}>
                                                <title>{`${dataPoint.date}\n${rupiah(dataPoint.revenue)} (${dataPoint.orders} pesanan)`}</title>
                                                <circle cx={x} cy={y} r="5" className="fill-white stroke-indigo-600" strokeWidth="3" />
                                                <text x={x} y="155" textAnchor="middle" className="fill-slate-400 text-[11px]">
                                                    {new Date(dataPoint.date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })}
                                                </text>
                                            </g>
                                        );
                                    })}
                                </svg>
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
                                        {q.event_title} - {q.category_name}
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