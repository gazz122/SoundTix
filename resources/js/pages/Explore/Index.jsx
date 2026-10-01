import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import EventCard from '@/components/EventCard';
import PublicLayout from '@/components/PublicLayout';
import UiEmptyState from '@/components/ui/EmptyState';

export default function Index({ events, filters }) {
    const list = events.data ?? [];
    const [q, setQ] = useState(filters.q ?? '');

    const submit = (event) => {
        event.preventDefault();
        router.get('/jelajah', { q }, { preserveState: true });
    };

    return (
        <PublicLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-slate-900">Jelajah Event</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Cari konser dan festival favoritmu untuk dijelajahi atau disimpan.
                </p>
            </div>

            <form onSubmit={submit} className="relative mb-8 max-w-xl" role="search">
                <Search aria-hidden="true" className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="q"
                    value={q}
                    onChange={(event) => setQ(event.target.value)}
                    placeholder="Cari event, artis, atau kota..."
                    aria-label="Cari event"
                    className="w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                />
            </form>

            {list.length === 0 ? (
                <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm">
                    <UiEmptyState
                        title={q ? `Tidak ada event cocok dengan "${q}"` : 'Belum ada event tersedia'}
                        description={q ? 'Coba kata kunci lain untuk mencari konser favoritmu.' : 'Cek kembali - event baru sedang disiapkan oleh para organizer.'}
                    />
                </div>
            ) : (
                <section aria-label="Daftar event">
                    <p className="mb-4 text-sm text-slate-500">{events.total ?? list.length} event ditemukan</p>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {list.map((event) => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </div>
                </section>
            )}

            {events.links?.length > 3 && <Pagination links={events.links} />}
        </PublicLayout>
    );
}

function Pagination({ links }) {
    return (
        <nav className="mt-10 flex flex-wrap items-center justify-center gap-2">
            {links.map((link, index) => {
                const label = String(link.label);
                const isPage = Number.isFinite(Number(label));
                return (
                    <Link
                        key={index}
                        href={link.url ?? '#'}
                        className={`rounded-lg border px-3.5 py-2 text-sm ${
                            link.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                        }`}
                    >
                        {isPage ? label : label.includes('Previous') ? '←' : '→'}
                    </Link>
                );
            })}
        </nav>
    );
}