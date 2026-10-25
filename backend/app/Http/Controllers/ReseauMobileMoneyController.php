<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class ReseauMobileMoneyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(array_values(config('facturation.reseaux')));
    }
}
