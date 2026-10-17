<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Techno;
use Illuminate\Http\JsonResponse;

final class TechnoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Techno::valeurs());
    }
}
