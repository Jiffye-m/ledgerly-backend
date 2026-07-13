<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ReceiptMail;
use App\Models\Sale;
use App\Services\ReceiptPdfService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class ReceiptController extends Controller
{
    /**
     * GET /sales/{sale}/receipt/pdf — authenticated download for the staff/owner.
     */
    public function download(Sale $sale, ReceiptPdfService $pdfService): Response
    {
        $this->authorizeBusiness($sale);

        $pdf = $pdfService->build($sale);

        return $pdf->download("invoice-{$sale->invoice_number}.pdf");
    }

    /**
     * GET /receipts/{sale}/view?signature=... — public, no auth. The link
     * itself (a Laravel "signed" URL) is the access control: it can't be
     * guessed or tampered with, and can be set to expire.
     */
    public function publicView(Sale $sale, ReceiptPdfService $pdfService): Response
    {
        $pdf = $pdfService->build($sale);

        return $pdf->stream("invoice-{$sale->invoice_number}.pdf");
    }

    /**
     * POST /sales/{sale}/receipt/email  { "email"?: "..." }
     * Falls back to the customer's saved email if none is passed.
     */
    public function email(Request $request, Sale $sale): JsonResponse
    {
        $this->authorizeBusiness($sale);

        $email = $request->input('email') ?: $sale->customer?->email;

        if (! $email) {
            return response()->json([
                'message' => 'No email address provided or on file for this customer.',
            ], 422);
        }

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
        if ($validator->fails()) {
            return response()->json(['message' => 'That email address looks invalid.'], 422);
        }

        $sale->loadMissing(['items', 'customer', 'business.setting']);
        Mail::to($email)->send(new ReceiptMail($sale));

        return response()->json(['message' => "Receipt emailed to {$email}."]);
    }

    /**
     * POST /sales/{sale}/receipt/whatsapp  { "phone"?: "..." }
     * Falls back to the customer's saved phone if none is passed.
     *
     * If WHATSAPP_TOKEN / WHATSAPP_PHONE_NUMBER_ID are set in .env, sends
     * directly via the Cloud API. Otherwise (or if that send fails —
     * e.g. outside the 24-hour session window) returns a wa.me link with
     * the message pre-filled, for the frontend to open in one tap. This
     * means WhatsApp sharing works today, with zero WhatsApp Business
     * account setup required.
     */
    public function whatsapp(Request $request, Sale $sale, WhatsAppService $whatsapp): JsonResponse
    {
        $this->authorizeBusiness($sale);

        $phone = $request->input('phone') ?: $sale->customer?->phone;

        if (! $phone) {
            return response()->json([
                'message' => 'No phone number provided or on file for this customer.',
            ], 422);
        }

        $sale->loadMissing('business');

        $url = URL::temporarySignedRoute('receipts.public', now()->addDays(7), ['sale' => $sale->id]);

        $message = sprintf(
            "Hi! Here's your receipt from %s — Invoice %s, Total %s%s. View/download: %s",
            $sale->business->name,
            $sale->invoice_number,
            $sale->business->currency_symbol,
            number_format($sale->total, 2),
            $url
        );

        if ($whatsapp->isConfigured()) {
            $result = $whatsapp->sendText($phone, $message);

            if ($result['ok']) {
                return response()->json(['sent_via' => 'api', 'message' => 'Receipt sent via WhatsApp.']);
            }
            // Fall through to the link below — likely outside the 24hr
            // session window or the account isn't fully approved yet.
        }

        return response()->json([
            'sent_via' => 'link',
            'whatsapp_link' => $whatsapp->shareableLink($phone, $message),
            'message' => $message,
        ]);
    }
}
