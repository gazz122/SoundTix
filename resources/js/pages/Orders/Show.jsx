import { Link, router, usePage } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import html2pdf from 'html2pdf.js';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '@/components/AppLayout';
import OrderStatusBadge from '@/components/OrderStatusBadge';
import { formatDate, rupiah } from '@/lib/format';

const BANKS = [
    { value: 'bca', label: 'BCA' },
    { value: 'mandiri', label: 'Bank Mandiri' },
    { value: 'bni', label: 'Bank BNI' },
    { value: 'bri', label: 'Bank BRI' },
    { value: 'permata', label: 'Bank Permata' },
    { value: 'cimb', label: 'CIMB Niaga' },
];

export default function Show({ order }) {
    const { flash } = usePage().props;
    const pending = order.status === 'pending';
    const paid = order.status === 'paid';
    const activePayment = order.payments.find((payment) => payment.status === 'pending');
    const latestRefund = order.refunds[0];

    // Covers payments settled outside this tab (VA transfer from another device,
    // or a Snap popup the user closed) so the order reflects reality without a
    // manual click. Bounded so a gateway outage cannot poll forever.
    useAutoCheckPendingPayment(activePayment);

    return (
        <AppLayout>
            <div className="mb-8">
                <Link href="/orders" className="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                    ← Kembali ke Tiket Saya
                </Link>
            </div>

            {flash.success && (
                <div className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="mb-6 rounded-lg border border-rose-200 bg-rose-50/60 px-4 py-3 text-sm text-rose-700">
                    {flash.error}
                </div>
            )}

            <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="font-mono text-sm text-slate-400">No. Pesanan</p>
                        <h1 className="text-2xl font-semibold text-slate-900">{order.order_code}</h1>
                        <p className="mt-1 text-sm text-slate-500">Dibuat {order.created_at}</p>
                    </div>
                    <OrderStatusBadge status={order.status} />
                </div>

                {pending && (
                    <div className="mt-6 rounded-lg border border-amber-200 bg-amber-50/60 px-4 py-3">
                        <p className="text-sm font-medium text-amber-700">
                            Segera selesaikan pembayaran.
                        </p>
                        <Countdown from={order.expires_at} />
                    </div>
                )}
            </div>

            {pending && (
                <div className="mt-6 bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-6">
                    <h2 className="font-semibold text-slate-900">Pembayaran</h2>

                    {activePayment ? (
                        <ActivePayment order={order} payment={activePayment} />
                    ) : (
                        <PaymentMethods order={order} />
                    )}
                </div>
            )}

            {paid && (
                <div className="mt-6 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3">
                    <p className="text-sm font-medium text-emerald-700">
                        Pembayaran lunas {order.paid_at ? `pada ${order.paid_at}` : ''}.
                    </p>
                    <p className="mt-1 text-sm text-emerald-600">
                        {order.tickets.length} e-tiket telah diterbitkan.
                    </p>
                </div>
            )}

            {latestRefund && (
                <div
                    className={`mt-6 rounded-lg border px-4 py-3 ${
                        latestRefund.status === 'failed'
                            ? 'border-rose-200 bg-rose-50/60'
                            : 'border-amber-200 bg-amber-50/60'
                    }`}
                >
                    <p
                        className={`text-sm font-medium ${
                            latestRefund.status === 'failed' ? 'text-rose-700' : 'text-amber-700'
                        }`}
                    >
                        {refundMessage(latestRefund.status)}
                    </p>
                    {latestRefund.reason && (
                        <p className="mt-1 text-sm text-slate-600">Alasan: {latestRefund.reason}</p>
                    )}
                    {latestRefund.status === 'waiting_bank_details' && (
                        <Link
                            href={`/refunds/${latestRefund.id}/account`}
                            className="mt-3 inline-flex rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-indigo-700"
                        >
                            Isi data rekening
                        </Link>
                    )}
                </div>
            )}

            {order.tickets.length > 0 && (
                <div className="mt-8">
                    <h2 className="mb-4 font-semibold text-slate-900">E-Tiket</h2>
                    <div className="grid gap-6 sm:grid-cols-2">
                        {order.tickets.map((ticket) => (
                            <ETicket key={ticket.id} ticket={ticket} order={order} />
                        ))}
                    </div>
                </div>
            )}

            {order.rundown_links?.length > 0 && <RundownQrSection links={order.rundown_links} />}

            <div className="mt-6 bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm">
                <div className="divide-y divide-slate-200/80">
                    {order.items.map((item) => (
                        <div key={item.id} className="px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-medium text-slate-900">{item.category}</p>
                                {item.event && (
                                    <Link href={`/events/${item.event.slug}`} className="text-sm text-indigo-600 hover:text-indigo-700">
                                        {item.event.title} · {item.event.ticket_day_label ? `${item.event.ticket_day_label} · ` : ''}
                                        {formatDate(item.event.event_date)}
                                    </Link>
                                )}
                            </div>
                            <div className="text-right">
                                <p className="text-sm text-slate-500 tabular-nums">
                                    {rupiah(item.price_per_unit)} x {item.quantity}
                                </p>
                                <p className="font-semibold text-slate-900 tabular-nums">{rupiah(item.subtotal)}</p>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="px-6 py-4 bg-slate-50 border-t border-slate-200/80 space-y-1 text-sm">
                    <Row label="Subtotal" value={rupiah(order.total_amount)} />
                    <Row label="Biaya Layanan" value={rupiah(order.admin_fee)} />
                    <div className="flex items-center justify-between pt-2 text-base font-semibold text-slate-900">
                        <span>Total</span>
                        <span className="tabular-nums">{rupiah(order.grand_total)}</span>
                    </div>
                </div>
            </div>

            {pending && (
                <div className="mt-6 flex justify-end">
                    <button
                        type="button"
                        onClick={() => {
                            if (window.confirm('Batalkan pesanan ini? Stok tiket akan dikembalikan.')) {
                                router.post(`/orders/${order.order_code}/cancel`, {}, { preserveScroll: true });
                            }
                        }}
                        className="bg-white hover:bg-rose-50 text-rose-600 border border-rose-300 font-medium py-2.5 px-5 rounded-lg transition-all"
                    >
                        Batalkan Pesanan
                    </button>
                </div>
            )}
        </AppLayout>
    );
}

function refundMessage(status) {
    if (status === 'failed') {
        return 'Refund belum berhasil diproses. Tim kami sedang menfollow up.';
    }

    if (status === 'waiting_bank_details') {
        return 'Kami butuh rekening tujuan untuk mengirim dana pengembalian Anda.';
    }

    return 'Pengembalian dana sedang diproses ke metode pembayaran awal Anda.';
}

function PaymentMethods({ order }) {
    const [choosingBank, setChoosingBank] = useState(false);
    const [bank, setBank] = useState(null);
    const bankLabel = BANKS.find((item) => item.value === bank)?.label;

    return (
        <div>
            <p className="mt-1 text-sm text-slate-500">Pilih metode pembayaran untuk melanjutkan.</p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <MethodButton
                    label="Transfer Bank"
                    hint="BCA, Mandiri, BNI, BRI, Permata, CIMB"
                    active={choosingBank}
                    onClick={() => {
                        setChoosingBank((value) => !value);
                        setBank(null);
                    }}
                />
                <MethodButton label="QRIS / E-Wallet" hint="GoPay, OVO, DANA, ShopeePay, LinkAja" onClick={() => selectMethod(order, 'qris')} />
            </div>

            {choosingBank && (
                <div className="mt-4 rounded-xl border border-slate-200/80 p-4">
                    <p className="text-sm font-medium text-slate-700">Pilih bank tujuan transfer</p>
                    <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {BANKS.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                onClick={() => setBank(item.value)}
                                className={`rounded-lg border px-3 py-2.5 text-sm font-medium transition-all ${
                                    bank === item.value
                                        ? 'border-indigo-600 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-600'
                                        : 'border-slate-300 bg-white text-slate-700 hover:border-indigo-600'
                                }`}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        disabled={bank === null}
                        onClick={() => selectMethod(order, 'bank_transfer', bank)}
                        className="mt-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {bank === null ? 'Pilih bank terlebih dahulu' : `Lanjutkan Transfer ${bankLabel}`}
                    </button>
                </div>
            )}
        </div>
    );
}

function ActivePayment({ order, payment }) {
    if (payment.payment_type === 'bank_transfer') {
        return <BankTransferPanel order={order} payment={payment} />;
    }

    return <QrisPanel order={order} payment={payment} />;
}

function BankTransferPanel({ order, payment }) {
    const [copied, setCopied] = useState(false);
    const bankLabel = BANKS.find((item) => item.value === payment.bank)?.label ?? payment.bank;

    async function copyVa() {
        try {
            await navigator.clipboard.writeText(payment.va_number);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    }

    return (
        <div className="mt-4 space-y-4">
            <div className="rounded-xl border border-slate-200/80 p-4">
                <p className="text-sm font-medium text-slate-700">Nomor Virtual Account {bankLabel}</p>
                <div className="mt-2 flex flex-wrap items-center gap-3">
                    <span className="font-mono text-xl font-semibold tracking-wider text-slate-900">{payment.va_number}</span>
                    <button
                        type="button"
                        onClick={copyVa}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                    >
                        {copied ? '✓ Tersalin' : 'Salin'}
                    </button>
                </div>
                <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-sm">
                    <span className="text-slate-500">Total Pembayaran</span>
                    <span className="font-semibold text-slate-900 tabular-nums">{rupiah(payment.gross_amount)}</span>
                </div>
            </div>

            <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4">
                <p className="text-sm font-semibold text-slate-900">Cara Bayar via m-Banking</p>
                <ol className="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-600">
                    <li>Buka aplikasi m-Banking Anda dan pilih menu Transfer.</li>
                    <li>Pilih "Virtual Account" / "Billing", lalu masukkan nomor VA di atas.</li>
                    <li>Periksa nama tujuan dan nominal pembayaran, lalu konfirmasi dengan PIN.</li>
                </ol>
            </div>

            <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4">
                <p className="text-sm font-semibold text-slate-900">Cara Bayar via ATM</p>
                <ol className="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-600">
                    <li>Masukkan kartu dan PIN Anda.</li>
                    <li>Pilih Transaksi Lainnya → Transfer.</li>
                    <li>Masukkan nomor Virtual Account di atas beserta nominal, lalu konfirmasi.</li>
                </ol>
            </div>

            <CheckPaymentButton payment={payment} />
        </div>
    );
}

function QrisPanel({ order, payment }) {
    return (
        <div className="mt-4 space-y-4">
            <div className="rounded-xl border border-slate-200/80 p-6 flex flex-col items-center">
                <div className="rounded-lg bg-white p-3 shadow-inner">
                    <QRCodeSVG value={payment.qr_string} size={200} level="M" />
                </div>
                <p className="mt-4 text-sm text-slate-500 text-center max-w-sm">
                    Buka aplikasi e-wallet (GoPay, OVO, DANA, ShopeePay, LinkAja) atau m-Banking,
                    pilih menu QRIS, lalu scan kode QR di atas.
                </p>
                <p className="mt-3 text-sm">
                    <span className="text-slate-500">Total Pembayaran </span>
                    <span className="font-semibold text-slate-900 tabular-nums">{rupiah(payment.gross_amount)}</span>
                </p>
            </div>

            <CheckPaymentButton payment={payment} />
        </div>
    );
}

const AUTO_CHECK_INTERVAL_MS = 5000;
const AUTO_CHECK_MAX_ATTEMPTS = 6;

function useAutoCheckPendingPayment(payment) {
    const attempts = useRef(0);

    useEffect(() => {
        if (!payment) {
            attempts.current = 0;
            return;
        }

        const id = setInterval(() => {
            if (attempts.current >= AUTO_CHECK_MAX_ATTEMPTS) {
                clearInterval(id);
                return;
            }

            attempts.current += 1;
            router.post(`/payments/${payment.id}/check`, {}, { preserveScroll: true });
        }, AUTO_CHECK_INTERVAL_MS);

        return () => clearInterval(id);
    }, [payment?.id]);
}

function CheckPaymentButton({ payment }) {
    const [checking, setChecking] = useState(false);

    return (
        <button
            type="button"
            disabled={checking}
            onClick={() => {
                setChecking(true);
                router.post(`/payments/${payment.id}/check`, {}, {
                    preserveScroll: true,
                    onFinish: () => setChecking(false),
                });
            }}
            className="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm disabled:opacity-60"
        >
            {checking ? 'Memeriksa pembayaran...' : 'Cek Status Pembayaran'}
        </button>
    );
}

function ETicket({ ticket, order }) {
    const [busy, setBusy] = useState(false);
    const refId = `eticket-${ticket.id}`;

    async function download() {
        const element = document.getElementById(refId);
        if (!element) return;

        setBusy(true);
        try {
            await html2pdf()
                .set({
                    margin: 4,
                    filename: `E-Ticket-${ticket.ticket_code}.pdf`,
                    image: { type: 'jpeg', quality: 0.95 },
                    html2canvas: { scale: 2, useCORS: true },
                    jsPDF: { unit: 'mm', format: 'a6', orientation: 'portrait' },
                })
                .from(element)
                .save();
        } finally {
            setBusy(false);
        }
    }

    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <span className="text-sm text-slate-500">{ticket.category}</span>
                {ticket.status === 'active' && (
                    <span className="inline-flex items-center gap-2 text-xs font-medium text-emerald-700">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        Aktif
                    </span>
                )}
            </div>
            <button
                type="button"
                disabled={busy}
                onClick={download}
                className="mb-3 w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm disabled:opacity-60"
            >
                {busy ? 'Menyiapkan PDF...' : 'Unduh E-Ticket (PDF)'}
            </button>
            <TicketCard refId={refId} ticket={ticket} order={order} />
        </div>
    );
}

// Inline styles only (no Tailwind classes): html2canvas cannot parse the
// oklch colors Tailwind v4 emits, which would break html2pdf.js rendering.
const ticketStyles = {
    card: {
        background: '#ffffff',
        borderRadius: 24,
        border: '1px solid #e2e8f0',
        boxShadow: '0 1px 3px rgba(15, 23, 42, 0.10)',
        overflow: 'hidden',
    },
    band: { background: '#6b46d1', padding: '18px 20px', color: '#ffffff' },
    row: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 },
    brand: { fontSize: 12, fontWeight: 700, letterSpacing: 2, textTransform: 'uppercase' },
    ticketCode: { fontSize: 11, fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace', opacity: 0.9 },
    title: { fontSize: 19, fontWeight: 800, marginTop: 10, lineHeight: 1.25, color: '#ffffff' },
    subtitle: { fontSize: 12, opacity: 0.92, marginTop: 3, color: '#ffffff' },
    body: { padding: '14px 20px' },
    metaRow: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 16, padding: '7px 0', borderBottom: '1px dashed #e2e8f0', fontSize: 12 },
    metaLabel: { color: '#64748b', whiteSpace: 'nowrap' },
    metaValue: { color: '#0f172a', fontWeight: 700, textAlign: 'right' },
    qrWrap: { display: 'flex', justifyContent: 'center', padding: '16px 0 10px' },
    qrNote: { textAlign: 'center', fontSize: 11, color: '#64748b', lineHeight: 1.5 },
    stub: { borderTop: '2px dashed #d0c6f3', padding: '12px 18px', background: '#f8fafc' },
    skTitle: { fontSize: 9.5, fontWeight: 800, letterSpacing: 1.5, textTransform: 'uppercase', color: '#6b46d1' },
    skItem: { fontSize: 9, color: '#475569', lineHeight: 1.5, marginTop: 3 },
};

function TicketCard({ refId, ticket, order }) {
    return (
        <div id={refId} style={ticketStyles.card}>
            <div style={ticketStyles.band}>
                <div style={ticketStyles.row}>
                    <span style={ticketStyles.brand}>SoundTix · E-Tiket</span>
                    <span style={{ ...ticketStyles.ticketCode, color: '#e0e7ff' }}>{ticket.ticket_code}</span>
                </div>
                <div style={ticketStyles.title}>{ticket.event_title}</div>
                <div style={ticketStyles.subtitle}>
                    {ticket.category} · {ticket.ticket_day_label ? `${ticket.ticket_day_label} · ` : ''}
                    {formatDate(ticket.event_date)} · {order.order_code}
                </div>
            </div>

            <div style={ticketStyles.body}>
                <div style={ticketStyles.metaRow}>
                    <span style={ticketStyles.metaLabel}>Acara</span>
                    <span style={{ ...ticketStyles.metaValue, maxWidth: '70%' }}>{ticket.event_title}</span>
                </div>
                <div style={ticketStyles.metaRow}>
                    <span style={ticketStyles.metaLabel}>Hari</span>
                    <span style={ticketStyles.metaValue}>{ticket.ticket_day_label ?? 'Sesuai jadwal event'}</span>
                </div>
                <div style={ticketStyles.metaRow}>
                    <span style={ticketStyles.metaLabel}>Tanggal</span>
                    <span style={ticketStyles.metaValue}>{formatDate(ticket.event_date)}</span>
                </div>
                {ticket.start_time && (
                    <div style={ticketStyles.metaRow}>
                        <span style={ticketStyles.metaLabel}>Jam Mulai</span>
                        <span style={ticketStyles.metaValue}>{ticket.start_time}</span>
                    </div>
                )}
                {ticket.venue && (
                    <div style={ticketStyles.metaRow}>
                        <span style={ticketStyles.metaLabel}>Lokasi</span>
                        <span style={{ ...ticketStyles.metaValue, maxWidth: '70%' }}>{ticket.venue}</span>
                    </div>
                )}
                <div style={ticketStyles.metaRow}>
                    <span style={ticketStyles.metaLabel}>Kategori</span>
                    <span style={ticketStyles.metaValue}>{ticket.category}</span>
                </div>
                <div style={ticketStyles.metaRow}>
                    <span style={ticketStyles.metaLabel}>No. Pesanan</span>
                    <span style={{ ...ticketStyles.metaValue, fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace' }}>{order.order_code}</span>
                </div>

                <div style={ticketStyles.qrWrap}>
                    <QRCodeSVG value={ticket.qr_payload} size={240} level="M" />
                </div>
                <p style={ticketStyles.qrNote}>
                    Tunjukkan QR ini beserta identitas Anda kepada petugas saat check-in.
                </p>
            </div>

            <div style={ticketStyles.stub}>
                <div style={ticketStyles.skTitle}>Syarat &amp; Ketentuan</div>
                <div style={ticketStyles.skItem}>1. Tiket berlaku untuk 1 (satu) orang dan tidak dapat dipindahtangankan.</div>
                <div style={ticketStyles.skItem}>2. Tiket yang rusak, hilang, atau QR telah digunakan tidak dapat diproses ulang.</div>
                <div style={ticketStyles.skItem}>3. Panitia berhak menolak masuk jika tiket terbukti palsu atau duplikat.</div>
                <div style={ticketStyles.skItem}>4. Masuknya penonton mengikuti ketentuan dan kebijakan lokasi acara.</div>
            </div>
        </div>
    );
}

function selectMethod(order, method, bank) {
    const payload = { method, ...(bank ? { bank } : {}) };
    router.post(`/orders/${order.order_code}/pay`, payload, { preserveScroll: true });
}

function MethodButton({ label, hint, onClick, active }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`group text-left bg-white border rounded-xl p-4 transition-all ${
                active ? 'border-indigo-600 ring-1 ring-indigo-600' : 'border-slate-300 hover:border-indigo-600 hover:shadow-sm'
            }`}
        >
            <p className="font-medium text-slate-900 group-hover:text-indigo-700">{label}</p>
            <p className="mt-0.5 text-sm text-slate-500">{hint}</p>
        </button>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex items-center justify-between text-slate-600">
            <span>{label}</span>
            <span>{value}</span>
        </div>
    );
}

function Countdown({ from }) {
    const [remaining, setRemaining] = useState(remainingSeconds(from));

    useEffect(() => {
        const timer = setInterval(() => setRemaining(remainingSeconds(from)), 1000);
        return () => clearInterval(timer);
    }, [from]);

    useEffect(() => {
        if (remaining === 0) {
            router.reload({ only: ['order'] });
        }
    }, [remaining]);

    if (remaining <= 0) {
        return <p className="mt-1 text-sm text-rose-600">Batas waktu pembayaran telah habis.</p>;
    }

    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;

    return (
        <p className="mt-1 text-sm text-amber-700" aria-live="polite">
            Sisa waktu: <span className="font-mono font-semibold tabular-nums">{String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}</span>
        </p>
    );
}

function remainingSeconds(expiresAtIso) {
    const diff = new Date(expiresAtIso).getTime() - Date.now();
    return Math.max(0, Math.floor(diff / 1000));
}

function RundownQrSection({ links }) {
    const [activeLink, setActiveLink] = useState(null);

    return (
        <div className="mt-8">
            <h2 className="mb-1 font-semibold text-slate-900">QR Rundown Acara</h2>
            <p className="mb-4 text-sm text-slate-500">
                Scan QR ini dengan kamera HP untuk membuka rundown. Halaman rundown selalu mengambil jadwal
                terbaru dari server, jadi kalau organizer mengubah jadwal, cukup refresh halaman di HP.
                QR ini hanya untuk jadwal, bukan tiket masuk.
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                {links.map((link) => (
                    <div key={link.event_id} className="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                        <p className="font-medium text-slate-900">{link.event_title}</p>
                        {link.event_date && <p className="mt-0.5 text-sm text-slate-500">{formatDate(link.event_date)}</p>}
                        <div className="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => setActiveLink(link)}
                                className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm"
                            >
                                Lihat QR Rundown
                            </button>
                            <a
                                href={link.viewer_url}
                                target="_blank"
                                rel="noreferrer"
                                className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-4 rounded-lg transition-all"
                            >
                                Buka Rundown
                            </a>
                        </div>
                    </div>
                ))}
            </div>

            {activeLink && <RundownQrModal link={activeLink} onClose={() => setActiveLink(null)} />}
        </div>
    );
}

function RundownQrModal({ link, onClose }) {
    const [copied, setCopied] = useState(false);
    const isLocalhost = /localhost|127\.0\.0\.1/.test(link.viewer_url);

    async function copyPayload() {
        try {
            await navigator.clipboard.writeText(link.payload);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div className="max-h-[90vh] w-full max-w-sm overflow-y-auto rounded-xl bg-white p-6 text-center shadow-xl">
                <h3 className="text-lg font-semibold text-slate-900">{link.event_title}</h3>
                <p className="mt-1 text-sm text-slate-500">
                    Scan QR ini dengan kamera HP. Halaman rundown akan terbuka langsung di browser.
                </p>
                <div className="mt-4 flex justify-center">
                    <QRCodeSVG value={link.viewer_url} size={240} level="L" />
                </div>
                <p className="mt-3 break-all text-xs text-slate-400">{link.viewer_url}</p>
                {isLocalhost && (
                    <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        Alamat QR masih localhost, jadi tidak bisa dibuka dari HP. Buka halaman ini lewat IP LAN
                        komputer atau domain deploy.
                    </p>
                )}
                <button
                    type="button"
                    onClick={copyPayload}
                    className="mt-2 text-xs font-medium text-indigo-600 hover:text-indigo-700"
                >
                    {copied ? 'Payload tersalin' : 'Salin payload untuk scan manual'}
                </button>
                <p className="mt-2 text-xs text-slate-500">QR ini bukan tiket masuk konser.</p>
                <button
                    type="button"
                    onClick={onClose}
                    className="mt-5 w-full bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-5 rounded-lg transition-all"
                >
                    Tutup
                </button>
            </div>
        </div>
    );
}