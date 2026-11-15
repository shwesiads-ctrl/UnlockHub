<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentRequest;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $data = $this->build($from, $to);

        return view('admin.reports.index', $data + ['from' => $from, 'to' => $to]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $days = $this->build($from, $to)['days'];

        return response()->streamDownload(function () use ($days) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Orders placed', 'Completed', 'Rejected', 'Sales (Ks)', 'Cost (Ks)', 'Profit (Ks)', 'Top-ups approved (Ks)']);
            foreach ($days as $d) {
                fputcsv($out, [$d['date'], $d['placed'], $d['success'], $d['rejected'], $d['sales'], $d['cost'], $d['profit'], $d['deposits']]);
            }
            fclose($out);
        }, "report-{$from->format('Ymd')}-{$to->format('Ymd')}.csv", ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $preset = $request->query('preset');
        $to = now()->endOfDay();

        $from = match ($preset) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(6)->startOfDay(),
            'month' => now()->startOfMonth(),
            'last_month' => now()->subMonthNoOverflow()->startOfMonth(),
            default => null,
        };

        if ($preset === 'last_month') {
            $to = now()->subMonthNoOverflow()->endOfMonth();
        }

        if (! $from) {
            try {
                $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(29)->startOfDay();
                $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : $to;
            } catch (\Throwable) {
                $from = now()->subDays(29)->startOfDay();
            }
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // Keep it fast: max 1 year
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        return [$from, $to];
    }

    private function build(Carbon $from, Carbon $to): array
    {
        $orders = Order::whereBetween('created_at', [$from, $to]);

        $placedByDay = (clone $orders)->selectRaw('DATE(created_at) AS d, COUNT(*) AS n')->groupBy('d')->pluck('n', 'd');

        $finished = Order::whereBetween('completed_at', [$from, $to])
            ->selectRaw("DATE(completed_at) AS d,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS ok,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS bad,
                SUM(CASE WHEN status = 'success' THEN price ELSE 0 END) AS sales,
                SUM(CASE WHEN status = 'success' THEN cost ELSE 0 END) AS cost")
            ->groupBy('d')->get()->keyBy('d');

        $deposits = PaymentRequest::where('status', PaymentRequest::APPROVED)->whereBetween('reviewed_at', [$from, $to])
            ->selectRaw('DATE(reviewed_at) AS d, SUM(credited_amount) AS total')->groupBy('d')->pluck('total', 'd');

        $days = [];
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay()) as $day) {
            $k = $day->toDateString();
            $f = $finished->get($k);
            $sales = (float) ($f->sales ?? 0);
            $cost = (float) ($f->cost ?? 0);
            $days[] = [
                'date' => $k,
                'placed' => (int) ($placedByDay[$k] ?? 0),
                'success' => (int) ($f->ok ?? 0),
                'rejected' => (int) ($f->bad ?? 0),
                'sales' => $sales,
                'cost' => $cost,
                'profit' => round($sales - $cost, 2),
                'deposits' => (float) ($deposits[$k] ?? 0),
            ];
        }

        $totals = [
            'placed' => array_sum(array_column($days, 'placed')),
            'success' => array_sum(array_column($days, 'success')),
            'rejected' => array_sum(array_column($days, 'rejected')),
            'sales' => array_sum(array_column($days, 'sales')),
            'cost' => array_sum(array_column($days, 'cost')),
            'profit' => array_sum(array_column($days, 'profit')),
            'deposits' => array_sum(array_column($days, 'deposits')),
            'new_users' => User::whereBetween('created_at', [$from, $to])->count(),
        ];
        $finishedCount = $totals['success'] + $totals['rejected'];
        $totals['success_rate'] = $finishedCount ? round($totals['success'] / $finishedCount * 100, 1) : null;

        $topServices = Order::where('status', Order::SUCCESS)->whereBetween('completed_at', [$from, $to])
            ->selectRaw('service_id, service_name, COUNT(*) AS n, SUM(price) AS sales, SUM(price - cost) AS profit')
            ->groupBy('service_id', 'service_name')->orderByDesc('sales')->limit(10)->get();

        $byMethod = PaymentRequest::where('payment_requests.status', PaymentRequest::APPROVED)->whereBetween('payment_requests.reviewed_at', [$from, $to])
            ->join('payment_methods', 'payment_methods.id', '=', 'payment_requests.payment_method_id')
            ->selectRaw('payment_methods.name AS method, COUNT(*) AS n, SUM(credited_amount) AS total')
            ->groupBy('payment_methods.name')->orderByDesc('total')->get();

        $topUsers = Order::where('orders.status', Order::SUCCESS)->whereBetween('orders.completed_at', [$from, $to])
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->selectRaw('users.id, users.username, COUNT(*) AS n, SUM(orders.price) AS sales')
            ->groupBy('users.id', 'users.username')->orderByDesc('sales')->limit(10)->get();

        return compact('days', 'totals', 'topServices', 'byMethod', 'topUsers');
    }
}
