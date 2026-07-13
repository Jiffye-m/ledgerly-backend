<?php

namespace App\Mail;

use App\Models\Sale;
use App\Services\ReceiptPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Sale $sale)
    {
    }

    public function build(): self
    {
        $pdf = app(ReceiptPdfService::class)->build($this->sale);

        return $this
            ->subject("Your receipt from {$this->sale->business->name} — {$this->sale->invoice_number}")
            ->view('emails.receipt')
            ->with(['sale' => $this->sale])
            ->attachData(
                $pdf->output(),
                "invoice-{$this->sale->invoice_number}.pdf",
                ['mime' => 'application/pdf']
            );
    }
}
