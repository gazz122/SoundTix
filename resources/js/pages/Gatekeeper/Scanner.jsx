import { Link, router, usePage } from '@inertiajs/react';
import jsQR from 'jsqr';
import { useEffect, useRef, useState } from 'react';
import Logo from '@/components/Logo';

export default function Scanner({ rundowns = [] }) {
    const { flash } = usePage().props;
    const result = flash?.scan_result ?? null;

    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);
    const lastSubmitRef = useRef(null);

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
        router.post('/dashboard/scan/validate', { payload: code }, { preserveScroll: true });
    };

    useEffect(() => {
        if (!cameraOn || !result) return;
        setScanning(true);
    }, [result, cameraOn]);

    useEffect(() => {
        let animationFrame;
        let absentFrames = 0;

        const getDetector = () => {
            if (!('BarcodeDetector' in window)) return null;

            try {
                return new BarcodeDetector({ formats: ['qr_code'] });
            } catch {
                try {
                    return new BarcodeDetector({ formats: ['qr'] });
                } catch {
                    return null;
                }
            }
        };

        const detector = getDetector();

        const tick = async () => {
            const video = videoRef.current;

            if (video?.readyState === video.HAVE_ENOUGH_DATA) {
                let raw = null;

                if (detector) {
                    try {
                        const codes = await detector.detect(video);
                        const code = codes.find((c) => c.format === 'qr_code' || c.format === 'qr');
                        raw = code?.rawValue ?? null;
                    } catch {
                        // native detector hit an error; fall back to jsQR this frame
                    }
                }

                if (raw === null) {
                    const canvas = canvasRef.current;

                    if (canvas) {
                        const width = Math.min(video.videoWidth, 640);
                        const scale = width / video.videoWidth;
                        const height = Math.round(video.videoHeight * scale);

                        canvas.width = width;
                        canvas.height = height;

                        const context = canvas.getContext('2d', { willReadFrequently: true });
                        context.drawImage(video, 0, 0, width, height);

                        const image = context.getImageData(0, 0, width, height);
                        const code = jsQR(image.data, width, height, { inversionAttempts: 'dontInvert' });
                        raw = code?.data ?? null;
                    }
                }

                if (raw) {
                    absentFrames = 0;

                    if (lastSubmitRef.current !== raw) {
                        lastSubmitRef.current = raw;
                        setScanning(false);
                        submitScan(raw);
                        return;
                    }
                } else {
                    absentFrames += 1;

                    if (absentFrames >= 15) {
                        lastSubmitRef.current = null;
                    }
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
        router.post('/dashboard/scan/validate', { payload }, { preserveScroll: true });
    };

    return (
        <div className="dark min-h-screen bg-slate-950 text-slate-800 font-sans">
            <header className="border-b border-slate-800/60 bg-slate-950/95 backdrop-blur sticky top-0 z-20">
                <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
                    <Link href="/dashboard/scan" className="flex items-center gap-3">
                        <Logo dark withText={false} iconClassName="h-9 w-9" />
                        <div>
                            <p className="text-base font-semibold leading-tight text-white">Scanner Tiket</p>
                            <p className="text-xs text-slate-400">Validasi masuk penonton</p>
                        </div>
                    </Link>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-300 transition-all hover:bg-slate-800 hover:text-white"
                    >
                        Keluar
                    </button>
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                {result && <ValidationResult result={result} />}

                {rundowns.length > 0 && (
                    <div className="mb-6 overflow-hidden rounded-xl border border-slate-800/60 bg-slate-900 shadow-lg">
                        <div className="border-b border-slate-800 px-5 py-4">
                            <h2 className="font-medium text-white">Rundown Acara Terdekat</h2>
                        </div>
                        <div className="divide-y divide-slate-800">
                            {rundowns.map((event) => (
                                <div key={event.id} className="px-5 py-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-medium text-white">{event.title}</p>
                                        <p className="text-xs text-slate-400">{event.event_date}</p>
                                    </div>
                                    {(event.open_gate || event.close_gate) && (
                                        <p className="mt-1 text-xs text-indigo-300">
                                            Pintu buka {event.open_gate} WIB{event.close_gate ? ` · tutup ${event.close_gate} WIB` : ''}
                                        </p>
                                    )}
                                    {event.rundowns.length > 0 ? (
                                        <ul className="mt-3 space-y-2">
                                            {event.rundowns.map((item, index) => (
                                                <li
                                                    key={`${item.title}-${index}`}
                                                    className="flex gap-3 text-sm"
                                                >
                                                    <span className="shrink-0 font-mono font-medium text-indigo-300">
                                                        {item.start_time}{item.end_time ? `–${item.end_time}` : ''}
                                                    </span>
                                                    <span className="text-slate-200">
                                                        {item.title}
                                                        {item.description && (
                                                            <span className="block text-xs text-slate-400">{item.description}</span>
                                                        )}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="mt-2 text-xs text-slate-500">Belum ada rundown sesi.</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="overflow-hidden rounded-xl border border-slate-800/60 bg-slate-900 shadow-lg">
                        <div className="border-b border-slate-800 px-5 py-4">
                            <h2 className="font-medium text-white">Pindai dengan Kamera</h2>
                        </div>
                        <div className="p-5">
                            <div className="relative aspect-[4/3] overflow-hidden rounded-lg bg-slate-950">
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
                                        className="w-full bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 py-2.5 px-5 rounded-lg transition-all"
                                    >
                                        Hentikan Kamera
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={startCamera}
                                        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white dark:text-slate-950 font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm"
                                    >
                                        Mulai Kamera
                                    </button>
                                )}
                            </div>

                            {error && <p className="mt-3 text-sm text-red-400">{error}</p>}
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-slate-800/60 bg-slate-900 shadow-lg">
                        <div className="border-b border-slate-800 px-5 py-4">
                            <h2 className="font-medium text-white">Input Manual</h2>
                        </div>
                        <form onSubmit={submitManual} className="p-5 flex flex-col gap-4">
                            <div>
                                <label htmlFor="payload" className="block text-sm font-medium text-slate-300 mb-1.5">
                                    Payload QR
                                </label>
                                <input
                                    id="payload"
                                    type="text"
                                    value={payload}
                                    onChange={(event) => setPayload(event.target.value)}
                                    className="w-full bg-slate-950 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all"
                                    placeholder="tempel payload QR dari tiket"
                                />
                                <p className="mt-1.5 text-xs text-slate-400">
                                    Untuk pengecekan cepat tanpa kamera, mis. dari email tiket atau saat demo.
                                </p>
                            </div>
                            <button
                                type="submit"
                                className="bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 py-2.5 px-5 rounded-lg transition-all"
                            >
                                Validasi Manual
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    );
}

function ValidationResult({ result }) {
    const accepted = result.status === 'success';
    const panelClass = accepted
        ? 'bg-emerald-500/10 border-emerald-500/30'
        : 'bg-red-500/10 border-red-500/30';
    const badgeClass = accepted ? 'bg-emerald-500' : 'bg-red-500';
    const accentClass = accepted ? 'text-emerald-300' : 'text-red-300';

    return (
        <div className={`border rounded-xl p-5 shadow-lg ${panelClass}`}>
            <div className="flex items-center gap-3">
                <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white font-bold dark:text-slate-950 ${badgeClass}`}>
                    {accepted ? '✓' : '!'}
                </span>
                <div>
                    <p className={`font-semibold ${accentClass}`}>{result.message}</p>
                    {result.ticket && (
                        <p className={`mt-0.5 text-sm ${accepted ? 'text-emerald-400' : 'text-red-400'}`}>
                            {result.ticket.status_label} · {result.ticket.event_title}
                        </p>
                    )}
                </div>
            </div>

            {result.ticket && accepted && (
                <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-slate-200">
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
            <p className="text-xs font-medium uppercase tracking-wider text-slate-400">{label}</p>
            <p className="mt-0.5 font-medium text-slate-100">{value}</p>
        </div>
    );
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('id-ID', { dateStyle: 'long' }) : '-';
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}