import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/components/AppLayout';

export default function Index({ items, summary, filters }) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    function applyFilters() {
        router.get('/organizer/reports', { from, to }, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <div className="mb-8 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Laporan Penjualan</h1>
                    <p className="mt-1 text-sm text-slate-500">Data tiket berstatus PAID dari seluruh event Anda.</p>
                </div>
                <div className="flex gap-2">
                    <a
                        href={`/organizer/reports/csv?from=${filters.from}&to=${filters.to}`}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        CSV
                    </a>
                    <a
                        href={`/organizer/reports/print?from=${filters.from}&to=${filters.to}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        Cetak / PDF
                    </a>
                </div>
            </div>

            <div className="mb-6 flex flex-wrap items-end gap-3">
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-600">Dari</label>
                    <input
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                    />
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-600">Sampai</label>
                    <input
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                    />
                </div>
                <button
                    onClick={applyFilters}
                    className="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Tampilkan
                </button>
            </div>

            <div className="mb-4 flex gap-4 text-sm text-slate-600">
                <span>Total: <strong>{summary.total_quantity}</strong> tiket</span>
                <span>Pendapatan: <strong>{rupiah(summary.total_revenue)}</strong></span>
            </div>

            <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th className="px-4 py-3 text-left">Kode Pesanan</th>
                                <th className="px-4 py-3 text-left">Event</th>
                                <th className="px-4 py-3 text-left">Kategori</th>
                                <th className="px-4 py-3 text-right">Jumlah</th>
                                <th className="px-4 py-3 text-right">Harga/Item</th>
                                <th className="px-4 py-3 text-right">Subtotal</th>
                                <th className="px-4 py-3 text-left">Dibayar Pada</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-slate-400">Tidak ada data untuk rentang ini.</td>
                                </tr>
                            ) : (
                                items.map((item, i) => (
                                    <tr key={i} className="hover:bg-slate-50/50">
                                        <td className="px-4 py-2.5 font-medium text-slate-800">{item.order_code}</td>
                                        <td className="px-4 py-2.5 text-slate-700">{item.event}</td>
                                        <td className="px-4 py-2.5 text-slate-700">{item.category}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums">{item.quantity}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums">{rupiah(item.price_per_unit)}</td>
                                        <td className="px-4 py-2.5 text-right font-medium tabular-nums">{rupiah(item.subtotal)}</td>
                                        <td className="px-4 py-2.5 text-slate-500">{item.paid_at}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function rupiah(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(
        Number(value) || 0,
    );
}