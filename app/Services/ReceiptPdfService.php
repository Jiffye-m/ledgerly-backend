<?php

namespace App\Services;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;

class ReceiptPdfService
{
    public function build(Sale $sale): PdfInstance
    {
        $sale->loadMissing(['items', 'customer', 'business.setting']);

        return Pdf::loadView('receipts.pdf', ['sale' => $sale])
            ->setPaper('a5'); // receipt-sized, not full A4 — closer to what a POS printer expects
    }
}
