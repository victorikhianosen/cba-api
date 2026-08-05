<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1e293b; line-height: 1.4; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 16px; }
        .header img { width: 100%; max-height: 90px; }
        .header h1 { margin: 0; font-size: 18px; color: #1e3a8a; }
        .title { text-align: center; font-size: 14px; font-weight: bold; letter-spacing: 1px; margin: 14px 0; text-transform: uppercase; }

        .info-row { display: table; width: 100%; margin-bottom: 16px; }
        .info-row .col { display: table-cell; width: 50%; vertical-align: top; font-size: 10.5px; }
        .info-row .col p { margin: 2px 0; }
        .info-row .label { color: #64748b; display: inline-block; width: 110px; }

        table.summary { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.summary td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: center; }
        table.summary .label { background: #f1f5f9; font-size: 9px; font-weight: bold; color: #64748b; text-transform: uppercase; }
        table.summary .value { font-size: 12px; font-weight: bold; color: #0f172a; }

        table.ledger { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.ledger th, table.ledger td { border: 1px solid #cbd5e1; padding: 5px 7px; font-size: 9.5px; text-align: left; word-wrap: break-word; }
        table.ledger th { background: #1e3a8a; color: #fff; text-transform: uppercase; font-size: 8.5px; letter-spacing: 0.3px; }
        table.ledger td.amount, table.ledger th.amount { text-align: right; }
        table.ledger tr:nth-child(even) td { background: #f8fafc; }
        table.ledger .opening td { background: #f1f5f9; font-weight: bold; }
        table.ledger .col-date { width: 12%; white-space: nowrap; }
        table.ledger .col-date .time { display: block; color: #64748b; font-size: 8.5px; margin-top: 1px; }
        table.ledger .col-reference { width: 17%; }
        table.ledger .col-narration { width: 31%; }
        table.ledger .col-debit, table.ledger .col-credit, table.ledger .col-balance { width: 13.33%; }

        .footer { margin-top: 18px; border-top: 1px solid #cbd5e1; padding-top: 6px; font-size: 8.5px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($headerImageBase64))
            <img src="{{ $headerImageBase64 }}" alt="{{ $companyName }}">
        @else
            <h1>{{ $companyName }}</h1>
        @endif
    </div>

    <div class="title">Statement of Account</div>

    <div class="info-row">
        <div class="col">
            <p><span class="label">Account Name</span> {{ $accountName }}</p>
            <p><span class="label">Account Number</span> {{ $accountNumber }}</p>
            <p><span class="label">Account Type</span> {{ $accountProduct }}</p>
            <p><span class="label">Currency</span> {{ $currencyCode }}</p>
        </div>
        <div class="col">
            <p><span class="label">Customer</span> {{ $customerName }}</p>
            <p><span class="label">Statement Period</span> {{ $periodFrom }} &ndash; {{ $periodTo }}</p>
            <p><span class="label">Generated On</span> {{ $generatedAt }}</p>
        </div>
    </div>

    <table class="summary">
        <tr>
            <td class="label">Opening Balance</td>
            <td class="label">Total Debit</td>
            <td class="label">Total Credit</td>
            <td class="label">Closing Balance</td>
        </tr>
        <tr>
            <td class="value">{{ $currencySymbol }}{{ $openingBalance }}</td>
            <td class="value">{{ $currencySymbol }}{{ $totalDebit }}</td>
            <td class="value">{{ $currencySymbol }}{{ $totalCredit }}</td>
            <td class="value">{{ $currencySymbol }}{{ $closingBalance }}</td>
        </tr>
    </table>

    <table class="ledger">
        <thead>
            <tr>
                <th class="col-date">Date</th>
                <th class="col-reference">Reference</th>
                <th class="col-narration">Narration</th>
                <th class="amount col-debit">Debit</th>
                <th class="amount col-credit">Credit</th>
                <th class="amount col-balance">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="opening">
                <td colspan="5">Opening Balance</td>
                <td class="amount">{{ $currencySymbol }}{{ $openingBalance }}</td>
            </tr>
            @forelse($transactions as $row)
                <tr>
                    <td class="col-date">{{ $row['date'] }}<span class="time">{{ $row['time'] }}</span></td>
                    <td>{{ $row['reference'] }}</td>
                    <td>{{ $row['narration'] }}</td>
                    <td class="amount">{{ $row['debit'] ? $currencySymbol . $row['debit'] : '' }}</td>
                    <td class="amount">{{ $row['credit'] ? $currencySymbol . $row['credit'] : '' }}</td>
                    <td class="amount">{{ $currencySymbol }}{{ $row['balance'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:#94a3b8; padding:14px;">No transactions in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        This is a system-generated statement and does not require a physical signature to be valid.
    </div>
</body>
</html>
