<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : now()->startOfYear();

        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : now()->endOfYear();

        $propertyId = $request->query('property_id') ? (int) $request->query('property_id') : null;
        $unitId = $request->query('unit_id') ? (int) $request->query('unit_id') : null;
        $category = $request->query('category');
        $transactionType = (string) $request->query('transaction_type', 'all');

        $reportData = $this->reportService->getFinancialReportData(
            $workspace,
            $startDate,
            $endDate,
            $propertyId,
            $unitId,
            $category,
            $transactionType
        );

        return Inertia::render('Reports/Index', $reportData);
    }

    /**
     * Export Rent Collections CSV (Excel-compatible with UTF-8 BOM).
     */
    public function exportCollections(Request $request): StreamedResponse
    {
        $workspace = $this->resolveWorkspace($request);

        return $this->reportService->exportCollections($workspace);
    }

    /**
     * Export Payments & Receipts CSV.
     */
    public function exportPayments(Request $request): StreamedResponse
    {
        $workspace = $this->resolveWorkspace($request);

        return $this->reportService->exportPayments($workspace);
    }

    /**
     * Export Expenses CSV.
     */
    public function exportExpenses(Request $request): StreamedResponse
    {
        $workspace = $this->resolveWorkspace($request);

        return $this->reportService->exportExpenses($workspace);
    }

    /**
     * Export Leases CSV.
     */
    public function exportLeases(Request $request): StreamedResponse
    {
        $workspace = $this->resolveWorkspace($request);

        return $this->reportService->exportLeases($workspace);
    }
}
