<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My Leave Requests') }}</h2>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">My Leave Requests</h3>

    @if (count($requests) === 0)
        <p class="text-sm text-gray-500">You haven't submitted any leave requests yet.</p>
    @else
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-2 pr-4">Leave Type</th>
                    <th class="py-2 pr-4">Dates</th>
                    <th class="py-2 pr-4">Days</th>
                    <th class="py-2 pr-4">Status</th>
                    <th class="py-2 pr-4">Approver</th>
                    <th class="py-2 pr-4">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($requests as $request)
                    <tr>
                        <td class="py-2 pr-4">{{ $request->leave_type_name }}</td>
                        <td class="py-2 pr-4">
                            {{ \Illuminate\Support\Carbon::parse($request->start_date)->format('M j, Y') }}
                            &ndash;
                            {{ \Illuminate\Support\Carbon::parse($request->end_date)->format('M j, Y') }}
                            @if ($request->is_half_day)
                                <span class="text-gray-400">(half day)</span>
                            @endif
                        </td>
                        <td class="py-2 pr-4">{{ $request->total_days }}</td>
                        <td class="py-2 pr-4">
                            <span @class([
                                'px-2 py-1 rounded-full text-xs font-medium',
                                'bg-yellow-100 text-yellow-800' => $request->status === 'pending',
                                'bg-green-100 text-green-800' => $request->status === 'approved',
                                'bg-red-100 text-red-800' => $request->status === 'rejected',
                            ])>
                                {{ ucfirst($request->status) }}
                            </span>
                        </td>
                        <td class="py-2 pr-4">{{ $request->approver_name ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $request->decision_note ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    </div>
    </div>
</div>
