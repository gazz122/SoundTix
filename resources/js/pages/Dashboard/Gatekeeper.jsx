import AppLayout from '@/components/AppLayout';
import StatCard from '@/components/ui/StatCard';

export default function Gatekeeper({ stats }) {
    return (
        <AppLayout>
            <Header
                name="Dashboard Gatekeeper"
                description="Ringkasan aktivitas validasi tiket di area gate."
            />

            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard label="Validasi Hari Ini" value={stats.validated_today} hint={`Tanggal ${today()}`} />
                <StatCard label="Total Validasi" value={stats.validated_total} hint="Akumulasi Anda" />
                <StatCard label="Tiket Aktif Platform" value={stats.active_tickets} hint="Belum dipindai" />
            </div>

            <Panel>
                <p className="text-sm text-slate-500">
                    Antarmuka scanner QR (kamera web) akan tersedia di milestone Gatekeeper Scanner. Gunakan API di
                    bawah untuk integrasi validasi.
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

function today() {
    return new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}