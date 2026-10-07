import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

const SLIDE_INTERVAL_MS = 5000;

export default function BannerCarousel({ banners }) {
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const touchStartX = useRef(null);
    const count = banners.length;

    const go = useCallback(
        (next) => setIndex(((next % count) + count) % count),
        [count],
    );

    useEffect(() => {
        if (paused || count <= 1) return;
        const timer = setInterval(() => setIndex((prev) => (prev + 1) % count), SLIDE_INTERVAL_MS);
        return () => clearInterval(timer);
    }, [paused, count]);

    if (count === 0) return null;

    function isInternal(url) {
        return url.startsWith('/') && !url.startsWith('//');
    }

    function SlideContent({ banner }) {
        const image = (
            <img
                src={banner.image_url}
                alt={banner.title ?? 'Promo SoundTix'}
                className="h-auto w-full object-cover sm:h-full"
            />
        );

        if (!banner.link_url) {
            return image;
        }

        const classes = 'block w-full sm:h-full';

        return isInternal(banner.link_url) ? (
            <Link href={banner.link_url} className={classes}>
                {image}
            </Link>
        ) : (
            <a href={banner.link_url} target="_blank" rel="noopener noreferrer" className={classes}>
                {image}
            </a>
        );
    }

    return (
        <section
            className="relative w-full overflow-hidden rounded-2xl bg-slate-100 shadow-sm sm:aspect-[21/7]"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onTouchStart={(event) => { touchStartX.current = event.touches[0].clientX; }}
            onTouchEnd={(event) => {
                if (touchStartX.current === null) return;
                const delta = event.changedTouches[0].clientX - touchStartX.current;
                touchStartX.current = null;
                if (Math.abs(delta) > 40) go(index + (delta < 0 ? 1 : -1));
            }}
            onKeyDown={(event) => {
                if (event.key === 'ArrowLeft') go(index - 1);
                if (event.key === 'ArrowRight') go(index + 1);
            }}
            role="region"
            aria-roledescription="carousel"
            aria-label="Promo SoundTix"
        >
            <div
                className="flex sm:h-full transition-transform duration-700 ease-out motion-reduce:transition-none"
                style={{ transform: `translateX(-${index * 100}%)` }}
            >
                {banners.map((banner, slide) => (
                    <article
                        key={banner.id || `fallback-${slide}`}
                        className="relative w-full shrink-0 sm:h-full"
                        role="group"
                        aria-roledescription="slide"
                        aria-label={`${banner.title ?? 'Promo'}, slide ${slide + 1} dari ${count}`}
                    >
                        <SlideContent banner={banner} />
                    </article>
                ))}
            </div>

            {count > 1 && (
                <>
                    <button
                        onClick={() => go(index - 1)}
                        aria-label="Slide sebelumnya"
                        className="absolute left-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/70 text-slate-900 shadow-sm backdrop-blur transition-colors hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:bg-white/50 sm:hover:bg-white/80"
                    >
                        <ChevronLeft aria-hidden="true" className="h-5 w-5" />
                    </button>
                    <button
                        onClick={() => go(index + 1)}
                        aria-label="Slide berikutnya"
                        className="absolute right-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/70 text-slate-900 shadow-sm backdrop-blur transition-colors hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:bg-white/50 sm:hover:bg-white/80"
                    >
                        <ChevronRight aria-hidden="true" className="h-5 w-5" />
                    </button>

                    <div className="absolute inset-x-0 bottom-4 flex items-center justify-center gap-2 px-4">
                        {banners.map((banner, slide) => (
                            <button
                                key={banner.id || `fallback-${slide}`}
                                onClick={() => go(slide)}
                                aria-label={`Ke slide ${slide + 1}`}
                                aria-current={slide === index ? 'true' : undefined}
                                className={`rounded-full transition-all ${
                                    slide === index ? 'h-2 w-6 bg-indigo-600 sm:h-3 sm:w-8' : 'h-2 w-2 bg-slate-900/25 hover:bg-slate-900/45 sm:h-3 sm:w-3'
                                }`}
                            />
                        ))}
                    </div>
                </>
            )}
        </section>
    );
}