<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class Controller
{
    /**
     * Guard against one business reading/editing another business's data
     * via route-model-binding (e.g. GET /products/17 where 17 belongs to
     * someone else's shop). Call this first in every show/update/destroy.
     */
    protected function authorizeBusiness(Model $model): void
    {
        if ($model->business_id !== request()->business()?->id) {
            throw new HttpException(403, 'This record does not belong to your business.');
        }
    }

    /**
     * Staff are confined to the one branch on their membership — this
     * returns that branch_id so a controller can force-scope a query to
     * it. Owner/admin have a null branch_id (every branch), so this
     * returns null for them, meaning "no restriction."
     */
    protected function requiredBranchId(): ?int
    {
        $membership = request()->membership();

        return $membership && $membership->isStaff() ? $membership->branch_id : null;
    }

    /**
     * The branch_id to write onto a new record: staff always get their
     * assigned branch automatically; owner/admin get whatever branch_id
     * they explicitly passed in the request (or none, for businesses that
     * don't use branches at all).
     */
    protected function resolveBranchIdForWrite(?int $requestedBranchId): ?int
    {
        return $this->requiredBranchId() ?? $requestedBranchId;
    }
}
