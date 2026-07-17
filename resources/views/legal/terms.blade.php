@extends('legal.layout')

@section('content')
    @if ($locale === 'ar')
        <p>باستخدامك تطبيق Torino Moda Style فإنك توافق على هذه الشروط.</p>
        <h2>استخدام الخدمة</h2>
        <p>يجب أن تقدم معلومات دقيقة وأن تستخدم التطبيق للشراء الشخصي المشروع فقط.</p>
        <h2>الطلبات والأسعار</h2>
        <p>الأسعار والتوفر قد يتغيران. يتم تأكيد الطلب بعد إتمام الدفع بنجاح.</p>
        <h2>المسؤولية</h2>
        <p>الخدمة تُقدم كما هي ضمن حدود القانون المعمول به.</p>
    @else
        <p>By using Torino Moda Style you agree to these terms.</p>
        <h2>Use of the service</h2>
        <p>You must provide accurate information and use the app only for lawful personal shopping.</p>
        <h2>Orders and pricing</h2>
        <p>Prices and availability may change. Orders are confirmed after successful payment.</p>
        <h2>Liability</h2>
        <p>The service is provided as available within applicable law.</p>
    @endif
@endsection
