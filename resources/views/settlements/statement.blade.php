<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بيان تسوية مبلغ التأمين — عقد #{{ $lease->id }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background-color: #F8F4EA;
            color: #163E3E;
            padding: 30px 15px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .container {
            max-width: 850px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid #E2DDD2;
            box-shadow: 0 4px 20px rgba(22, 62, 62, 0.08);
            padding: 40px;
            position: relative;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px dashed #E2DDD2;
            padding-bottom: 25px;
            margin-bottom: 25px;
        }
        .brand-title {
            font-size: 26px;
            font-weight: 800;
            color: #163E3E;
            margin-bottom: 4px;
        }
        .brand-sub {
            font-size: 13px;
            color: #5B6770;
            line-height: 1.6;
        }
        .doc-badge {
            text-align: left;
        }
        .doc-title {
            font-size: 20px;
            font-weight: 800;
            color: #163E3E;
            margin-bottom: 4px;
        }
        .doc-no {
            font-family: monospace;
            font-size: 15px;
            font-weight: 700;
            color: #163E3E;
            background: #E8F0F0;
            padding: 4px 12px;
            border-radius: 8px;
            display: inline-block;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            background: #F8F4EA;
            border: 1px solid #E2DDD2;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .meta-item {
            font-size: 13px;
        }
        .meta-label {
            color: #5B6770;
            font-weight: 600;
            display: block;
            margin-bottom: 4px;
        }
        .meta-val {
            color: #163E3E;
            font-weight: 700;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 25px;
        }
        .summary-card {
            background: #F8FAFC;
            border: 1px solid #E2DDD2;
            border-radius: 14px;
            padding: 16px;
            text-align: center;
        }
        .summary-card.highlight {
            background: #163E3E;
            color: #ffffff;
            border-color: #163E3E;
        }
        .summary-card.highlight .card-label {
            color: #D6E0E7;
        }
        .summary-card.highlight .card-amount {
            color: #ffffff;
        }
        .summary-card.danger {
            background: #FFF1F2;
            border-color: #FECDD3;
        }
        .summary-card.danger .card-amount {
            color: #BE123C;
        }
        .card-label {
            font-size: 12px;
            font-weight: 600;
            color: #5B6770;
            margin-bottom: 6px;
        }
        .card-amount {
            font-size: 20px;
            font-weight: 800;
            font-family: monospace;
            color: #163E3E;
        }
        .section-heading {
            font-size: 15px;
            font-weight: 800;
            color: #163E3E;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #E2DDD2;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .table-wrap {
            border: 1px solid #E2DDD2;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 25px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: right;
            font-size: 13px;
        }
        th {
            background: #F8FAFC;
            color: #5B6770;
            font-weight: 700;
            padding: 12px 14px;
            border-bottom: 1px solid #E2DDD2;
        }
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #F1EFEA;
            color: #163E3E;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .amount-col {
            font-family: monospace;
            font-weight: 700;
            text-align: left;
            direction: ltr;
        }
        .notes-box {
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 25px;
            font-size: 12.5px;
            line-height: 1.6;
            color: #334155;
        }
        .signatures {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px dashed #E2DDD2;
        }
        .sig-box {
            text-align: center;
        }
        .sig-label {
            font-size: 13px;
            font-weight: 700;
            color: #163E3E;
            margin-bottom: 45px;
        }
        .sig-line {
            border-top: 1px solid #CBD5E1;
            padding-top: 8px;
            font-size: 12px;
            color: #64748B;
        }
        .actions-bar {
            margin-top: 25px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
        }
        .btn-primary {
            background: #163E3E;
            color: #ffffff;
        }
        .btn-primary:hover {
            background: #102F2F;
        }
        .btn-secondary {
            background: #ffffff;
            color: #163E3E;
            border: 1px solid #D6E0E7;
        }
        .btn-secondary:hover {
            background: #F8FAFC;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .container {
                border: none;
                box-shadow: none;
                padding: 15px;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="brand-title">{{ $workspace->name ?? 'Nook Mate' }}</div>
                <div class="brand-sub">
                    إدارة الأملاك والعقارات الذكية<br>
                    @if(!empty($workspace->phone)) هاتف: {{ $workspace->phone }} @endif
                    @if(!empty($workspace->address)) • {{ $workspace->address }} @endif
                </div>
            </div>
            <div class="doc-badge">
                <div class="doc-title">بيان تسوية مبلغ التأمين</div>
                <div class="doc-no">SETTLE-#{{ $settlement->id ?? $lease->id }}</div>
            </div>
        </div>

        <!-- Meta Grid -->
        <div class="meta-grid">
            <div class="meta-item">
                <span class="meta-label">المستأجر</span>
                <span class="meta-val">{{ $lease->renter?->name ?? '—' }} ({{ $lease->renter?->phone ?? '—' }})</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">العقار والوحدة</span>
                <span class="meta-val">{{ $lease->unit?->property?->name ?? '—' }} — {{ $lease->unit?->name ?? '—' }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">فترة العقد التعاقدية</span>
                <span class="meta-val">{{ $lease->starts_on?->format('d/m/Y') }} إلى {{ $lease->ends_on?->format('d/m/Y') }} ({{ $lease->status === 'terminated' ? 'عقد منتهٍ' : 'نشط' }})</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">تاريخ اعتماد التسوية</span>
                <span class="meta-val">{{ $settlement->settled_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y') }} بواسطة {{ $settlement->settledBy?->name ?? 'الإدارة' }}</span>
            </div>
        </div>

        <!-- Summary Cards (Section 8.2 Table) -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="card-label">مبلغ التأمين المستلم</div>
                <div class="card-amount">{{ number_format((float) ($settlement->total_received ?? $lease->total_deposit_received), 2) }} ج.م</div>
            </div>
            <div class="summary-card danger">
                <div class="card-label">إجمالي الخصومات المعتمدة</div>
                <div class="card-amount">{{ number_format((float) ($settlement->total_deductions ?? $lease->total_deposit_deductions), 2) }} ج.م</div>
            </div>
            <div class="summary-card highlight">
                <div class="card-label">المبلغ المستحق للمستأجر</div>
                <div class="card-amount">{{ number_format((float) ($settlement->refundable_balance ?? $lease->refundable_deposit_balance) + (float) ($settlement->total_refunded ?? 0), 2) }} ج.م</div>
            </div>
        </div>

        <!-- Breakdown: Rent Arrears Deductions -->
        @if($rentDeductions->isNotEmpty())
            <div class="section-heading">
                <span>1. خصومات متأخرات الإيجار المستحقة</span>
                <span style="font-size: 13px; font-weight: normal; color: #BE123C;">
                    المجموع: {{ number_format((float) $rentDeductions->sum('amount'), 2) }} ج.م
                </span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>البيان والتفاصيل</th>
                            <th>تاريخ الخصم</th>
                            <th>التسوية في سجل الإيجار</th>
                            <th class="amount-col">المبلغ (ج.م)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rentDeductions as $rd)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $rd->description }} @if(!empty($rd->notes)) <small style="display:block; color:#64748B;">{{ $rd->notes }}</small> @endif</td>
                                <td>{{ $rd->deducted_at?->format('d/m/Y') }}</td>
                                <td style="color: #047857; font-weight: 600;">تمت التسوية وسداد المطالبة</td>
                                <td class="amount-col">{{ number_format((float) $rd->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- Breakdown: Damage / Repairs / Other Deductions -->
        @if($damageDeductions->isNotEmpty())
            <div class="section-heading">
                <span>2. خصومات التلفيات والأضرار والإصلاحات</span>
                <span style="font-size: 13px; font-weight: normal; color: #BE123C;">
                    المجموع: {{ number_format((float) $damageDeductions->sum('amount'), 2) }} ج.م
                </span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التصنيف</th>
                            <th>البيان والسبب المبرر</th>
                            <th>الملاحظات</th>
                            <th>التاريخ</th>
                            <th class="amount-col">المبلغ (ج.م)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($damageDeductions as $dd)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $dd->category_label }}</td>
                                <td>{{ $dd->description }}</td>
                                <td>{{ $dd->notes ?? '—' }}</td>
                                <td>{{ $dd->deducted_at?->format('d/m/Y') }}</td>
                                <td class="amount-col">{{ number_format((float) $dd->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($rentDeductions->isEmpty() && $damageDeductions->isEmpty())
            <div class="notes-box" style="text-align: center; color: #047857; font-weight: 700;">
                لا توجد أي خصومات مسجلة على مبلغ التأمين — استحقاق كامل للتأمين لصالح المستأجر.
            </div>
        @endif

        <!-- Refund Status Breakdown -->
        <div class="section-heading">
            <span>3. حالة استرداد المبلغ للمستأجر</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>إجمالي المستحق رده</th>
                        <th>المبلغ المسدد فعلياً</th>
                        <th>المتبقي للصرف</th>
                        <th>حالة الصرف</th>
                        <th>طريقة التحويل / السداد</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="amount-col">{{ number_format((float) ($settlement->refundable_balance ?? 0) + (float) ($settlement->total_refunded ?? 0), 2) }} ج.م</td>
                        <td class="amount-col" style="color: #047857;">{{ number_format((float) ($settlement->total_refunded ?? 0), 2) }} ج.م</td>
                        <td class="amount-col" style="color: #B45309;">{{ number_format((float) ($settlement->refundable_balance ?? 0), 2) }} ج.م</td>
                        <td>
                            @if(($settlement->refund_status ?? '') === 'refunded' || ($settlement->refundable_balance ?? 0) <= 0)
                                <span style="color: #047857; font-weight: 700;">تم الصرف بالكامل</span>
                            @else
                                <span style="color: #B45309; font-weight: 700;">بانتظار الصرف للمستأجر</span>
                            @endif
                        </td>
                        <td>{{ $settlement->refund_method ?? ($settlement->total_refunded > 0 ? 'تحويل / نقدي' : '—') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if(!empty($settlement->inspection_notes) || !empty($settlement->notes))
            <div class="notes-box">
                @if(!empty($settlement->inspection_notes))
                    <strong>ملاحظات فحص الوحدة:</strong> {{ $settlement->inspection_notes }}<br>
                @endif
                @if(!empty($settlement->notes))
                    <strong>ملاحظات التسوية:</strong> {{ $settlement->notes }}
                @endif
            </div>
        @endif

        <!-- Signatures Area -->
        <div class="signatures">
            <div class="sig-box">
                <div class="sig-label">توقيع المستأجر بالاستلام والموافقة</div>
                <div class="sig-line">الاسم: {{ $lease->renter?->name ?? '.......................' }}</div>
            </div>
            <div class="sig-box">
                <div class="sig-label">ختم وتوقيع إدارة العقارات / المؤجر</div>
                <div class="sig-line">{{ $workspace->name ?? 'إدارة الأملاك' }}</div>
            </div>
        </div>

        <!-- Actions (Print & Return) -->
        <div class="actions-bar no-print">
            <button onclick="window.print()" class="btn btn-primary">
                <span>طباعة بيان التسوية</span>
            </button>
            <a href="{{ route('leases.show', $lease->id) }}" class="btn btn-secondary">
                <span>العودة إلى تفاصيل العقد</span>
            </a>
        </div>
    </div>
</body>
</html>

