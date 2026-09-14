import AppLayout from '@/components/AppLayout';
import StatCard from '@/components/ui/StatCard';

export default function Admin({ stats }) {
    return (
        <AppLayout>
            <Header
                name="Dashboard System Admin"
                description="Statistik makro platform dan pendapatan global."
            />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <StatCard label="Total Pengguna" value={stats.total_users} hint="Semua role" />
                <StatCard label="Total Event" value={stats.total_events} hint="Semua status" />
                <StatCard label="Menunggu Persetujuan" value={stats.pending_events} hint="Status PENDING" />
                <StatCard label="Total Pesanan" value={stats.total_orders} hint="Semua status" />
                <StatCard label="Tiket Aktif" value={stats.active_tickets} hint="Status ACTIVE" />
                <StatCard label="Pendapatan Global" value={rupiah(stats.revenue)} hint="Dari pesanan PAID" />
            </div>

            <Panel>
                <p className="text-sm text-slate-500">
                    Verifikasi event dari menu Verifikasi Event, dan kelola akun pengguna dari menu Kelola Pengguna.
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

function rupiah(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(
        Number(value) || 0,
    );
}