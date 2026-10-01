import { Link } from '@inertiajs/react';
import EventCard from '@/components/EventCard';
import PublicLayout from '@/components/PublicLayout';
import UiEmptyState from '@/components/ui/EmptyState';

export default function Index({ events }) {
    const list = events.data ?? [];

    return (
        <PublicLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-slate-900">Tersimpan</h1>
                <p className="mt-1 text-sm text-slate-500">Event favorit yang kamu simpan untuk ditonton berikutnya.</p>
            </div>

            {list.length === 0 ? (
                <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm">
                    <UiEmptyState
                        title="Belum Ada Event Tersimpan"
                        description="Ketuk ikon bookmark di kartu event untuk menyimpannya di sini."
                        action={
                            <Link
                                href="/jelajah"
                                className="inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm"
                            >
                                Jelajahi Event
                            </Link>
                        }
                    />
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {list.map((event) => (
                        <EventCard key={event.id} event={event} />
                    ))}
                </div>
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