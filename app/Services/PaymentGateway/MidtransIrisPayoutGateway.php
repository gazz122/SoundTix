<?php

namespace App\Services\PaymentGateway;

use App\Models\Refund;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

/**
 * Midtrans Iris payouts. Iris is a separate Midtrans product: it needs its own
 * API key and an approved merchant account, so this is only reachable once
 * services.midtrans.iris_enabled is on.
 */
class MidtransIrisPayoutGateway
{
    /**
     * Send one payout and return the payout entry Iris answered with.
     *
     * The caller passes refund_key as the idempotency key. Iris reads it from the
     * X-Idempotency-Key header, not the body, so a retry after a timeout returns
     * the first payout instead of sending the money a second time.
     *
     * ponytail: Iris answers `queued`, which means the transfer was accepted, not
     * that it landed. We treat an accepted payout as the end of the job, so a
     * payout the bank rejects afterwards stays marked completed. Add the Iris
     * payout notification webhook if payouts ever need reconciling for real.
     *
     * @return array<string, mixed>
     */
    public function payout(Refund $refund, string $reference): array
    {
        $response = $this->client()
            ->withHeaders(['X-Idempotency-Key' => $reference])
            ->post($this->baseUrl().'/api/v1/payouts', [
                'payouts' => [array_filter([
                    'beneficiary_name' => $refund->account_holder_name,
                    'beneficiary_account' => $refund->account_number,
                    // Iris wants a bank code (bca, bni, bri, mandiri, ...), not the
                    // display name the buyer typed. A wrong code is a 4xx, which the
                    // job turns into a manual fallback rather than a silent no-op.
                    'beneficiary_bank' => strtolower((string) $refund->bank_name),
                    'beneficiary_email' => $refund->order?->customer?->email,
                    'amount' => number_format((float) $refund->amount, 2, '.', ''),
                    'notes' => $this->notesFor($refund),
                ], fn (mixed $value): bool => filled($value))],
            ])
            ->throw()
            ->json();

        $payout = data_get($response, 'payouts.0');

        if (! is_array($payout) || ! filled($payout['reference_no'] ?? null)) {
            throw new UnexpectedValueException('Iris did not return a payout reference number.');
        }

        return $payout;
    }

    /**
     * Iris only accepts spaces and ASCII letters/digits in a note aimed at a
     * bank, so the dashes in the order code have to go. Capped at the 100
     * character limit Iris documents.
     */
    private function notesFor(Refund $refund): string
    {
        $notes = preg_replace('/[^A-Za-z0-9 ]/', '', 'Refund '.$refund->order?->order_code);

        return substr((string) $notes, 0, 100);
    }

    public function isConfigured(): bool
    {
        return filter_var(config('services.midtrans.iris_enabled'), FILTER_VALIDATE_BOOL)
            && filled(config('services.midtrans.iris_api_key'));
    }

    private function client(): PendingRequest
    {
        $apiKey = config('services.midtrans.iris_api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new UnexpectedValueException('The Midtrans Iris API key is not configured.');
        }

        return Http::withBasicAuth($apiKey, '')
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function baseUrl(): string
    {
        return $this->isProduction()
            ? 'https://app.midtrans.com/iris'
            : 'https://app.sandbox.midtrans.com/iris';
    }

    private function isProduction(): bool
    {
        return filter_var(config('services.midtrans.is_production'), FILTER_VALIDATE_BOOL);
    }
}
