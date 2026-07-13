<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * Sends via the official WhatsApp Business Cloud API (Meta).
     * NOTE: Meta only allows freeform text messages within a 24-hour window
     * of the customer last messaging your business number. Outside that
     * window you need an approved message "template" instead. For a
     * fresh/unverified WhatsApp Business account, expect this to fail
     * until that's set up — that's exactly why the controller falls back
     * to a wa.me link when this isn't configured or a send fails.
     */
    public function sendText(string $phone, string $message): array
    {
        $response = Http::withToken(config('services.whatsapp.token'))
            ->post('https://graph.facebook.com/v20.0/'.config('services.whatsapp.phone_number_id').'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizePhone($phone),
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json(),
        ];
    }

    /**
     * Assumes Nigerian local numbers (0803...) when no country code is
     * present. Adjust the default prefix here if you expand beyond Nigeria.
     */
    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0')) {
            return '234'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '234')) {
            return '234'.$digits;
        }

        return $digits;
    }

    public function shareableLink(string $phone, string $message): string
    {
        return 'https://wa.me/'.$this->normalizePhone($phone).'?text='.urlencode($message);
    }
}
