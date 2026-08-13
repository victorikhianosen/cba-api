<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1e293b; line-height: 1.4; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 16px; }
        .header h1 { margin: 0; font-size: 18px; color: #1e3a8a; }
        .title { text-align: center; font-size: 15px; font-weight: bold; margin: 14px 0; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 9.5px; font-weight: bold; text-transform: uppercase; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-dryrun { background: #dbeafe; color: #1e40af; margin-left: 6px; }

        .info-row { display: table; width: 100%; margin-bottom: 16px; }
        .info-row .col { display: table-cell; width: 50%; vertical-align: top; font-size: 10.5px; }
        .info-row .col p { margin: 2px 0; }
        .info-row .label { color: #64748b; display: inline-block; width: 110px; }

        table.summary { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.summary td { border: 1px solid #cbd5e1; padding: 10px; text-align: center; }
        table.summary .label { background: #f1f5f9; font-size: 9px; font-weight: bold; color: #64748b; text-transform: uppercase; }
        table.summary .value { font-size: 18px; font-weight: bold; color: #0f172a; }
        table.summary .value.fail { color: #b91c1c; }

        .section-title { font-size: 11px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.3px; margin: 18px 0 8px; }

        table.items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.items th, table.items td { border: 1px solid #cbd5e1; padding: 6px 7px; font-size: 9px; text-align: left; word-wrap: break-word; }
        table.items th { background: #1e3a8a; color: #fff; text-transform: uppercase; font-size: 8.5px; letter-spacing: 0.3px; }
        table.items tr:nth-child(even) td { background: #f8fafc; }
        table.items tr.row-failed td { background: #fef2f2; }
        table.items tr.row-failed:nth-child(even) td { background: #fee2e2; }
        table.items .col-num { width: 4%; }
        table.items .col-item { width: 22%; }
        table.items .col-status { width: 9%; }
        table.items .col-amount { width: 10%; text-align: right; }
        table.items .col-reference { width: 17%; }
        table.items .col-detail { width: 38%; }
        table.items td.amount { text-align: right; font-weight: bold; }
        table.items td.detail-fail { color: #b91c1c; font-weight: bold; }

        .status-pill { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .status-success { background: #dcfce7; color: #166534; }
        .status-failed { background: #fee2e2; color: #b91c1c; }

        .no-items { text-align: center; color: #64748b; font-weight: bold; padding: 14px; border: 1px solid #cbd5e1; background: #f8fafc; }

        .footer { margin-top: 18px; border-top: 1px solid #cbd5e1; padding-top: 6px; font-size: 8.5px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
    </div>

    <div class="title">{{ $reportTitle }}</div>

    <div class="info-row">
        <div class="col">
            <p><span class="label">Status</span>
                <span class="badge {{ $totalFailed === 0 ? 'badge-success' : 'badge-warning' }}">{{ $status }}</span>
                @if($dryRun)
                    <span class="badge badge-dryrun">Dry Run</span>
                @endif
            </p>
            <p><span class="label">Started</span> {{ $startedAt }}</p>
            <p><span class="label">Finished</span> {{ $finishedAt }}</p>
        </div>
        <div class="col">
            <p><span class="label">Duration</span> {{ number_format($durationMs) }} ms</p>
            <p><span class="label">Generated On</span> {{ $generatedAt }}</p>
        </div>
    </div>

    <table class="summary">
        <tr>
            <td class="label">Total Ran</td>
            <td class="label">Total Successful</td>
            <td class="label">Total Failed</td>
        </tr>
        <tr>
            <td class="value">{{ $totalRan }}</td>
            <td class="value">{{ $totalSuccessful }}</td>
            <td class="value {{ $totalFailed > 0 ? 'fail' : '' }}">{{ $totalFailed }}</td>
        </tr>
    </table>

    <div class="section-title">Everything That Ran ({{ $totalRan }})</div>

    @if(empty($items))
        <div class="no-items">Nothing to process on this run.</div>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-item">Item</th>
                    <th class="col-status">Status</th>
                    <th class="col-amount">Amount</th>
                    <th class="col-reference">Reference</th>
                    <th class="col-detail">Detail / Reason for Failure</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                    <tr class="{{ $item['status'] === 'failed' ? 'row-failed' : '' }}">
                        <td class="col-num">{{ $index + 1 }}</td>
                        <td class="col-item">{{ $item['label'] }}</td>
                        <td class="col-status">
                            <span class="status-pill {{ $item['status'] === 'success' ? 'status-success' : 'status-failed' }}">
                                {{ ucfirst($item['status']) }}
                            </span>
                        </td>
                        <td class="col-amount">{{ $item['amount'] !== null ? number_format((float) $item['amount'], 2) : '—' }}</td>
                        <td class="col-reference">{{ $item['reference'] ?? '—' }}</td>
                        <td class="col-detail {{ $item['status'] === 'failed' ? 'detail-fail' : '' }}">{{ $item['detail'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        This is a system-generated report and does not require a physical signature to be valid.
    </div>
</body>
</html>
