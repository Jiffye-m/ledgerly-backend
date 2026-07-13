<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /**
     * GET /reports/dashboard — the numbers for the home screen.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;
        $today = Carbon::today()->toDateString();

        $todaySalesQuery = $this->completedSales($businessId)->whereDate('created_at', $today);

        return response()->json([
            'today' => [
                'sales_total' => (float) (clone $todaySalesQuery)->sum('total'),
                'transactions' => (clone $todaySalesQuery)->count(),
                'profit' => $this->grossProfit($businessId, $today, $today),
                'expenses' => (float) Expense::where('business_id', $businessId)
                    ->whereDate('expense_date', $today)->sum('amount'),
            ],
            'inventory' => [
                'total_products' => Product::where('business_id', $businessId)->count(),
                'low_stock_count' => Product::where('business_id', $businessId)
                    ->whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
            ],
            'total_customers' => Customer::where('business_id', $businessId)->count(),
            'recent_sales' => SaleResource::collection(
                Sale::with('customer')->where('business_id', $businessId)->latest()->take(5)->get()
            ),
        ]);
    }

    /**
     * GET /reports/daily?date=YYYY-MM-DD (defaults to today)
     */
    public function daily(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;
        $date = $request->query('date', Carbon::today()->toDateString());

        $salesQuery = $this->completedSales($businessId)->whereDate('created_at', $date);
        $expensesTotal = (float) Expense::where('business_id', $businessId)
            ->whereDate('expense_date', $date)->sum('amount');
        $grossProfit = $this->grossProfit($businessId, $date, $date);

        $byPaymentMethod = (clone $salesQuery)
            ->selectRaw('payment_method, SUM(total) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'date' => $date,
            'sales_total' => (float) (clone $salesQuery)->sum('total'),
            'transactions' => (clone $salesQuery)->count(),
            'gross_profit' => $grossProfit,
            'expenses_total' => $expensesTotal,
            'net_profit' => $grossProfit - $expensesTotal,
            'by_payment_method' => $byPaymentMethod,
        ]);
    }

    /**
     * GET /reports/monthly?month=7&year=2026 (defaults to current month)
     */
    public function monthly(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;
        $month = $request->integer('month', Carbon::now()->month);
        $year = $request->integer('year', Carbon::now()->year);
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $salesByDay = $this->completedSales($businessId)
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $expensesByDay = Expense::where('business_id', $businessId)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('expense_date as date, SUM(amount) as total')
            ->groupBy('expense_date')
            ->pluck('total', 'date');

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $days[] = [
                'date' => $key,
                'sales' => (float) ($salesByDay[$key] ?? 0),
                'expenses' => (float) ($expensesByDay[$key] ?? 0),
            ];
        }

        $totalSales = (float) $salesByDay->sum();
        $totalExpenses = (float) $expensesByDay->sum();
        $grossProfit = $this->grossProfit($businessId, $start->toDateString(), $end->toDateString());

        $topProducts = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('sale_items.product_name, SUM(sale_items.quantity) as quantity_sold, SUM(sale_items.subtotal) as revenue')
            ->groupBy('sale_items.product_name')
            ->orderByDesc('revenue')
            ->take(5)
            ->get();

        return response()->json([
            'month' => $start->format('Y-m'),
            'total_sales' => $totalSales,
            'total_expenses' => $totalExpenses,
            'gross_profit' => $grossProfit,
            'net_profit' => $grossProfit - $totalExpenses,
            'days' => $days,
            'top_products' => $topProducts,
        ]);
    }

    /**
     * GET /reports/profit?from=YYYY-MM-DD&to=YYYY-MM-DD
     * (defaults to the current month so far)
     */
    public function profit(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;
        $from = $request->query('from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->query('to', Carbon::now()->toDateString());

        $orderRevenue = (float) $this->completedSales($businessId)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('total');

        $itemTotals = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->selectRaw('SUM(sale_items.subtotal) as item_revenue, SUM(sale_items.cost_price * sale_items.quantity) as cogs')
            ->first();

        $itemRevenue = (float) ($itemTotals->item_revenue ?? 0);
        $cogs = (float) ($itemTotals->cogs ?? 0);
        $grossProfit = $itemRevenue - $cogs;

        $expensesTotal = (float) Expense::where('business_id', $businessId)
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->sum('amount');

        return response()->json([
            'from' => $from,
            'to' => $to,
            'order_revenue' => $orderRevenue, // what customers actually paid (after discount, incl. tax)
            'item_revenue' => $itemRevenue,   // sum of item subtotals before order-level discount/tax
            'cost_of_goods_sold' => $cogs,
            'gross_profit' => $grossProfit,
            'expenses_total' => $expensesTotal,
            'net_profit' => $grossProfit - $expensesTotal,
            'note' => 'gross_profit is item-level margin (item_revenue - COGS). order_revenue can differ slightly because it reflects per-order discount/tax adjustments.',
        ]);
    }

    private function completedSales(int $businessId)
    {
        return Sale::where('business_id', $businessId)->where('status', 'completed');
    }

    private function grossProfit(int $businessId, string $from, string $to): float
    {
        return (float) SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->selectRaw('SUM((sale_items.unit_price - sale_items.cost_price) * sale_items.quantity) as profit')
            ->value('profit') ?? 0;
    }
}
