<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;

class RundownPayload
{
    /**
     * Build a signed payload string for a paid order and event.
     *
     * Payload format: rundown.{order_id}.{event_id}.{hmac_sha256}
     */
    public function payload(Order $order, Event $event): string
    {
        $data = "rundown.{$order->id}.{$event->id}";

        return $data.'.'.hash_hmac('sha256', $data, (string) config('app.key'));
    }

    /**
     * Verify the signature and return the referenced order and event ids.
     *
     * @return array{order_id: int, event_id: int}|null
     */
    public function parse(string $payload): ?array
    {
        $parts = explode('.', $payload);

        if (count($parts) !== 4 || $parts[0] !== 'rundown') {
            return null;
        }

        $hmac = array_pop($parts);
        $data = implode('.', $parts);

        if (! hash_equals(hash_hmac('sha256', $data, (string) config('app.key')), $hmac)) {
            return null;
        }

        return ['order_id' => (int) $parts[1], 'event_id' => (int) $parts[2]];
    }

    /**
     * Build a live rundown URL for a paid order and event.
     *
     * The page re-queries the database on every load (and polls every 30s), so
     * organizer edits show up on the buyer's phone after a refresh.
     */
    public function viewerUrl(Order $order, Event $event): string
    {
        return $this->baseUrl().'/rundown/scan?payload='.urlencode($this->payload($order, $event));
    }

    /**
     * Use the request host, but replace localhost with the machine's LAN IP so
     * a phone on the same Wi-Fi can open the QR without deploying the app.
     */
    private function baseUrl(): string
    {
        $request = request();
        $host = $request->getHost();

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) && app()->environment('local')) {
            $lanIp = $this->lanIp();

            if ($lanIp !== null) {
                $port = $request->getPort();

                return $request->getScheme().'://'.$lanIp.(in_array($port, [80, 443], true) ? '' : ':'.$port);
            }
        }

        return $request->getSchemeAndHttpHost();
    }

    private function lanIp(): ?string
    {
        $hostname = gethostname();

        if ($hostname !== false) {
            foreach (gethostbynamel($hostname) ?: [] as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ! str_starts_with($ip, '127.')) {
                    return $ip;
                }
            }
        }

        if (function_exists('socket_create')) {
            $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

            if ($socket !== false) {
                if (@socket_connect($socket, '8.8.8.8', 80)) {
                    $address = '';

                    if (@socket_getsockname($socket, $address) && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ! str_starts_with($address, '127.')) {
                        socket_close($socket);

                        return $address;
                    }
                }

                socket_close($socket);
            }
        }

        return null;
    }
}
