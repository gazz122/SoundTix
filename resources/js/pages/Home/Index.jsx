import { Link, useForm } from '@inertiajs/react';
import EventCard from '@/components/EventCard';
import PublicLayout from '@/components/PublicLayout';

export default function Index({ events, filters, cities, categories }) {
    const { data, setData, get } = useForm({
        q: filters.q ?? '',
        city: filters.city ?? '',
        date: filters.date ?? '',
        category: filters.category ?? '',
    });

    function apply(event) {
        event?.preventDefault();
        get('/', {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function reset() {
        setData({ q: '', city: '', date: '', category: '' });
        get('/', {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    const list = events.data ?? [];

    return (
        <PublicLayout>
            <section className="bg-white border border-slate-200/80 rounded-2xl shadow-sm px-6 py-12 sm:px-10">
                <div className="max-w-2xl">
                    <h1 className="text-3xl sm:text-4xl font-semibold text-slate-900 leading-tight">
                        Konser impian Anda, dalam sekali ketik.
                    </h1>
                    <p className="mt-3 text-slate-500">
                        Temukan konser, festival, dan event musik terbaik di kota Anda. Pesan tiket digital dengan
                        mudah dan aman.
                    </p>

                    <div className="mt-6 flex flex-col sm:flex-row gap-3">
                        <input
                            type="search"
                            value={data.q}
                            onChange={(event) => setData('q', event.target.value)}
                            onKeyDown={(event) => event.key === 'Enter' && apply()}
                            placeholder="Cari event, kota, atau venue..."
                            className="flex-1 bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                        />
                        <button
                            type="button"
                            onClick={apply}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-6 rounded-lg transition-all shadow-sm"
                        >
                            Cari
                        </button>
                    </div>
                </div>
            </section>

            <section className="mt-6 bg-white border border-slate-200/80 rounded-xl shadow-sm p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FilterSelect label="Kota" value={data.city} onChange={(value) => setData('city', value)} options={cities} />
                    <FilterInput label="Tanggal" type="date" value={data.date} onChange={(value) => setData('date', value)} />
                    <FilterSelect
                        label="Kategori Tiket"
                        value={data.category}
                        onChange={(value) => setData('category', value)}
                        options={categories}
                    />
                    <div className="flex items-end gap-2">
                        <button
                            type="button"
                            onClick={apply}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm"
                        >
                            Terapkan
                        </button>
                        <button
                            type="button"
                            onClick={reset}
                            className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm py-2 px-4 rounded-lg transition-all"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </section>

            <section className="mt-10">
                <h2 className="text-xl font-semibold text-slate-900 mb-4">Featured Events</h2>
                {list.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {list.slice(0, 3).map((event) => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </div>
                )}
            </section>

            <section className="mt-10">
                <h2 className="text-xl font-semibold text-slate-900 mb-4">Semua Event</h2>
                {list.slice(3).length === 0 && list.length > 0 ? (
                    <p className="text-sm text-slate-500">Event lainnya segera hadir.</p>
                ) : (
                    list.length > 0 && (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {list.slice(3).map((event) => (
                                <EventCard key={event.id} event={event} />
                            ))}
                        </div>
                    )
                )}
            </section>

            {events.links?.length > 3 && <Pagination links={events.links} />}
        </PublicLayout>
    );
}

function FilterSelect({ label, value, onChange, options }) {
    return (
        <label className="block">
            <span className="text-sm font-medium text-slate-700">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1.5 w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
            >
                <option value="">Semua</option>
                {options.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
        </label>
    );
}

function FilterInput({ label, type, value, onChange }) {
    return (
        <label className="block">
            <span className="text-sm font-medium text-slate-700">{label}</span>
            <input
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1.5 w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
            />
        </label>
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

function EmptyState() {
    return (
        <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm p-10 text-center">
            <p className="text-sm text-slate-500">Tidak ada event yang cocok dengan pencarian Anda.</p>
        </div>
    );
}