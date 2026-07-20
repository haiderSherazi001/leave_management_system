<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Monthly Attendance Report</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#4f46e5; padding:20px 32px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:bold;">LeaveDesk</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 16px; font-size:20px; color:#111827;">Monthly Attendance Report</h1>
                            <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#374151;">
                                Attached is the complete attendance record for
                                <strong>{{ \Illuminate\Support\Carbon::parse($startDate)->format('F j, Y') }}</strong>
                                through
                                <strong>{{ \Illuminate\Support\Carbon::parse($endDate)->format('F j, Y') }}</strong>.
                            </p>
                            <p style="margin:0; font-size:14px; line-height:1.6; color:#374151;">
                                The spreadsheet covers every active employee for each working day in this period, including present, late, on-leave, and absent days, and is ready for payroll processing.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; background-color:#f9fafb; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:12px; color:#9ca3af;">This is an automated report sent on the 1st of every month by LeaveDesk.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
