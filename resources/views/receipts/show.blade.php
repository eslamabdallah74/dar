<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سند قبض إلكتروني — {{ $payment->reference }}</title>
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
        .receipt-container {
            max-width: 800px;
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
        .receipt-badge {
            text-align: left;
        }
        .receipt-title {
            font-size: 20px;
            font-weight: 800;
            color: #163E3E;
            margin-bottom: 4px;
        }
        .receipt-no {
            font-family: monospace;
            font-size: 16px;
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
        .amount-box {
            background: linear-gradient(135deg, #163E3E 0%, #102F2F 100%);
            color: #ffffff;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            margin-bottom: 25px;
        }
        .amount-label {
            font-size: 13px;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 6px;
        }
        .amount-val {
            font-size: 34px;
            font-weight: 800;
            font-family: 'Tajawal', sans-serif;
            letter-spacing: -0.5px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 25px;
        }
        .table th {
            background: #F1F5F9;
            color: #475569;
            font-weight: 700;
            padding: 12px 14px;
            text-align: right;
            border-bottom: 2px solid #CBD5E1;
        }
        .table td {
            padding: 12px 14px;
            border-bottom: 1px solid #E2E8F0;
            color: #1E293B;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-top: 1px solid #E2E8F0;
            padding-top: 25px;
            font-size: 12px;
            color: #64748B;
        }
        .actions-bar {
            max-width: 800px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: #163E3E;
            color: #ffffff;
            border: none;
        }
        .btn-primary:hover {
            background: #102F2F;
        }
        .btn-outline {
            background: #ffffff;
            color: #475569;
            border: 1px solid #CBD5E1;
        }
        .btn-outline:hover {
            background: #F8FAFC;
        }
        .stamp-box {
            text-align: center;
            border: 2px dashed #CBD5E1;
            padding: 15px 30px;
            border-radius: 14px;
            display: inline-block;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                border: none;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="actions-bar no-print">
        <a href="{{ url()->previous() }}" class="btn btn-outline">
            <span>← العودة للنظام</span>
        </a>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <span>طباعة سند القبض / حفظ كـ PDF</span>
            </button>
        </div>
    </div>

    <div class="receipt-container">
        <!-- الهيدر الرسمي -->
        <div class="header">
            <div>
                <div class="brand-title">{{ $workspace->name }}</div>
                <div class="brand-sub">
                    @if($workspace->cr_number)
                        سجل تجاري: {{ $workspace->cr_number }} &bull;
                    @endif
                    @if($workspace->tax_number)
                        رقم ضريبي: {{ $workspace->tax_number }}<br>
                    @endif
                    @if($workspace->phone)
                        هاتف: {{ $workspace->phone }} &bull;
                    @endif
                    @if($workspace->address)
                        {{ $workspace->address }}
                    @endif
                </div>
            </div>

            <div class="receipt-badge">
                <div class="receipt-title">سند قبض إلكتروني</div>
                <div class="receipt-no">{{ $payment->reference ?? 'REC-'.$payment->id }}</div>
            </div>
        </div>

        <!-- بيانات السند -->
        <div class="meta-grid">
            <div class="meta-item">
                <span class="meta-label">استلمنا من المستأجر / العميل:</span>
                <span class="meta-val" style="font-size: 15px;">{{ $payment->renter?->name ?? 'مستأجر غير معروف' }}</span>
                @if($payment->renter?->national_id)
                    <span style="display: block; font-size: 11px; color: #64748B; margin-top: 2px;">
                        رقم الهوية / الإقامة: {{ $payment->renter->national_id }}
                    </span>
                @endif
            </div>

            <div class="meta-item">
                <span class="meta-label">تاريخ وساعة التحصيل:</span>
                <span class="meta-val">{{ $payment->paid_at?->format('d/m/Y — H:i') ?? $payment->created_at?->format('d/m/Y') }}</span>
            </div>

            <div class="meta-item">
                <span class="meta-label">طريقة السداد:</span>
                <span class="meta-val">
                    {{ match ($payment->payment_method) {
                        'bank_transfer' => 'تحويل بنكي رسمي',
                        'cash' => 'سداد نقدي',
                        'cheque' => 'شيك مصرفي',
                        default => 'إلكتروني / إنستاباي / بطاقة بنكية',
                    } }}
                </span>
            </div>

            <div class="meta-item">
                <span class="meta-label">العقار والوحدة:</span>
                <span class="meta-val">
                    @php
                        $firstAlloc = $payment->allocations->first();
                        $unit = $firstAlloc?->rentCharge?->lease?->unit;
                        $prop = $unit?->property;
                    @endphp
                    {{ $prop ? $prop->name . ' — ' : '' }}{{ $unit?->name ?? 'عقد إيجار المنشأة' }}
                </span>
            </div>
        </div>

        <!-- صندوق المبلغ الكبير -->
        <div class="amount-box">
            <div class="amount-label">المبلغ المستلم بالعملة المعتمدة</div>
            <div class="amount-val">
                {{ number_format((float) $payment->amount, 2) }}
                <span style="font-size: 20px; font-weight: 600;">
                    {{ match (strtoupper($workspace->currency ?? 'EGP')) {
                        'EGP' => 'جنيه مصري',
                        'SAR' => 'ريال سعودي',
                        'AED' => 'درهم إماراتي',
                        'USD' => 'دولار أمريكي',
                        default => $workspace->currency ?? 'ج.م',
                    } }}
                </span>
            </div>
        </div>

        <!-- جدول التخصيصات للمستحقات -->
        @if($payment->allocations->isNotEmpty())
            <table class="table">
                <thead>
                    <tr>
                        <th>بند الاستحقاق</th>
                        <th>الفترة الإيجارية</th>
                        <th>تاريخ الاستحقاق</th>
                        <th>المبلغ المخصص</th>
                        <th>المتبقي على الفاتورة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payment->allocations as $alloc)
                        @php
                            $charge = $alloc->rentCharge;
                        @endphp
                        <tr>
                            <td style="font-weight: 700;">إيجار شهري تعاقدي</td>
                            <td>{{ $charge?->period_start?->format('m/Y') ?? '—' }}</td>
                            <td>{{ $charge?->due_date?->format('d/m/Y') ?? '—' }}</td>
                            <td style="font-weight: 700; color: #163E3E;">{{ number_format((float) $alloc->amount, 2) }} {{ $workspace->currency }}</td>
                            <td>{{ number_format((float) ($charge?->outstanding_balance ?? 0), 2) }} {{ $workspace->currency }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($payment->notes)
            <div style="background: #F8F4EA; border: 1px solid #E2DDD2; border-radius: 12px; padding: 14px; font-size: 13px; margin-bottom: 25px;">
                <span style="font-weight: 700; color: #163E3E; display: block; margin-bottom: 4px;">ملاحظات إضافية:</span>
                <span style="color: #1E293B;">{{ $payment->notes }}</span>
            </div>
        @endif

        @if($payment->receipt_path)
            <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 12px; padding: 14px; font-size: 13px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                    </svg>
                    <span style="font-weight: 700; color: #166534;">مرفق إثبات الدفع / إشعار التحويل</span>
                </div>
                <a href="{{ route('payments.attachment', $payment) }}" target="_blank" class="no-print" style="color: #15803D; font-weight: 700; text-decoration: underline;">
                    عرض المستند المرفق &larr;
                </a>
            </div>
        @endif

        <!-- التذييل والتوقيع -->
        <div class="footer">
            <div>
                <p>حرر بواسطة: {{ $payment->recordedBy?->name ?? 'إدارة العقار' }}</p>
                <p style="margin-top: 4px; font-size: 11px;">هذا السند إلكتروني ومعتمد ومسجل بسجلات منصة «Nook Mate» لإدارة وتأجير العقارات والأملاك.</p>
            </div>

            <div class="stamp-box">
                <span style="display: block; font-size: 11px; color: #64748B; margin-bottom: 6px;">الختم والتوقيع المعتمد</span>
                <div style="font-weight: 800; color: #163E3E; font-size: 14px;">{{ $workspace->name }}</div>
            </div>
        </div>
    </div>
</body>
</html>
