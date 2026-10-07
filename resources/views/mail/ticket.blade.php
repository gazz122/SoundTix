<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>E-Tiket SoundTix</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;">
                    <tr>
                        <td style="padding:0 16px 20px;">
                            <div style="text-align:center;font-size:20px;font-weight:bold;color:#23212e;">
                                SoundTix
                            </div>
                            <div style="text-align:center;font-size:13px;color:#64748b;margin-top:2px;">
                                E-Tiket Konser Digital
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff;border-radius:16px;padding:28px;box-shadow:0 6px 24px rgba(30,27,75,.08);">
                            <div style="font-size:22px;font-weight:bold;color:#0f172a;">
                                {{ $ticket['event_title'] }}
                            </div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:16px;">
                                <tr>
                                    <td style="padding:4px 0;font-size:13px;color:#0f172a;">
                                        <span style="color:#64748b;">{{ $ticket['ticket_day_label'] ? $ticket['ticket_day_label'].' · ' : '' }}Tanggal</span><br>
                                        <strong>{{ $ticket['event_date'] }}</strong>
                                    </td>
                                    <td style="padding:4px 0;font-size:13px;color:#0f172a;">
                                        <span style="color:#64748b;">Jam</span><br>
                                        <strong>{{ $ticket['start_time'] }}</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0;font-size:13px;color:#0f172a;">
                                        <span style="color:#64748b;">Lokasi</span><br>
                                        <strong>{{ $ticket['venue'] }}</strong>
                                    </td>
                                    <td style="padding:4px 0;font-size:13px;color:#0f172a;">
                                        <span style="color:#64748b;">Kategori</span><br>
                                        <strong>{{ $ticket['category'] }}</strong>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin-top:20px;padding-top:16px;border-top:2px dashed #e2e8f0;text-align:center;">
                                <img
                                    src="{{ $message->embedData($qrPng, 'eticket-'.Str::slug($ticket['ticket_code']).'.png') }}"
                                    alt="QR Code E-Tiket"
                                    width="240"
                                    height="240"
                                    style="display:inline-block;width:240px;height:240px;"
                                >
                                <div style="margin-top:10px;font-family:Consolas,Menlo,monospace;font-size:14px;font-weight:bold;letter-spacing:1px;color:#6b46d1;">
                                    {{ $ticket['ticket_code'] }}
                                </div>
                                <div style="margin-top:4px;font-size:12px;color:#94a3b8;">
                                    Tunjukkan QR ini di pintu masuk untuk scan.
                                </div>
                            </div>

                            <div style="margin-top:20px;font-size:12px;color:#64748b;line-height:1.6;">
                                Order: <strong style="color:#0f172a;">{{ $order['order_code'] }}</strong>
                                &nbsp;·&nbsp; Atas nama: <strong style="color:#0f172a;">{{ $order['customer_name'] }}</strong>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top:16px;text-align:center;font-size:11px;color:#94a3b8;">
                            Email ini dihasilkan otomatis oleh SoundTix. Jangan membalas email ini.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>