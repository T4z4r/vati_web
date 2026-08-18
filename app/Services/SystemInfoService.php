<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Payment;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemInfoService
{
    public function overview(): array
    {
        return [
            'system' => $this->systemInfo(),
            'storage' => $this->storageInfo(),
            'records' => $this->recordCounts(),
            'summary' => $this->summaryStats(),
        ];
    }

    public function systemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'mysql_version' => $this->mysqlVersion(),
            'server_os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'max_upload' => ini_get('upload_max_filesize'),
            'max_execution' => ini_get('max_execution_time') . 's',
            'memory_limit' => ini_get('memory_limit'),
            'timezone' => config('app.timezone'),
        ];
    }

    public function storageInfo(): array
    {
        $publicPath = public_path();
        $totalSpace = disk_total_space($publicPath);
        $freeSpace = disk_free_space($publicPath);

        return [
            'public_total' => $this->formatBytes($totalSpace),
            'public_free' => $this->formatBytes($freeSpace),
            'public_used' => $this->formatBytes($totalSpace - $freeSpace),
            'public_used_percent' => $totalSpace > 0 ? round((($totalSpace - $freeSpace) / $totalSpace) * 100, 1) : 0,
            'database_size' => $this->databaseSize(),
        ];
    }

    public function recordCounts(): array
    {
        return [
            ['label' => 'Regions', 'table' => 'regions', 'count' => DB::table('regions')->count()],
            ['label' => 'Areas', 'table' => 'areas', 'count' => DB::table('areas')->count()],
            ['label' => 'Branches', 'table' => 'branches', 'count' => Branch::count()],
            ['label' => 'Staff Users', 'table' => 'users', 'count' => DB::table('users')->count()],
            ['label' => 'Groups', 'table' => 'member_groups', 'count' => MemberGroup::count()],
            ['label' => 'Members', 'table' => 'members', 'count' => Member::withTrashed()->count()],
            ['label' => 'Loan Products', 'table' => 'loan_products', 'count' => LoanProduct::count()],
            ['label' => 'Loan Applications', 'table' => 'loan_applications', 'count' => LoanApplication::withTrashed()->count()],
            ['label' => 'Loans', 'table' => 'loans', 'count' => Loan::count()],
            ['label' => 'Payments', 'table' => 'payments', 'count' => Payment::count()],
            ['label' => 'Activity Log Entries', 'table' => 'activity_log', 'count' => DB::table('activity_log')->count()],
        ];
    }

    public function summaryStats(): array
    {
        $activeLoanStatuses = ['active', 'overdue'];

        return [
            'total_members' => Member::count(),
            'active_loans' => Loan::whereIn('status', $activeLoanStatuses)->count(),
            'total_portfolio' => (float) Loan::whereIn('status', $activeLoanStatuses)->sum('total_balance'),
            'activity_today' => DB::table('activity_log')->whereDate('created_at', today())->count(),
            'pending_applications' => LoanApplication::whereIn('status', ['draft', 'submitted', 'credit_review', 'recommended'])->count(),
        ];
    }

    private function mysqlVersion(): string
    {
        try {
            return DB::selectOne('SELECT VERSION() AS version')->version ?? 'Unknown';
        } catch (\Exception) {
            return 'Unknown';
        }
    }

    private function databaseSize(): string
    {
        try {
            $dbName = config('database.connections.mysql.database');
            $result = DB::selectOne(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                 FROM information_schema.tables 
                 WHERE table_schema = ?",
                [$dbName]
            );
            return ($result->size_mb ?? 0) . ' MB';
        } catch (\Exception) {
            return 'N/A';
        }
    }

    private function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
