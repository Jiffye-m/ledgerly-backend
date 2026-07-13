<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.5;">
    <p>Hi {{ $sale->customer->name ?? 'there' }},</p>

    <p>
        Thanks for shopping with <strong>{{ $sale->business->name }}</strong>.
        Your receipt for invoice <strong>{{ $sale->invoice_number }}</strong>
        (total {{ $sale->business->currency_symbol }}{{ number_format($sale->total, 2) }})
        is attached as a PDF.
    </p>

    @if($sale->business->setting?->receipt_footer)
        <p style="color: #555; font-size: 13px;">{{ $sale->business->setting->receipt_footer }}</p>
    @endif

    <p>— {{ $sale->business->name }}</p>
</body>
</html>
