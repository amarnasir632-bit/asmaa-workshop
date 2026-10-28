<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans, sans-serif; direction: rtl; font-size: 8pt; color: #24313d; }
        h1 { font-size: 14pt; color: #1f4e79; margin: 0 0 5px 0; }
        p { margin: 3px 0; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 0.5px solid #cbd5e1; padding: 5px; text-align: right; }
        th { background-color: #eef2f6; color: #1f4e79; font-size: 7.5pt; }
        .text-center { text-align: center; }
        .muted { color: #64748b; font-size: 7pt; }
    </style>
</head>
<body>
    <h1>مشغل أسماء · نموذج تفصيل يدوي للترزي</h1>
    <p>نموذج ورقي فارغ لتسجيل مقاسات وطلبات التفصيل يدوياً</p>

    <table>
        <thead>
            <tr>
                <th style="width: 20px;">#</th>
                <th>رقم الطلب</th>
                <th>العميل</th>
                <th>المستفيد</th>
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
            @for($i = 1; $i <= $rows; $i++)
                <tr>
                    <td class="text-center" style="height: 24px;">{{ $i }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endfor
        </tbody>
    </table>
    <p class="muted">أُنشئ هذا النموذج في {{ now(config('app.display_timezone'))->format('Y-m-d H:i') }} بتوقيت الخرطوم.</p>
</body>
</html>
