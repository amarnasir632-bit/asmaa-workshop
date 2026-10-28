<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans, sans-serif; direction: rtl; font-size: 8pt; color: #24313d; }
        h1 { font-size: 14pt; color: #1f4e79; margin: 0 0 5px 0; }
        p { margin: 3px 0; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 0.5px solid #cbd5e1; padding: 4px; text-align: right; }
        th { background-color: #eef2f6; color: #1f4e79; font-size: 7.5pt; }
        .text-center { text-align: center; }
        .muted { color: #64748b; font-size: 7pt; }
    </style>
</head>
<body>
    <h1>مشغل أسماء · تقرير تفصيل الترزي</h1>
    <p>الفترة من {{ $from }} إلى {{ $to }} | إجمالي الطلبات: {{ $ordersCount }} | إجمالي القطع: {{ $quantityTotal }}</p>

    <table>
        <thead>
            <tr>
                <th style="width: 20px;">#</th>
                <th>رقم الطلب</th>
                <th>العميل</th>
                <th>المستفيد / الطفل</th>
                <th>البيان</th>
                <th style="width: 25px;">العدد</th>
                <th style="width: 30px;">كتف</th>
                <th style="width: 30px;">صدر</th>
                <th style="width: 30px;">كم</th>
                <th style="width: 30px;">وسط</th>
                <th style="width: 30px;">طول</th>
                <th>التسليم</th>
                <th>ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="text-center">{{ $row['serial'] }}</td>
                    <td>{{ $row['number'] }}</td>
                    <td>{{ $row['customer'] }}</td>
                    <td>{{ $row['beneficiary'] ?? '—' }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="text-center">{{ $row['quantity'] }}</td>
                    <td class="text-center">{{ $row['shoulder'] ?? '—' }}</td>
                    <td class="text-center">{{ $row['chest'] ?? '—' }}</td>
                    <td class="text-center">{{ $row['sleeve'] ?? '—' }}</td>
                    <td class="text-center">{{ $row['waist'] ?? '—' }}</td>
                    <td class="text-center">{{ $row['length'] ?? '—' }}</td>
                    <td>{{ $row['due_on'] ?? '—' }}</td>
                    <td>{{ $row['notes'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center">لا توجد طلبات في الفترة المحددة.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <p class="muted">أُنشئ هذا التقرير في {{ now(config('app.display_timezone'))->format('Y-m-d H:i') }} بتوقيت الخرطوم.</p>
</body>
</html>
