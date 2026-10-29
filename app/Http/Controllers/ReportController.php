<?php

namespace App\Http\Controllers;

use App\Services\Reports;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$key, $reports] = $this->resolve($request, $request->query('report', 'project-status'));

        return view('reports.index', [
            'available' => Reports::available($request->user()),
            'key' => $key,
            'report' => $reports->build($key),
            'from' => $request->query('from', today()->startOfMonth()->toDateString()),
            'to' => $request->query('to', today()->toDateString()),
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        [$key, $reports] = $this->resolve($request, $report);
        $data = $reports->build($key);
        $clean = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v; // CSV formula-injection guard

        return response()->streamDownload(function () use ($data, $clean) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $data['columns'], escape: '');
            foreach ($data['rows'] as $row) {
                fputcsv($out, array_map($clean, $row), escape: '');
            }
            fclose($out);
        }, $key.'-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function resolve(Request $request, string $key): array
    {
        $this->authorize('view-reports');
        abort_unless(array_key_exists($key, Reports::available($request->user())), 404);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? today()->startOfMonth());
        $to = Carbon::parse($data['to'] ?? today());

        return [$key, new Reports($request->user(), $from, $to)];
    }
}
