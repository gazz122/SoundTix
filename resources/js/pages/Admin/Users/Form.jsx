import { Link, useForm } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';

export default function Form({ user, roles }) {
    const isEdit = user !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: isEdit ? undefined : '',
        phone: user?.phone ?? '',
        role: user?.role ?? (roles[0]?.value ?? 'customer'),
        password: '',
        is_active: user?.is_active ?? true,
    });

    function submit(event) {
        event.preventDefault();

        if (isEdit) {
            put(`/admin/users/${user.id}`);
        } else {
            post('/admin/users');
        }
    }

    return (
        <AppLayout>
            <BackLink />

            <Header
                name={isEdit ? 'Edit Pengguna' : 'Tambah Pengguna'}
                description={isEdit ? `Memperbarui akun ${user.name}.` : 'Buat akun baru untuk customer, organizer, atau gatekeeper.'}
            />

            <form onSubmit={submit} className="max-w-xl bg-white border border-slate-200/80 rounded-xl shadow-sm p-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama Lengkap" error={errors.name} className="sm:col-span-2">
                        <input
                            type="text"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            className={inputClass(!!errors.name)}
                            placeholder="Nama pengguna"
                        />
                    </Field>

                    {!isEdit && (
                        <Field label="Email" error={errors.email} className="sm:col-span-2">
                            <input
                                type="email"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                className={inputClass(!!errors.email)}
                                placeholder="email@contoh.com"
                            />
                        </Field>
                    )}

                    <Field label="No. HP" error={errors.phone}>
                        <input
                            type="text"
                            value={data.phone}
                            onChange={(event) => setData('phone', event.target.value)}
                            className={inputClass(!!errors.phone)}
                            placeholder="Opsional"
                        />
                    </Field>

                    <Field label="Peran" error={errors.role}>
                        <select
                            value={data.role}
                            onChange={(event) => setData('role', event.target.value)}
                            className={inputClass(!!errors.role)}
                        >
                            {roles.map((role) => (
                                <option key={role.value} value={role.value}>
                                    {role.label}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label={isEdit ? 'Password (kosongkan jika tidak diubah)' : 'Password'} error={errors.password}>
                        <input
                            type="password"
                            value={data.password}
                            onChange={(event) => setData('password', event.target.value)}
                            className={inputClass(!!errors.password)}
                            placeholder={isEdit ? '••••••••' : 'Min. 8 karakter'}
                        />
                    </Field>

                    {isEdit && (
                        <Field label="Status Akun" error={errors.is_active}>
                            <select
                                value={data.is_active ? 'active' : 'inactive'}
                                onChange={(event) => setData('is_active', event.target.value === 'active')}
                                className={inputClass(!!errors.is_active)}
                            >
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </Field>
                    )}
                </div>

                {errors.user && (
                    <p className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {errors.user}
                    </p>
                )}

                <div className="mt-6 flex gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-medium py-2.5 px-6 rounded-lg transition-all shadow-sm"
                    >
                        {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Buat Pengguna'}
                    </button>
                    <Link
                        href="/admin/users"
                        className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 py-2.5 px-6 rounded-lg transition-all"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}

function BackLink() {
    return (
        <Link href="/admin/users" className="mb-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-700">
            ← Kembali ke daftar pengguna
        </Link>
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

function Field({ label, error, children, className = '' }) {
    return (
        <label className={`block ${className}`}>
            <span className="text-sm font-medium text-slate-700">{label}</span>
            <div className="mt-1.5">{children}</div>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </label>
    );
}

function inputClass(hasError) {
    return [
        'w-full bg-white border text-slate-900 rounded-lg px-3 py-2',
        'focus:outline-none focus:ring-1 transition-all',
        hasError
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-slate-300 focus:border-indigo-600 focus:ring-indigo-600',
    ].join(' ');
}