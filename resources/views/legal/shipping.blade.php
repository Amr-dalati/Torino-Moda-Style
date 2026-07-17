@extends('legal.layout')

@section('content')
    @if ($locale === 'ar')
        <p>نوضح هنا مناطق التوصيل والرسوم والأوقات المتوقعة.</p>
        <h2>مناطق التوصيل</h2>
        <p>تعتمد مناطق التوصيل على البيانات المتاحة داخل التطبيق وقت الطلب.</p>
        <h2>الرسوم</h2>
        <p>يتم حساب رسوم التوصيل أثناء عملية الدفع قبل تأكيد الطلب.</p>
        <h2>الأوقات المتوقعة</h2>
        <p>الأوقات تقديرية وقد تتأثر بعوامل تشغيلية خارجة عن التطبيق.</p>
    @else
        <p>This page explains delivery areas, fees, and expected timelines.</p>
        <h2>Delivery areas</h2>
        <p>Supported delivery areas are shown in the app at checkout time.</p>
        <h2>Fees</h2>
        <p>Delivery fees are calculated during checkout before order confirmation.</p>
        <h2>Timelines</h2>
        <p>Delivery estimates are indicative and may vary for operational reasons.</p>
    @endif
@endsection
