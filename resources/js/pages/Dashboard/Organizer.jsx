import AppLayout from '@/components/AppLayout';
import StatCard from '@/components/ui/StatCard';

export default function Organizer({ stats }) {
    return (
        <AppLayout>
            <Header
                name="Dashboard Event Organizer"
                description="Ringkasan event, tiket, dan pendapatan Anda."
            />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Total Event" value={stats.total_events} hint="Semua status" />
                <StatCard label="Event Published" value={stats.published_events} hint="Sedang tayang" />
                <StatCard label="Menunggu Persetujuan" value={stats.pending_events} hint="Status PENDING" />
                <StatCard label="Pendapatan" value={rupiah(stats.revenue)} hint="Dari pesanan PAID" />
            </div>

            <Panel>
                <p className="text-sm text-slate-500">
                    Tabel penjualan & grafik tren akan tersedia di milestone Analytics. Pembuatan dan pengelolaan event
                    datang di milestone berikutnya.
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