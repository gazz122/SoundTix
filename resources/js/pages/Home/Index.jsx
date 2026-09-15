import { Link } from '@inertiajs/react';
import EventCard from '@/components/EventCard';
import HeroCarousel from '@/components/HeroCarousel';
import PublicLayout from '@/components/PublicLayout';

export default function Index({ events, featured, filters }) {
    const list = events.data ?? [];
    const heroEvents = featured.data ?? [];
    const searching = Boolean(filters.q);

    return (
        <PublicLayout>
            {!searching && (
                <>
                    <HeroCarousel events={heroEvents} />
                    <div className="mt-10 text-center">
                        <h1 className="text-2xl sm:text-3xl font-semibold text-slate-900">Konser impian Anda, dalam sekali ketik.</h1>
                        <p className="mt-2 text-slate-500">
                            Temukan konser, festival, dan event musik terbaik di kota Anda. Pesan tiket digital dengan
                            mudah dan aman — gunakan kolom pencarian di bagian atas.
                        </p>
                    </div>
                </>
            )}

            <section className="mt-10">
                <h2 className="text-xl font-semibold text-slate-900 mb-4">
                    {searching ? `Hasil Pencarian "${filters.q}"` : 'Featured Events'}
                </h2>
                {list.length === 0 ? (
                    <EmptyState search={filters.q} />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {list.map((event) => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </div>
                )}
            </section>

            {events.links?.length > 3 && <Pagination links={events.links} />}
        </PublicLayout>
    );
}

function Pagination({ links }) {
    return (
        <nav className="mt-10 flex flex-wrap items-center justify-center gap-2">
            {links.map((link, index) => {
                const isPage = Number.isFinite(Number(link.label));
                return (
                    <Link
                        key={index}
                        href={link.url ?? '#'}
                        preserveScroll
                        className={
                            link.active
                                ? 'bg-indigo-600 text-white font-medium px-3.5 py-2 rounded-lg text-sm'
                                : 'bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 px-3.5 py-2 rounded-lg text-sm'
                        }
                    >
                        {isPage ? link.label : link.label === '&laquo; Previous' ? '←' : '→'}
                    </Link>
                );
            })}
        </nav>
    );
}

function EmptyState({ search }) {
    return (
        <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm p-10 text-center">
            <p className="text-sm text-slate-500">
                {search
                    ? `Tidak ada event yang cocok dengan "${search}". Coba kata kunci lain.`
                    : 'Belum ada event tersedia saat ini.'}
            </p>
        </div>
    );
}