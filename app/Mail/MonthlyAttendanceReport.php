<?php

declare(strict_types=1);

namespace App\Mail;

use App\Exports\AttendanceExport;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

final class MonthlyAttendanceReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $startDate,
        private readonly string $endDate,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Monthly Attendance Report — '.CarbonImmutable::parse($this->startDate)->format('F Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.monthly-attendance-report',
            with: [
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $export = app(AttendanceExport::class, ['start' => $this->startDate, 'end' => $this->endDate]);
        $filename = "attendance-{$this->startDate}-to-{$this->endDate}.xlsx";

        // 'Xlsx' is Maatwebsite\Excel\Excel::XLSX's literal value — that
        // constant lives on the concrete class, not the Facades\Excel proxy
        // used below, and importing both under the same short name isn't
        // worth the aliasing for one string constant.
        return [
            Attachment::fromData(fn () => Excel::raw($export, 'Xlsx'), $filename)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
