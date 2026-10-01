import { Link, useForm } from '@inertiajs/react';
import Logo from '@/components/Logo';


export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event) {
        event.preventDefault();
        post('/login');
    }

    return (
        <div className="min-h-screen bg-slate-50 text-slate-800 font-sans flex items-center justify-center px-4">
            <div className="w-full max-w-md">
                <div className="mb-8 text-center">
                    <div className="mx-auto mb-4 flex w-fit items-center gap-2.5">
                         <Logo iconClassName="h-14 w-14" textClassName="text-2xl font-semibold" />
                     </div>
                    <h1 className="text-2xl font-semibold text-slate-900">Masuk SoundTix</h1>
                    <p className="mt-1 text-sm text-slate-500">Masuk untuk mengelola tiket dan event konser Anda.</p>
                </div>

                <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-6 sm:p-8">
                    <form onSubmit={submit} className="space-y-5">
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
                            label="Password"
                            type="password"
                            value={data.password}
                            onChange={(value) => setData('password', value)}
                            error={errors.password}
                            placeholder="••••••••"
                            autoComplete="current-password"
                        />

                        <div className="flex items-center gap-2">
                            <input
                                id="remember"
                                type="checkbox"
                                checked={data.remember}
                                onChange={(event) => setData('remember', event.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600"
                            />
                            <label htmlFor="remember" className="text-sm text-slate-600">
                                Ingat saya
                            </label>
                        </div>

                        <div className="flex items-center justify-between pt-1">
                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm disabled:opacity-60"
                            >
                                {processing ? 'Memproses...' : 'Masuk'}
                            </button>
                            <Link href="/register" className="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                Buat akun
                            </Link>
                        </div>
                    </form>
                </div>

                <p className="mt-6 text-center text-xs text-slate-400">
                    SoundTix - Platform e-ticketing konser digital
                </p>
            </div>
        </div>
    );
}

function Field({ label, type, value, onChange, error, placeholder, autoComplete }) {
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