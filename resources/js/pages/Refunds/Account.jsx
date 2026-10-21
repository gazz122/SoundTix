import { Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';
import { rupiah } from '@/lib/format';

const statusStyles = {
    pending: 'border-amber-200 bg-amber-50/60 text-amber-700',
    waiting_bank_details: 'border-orange-200 bg-orange-50/60 text-orange-700',
    processing: 'border-blue-200 bg-blue-50/60 text-blue-700',
    completed: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
    failed: 'border-rose-200 bg-rose-50/60 text-rose-700',
    refunded: 'border-blue-200 bg-blue-50/60 text-blue-700',
};

export default function Account({ refund }) {
    const { flash } = usePage().props;
    const form = useForm({
        bank_name: refund.bank_name ?? '',
        account_number: refund.account_number ?? '',
        account_holder_name: refund.account_holder_name ?? '',
    });

    function submit(event) {
        event.preventDefault();
        form.post(`/refunds/${refund.id}/account`, { preserveScroll: true });
    }

    const canSubmit = refund.can_submit;

    return (
        <AppLayout>
            <div className="mx-auto max-w-xl">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold text-slate-900">Data Rekening Refund</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Isi rekening tujuan supaya dana pesanan yang dibatalkan bisa dikembalikan.
                    </p>
                </div>

                {flash.success && <Flash type="success">{flash.success}</Flash>}
                {flash.error && <Flash type="error">{flash.error}</Flash>}

                <div className="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-slate-200/80 bg-slate-50/60 px-4 py-3 text-sm">
                        <span className="font-mono text-slate-800">{refund.order_code}</span>
                        <span className="text-slate-600">{refund.event_title ?? '-'}</span>
                        <span className="ml-auto font-medium tabular-nums text-slate-900">{rupiah(refund.amount)}</span>
                    </div>

                    <div className="mt-3 flex items-center gap-2">
                        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-medium ${statusStyles[refund.status] ?? statusStyles.pending}`}>
                            {refund.status_label}
                        </span>
                        {refund.is_automated_payout && (
                            <span className="text-xs text-slate-500">Payout otomatis via Midtrans Iris</span>
                        )}
                    </div>

                    {canSubmit ? (
                        <form onSubmit={submit} className="mt-5 space-y-3">
                            <label className="block space-y-1">
                                <span className="block text-xs font-medium text-slate-700">
                                    Nama Bank <span className="text-rose-500">*</span>
                                </span>
                                <input
                                    type="text"
                                    value={form.data.bank_name}
                                    onChange={(event) => form.setData('bank_name', event.target.value)}
                                    placeholder="Contoh: BCA"
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                                />
                                {form.errors.bank_name && <p className="text-xs text-rose-600">{form.errors.bank_name}</p>}
                            </label>

                            <label className="block space-y-1">
                                <span className="block text-xs font-medium text-slate-700">
                                    Nomor Rekening <span className="text-rose-500">*</span>
                                </span>
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    value={form.data.account_number}
                                    onChange={(event) => form.setData('account_number', event.target.value)}
                                    placeholder="Contoh: 1234567890"
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                                />
                                {form.errors.account_number && <p className="text-xs text-rose-600">{form.errors.account_number}</p>}
                            </label>

                            <label className="block space-y-1">
                                <span className="block text-xs font-medium text-slate-700">
                                    Nama Pemilik Rekening <span className="text-rose-500">*</span>
                                </span>
                                <input
                                    type="text"
                                    value={form.data.account_holder_name}
                                    onChange={(event) => form.setData('account_holder_name', event.target.value)}
                                    placeholder="Nama sesuai buku rekening"
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                                />
                                {form.errors.account_holder_name && <p className="text-xs text-rose-600">{form.errors.account_holder_name}</p>}
                            </label>

                            <div className="flex justify-end pt-1">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-indigo-700 disabled:opacity-60"
                                >
                                    {form.processing ? 'Menyimpan...' : 'Simpan Data Rekening'}
                                </button>
                            </div>
                        </form>
                    ) : (
                        <p className="mt-5 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700">
                            Refund pesanan ini sudah selesai diproses.
                        </p>
                    )}
                </div>

                <div className="mt-4 text-center">
                    <Link href="/orders" className="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                        Kembali ke daftar pesanan
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}

function Flash({ type, children }) {
    const styles = {
        success: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
        error: 'border-rose-200 bg-rose-50/60 text-rose-700',
    };

    return <div className={`mb-6 rounded-lg border px-4 py-3 text-sm ${styles[type]}`}>{children}</div>;
}
