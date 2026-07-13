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
        if ($model->business_id !== request()->user()->business_id) {
            throw new HttpException(403, 'This record does not belong to your business.');
        }
    }
}
