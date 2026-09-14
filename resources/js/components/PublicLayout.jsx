import { Link, usePage } from '@inertiajs/react';

export default function PublicLayout({ children }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    return (
        <div className="min-h-screen bg-slate-50 text-slate-800 font-sans">
            <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between py-3">
                    <Link href="/" className="flex items-center gap-2.5">
                        <span className="grid h-9 w-9 place-items-center rounded-xl bg-indigo-600 font-bold text-white shadow-sm">
                            S
                        </span>
                        <span className="text-lg font-semibold text-slate-900">SoundTix</span>
                    </Link>

                    <nav className="flex items-center gap-3">
                        {user ? (
                            <Link
                                href="/dashboard"
                                className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-5 rounded-lg transition-all shadow-sm"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href="/login"
                                    className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 py-2 px-5 rounded-lg transition-all"
                                >
                                    Masuk
                                </Link>
                                <Link
                                    href="/register"
                                    className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-5 rounded-lg transition-all shadow-sm"
                                >
                                    Daftar
                                </Link>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">{children}</main>

            <footer className="border-t border-slate-200/80">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-xs text-slate-400">
                    SoundTix — Platform e-ticketing konser digital
                </div>
            </footer>
        </div>
    );
}