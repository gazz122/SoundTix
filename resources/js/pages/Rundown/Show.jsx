import { Link, usePoll } from '@inertiajs/react';
import PublicLayout from '@/components/PublicLayout';
import { formatDate } from '@/lib/format';

const wibFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

function wibNow() {
    const parts = Object.fromEntries(wibFormatter.formatToParts(new Date()).map((part) => [part.type, part.value]));

    return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` };
}

function wibClock(iso) {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(new Date(iso));
}

export default function Show({ event }) {
    usePoll(30000, { only: ['event'], preserveScroll: true });

    const isMultiDay = event.duration_type === 'multi_day';
    const days = event.event_days ?? [];
    const now = wibNow();

    const groups = isMultiDay
        ? days.map((day) => ({
              key: day.id,
              date: day.date,
              label: `Day ${day.day_number} \u00b7 ${formatDate(day.date)}`,
              items: event.rundowns.filter((item) => item.event_day_id === day.id),
          }))
        : [{ key: 'single', date: event.event_date, label: null, items: event.rundowns }];

    const lastUpdated = event.rundowns.reduce(
        (latest, item) => (item.updated_at && item.updated_at > latest ? item.updated_at : latest),
        '',
    );

    return (
        <PublicLayout>
            <div className="mx-auto max-w-3xl">
                <div className="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600">Rundown Acara</p>
                    <h1 className="mt-1 text-2xl font-semibold text-slate-900">{event.title}</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {formatDate(event.event_date)} · {event.venue_name}, {event.city}
                    </p>
                    <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        Halaman ini hanya menampilkan jadwal acara, bukan tiket masuk. QR tiket tetap dipakai untuk
                        check-in di venue.
                    </p>
                    <Link href="/rundown/scan" className="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-700">
                        Scan ulang QR
                    </Link>
                </div>

                <div className="mt-6 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 className="text-lg font-semibold text-slate-900">Jadwal Penampilan</h2>
                    {lastUpdated && (
                        <p className="text-xs text-slate-400">Jadwal diperbarui {wibClock(lastUpdated)} WIB</p>
                    )}
                </div>

                {event.rundowns.length === 0 ? (
                    <div className="mt-3 rounded-xl border border-slate-200/80 bg-white p-8 text-center text-sm text-slate-400">
                        Organizer belum mengisi rundown untuk acara ini.
                    </div>
                ) : (
                    <div className="mt-3 space-y-6">
                        {groups.map((group) => (
                            <div key={group.key}>
                                {group.label && (
                                    <p className="mb-2 text-sm font-semibold text-indigo-600">{group.label}</p>
                                )}
                                <ol className="space-y-2">
                                    {group.items.map((item) => {
                                        const isLive =
                                            group.date === now.date &&
                                            item.start_time <= now.time &&
                                            (!item.end_time || now.time < item.end_time);

                                        return (
                                            <li
                                                key={item.id}
                                                className={`flex gap-4 rounded-xl border p-4 ${
                                                    isLive
                                                        ? 'border-indigo-300 bg-indigo-50/60'
                                                        : 'border-slate-200/80 bg-white'
                                                }`}
                                            >
                                                <div className="shrink-0 text-center">
                                                    <p className="text-sm font-bold text-indigo-600">{item.start_time}</p>
                                                    {item.end_time && (
                                                        <p className="text-xs text-slate-400">- {item.end_time}</p>
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    {isLive && (
                                                        <span className="mb-1 inline-block rounded-md bg-indigo-600 px-2 py-0.5 text-[11px] font-semibold text-white">
                                                            Sedang tampil
                                                        </span>
                                                    )}
                                                    <p className="font-medium text-slate-900">
                                                        {item.title ?? item.performer?.name}
                                                    </p>
                                                    {item.title && item.performer && (
                                                        <p className="text-sm text-slate-600">{item.performer.name}</p>
                                                    )}
                                                    {item.performer?.genre && (
                                                        <span className="mt-1 inline-block rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-600">
                                                            {item.performer.genre}
                                                        </span>
                                                    )}
                                                    {item.description && (
                                                        <p className="mt-1 text-sm text-slate-500">{item.description}</p>
                                                    )}
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ol>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}
