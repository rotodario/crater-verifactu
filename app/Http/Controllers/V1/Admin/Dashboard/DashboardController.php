<?php

namespace Crater\Http\Controllers\V1\Admin\Dashboard;

use Carbon\Carbon;
use Crater\Http\Controllers\Controller;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\Estimate;
use Crater\Models\Expense;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;

class DashboardController extends Controller
{
    protected function getCurrentFiscalStartYear($fiscalStartMonth)
    {
        $now = Carbon::now();

        return (int) ($fiscalStartMonth <= $now->month ? $now->year : $now->year - 1);
    }

    protected function getFiscalDateRange($fiscalStartMonth, $startYear)
    {
        $startDate = Carbon::create($startYear, $fiscalStartMonth, 1)->startOfMonth();
        $endDate = (clone $startDate)->endOfMonth();

        return [$startDate, clone $startDate, $endDate];
    }

    protected function getAvailableFiscalYears($companyId, $currentFiscalStartYear)
    {
        $invoiceYear = Invoice::whereCompanyId($companyId)->min('invoice_date');
        $expenseYear = Expense::whereCompanyId($companyId)->min('expense_date');
        $paymentYear = Payment::whereCompanyId($companyId)->min('payment_date');

        $dates = collect([$invoiceYear, $expenseYear, $paymentYear])->filter();

        if ($dates->isEmpty()) {
            return [$currentFiscalStartYear];
        }

        $minYear = $dates->map(function ($date) {
            return Carbon::parse($date)->year;
        })->min();

        return range($currentFiscalStartYear, $minYear);
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $company = Company::find($request->header('company'));

        $this->authorize('view dashboard', $company);

        $invoice_totals = [];
        $expense_totals = [];
        $receipt_totals = [];
        $net_income_totals = [];

        $i = 0;
        $months = [];
        $monthCounter = 0;
        $fiscalYear = CompanySetting::getSetting('fiscal_year', $request->header('company'));
        $terms = explode('-', $fiscalYear);
        $fiscalStartMonth = (int) $terms[0];
        $currentFiscalStartYear = $this->getCurrentFiscalStartYear($fiscalStartMonth);
        $selectedFiscalStartYear = $request->has('year')
            ? (int) $request->year
            : $currentFiscalStartYear;
        $availableYears = $this->getAvailableFiscalYears($request->header('company'), $currentFiscalStartYear);

        if (!in_array($selectedFiscalStartYear, $availableYears)) {
            $selectedFiscalStartYear = $currentFiscalStartYear;
        }

        if ($request->has('previous_year')) {
            $selectedFiscalStartYear = $currentFiscalStartYear - 1;
        }

        [$startDate, $start, $end] = $this->getFiscalDateRange($fiscalStartMonth, $selectedFiscalStartYear);

        while ($monthCounter < 12) {
            array_push(
                $invoice_totals,
                Invoice::whereBetween(
                    'invoice_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                )
                ->whereCompany()
                ->where('fiscal_status', '!=', Invoice::FISCAL_STATUS_ANNULLED)
                ->sum('base_total')
            );
            array_push(
                $expense_totals,
                Expense::whereBetween(
                    'expense_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                )
                ->whereCompany()
                ->sum('base_amount')
            );
            array_push(
                $receipt_totals,
                Payment::whereBetween(
                    'payment_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                )
                ->whereCompany()
                ->sum('base_amount')
            );
            array_push(
                $net_income_totals,
                ($receipt_totals[$i] - $expense_totals[$i])
            );
            $i++;
            array_push($months, $start->format('M'));
            $monthCounter++;
            $end->startOfMonth();
            $start->addMonth()->startOfMonth();
            $end->addMonth()->endOfMonth();
        }

        $start->subMonth()->endOfMonth();

        $total_sales = Invoice::whereBetween(
            'invoice_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->where('fiscal_status', '!=', Invoice::FISCAL_STATUS_ANNULLED)
            ->sum('base_total');

        $total_receipts = Payment::whereBetween(
            'payment_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->sum('base_amount');

        $total_expenses = Expense::whereBetween(
            'expense_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->sum('base_amount');

        $total_net_income = (int)$total_receipts - (int)$total_expenses;

        $chart_data = [
            'months' => $months,
            'invoice_totals' => $invoice_totals,
            'expense_totals' => $expense_totals,
            'receipt_totals' => $receipt_totals,
            'net_income_totals' => $net_income_totals,
        ];

        $total_customer_count = Customer::whereCompany()->count();
        $total_invoice_count = Invoice::whereCompany()
            ->where('fiscal_status', '!=', Invoice::FISCAL_STATUS_ANNULLED)
            ->count();
        $total_estimate_count = Estimate::whereCompany()->count();
        $total_amount_due = Invoice::whereCompany()
            ->where('fiscal_status', '!=', Invoice::FISCAL_STATUS_ANNULLED)
            ->sum('base_due_amount');

        $recent_due_invoices = Invoice::with('customer')
            ->whereCompany()
            ->where('fiscal_status', '!=', Invoice::FISCAL_STATUS_ANNULLED)
            ->where('base_due_amount', '>', 0)
            ->take(5)
            ->latest()
            ->get();
        $recent_estimates = Estimate::with('customer')->whereCompany()->take(5)->latest()->get();

        return response()->json([
            'total_amount_due' => $total_amount_due,
            'total_customer_count' => $total_customer_count,
            'total_invoice_count' => $total_invoice_count,
            'total_estimate_count' => $total_estimate_count,

            'recent_due_invoices' => BouncerFacade::can('view-invoice', Invoice::class) ? $recent_due_invoices : [],
            'recent_estimates' => BouncerFacade::can('view-estimate', Estimate::class) ? $recent_estimates : [],

            'chart_data' => $chart_data,
            'available_years' => $availableYears,
            'current_fiscal_year' => $currentFiscalStartYear,
            'selected_fiscal_year' => $selectedFiscalStartYear,

            'total_sales' => $total_sales,
            'total_receipts' => $total_receipts,
            'total_expenses' => $total_expenses,
            'total_net_income' => $total_net_income,
        ]);
    }
}
