import { Link } from '@inertiajs/react';
import BannerCarousel from '@/components/BannerCarousel';
import EventCard from '@/components/EventCard';
import PublicLayout from '@/components/PublicLayout';
import UiEmptyState from '@/components/ui/EmptyState';

export default function Index({ events, banners, filters }) {
    const list = events.data ?? [];
    const searching = Boolean(filters.q);

    return (
        <PublicLayout>
            {!searching && (
                <>
                    <BannerCarousel banners={banners} />
                    <div className="mt-10 text-center">
                        <h1 className="text-2xl sm:text-3xl font-semibold text-white">Konser impian Anda, dalam sekali ketik.</h1>
                        <p className="mt-2 text-white">
                            Temukan konser, festival, dan event musik terbaik di kota Anda. Pesan tiket digital dengan
                            mudah dan aman - gunakan kolom pencarian di bagian atas.
                        </p>
                    </div>
                </>
            )}

            <section className="mt-10">
                <h2 className="text-xl font-semibold text-white mb-4">
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
                                : 'bg-black hover:bg-slate-100 text-slate-700 border border-slate-300 px-3.5 py-2 rounded-lg text-sm'
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
        <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm">
            <UiEmptyState
                title={search ? `Tidak ada event cocok dengan "${search}"` : 'Belum ada event tersedia'}
                description={search ? 'Coba kata kunci lain untuk mencari konser favoritmu.' : 'Cek kembali - event baru sedang disiapkan oleh para organizer.'}
            />
        </div>
    );
}