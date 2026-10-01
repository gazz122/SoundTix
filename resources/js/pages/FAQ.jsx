import { Head } from '@inertiajs/react';
import { CalendarCheck, ChevronDown, CreditCard, HelpCircle, QrCode, Search, SearchX, ShieldCheck, Ticket } from 'lucide-react';
import { useMemo, useState } from 'react';
import { BRAND_COLORS, GmailIcon, WhatsAppIcon } from '@/components/BrandIcons';
import PublicLayout from '@/components/PublicLayout';

const ICONS = {
    Ticket,
    ShieldCheck,
    QrCode,
    CreditCard,
    CalendarCheck,
};

export default function FAQ({ categories }) {
    const [search, setSearch] = useState('');
    const [active, setActive] = useState('all');

    const allFaqs = useMemo(() => categories.flatMap((category) => category.faqs), [categories]);

    const visibleFaqs = useMemo(() => {
        const query = search.trim().toLowerCase();

        const source = active === 'all'
            ? allFaqs
            : categories.find((category) => category.slug === active)?.faqs ?? [];

        if (!query) return source;

        return source.filter(
            (faq) =>
                faq.question.toLowerCase().includes(query) ||
                faq.answer.toLowerCase().includes(query) ||
                (faq.category_name ?? '').toLowerCase().includes(query),
        );
    }, [allFaqs, categories, active, search]);

    const noCategories = categories.length === 0;

    return (
        <PublicLayout>
            <Head title="Pusat Bantuan FAQ" />

            <div className="mx-auto max-w-3xl">
                <section className="text-center">
                    <p className="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">Pusat Bantuan</p>
                    <h1 className="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        Apa yang bisa kami bantu?
                    </h1>
                    <p className="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-600">
                        Temukan jawaban cepat seputar pembelian tiket, verifikasi akun, e-ticket, pembayaran, hingga
                        check-in di venue.
                    </p>
                </section>

                <div className="relative mt-8">
                    <Search aria-hidden="true" className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari pertanyaan atau kata kunci..."
                        aria-label="Cari pertanyaan"
                        className="w-full rounded-xl border border-slate-300 bg-white py-3 pl-11 pr-4 text-slate-900 placeholder-slate-400 shadow-sm focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/20"
                    />
                </div>

                <div className="mt-6 -mx-4 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
                    <div className="flex gap-2 whitespace-nowrap">
                        <TabButton
                            active={active === 'all'}
                            label="Semua"
                            icon={HelpCircle}
                            onClick={() => setActive('all')}
                        />
                        {categories.map((category) => (
                            <TabButton
                                key={category.id}
                                active={active === category.slug}
                                label={category.name}
                                icon={ICONS[category.icon] ?? HelpCircle}
                                onClick={() => setActive(category.slug)}
                            />
                        ))}
                    </div>
                </div>

                {noCategories ? (
                    <EmptyResult
                        title="FAQ belum tersedia"
                        description="Konten pusat bantuan sedang disiapkan. Silakan kembali lagi nanti atau hubungi tim kami."
                    />
                ) : visibleFaqs.length === 0 ? (
                    <EmptyResult
                        title="Tidak ada hasil ditemukan"
                        description={`Tidak ada pertanyaan yang cocok dengan "${search}". Coba kata kunci lain atau hubungi tim kami.`}
                    />
                ) : (
                    <div className="mt-6 flex flex-col gap-3">
                        {visibleFaqs.map((faq) => (
                            <Accordion key={faq.id} faq={faq} />
                        ))}
                    </div>
                )}

                <section className="mt-10 rounded-2xl bg-indigo-600 px-6 py-8 text-white shadow-lg shadow-indigo-600/20 sm:px-10">
                    <div className="text-center">
                        <h2 className="text-xl font-semibold sm:text-2xl">Masih butuh bantuan?</h2>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-indigo-100">
                            Tim kami siap merespons pertanyaan Anda melalui WhatsApp atau email, Senin-Jumat pukul
                            09.00-18.00 WIB.
                        </p>
                    </div>
                    <div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                        <a
                            href="https://wa.me/6281511034467"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-2.5 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-50"
                        >
                            <WhatsAppIcon aria-hidden="true" className="h-4 w-4" color={BRAND_COLORS.whatsapp} />
                            Chat WhatsApp
                        </a>
                        <a
                            href="mailto:bagasdimas225@gmail.com"
                            className="inline-flex items-center justify-center gap-2 rounded-lg border border-white/40 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-white/10"
                        >
                            <GmailIcon aria-hidden="true" className="h-4 w-4" color={BRAND_COLORS.gmail} />
                            Email Support
                        </a>
                    </div>
                </section>
            </div>
        </PublicLayout>
    );
}

function TabButton({ active, label, icon: Icon, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition-all ${
                active
                    ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-600'
            }`}
        >
            <Icon aria-hidden="true" className="h-4 w-4" />
            {label}
        </button>
    );
}

function Accordion({ faq }) {
    const [open, setOpen] = useState(false);
    const id = `faq-${faq.id}`;

    return (
        <div className="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
            <button
                type="button"
                onClick={() => setOpen((current) => !current)}
                aria-expanded={open}
                aria-controls={id}
                className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left"
            >
                <span className="text-sm font-medium text-slate-900 sm:text-base">{faq.question}</span>
                <ChevronDown
                    aria-hidden="true"
                    className={`h-5 w-5 shrink-0 text-slate-400 transition-transform duration-300 ${open ? 'rotate-180 text-indigo-600' : ''}`}
                />
            </button>
            <div
                id={id}
                className={`grid transition-[grid-template-rows] duration-300 ease-in-out ${open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}
            >
                <div className="overflow-hidden">
                    <p className="border-t border-slate-100 px-5 py-4 text-sm leading-7 text-slate-600">{faq.answer}</p>
                </div>
            </div>
        </div>
    );
}

function EmptyResult({ title, description }) {
    return (
        <div className="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
            <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                <SearchX aria-hidden="true" className="h-6 w-6" />
            </span>
            <h2 className="mt-4 text-lg font-semibold text-slate-900">{title}</h2>
            <p className="mx-auto mt-1 max-w-sm text-sm leading-6 text-slate-500">{description}</p>
        </div>
    );
}