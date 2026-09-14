import { router, usePage } from '@inertiajs/react';
import jsQR from 'jsqr';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '@/components/AppLayout';

export default function Scanner() {
    const { flash } = usePage().props;
    const result = flash?.scan_result ?? null;

    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);

    const [cameraOn, setCameraOn] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [error, setError] = useState(null);
    const [payload, setPayload] = useState('');

    const stopCamera = () => {
        setScanning(false);
        setCameraOn(false);
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
    };

    const startCamera = async () => {
        setError(null);

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            streamRef.current = stream;

            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                await videoRef.current.play();
            }

            setCameraOn(true);
            setScanning(true);
        } catch {
            setError('Kamera tidak dapat diakses. Izinkan akses kamera atau gunakan input manual di bawah.');
        }
    };

    const submitScan = (code) => {
        router.post('/gatekeeper/validate', { payload: code }, { preserveScroll: true });
    };

    useEffect(() => {
        let animationFrame;

        const tick = () => {
            const video = videoRef.current;
            const canvas = canvasRef.current;

            if (video?.readyState === video.HAVE_ENOUGH_DATA && canvas) {
                const width = Math.min(video.videoWidth, 640);
                const scale = width / video.videoWidth;
                const height = Math.round(video.videoHeight * scale);

                canvas.width = width;
                canvas.height = height;

                const context = canvas.getContext('2d', { willReadFrequently: true });
                context.drawImage(video, 0, 0, width, height);

                const image = context.getImageData(0, 0, width, height);
                const code = jsQR(image.data, width, height, { inversionAttempts: 'dontInvert' });

                if (code?.data) {
                    setScanning(false);
                    submitScan(code.data);
                    return;
                }
            }

            animationFrame = requestAnimationFrame(tick);
        };

        if (scanning) {
            animationFrame = requestAnimationFrame(tick);
        }

        return () => cancelAnimationFrame(animationFrame);
    }, [scanning]);

    useEffect(() => stopCamera, []);

    const submitManual = (event) => {
        event.preventDefault();
        router.post('/gatekeeper/validate', { payload }, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <div className="flex flex-col gap-6">
                <header>
                    <h1 className="text-2xl font-semibold text-slate-900">Scanner Tiket</h1>
                    <p className="mt-1 text-slate-500">Pindai QR tiket untuk validasi masuk penonton.</p>
                </header>

                {result && <ValidationResult result={result} />}

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm">
                        <div className="border-b border-slate-100 px-5 py-4">
                            <h2 className="font-medium text-slate-900">Pindai dengan Kamera</h2>
                        </div>
                        <div className="p-5">
                            <div className="relative aspect-[4/3] overflow-hidden rounded-lg bg-slate-900">
                                <video ref={videoRef} playsInline muted className="absolute inset-0 h-full w-full object-cover" />
                                <canvas ref={canvasRef} className="hidden" />
                                {!cameraOn && (
                                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-1 text-slate-400 text-sm">
                                        <p className="font-medium">Kamera belum aktif</p>
                                        <p className="text-slate-500">Tampilkan seluruh QR dalam bingkai kamera.</p>
                                    </div>
                                )}
                            </div>

                            <div className="mt-4">
                                {cameraOn ? (
                                    <button
                                        type="button"
                                        onClick={stopCamera}
                                        className="w-full bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 py-2.5 px-5 rounded-lg transition-all"
                                    >
                                        Hentikan Kamera
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={startCamera}
                                        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm"
                                    >
                                        Mulai Kamera
                                    </button>
                                )}
                            </div>

                            {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
                        </div>
                    </div>

                    <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm">
                        <div className="border-b border-slate-100 px-5 py-4">
                            <h2 className="font-medium text-slate-900">Input Manual</h2>
                        </div>
                        <form onSubmit={submitManual} className="p-5 flex flex-col gap-4">
                            <div>
                                <label htmlFor="payload" className="block text-sm font-medium text-slate-700 mb-1.5">
                                    Payload QR
                                </label>
                                <input
                                    id="payload"
                                    type="text"
                                    value={payload}
                                    onChange={(event) => setPayload(event.target.value)}
                                    className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                                    placeholder="tempel payload QR dari tiket"
                                />
                                <p className="mt-1.5 text-xs text-slate-500">
                                    Untuk pengecekan cepat tanpa kamera, mis. dari email tiket atau saat demo.
                                </p>
                            </div>
                            <button
                                type="submit"
                                className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 py-2.5 px-5 rounded-lg transition-all"
                            >
                                Validasi Manual
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function ValidationResult({ result }) {
    const accepted = result.status === 'success';
    const panelClass = accepted
        ? 'bg-emerald-50 border-emerald-200'
        : 'bg-red-50 border-red-200';
    const badgeClass = accepted ? 'bg-emerald-600' : 'bg-red-600';
    const accentClass = accepted ? 'text-emerald-900' : 'text-red-900';

    return (
        <div className={`border rounded-xl p-5 shadow-sm ${panelClass}`}>
            <div className="flex items-center gap-3">
                <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white font-bold ${badgeClass}`}>
                    {accepted ? '✓' : '!'}
                </span>
                <div>
                    <p className={`font-semibold ${accentClass}`}>{result.message}</p>
                    {result.ticket && (
                        <p className={`mt-0.5 text-sm ${accepted ? 'text-emerald-700' : 'text-red-700'}`}>
                            {result.ticket.status_label} · {result.ticket.event_title}
                        </p>
                    )}
                </div>
            </div>

            {result.ticket && accepted && (
                <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <Detail label="Event" value={result.ticket.event_title} />
                    <Detail label="Tanggal" value={formatDate(result.ticket.event_date)} />
                    <Detail label="Kategori" value={result.ticket.category} />
                    <Detail label="No. Order" value={result.ticket.order_code} />
                    <Detail label="Kode Tiket" value={result.ticket.ticket_code} />
                    <Detail label="Waktu Validasi" value={formatDateTime(result.ticket.used_at)} />
                </div>
            )}
        </div>
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wider text-slate-500">{label}</p>
            <p className="mt-0.5 font-medium text-slate-800">{value}</p>
        </div>
    );
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('id-ID', { dateStyle: 'long' }) : '-';
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}