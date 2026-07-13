<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExpenseController extends Controller
{
    /**
     * GET /expenses?category=&from=&to=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with('user')
            ->where('business_id', $request->user()->business_id);

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('expense_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('expense_date', '<=', $to);
        }

        $expenses = $query->latest('expense_date')->paginate($request->integer('per_page', 20));

        return response()->json([
            'expenses' => ExpenseResource::collection($expenses),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = Expense::create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
            'user_id' => $request->user()->id,
            'expense_date' => $request->expense_date ?? Carbon::today(),
        ]);

        return response()->json([
            'expense' => new ExpenseResource($expense->load('user')),
        ], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->authorizeBusiness($expense);

        return response()->json([
            'expense' => new ExpenseResource($expense->load('user')),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $this->authorizeBusiness($expense);

        $expense->update($request->validated());

        return response()->json([
            'expense' => new ExpenseResource($expense->load('user')),
        ]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorizeBusiness($expense);

        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }
}
