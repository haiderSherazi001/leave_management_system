<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\AttendanceExportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class AttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $start,
        private readonly string $end,
        private readonly AttendanceExportService $service,
    ) {}

    public function collection(): Collection
    {
        return $this->service->rowsBetween($this->start, $this->end);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Employee Name', 'Date', 'Check-in Time', 'Check-out Time', 'Status'];
    }

    /**
     * @param  array{name: string, date: string, check_in: ?string, check_out: ?string, status: string}  $row
     * @return array<int, string>
     */
    public function map($row): array
    {
        return [
            $row['name'],
            $row['date'],
            $row['check_in'] ?? '—',
            $row['check_out'] ?? '—',
            $row['status'],
        ];
    }
}
