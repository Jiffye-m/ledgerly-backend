<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; padding: 24px; }
    .header { text-align: center; margin-bottom: 16px; }
    .business-name { font-size: 18px; font-weight: bold; margin: 0; }
    .business-meta { font-size: 11px; color: #555; margin: 2px 0; }
    .invoice-meta { display: flex; justify-content: space-between; margin: 16px 0 8px; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { text-align: left; border-bottom: 1px solid #333; padding: 6px 4px; font-size: 11px; text-transform: uppercase; }
    td { padding: 6px 4px; border-bottom: 1px solid #eee; font-size: 12px; }
    .text-right { text-align: right; }
    .totals { width: 60%; margin-left: auto; margin-top: 12px; }
    .totals td { border: none; padding: 3px 4px; }
    .totals .grand-total td { font-weight: bold; font-size: 14px; border-top: 1px solid #333; padding-top: 6px; }
    .footer { margin-top: 24px; text-align: center; font-size: 11px; color: #777; }
    .status-void { color: #b91c1c; font-weight: bold; text-align: center; margin: 8px 0; }
</style>
</head>
<body>
    <div class="header">
        <p class="business-name">{{ $sale->business->name }}</p>
        @if($sale->business->address)
            <p class="business-meta">{{ $sale->business->address }}</p>
        @endif
        @if($sale->business->phone || $sale->business->email)
            <p class="business-meta">
                {{ $sale->business->phone }}{{ $sale->business->phone && $sale->business->email ? ' · ' : '' }}{{ $sale->business->email }}
            </p>
        @endif
    </div>

    @if($sale->status === 'void')
        <p class="status-void">VOID — This sale has been cancelled</p>
    @endif

    <div class="invoice-meta">
        <div>
            <strong>Invoice:</strong> {{ $sale->invoice_number }}<br>
            <strong>Date:</strong> {{ $sale->created_at->format('d M Y, h:i A') }}
        </div>
        <div class="text-right">
            @if($sale->customer)
                <strong>Customer:</strong> {{ $sale->customer->name }}<br>
                @if($sale->customer->phone) {{ $sale->customer->phone }} @endif
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">{{ $sale->business->currency_symbol }}{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ $sale->business->currency_symbol }}{{ number_format($item->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="text-right">{{ $sale->business->currency_symbol }}{{ number_format($sale->subtotal, 2) }}</td></tr>
        @if($sale->discount > 0)
        <tr><td>Discount</td><td class="text-right">-{{ $sale->business->currency_symbol }}{{ number_format($sale->discount, 2) }}</td></tr>
        @endif
        @if($sale->tax > 0)
        <tr><td>Tax</td><td class="text-right">{{ $sale->business->currency_symbol }}{{ number_format($sale->tax, 2) }}</td></tr>
        @endif
        <tr class="grand-total"><td>Total</td><td class="text-right">{{ $sale->business->currency_symbol }}{{ number_format($sale->total, 2) }}</td></tr>
        <tr><td>Amount Paid</td><td class="text-right">{{ $sale->business->currency_symbol }}{{ number_format($sale->amount_paid, 2) }}</td></tr>
        <tr><td>Change</td><td class="text-right">{{ $sale->business->currency_symbol }}{{ number_format($sale->change, 2) }}</td></tr>
        <tr><td>Payment Method</td><td class="text-right">{{ ucfirst($sale->payment_method) }}</td></tr>
    </table>

    <div class="footer">
        @if($sale->business->setting?->receipt_footer)
            <p>{{ $sale->business->setting->receipt_footer }}</p>
        @else
            <p>Thank you for your patronage!</p>
        @endif
    </div>
</body>
</html>
