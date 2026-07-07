<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CompanyStructureData;
use Illuminate\Http\JsonResponse;

class CompanyStructureController extends Controller
{
    public function index(CompanyStructureData $companyStructureData): JsonResponse
    {
        return response()->json($companyStructureData->apiIndexData());
    }

    public function show(User $user, CompanyStructureData $companyStructureData): JsonResponse
    {
        return response()->json($companyStructureData->apiShowData($user));
    }
}
