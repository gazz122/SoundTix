import { Link } from '@inertiajs/react';
import { formatDate, rupiah } from '@/lib/format';

export default function EventCard({ event }) {
    return (
        <Link
            href={`/events/${event.slug}`}
            className="group block bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm hover:shadow-md hover:border-slate-300 transition-all duration-200"
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
                <p className="mt-3 text-sm font-semibold text-indigo-600">
                    {event.starting_price ? `Mulai ${rupiah(event.starting_price)}` : 'Harga menyusul'}
                </p>
            </div>
        </Link>
    );
}