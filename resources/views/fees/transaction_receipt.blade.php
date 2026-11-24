<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Transaction Receipt || {{ config('app.name') }}</title>
    <style>
        @page {
            margin: 20px;
            size: A4;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }
        .container {
            width: 100%;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-table td {
            vertical-align: top;
        }
        .school-info {
            text-align: left;
        }
        .school-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .school-address {
            font-size: 12px;
            color: #555;
        }
        .receipt-title {
            text-align: right;
        }
        .receipt-header {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 2px solid #333;
            display: inline-block;
            margin-bottom: 10px;
            padding-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            color: #555;
            width: 80px;
            display: inline-block;
        }
        .student-section {
            margin-top: 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            padding: 10px;
            background-color: #f9f9f9;
        }
        .student-table td {
            padding: 3px 5px;
        }
        .fee-table {
            margin-top: 20px;
        }
        .fee-table th {
            background-color: #eee;
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }
        .fee-table td {
            border-bottom: 1px solid #ddd;
            padding: 8px;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .total-section {
            margin-top: 10px;
        }
        .total-table td {
            padding: 5px 10px;
        }
        .grand-total {
            background-color: #333;
            color: #fff;
            padding: 8px 10px;
            font-weight: bold;
            font-size: 14px;
        }
        .small-text {
            font-size: 10px;
            color: #777;
        }
        .logo-img {
            max-height: 60px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    {{-- Header Section --}}
    <table class="header-table">
        <tr>
            {{-- Left: School Info --}}
            <td width="60%">
                <div class="school-info">
                   
                    @if ($school['horizontal_logo'] ?? '')
                        <img class="logo-img" src="{{ public_path('storage/') . $school['horizontal_logo'] }}" alt="Logo">
                    @else
                        <img class="logo-img" src="{{ public_path('assets/horizontal-logo2.svg') }}" alt="Logo">
                    @endif
                    <div class="school-name">{{$school['school_name'] ?? 'School Name'}}</div>
                    <div class="school-address">{!! nl2br(e($school['school_address'] ?? '')) !!}</div>
                </div>
            </td>
            
            {{-- Right: Receipt Info --}}
            <td width="40%" class="receipt-title">
                <div class="receipt-header">Transaction Receipt</div>
                <table style="width: 100%; text-align: right;">
                    <tr>
                        <td class="text-right"><span class="info-label">Transaction ID:</span></td>
                        <td class="text-right font-bold">{{$transaction->id ?? '-'}}</td>
                    </tr>
                    <tr>
                        <td class="text-right"><span class="info-label">Date:</span></td>
                        <td class="text-right">{{ $transaction->date ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-right"><span class="info-label">Status:</span></td>
                        <td class="text-right">{{ $transaction->status ?? '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Student Details Section --}}
    <div class="student-section">
        <table class="student-table">
            <tr>
                <td width="15%"><span class="info-label">Student:</span></td>
                <td width="45%" class="font-bold" style="font-size: 13px;">{{$student->user->full_name}}</td>
                <td width="15%"><span class="info-label">Class:</span></td>
                <td width="25%">{{$student->class_section->full_name ?? ''}}</td>
            </tr>
            <tr>
                <td><span class="info-label">Admission:</span></td>
                <td>{{ $student->admission_no ?? '-' }}</td>
                <td><span class="info-label">Session:</span></td>
                <td>{{ $fees->session_year->name ?? '-' }}</td>
            </tr>
        </table>
    </div>

    {{-- Fee Details Table --}}
    <table class="fee-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">Sr.</th>
                <th width="50%">Description</th>
                <th width="25%">Payment Info</th>
                <th width="20%" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td>
                    <div class="font-bold">
                        @if ($type == 'compulsory')
                            @if ($transaction->type == 'Installment Payment' && $transaction->installment_fee)
                                {{ $transaction->installment_fee->name }}
                            @else
                                Full Compulsory Payment
                            @endif
                            <div class="small-text">
                                {{ collect($fees->fees_class_type)->pluck('fees_type.name')->join(', ') ?? 'Fee Components' }}
                            </div>
                        @else
                            @if ($transaction->fees_class_type && $transaction->fees_class_type->fees_type)
                                {{ $transaction->fees_class_type->fees_type->name }}
                            @else
                                Optional Fee
                            @endif
                        @endif
                    </div>
                </td>
                <td>
                    <div>Mode: <strong>{{ $transaction->mode_name }}</strong></div>
                    @if($transaction->payment_transaction_id)
                        <div class="small-text">Gateway Txn: {{ $transaction->payment_transaction_id }}</div>
                    @endif
                    @if($transaction->cheque_no)
                        <div class="small-text">Cheque No: {{ $transaction->cheque_no }}</div>
                    @endif
                </td>
                <td class="text-right font-bold">
                    {{ $school['currency_symbol'] ?? '' }}{{ number_format($transaction->amount, 2) }}
                </td>
            </tr>
            @if ($type == 'compulsory' && $transaction->due_charges > 0)
            <tr>
                <td class="text-center">2</td>
                <td>Due Charges</td>
                <td>-</td>
                <td class="text-right font-bold">
                    {{ $school['currency_symbol'] ?? '' }}{{ number_format($transaction->due_charges, 2) }}
                </td>
            </tr>
            @endif
        </tbody>
    </table>

    {{-- Total Section --}}
    <div class="total-section">
        <table class="total-table" style="width: 40%; float: right;">
            @php
                $totalAmount = $transaction->amount;
                if ($type == 'compulsory' && $transaction->due_charges > 0) {
                    $totalAmount += $transaction->due_charges;
                }
            @endphp
            <tr>
                <td class="grand-total text-right">Total Paid:</td>
                <td class="grand-total text-right" width="40%">{{ $school['currency_symbol'] ?? '' }}{{ number_format($totalAmount, 2) }}</td>
            </tr>
        </table>
        <div style="clear: both;"></div>
    </div>
    
    @if ($transaction->status == 'Failed' || $transaction->status == 'Rejected')
    <div style="margin-top: 20px; color: red;">
        <strong>Rejection Reason:</strong> {{ $transaction->reason ?? $transaction->notes ?? 'Payment rejected' }}
    </div>
    @endif

</div>

</body>
</html>
