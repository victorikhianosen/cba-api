<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; line-height: 1.6; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 12px; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 20px; color: #1e3a8a; }
        .header p { margin: 2px 0; font-size: 10px; color: #64748b; }
        .ref-row { display: table; width: 100%; margin-bottom: 18px; }
        .ref-row .left, .ref-row .right { display: table-cell; width: 50%; font-size: 11px; }
        .ref-row .right { text-align: right; }
        .title { text-align: center; font-size: 14px; font-weight: bold; text-decoration: underline; margin: 22px 0; }
        .salutation { margin-bottom: 10px; }
        table.details { width: 100%; border-collapse: collapse; margin: 16px 0; }
        table.details th, table.details td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; font-size: 11px; }
        table.details th { background: #f1f5f9; width: 45%; font-weight: bold; color: #334155; }
        p { margin: 8px 0; text-align: justify; }
        .signature { margin-top: 48px; }
        .signature .line { margin-top: 40px; border-top: 1px solid #1e293b; width: 220px; }
        .footer { margin-top: 40px; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $companyName }}</h1>
        <p>{{ $companyAddress }}</p>
    </div>

    <div class="ref-row">
        <div class="left">
            Date: {{ $issuedDate }}<br>
            Ref: {{ $reference }}
        </div>
        <div class="right">
            {{ $customerName }}<br>
            CIF: {{ $cifNumber }}<br>
            @if($customerAddress)
                {{ $customerAddress }}<br>
            @endif
        </div>
    </div>

    <div class="title">CERTIFICATE OF INVESTMENT</div>

    <div class="salutation">Dear {{ $customerName }},</div>

    <p>
        We are pleased to confirm your investment placement with {{ $companyName }} under
        the details set out below. This letter serves as official confirmation of the
        investment and its terms.
    </p>

    <table class="details">
        <tr><th>Investment Account Number</th><td>{{ $accountNumber }}</td></tr>
        <tr><th>Investment Product</th><td>{{ $productName }}</td></tr>
        <tr><th>Principal Amount</th><td>{{ $currencySymbol }}{{ $principalAmount }}</td></tr>
        <tr><th>Current Principal</th><td>{{ $currencySymbol }}{{ $currentPrincipal }}</td></tr>
        <tr><th>Interest Rate (per annum)</th><td>{{ $interestRate }}%</td></tr>
        <tr><th>Interest Posting Period</th><td>{{ $interestPostingPeriod }}</td></tr>
        <tr><th>Tenure</th><td>{{ $tenure }} days</td></tr>
        <tr><th>Start Date</th><td>{{ $startDate }}</td></tr>
        <tr><th>Maturity Date</th><td>{{ $maturityDate }}</td></tr>
        <tr><th>Projected Maturity Value</th><td>{{ $currencySymbol }}{{ $maturityValue }}</td></tr>
        <tr><th>Auto Renewal</th><td>{{ $autoRenew }}</td></tr>
        <tr><th>Source Account</th><td>{{ $sourceAccountNumber }}</td></tr>
        <tr><th>Status</th><td>{{ $status }}</td></tr>
    </table>

    <p>
        The principal sum and accrued interest are subject to the terms and conditions
        governing this investment product as agreed at the point of placement. Early
        liquidation, where permitted, may attract applicable penalties as disclosed in
        your investment agreement.
    </p>

    <p>Please contact us should you require any further clarification regarding this investment.</p>

    <p>Thank you for banking with us.</p>

    <div class="signature">
        Yours faithfully,<br>
        <strong>{{ $companyName }}</strong>
        <div class="line"></div>
        Authorized Signatory
    </div>

    <div class="footer">
        This is a system-generated letter and does not require a physical signature to be valid.
        Generated on {{ $generatedAt }}.
    </div>
</body>
</html>
