import { Link } from '@inertiajs/react';

export default function ErrorPage({ status }) {
    const title = {
        404: 'Halaman Tidak Ditemukan',
        403: 'Akses Ditolak',
        500: 'Terjadi Kesalahan Server',
        503: 'Layanan Sedang Sibuk',
    }[status] ?? 'Terjadi Kesalahan';

    const message = {
        404: 'Halaman yang Anda cari tidak ada atau telah dipindahkan.',
        403: 'Anda tidak memiliki izin untuk mengakses halaman ini.',
    }[status] ?? 'Silakan coba lagi beberapa saat kemudian.';

    return (
        <div className="min-h-screen bg-slate-50 text-slate-800 font-sans flex items-center justify-center px-4">
            <div className="text-center">
                <p className="text-6xl font-semibold text-slate-300">{status}</p>
                <h1 className="mt-4 text-xl font-semibold text-slate-900">{title}</h1>
                <p className="mt-2 text-sm text-slate-500">{message}</p>
                <Link
                    href="/"
                    className="mt-8 inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm"
                >
                    Kembali ke Beranda
                </Link>
            </div>
        </div>
    );
}