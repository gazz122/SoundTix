import { Link, useForm } from '@inertiajs/react';
import Logo from '@/components/Logo';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/register', { onSuccess: () => reset() });
    }

    return (
        <div className="min-h-screen bg-slate-50 text-slate-800 font-sans flex items-center justify-center px-4 py-10">
            <div className="w-full max-w-md">
                <div className="mb-8 text-center">
                    <div className="mx-auto mb-4 flex w-fit items-center gap-2.5">
                        <Logo iconClassName="h-14 w-14" textClassName="text-2xl font-semibold" />
                    </div>
                    <h1 className="text-2xl font-semibold text-slate-900">Daftar Akun Baru</h1>
                    <p className="mt-1 text-sm text-slate-500">Buat akun untuk mulai membeli tiket konser.</p>
                </div>

                <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-6 sm:p-8">
                    <form onSubmit={submit} className="space-y-5">
                        <Field
                            label="Nama Lengkap"
                            value={data.name}
                            onChange={(value) => setData('name', value)}
                            error={errors.name}
                            placeholder="Nama Anda"
                            autoComplete="name"
                        />

                        <Field
                            label="Email"
                            type="email"
                            value={data.email}
                            onChange={(value) => setData('email', value)}
                            error={errors.email}
                            placeholder="nama@email.com"
                            autoComplete="email"
                        />

                        <Field
                            label="No. Telepon (opsional)"
                            value={data.phone}
                            onChange={(value) => setData('phone', value)}
                            error={errors.phone}
                            placeholder="08xxxxxxxxxx"
                            autoComplete="tel"
                        />

                        <Field
                            label="Password"
                            type="password"
                            value={data.password}
                            onChange={(value) => setData('password', value)}
                            error={errors.password}
                            placeholder="Minimal 8 karakter"
                            autoComplete="new-password"
                        />

                        <Field
                            label="Konfirmasi Password"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(value) => setData('password_confirmation', value)}
                            error={errors.password_confirmation}
                            placeholder="Ulangi password"
                            autoComplete="new-password"
                        />

                        <div className="flex items-center justify-between pt-1">
                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm disabled:opacity-60"
                            >
                                {processing ? 'Memproses...' : 'Daftar'}
                            </button>
                            <Link href="/login" className="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                Sudah punya akun
                            </Link>
                        </div>
                    </form>
                </div>

                <p className="mt-6 text-center text-xs text-slate-400">
                    SoundTix — Platform e-ticketing konser digital
                </p>
            </div>
        </div>
    );
}

function Field({ label, type = 'text', value, onChange, error, placeholder, autoComplete }) {
    return (
        <div className="space-y-1.5">
            <label className="block text-sm font-medium text-slate-700">{label}</label>
            <input
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                autoComplete={autoComplete}
                className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
            />
            {error && <p className="text-sm text-rose-600">{error}</p>}
        </div>
    );
}