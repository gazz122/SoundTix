import { Link } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';
import StatCard from '@/components/ui/StatCard';

export default function Customer({ stats }) {
    return (
        <AppLayout>
            <Header name="Dashboard Customer" description="Ringkasan tiket dan pesanan Anda." />

            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard label="Total Pesanan" value={stats.total_orders} hint="Semua status" />
                <StatCard label="Pesanan Dibayar" value={stats.paid_orders} hint="Status PAID" />
                <StatCard label="Tiket Aktif" value={stats.active_tickets} hint="Siap digunakan" />
            </div>

            <Panel>
                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p className="font-medium text-slate-800">Temukan konser berikutnya</p>
                        <p className="mt-1 text-sm text-slate-500">
                            Jelajahi event tayang atau lihat tiket yang sudah Anda miliki.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Link
                            href="/"
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm"
                        >
                            Cari Event
                        </Link>
                        <Link
                            href="/orders"
                            className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2.5 px-5 rounded-lg transition-all"
                        >
                            Tiket Saya
                        </Link>
                    </div>
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

function Panel({ children }) {
    return (
        <div className="mt-8 bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-6">
            {children}
        </div>
    );
}