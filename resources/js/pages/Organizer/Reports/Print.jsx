import { useEffect } from 'react';

export default function Print({ items, summary }) {
    useEffect(() => {
        const t = setTimeout(() => window.print(), 400);
        return () => clearTimeout(t);
    }, []);

    return (
        <div className="min-h-screen bg-white p-8 text-sm text-slate-900">
            <div className="mb-8 flex items-start justify-between">
                <div>
                    <h1 className="text-xl font-bold">Laporan Penjualan Tiket</h1>
                    <p className="text-slate-500">
                        {summary.from} &mdash; {summary.to}
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-xs text-slate-400">Dicetak {new Date().toLocaleString('id-ID')}</p>
                    <button
                        onClick={() => window.print()}
                        className="mt-2 rounded border border-slate-300 px-3 py-1 text-xs hover:bg-slate-50 print:hidden"
                    >
                        Cetak Ulang
                    </button>
                </div>
            </div>

            <div className="mb-6 flex gap-6 text-sm">
                <span>Total Tiket: <strong>{summary.total_quantity}</strong></span>
                <span>Total Pendapatan: <strong>{rupiah(summary.total_revenue)}</strong></span>
            </div>

            <table className="w-full border-collapse text-xs">
                <thead>
                    <tr className="border-b-2 border-slate-300 text-left uppercase tracking-wide text-slate-500">
                        <th className="py-2 pr-2">Kode Pesanan</th>
                        <th className="py-2 pr-2">Event</th>
                        <th className="py-2 pr-2">Kategori</th>
                        <th className="py-2 pr-2 text-right">Jumlah</th>
                        <th className="py-2 pr-2 text-right">Harga/Item</th>
                        <th className="py-2 pr-2 text-right">Subtotal</th>
                        <th className="py-2">Dibayar</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((item, i) => (
                        <tr key={i} className="border-b border-slate-100">
                            <td className="py-1.5 pr-2 font-medium">{item.order_code}</td>
                            <td className="py-1.5 pr-2">{item.event}</td>
                            <td className="py-1.5 pr-2">{item.category}</td>
                            <td className="py-1.5 pr-2 text-right tabular-nums">{item.quantity}</td>
                            <td className="py-1.5 pr-2 text-right tabular-nums">{rupiah(item.price_per_unit)}</td>
                            <td className="py-1.5 pr-2 text-right tabular-nums">{rupiah(item.subtotal)}</td>
                            <td className="py-1.5">{item.paid_at}</td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t-2 border-slate-300 font-semibold">
                        <td colSpan={3} className="py-2">TOTAL</td>
                        <td className="py-2 text-right tabular-nums">{summary.total_quantity}</td>
                        <td />
                        <td className="py-2 text-right tabular-nums">{rupiah(summary.total_revenue)}</td>
                        <td />
                    </tr>
                </tfoot>
            </table>

            <p className="mt-8 text-center text-[11px] text-slate-300">SoundTix &mdash; Laporan ini dihasilkan secara otomatis.</p>
        </div>
    );
}

function rupiah(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(
        Number(value) || 0,
    );
}