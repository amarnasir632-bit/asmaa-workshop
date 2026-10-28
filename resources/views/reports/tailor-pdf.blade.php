<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير تفصيل الترزي</title>
    <style>
        body {
            direction: rtl;
            text-align: right;
            font-family: 'dejavusans', sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 2px solid #1f4e79;
            padding-bottom: 6px;
        }
        .header-table td {
            border: none;
            padding: 3px 0;
            vertical-align: middle;
        }
        h1 {
            font-size: 16px;
            color: #1f4e79;
            margin: 0 0 4px 0;
            font-weight: bold;
        }
        p {
            margin: 2px 0;
            font-size: 9.5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 12px;
        }
        th {
            background-color: #1f4e79;
            color: #ffffff;
            font-weight: bold;
            padding: 6px 7px;
            border: 1px solid #1f4e79;
            text-align: right;
            font-size: 9px;
        }
        td {
            padding: 5px 7px;
            border: 1px solid #cbd5e1;
            text-align: right;
            vertical-align: middle;
            font-size: 9px;
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .muted {
            color: #64748b;
            font-size: 8.5px;
        }
        .totals-box {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
            display: inline-block;
            border-radius: 4px;
            font-size: 9.5px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 65%;">
                <h1>مشغل أسماء · تقرير تفصيل الترزي</h1>
                <p>
                    <strong>الفترة:</strong> من {{ $from }} إلى {{ $to }} &nbsp;|&nbsp;
                    <span class="totals-box">
                        إجمالي الطلبات: <strong>{{ $ordersCount }}</strong> &nbsp;|&nbsp;
                        إجمالي البنود: <strong>{{ $itemsCount }}</strong> &nbsp;|&nbsp;
                        إجمالي القطع: <strong>{{ $quantityTotal }} قطعة</strong>
                    </span>
                </p>
            </td>
            <td style="width: 35%; text-align: left;" class="text-left">
                <p class="muted">تاريخ الطباعة: {{ now(config('app.display_timezone'))->format('d/m/Y - h:i A') }}</p>
                <p class="muted">توقيت الخرطوم</p>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 24px; text-align: center;">#</th>
                <th style="width: 85px;">رقم الطلب</th>
                <th style="width: 110px;">العميل</th>
                <th style="width: 110px;">المستفيد / الطفل</th>
                <th>البيان والمواصفات</th>
                <th style="width: 32px; text-align: center;">العدد</th>
                <th style="width: 38px; text-align: center;">كتف</th>
                <th style="width: 38px; text-align: center;">صدر</th>
                <th style="width: 38px; text-align: center;">كم</th>
                <th style="width: 38px; text-align: center;">وسط</th>
                <th style="width: 38px; text-align: center;">طول</th>
                <th style="width: 75px; text-align: center;">التسليم</th>
                <th style="width: 120px;">ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="text-center" style="font-weight: bold; background-color: #f1f5f9;">{{ $row['serial'] }}</td>
                    <td style="font-weight: bold; color: #1f4e79;">{{ $row['number'] }}</td>
                    <td>{{ $row['customer'] }}</td>
                    <td>{{ $row['beneficiary'] ?? '—' }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="text-center" style="font-weight: bold; background-color: #f8fafc;">{{ $row['quantity'] }}</td>
                    <td class="text-center">{{ $row['shoulder'] ?? '—' }}</td>
                    <td class="text-center">{{ $row['chest'] ?? '—' }}</td>
                    <td class="text-center">{{ $row['sleeve'] ?? '—' }}</td>
                    <td class="text-center">{{ $row['waist'] ?? '—' }}</td>
                    <td class="text-center">{{ $row['length'] ?? '—' }}</td>
                    <td class="text-center">{{ $row['due_on'] ?? '—' }}</td>
                    <td class="muted">{{ $row['notes'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center muted" style="padding: 16px;">لا توجد طلبات تفصيل مسجلة في الفترة المحددة.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted" style="border-top: 1px dashed #cbd5e1; padding-top: 4px;">
        أُنشئ هذا التقرير تلقائياً عبر نظام مشغل أسماء في {{ now(config('app.display_timezone'))->format('d/m/Y H:i') }} بتوقيت الخرطوم.
    </p>
</body>
</html>
