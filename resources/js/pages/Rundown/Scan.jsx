import { Link, router, usePage } from '@inertiajs/react';
import jsQR from 'jsqr';
import { useEffect, useRef, useState } from 'react';
import PublicLayout from '@/components/PublicLayout';

export default function Scan() {
    const { errors } = usePage().props;
    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);
    const lastScanRef = useRef(null);

    const [cameraOn, setCameraOn] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [error, setError] = useState(null);
    const [payload, setPayload] = useState('');

    function stopCamera() {
        setScanning(false);
        setCameraOn(false);
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
    }

    async function startCamera() {
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
            setError('Kamera tidak dapat diakses. Izinkan akses kamera atau tempel payload secara manual.');
        }
    }

    function openPayload(code) {
        const value = code.trim();

        if (value !== '') {
            router.get('/rundown/scan', { payload: value });
        }
    }

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
                        const code = codes.find((entry) => entry.format === 'qr_code' || entry.format === 'qr');
                        raw = code?.rawValue ?? null;
                    } catch {
                        // Native detector failed this frame; jsQR below is the fallback.
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
                        raw = jsQR(image.data, width, height, { inversionAttempts: 'dontInvert' })?.data ?? null;
                    }
                }

                if (raw) {
                    absentFrames = 0;

                    if (lastScanRef.current !== raw) {
                        lastScanRef.current = raw;
                        stopCamera();
                        openPayload(raw);
                        return;
                    }
                } else {
                    absentFrames += 1;

                    if (absentFrames >= 15) {
                        lastScanRef.current = null;
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

    function submitManual(event) {
        event.preventDefault();
        openPayload(payload);
    }

    return (
        <PublicLayout>
            <div className="mx-auto max-w-2xl">
                <div className="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600">Payload Rundown</p>
                    <h1 className="mt-1 text-2xl font-semibold text-slate-900">Scan QR Rundown</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Pindai QR payload dari halaman Tiket Saya untuk melihat jadwal acara. QR ini bukan tiket
                        masuk konser.
                    </p>
                    <Link href="/orders" className="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-700">
                        ← Kembali ke Tiket Saya
                    </Link>
                </div>

                {errors?.payload && (
                    <p className="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {errors.payload}
                    </p>
                )}

                <div className="mt-6 overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                    <div className="border-b border-slate-200/80 px-5 py-4">
                        <h2 className="font-medium text-slate-900">Pindai dengan Kamera</h2>
                    </div>
                    <div className="p-5">
                        <div className="relative aspect-[4/3] overflow-hidden rounded-lg bg-slate-900">
                            <video ref={videoRef} playsInline muted className="absolute inset-0 h-full w-full object-cover" />
                            <canvas ref={canvasRef} className="hidden" />
                            {!cameraOn && (
                                <div className="absolute inset-0 flex flex-col items-center justify-center gap-1 text-sm text-slate-400">
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
                                    className="w-full rounded-lg border border-slate-300 bg-slate-100 px-5 py-2.5 text-slate-700 transition-all hover:bg-slate-200"
                                >
                                    Hentikan Kamera
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={startCamera}
                                    className="w-full rounded-lg bg-indigo-600 px-5 py-2.5 font-medium text-white shadow-sm transition-all hover:bg-indigo-700"
                                >
                                    Mulai Kamera
                                </button>
                            )}
                        </div>

                        {error && <p className="mt-3 text-sm text-rose-600">{error}</p>}
                    </div>
                </div>

                <form onSubmit={submitManual} className="mt-6 overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                    <div className="border-b border-slate-200/80 px-5 py-4">
                        <h2 className="font-medium text-slate-900">Input Manual</h2>
                    </div>
                    <div className="flex flex-col gap-4 p-5">
                        <div>
                            <label htmlFor="payload" className="mb-1.5 block text-sm font-medium text-slate-700">
                                Payload QR
                            </label>
                            <input
                                id="payload"
                                type="text"
                                value={payload}
                                onChange={(event) => setPayload(event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400 transition-all focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                placeholder="rundown.12.3.abc..."
                            />
                            <p className="mt-1.5 text-xs text-slate-400">
                                Tempel payload dari modal QR untuk demo di perangkat yang sama.
                            </p>
                        </div>
                        <button
                            type="submit"
                            className="rounded-lg border border-slate-300 bg-slate-100 px-5 py-2.5 text-slate-700 transition-all hover:bg-slate-200"
                        >
                            Buka Rundown
                        </button>
                    </div>
                </form>
            </div>
        </PublicLayout>
    );
}
