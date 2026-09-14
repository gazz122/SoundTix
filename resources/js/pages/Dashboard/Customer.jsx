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
                <p className="text-sm text-slate-500">
                    Pemesanan tiket akan tersedia di milestone berikutnya. Nantikan browsing event & checkout di
                    halaman ini.
                </p>
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