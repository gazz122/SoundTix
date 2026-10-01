import { Link, router, usePage } from '@inertiajs/react';
import { Bookmark } from 'lucide-react';
import { formatDate, rupiah } from '@/lib/format';

export default function EventCard({ event }) {
    const user = usePage().props.auth?.user;
    const saved = Boolean(event.is_saved);

    const toggleSave = (saveEvent) => {
        saveEvent.preventDefault();
        saveEvent.stopPropagation();
        const options = { preserveScroll: true };
        if (saved) {
            router.delete(`/events/${event.slug}/save`, options);
        } else {
            router.post(`/events/${event.slug}/save`, {}, options);
        }
    };

    return (
        <div className="relative">
            <Link
                href={`/events/${event.slug}`}
                className="group block bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm hover:shadow-sm hover:border-slate-300 transition-all duration-200"
            >
                <div className="h-36 relative">
                    {event.banner_url ? (
                        <img
                            src={event.banner_url}
                            alt={event.title}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="h-full bg-slate-100 p-4 flex flex-col justify-between">
                            <span className="self-start bg-white border border-slate-200/80 text-xs font-medium text-slate-600 px-2.5 py-1 rounded-md">
                                {formatDate(event.event_date)}
                            </span>
                            <span className="text-2xl font-bold text-slate-300 group-hover:text-indigo-300 transition-colors">
                                {event.venue_name.charAt(0)}
                            </span>
                        </div>
                    )}
                </div>

                <div className="p-4">
                    <h3 className="font-semibold text-slate-900 group-hover:text-indigo-700 transition-colors">
                        {event.title}
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">
                        {event.city} · {event.venue_name}
                    </p>
                    <p className="mt-1 text-xs text-slate-400">
                        {event.performers.slice(0, 2).map((performer) => performer.name).join(', ')}
                    </p>
                    <p className="mt-3 text-sm font-semibold text-indigo-600 tabular-nums">
                        {event.starting_price ? `Mulai ${rupiah(event.starting_price)}` : 'Harga menyusul'}
                    </p>
                </div>
            </Link>

            {user && (
                <button
                    type="button"
                    onClick={toggleSave}
                    aria-label={saved ? `Batalkan simpan ${event.title}` : `Simpan ${event.title}`}
                    aria-pressed={saved}
                    className={`absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full border shadow-sm transition-colors ${
                        saved
                            ? 'border-indigo-600 bg-indigo-600 text-white'
                            : 'border-slate-200 bg-white/95 text-slate-500 hover:border-indigo-300 hover:text-indigo-600'
                    }`}
                >
                    <Bookmark aria-hidden="true" className="h-4 w-4" fill={saved ? 'currentColor' : 'none'} />
                </button>
            )}
        </div>
    );
}