import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Logo from '@/components/Logo';

export default function Verify({ email }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ code: '' });
    const [resending, setResending] = useState(false);

    function submit(event) {
        event.preventDefault();
        post('/verify');
    }

    function resend() {
        setResending(true);
        router.post('/verify/send', {}, {
            preserveScroll: true,
            onFinish: () => setResending(false),
        });
    }

    return (
        <div className="min-h-screen bg-slate-50 text-slate-800 font-sans flex items-center justify-center px-4 py-10">
            <div className="w-full max-w-md">
                <div className="mb-8 text-center">
                    <div className="mx-auto mb-4 flex w-fit items-center gap-2.5">
                        <Logo iconClassName="h-14 w-14" textClassName="text-2xl font-semibold" />
                    </div>
                    <h1 className="text-2xl font-semibold text-slate-900">Verifikasi Email</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Kode 6 digit sudah dikirim ke <span className="font-medium text-slate-700">{email}</span>.
                    </p>
                </div>

                <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-6 sm:p-8">
                    {flash?.status && (
                        <div className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700">
                            {flash.status}
                        </div>
                    )}
                    {flash?.success && (
                        <div className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700">
                            {flash.success}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-5">
                        <div className="space-y-1.5">
                            <label htmlFor="code" className="block text-sm font-medium text-slate-700">
                                Kode Verifikasi
                            </label>
                            <input
                                id="code"
                                type="text"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                value={data.code}
                                onChange={(event) =>
                                    setData('code', event.target.value.replace(/\D/g, '').slice(0, 6))
                                }
                                placeholder="000000"
                                className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 text-center text-2xl font-semibold tracking-[0.5em] focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                                autoFocus
                            />
                            {errors.code && <p className="text-sm text-rose-600">{errors.code}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={processing || data.code.length !== 6}
                            className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-all shadow-sm disabled:opacity-60"
                        >
                            {processing ? 'Memverifikasi...' : 'Verifikasi Email'}
                        </button>

                        <div className="text-center">
                            <button
                                type="button"
                                disabled={resending}
                                onClick={resend}
                                className="text-sm font-medium text-indigo-600 hover:text-indigo-700 disabled:opacity-60"
                            >
                                {resending ? 'Mengirim ulang...' : 'Kirim ulang kode'}
                            </button>
                        </div>
                    </form>
                </div>

                <div className="mt-6 flex items-center justify-between">
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="text-sm text-slate-400 hover:text-slate-600"
                    >
                        Keluar
                    </button>
                    <p className="text-xs text-slate-400">Kode berlaku 10 menit.</p>
                </div>
            </div>
        </div>
    );
}