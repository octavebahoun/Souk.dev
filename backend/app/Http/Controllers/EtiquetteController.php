<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Etiquette;
use Illuminate\Http\JsonResponse;

final class EtiquetteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Etiquette::valeurs());
    }
}
