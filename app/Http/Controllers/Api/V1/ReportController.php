<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\BalanceSheetRequest;
use App\Http\Requests\Reports\IncomeStatementRequest;
use App\Http\Resources\BalanceSheetResource;
use App\Http\Resources\IncomeStatementResource;
use App\Http\Responses\ApiResponse;
use App\Services\Reporting\FinancialReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function incomeStatement(IncomeStatementRequest $request, FinancialReportService $service): JsonResponse
    {
        $validated = $request->validated();

        return ApiResponse::success(
            new IncomeStatementResource($service->incomeStatement($validated['from'], $validated['to']))
        );
    }

    public function balanceSheet(BalanceSheetRequest $request, FinancialReportService $service): JsonResponse
    {
        return ApiResponse::success(
            new BalanceSheetResource($service->balanceSheet($request->validated()['as_of']))
        );
    }
}
