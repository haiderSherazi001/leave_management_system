<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Attendance Report</title>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 4px;
        }

        p.subtitle {
            margin: 0 0 16px;
            color: #6b7280;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background-color: #f3f4f6;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>LeaveDesk — Attendance Report</h1>
    <p class="subtitle">
        {{ \Illuminate\Support\Carbon::parse($start)->format('F j, Y') }}
        &ndash;
        {{ \Illuminate\Support\Carbon::parse($end)->format('F j, Y') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>Date</th>
                <th>Check-in Time</th>
                <th>Check-out Time</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['check_in'] ?? '—' }}</td>
                    <td>{{ $row['check_out'] ?? '—' }}</td>
                    <td>{{ $row['status'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No attendance records for this date range.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
