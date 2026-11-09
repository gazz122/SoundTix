import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';

const ROLE_COLORS = {
    admin: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    organizer: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    customer: 'bg-sky-50 text-sky-700 border-sky-200',
    gatekeeper: 'bg-amber-50 text-amber-700 border-amber-200',
};

export default function Index({ users, filters }) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
        role: filters.role ?? '',
    });

    const currentUserId = usePage().props.auth?.user?.id;

    const list = users.data ?? [];

    function remove(user) {
        if (window.confirm(`Hapus akun "${user.name}"? Akun tidak akan bisa login kembali.`)) {
            router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
        }
    }

    function apply(event) {
        event?.preventDefault();
        get('/admin/users', {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function reset() {
        setData({ search: '', role: '' });
        get('/admin/users', {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <AppLayout>
            <Header
                name="Kelola Organizer"
                description="Daftar seluruh pengguna platform beserta peran dan status aktivasi."
            />

            <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-12">
                    <div className="lg:col-span-6">
                        <label className="block">
                            <span className="text-sm font-medium text-slate-700">Cari</span>
                            <input
                                type="search"
                                value={data.search}
                                onChange={(event) => setData('search', event.target.value)}
                                onKeyDown={(event) => event.key === 'Enter' && apply()}
                                placeholder="Nama atau email..."
                                className="mt-1.5 w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                            />
                        </label>
                    </div>
                    <div className="lg:col-span-3">
                        <label className="block">
                            <span className="text-sm font-medium text-slate-700">Peran</span>
                            <select
                                value={data.role}
                                onChange={(event) => setData('role', event.target.value)}
                                className="mt-1.5 w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                            >
                                <option value="">Semua Peran</option>
                                <option value="customer">Customer</option>
                                <option value="organizer">Event Organizer</option>
                                <option value="gatekeeper">Gatekeeper</option>
                            </select>
                        </label>
                    </div>
                    <div className="lg:col-span-3 flex flex-wrap items-end gap-2">
                        <button
                            type="button"
                            onClick={apply}
                            className="flex-1 whitespace-nowrap bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm"
                        >
                            Terapkan
                        </button>
                        <button
                            type="button"
                            onClick={reset}
                            className="whitespace-nowrap bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm px-3 py-2 rounded-lg transition-all"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <div className="mt-6 flex justify-end">
                <Link
                    href="/admin/users/create"
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm"
                >
                    + Tambah Pengguna
                </Link>
            </div>

            <div className="mt-4 bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm">
                {list.length === 0 ? (
                    <p className="p-10 text-center text-sm text-slate-500">Tidak ada pengguna yang cocok.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Nama</Th>
                                    <Th>Email</Th>
                                    <Th>Peran</Th>
                                    <Th>Status</Th>
                                    <Th>Terdaftar</Th>
                                    <Th align="right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {list.map((user) => (
                                    <tr key={user.id} className="hover:bg-slate-50/60 transition-colors">
                                        <Td strong>{user.name}</Td>
                                        <Td>{user.email}</Td>
                                        <Td>
                                            <RoleBadge role={user.role} label={user.role_label} />
                                        </Td>
                                        <Td>
                                            <ActiveBadge active={user.is_active} />
                                        </Td>
                                        <Td>{formatDate(user.created_at)}</Td>
                                        <Td align="right">
                                            <Link
                                                href={`/admin/users/${user.id}/edit`}
                                                className="text-indigo-600 hover:text-indigo-700 font-medium"
                                            >
                                                Edit
                                            </Link>
                                            {user.id !== currentUserId && (
                                                <>
                                                    <span className="mx-2 text-slate-300">·</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => remove(user)}
                                                        className="text-rose-600 hover:text-rose-700 font-medium"
                                                    >
                                                        Hapus
                                                    </button>
                                                </>
                                            )}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {users.links?.length > 3 && <Pagination links={users.links} />}
        </AppLayout>
    );
}

function Header({ name, description }) {
    return (
        <div className="mb-8">
            <h1 className="text-2xl font-semibold text-slate-900">{name}</h1>
            <p className="mt-1 text-sm text-slate-500">{description}</p>
        </div>
    );
}

function Th({ children, align }) {
    return (
        <th
            className={`px-4 py-3 font-medium text-slate-500 uppercase tracking-wider text-xs ${align === 'right' ? 'text-right' : 'text-left'}`}
        >
            {children}
        </th>
    );
}

function Td({ children, align, strong }) {
    return (
        <td className={`px-4 py-3 ${align === 'right' ? 'text-right' : 'text-left'}`}>
            <span className={strong ? 'font-medium text-slate-900' : 'text-slate-600'}>{children}</span>
        </td>
    );
}

function RoleBadge({ role, label }) {
    return (
        <span
            className={`inline-block rounded-md border px-2 py-0.5 text-xs font-medium ${ROLE_COLORS[role] ?? ROLE_COLORS.customer}`}
        >
            {label}
        </span>
    );
}

function ActiveBadge({ active }) {
    return active ? (
        <span className="inline-block rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
            Aktif
        </span>
    ) : (
        <span className="inline-block rounded-md border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
            Nonaktif
        </span>
    );
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
}

function Pagination({ links }) {
    return (
        <nav className="mt-6 flex flex-wrap items-center justify-center gap-2">
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