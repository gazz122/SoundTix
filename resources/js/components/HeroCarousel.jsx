import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { formatDate } from '@/lib/format';

const SLIDE_INTERVAL_MS = 5000;

export default function HeroCarousel({ events }) {
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const touchStartX = useRef(null);
    const count = events.length;

    useEffect(() => {
        if (paused || count <= 1) return;
        const timer = setInterval(() => setIndex((prev) => (prev + 1) % count), SLIDE_INTERVAL_MS);
        return () => clearInterval(timer);
    }, [paused, count]);

    if (count === 0) return null;

    function go(next) {
        setIndex(((next % count) + count) % count);
    }

    return (
        <section
            className="relative overflow-hidden rounded-2xl bg-slate-900 shadow-sm aspect-[4/3] sm:aspect-[21/10]"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onTouchStart={(event) => { touchStartX.current = event.touches[0].clientX; }}
            onTouchEnd={(event) => {
                if (touchStartX.current === null) return;
                const delta = event.changedTouches[0].clientX - touchStartX.current;
                touchStartX.current = null;
                if (Math.abs(delta) > 40) go(index + (delta < 0 ? 1 : -1));
            }}
            role="region"
            aria-roledescription="carousel"
            aria-label="Event unggulan"
        >
            <div
                className="flex h-full transition-transform duration-700 ease-out"
                style={{ transform: `translateX(-${index * 100}%)` }}
            >
                {events.map((event) => (
                    <article
                        key={event.id}
                        className="relative h-full w-full shrink-0"
                        role="group"
                        aria-roledescription="slide"
                        aria-label={`${event.title}, seluruh slide ${index + 1} dari ${count}`}
                    >
                        {event.banner_url ? (
                            <img src={event.banner_url} alt={`${event.title} banner`} className="absolute inset-0 h-full w-full object-cover" />
                        ) : (
                            <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-indigo-600 via-indigo-700 to-slate-900">
                                <span className="text-6xl font-bold text-white/20">{event.title.charAt(0)}</span>
                            </div>
                        )}

                        <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent" />

                        <div className="absolute inset-x-0 bottom-0 p-5 text-white sm:p-8">
                            <p className="text-[11px] font-medium uppercase tracking-wider text-indigo-300">
                                {formatDate(event.event_date)}
                            </p>
                            <h2 className="mt-1 text-xl font-bold leading-tight sm:text-3xl">{event.title}</h2>
                            <p className="mt-1 max-w-xl truncate text-sm text-slate-300 sm:text-base">
                                {event.performers.map((performer) => performer.name).join(', ')}
                            </p>
                            <p className="mt-0.5 text-sm text-slate-400">
                                {event.city} · {event.venue_name}
                            </p>
                            <Link
                                href={`/events/${event.slug}`}
                                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm hover:bg-indigo-50 transition-colors"
                            >
                                Lihat Detail
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </Link>
                        </div>
                    </article>
                ))}
            </div>

            <div className="absolute bottom-4 right-4 flex items-center gap-2">
                <button
                    onClick={() => go(index - 1)}
                    aria-label="Slide sebelumnya"
                    className="hidden h-9 w-9 place-items-center rounded-full bg-white/15 text-white backdrop-blur hover:bg-white/25 transition-colors sm:grid"
                >
                    ←
                </button>
                {count > 1 && (
                    <div className="flex gap-1.5 rounded-full bg-black/25 px-2.5 py-1.5 backdrop-blur">
                        {events.map((event, i) => (
                            <button
                                key={event.id}
                                onClick={() => go(i)}
                                aria-label={`Ke slide ${i + 1}`}
                                className={`h-1.5 w-1.5 rounded-full transition-all ${i === index ? 'w-5 bg-white' : 'bg-white/40 hover:bg-white/70'}`}
                            />
                        ))}
                    </div>
                )}
                <button
                    onClick={() => go(index + 1)}
                    aria-label="Slide berikutnya"
                    className="hidden h-9 w-9 place-items-center rounded-full bg-white/15 text-white backdrop-blur hover:bg-white/25 transition-colors sm:grid"
                >
                    →
                </button>
            </div>
        </section>
    );
}