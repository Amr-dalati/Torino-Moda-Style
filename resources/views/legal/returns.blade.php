@extends('legal.layout')

@section('content')
    @if ($locale === 'ar')
        <p>توضح هذه السياسة الإجراءات العامة للإرجاع والاسترداد.</p>
        <h2>الأهلية</h2>
        <p>تخضع طلبات الإرجاع لحالة المنتج وسياسة المتجر المعتمدة وقت الطلب.</p>
        <h2>الاسترداد</h2>
        <p>تتم معالجة الاستردادات المؤهلة عبر قنوات الدفع الأصلية عند توفرها.</p>
        <h2>ملاحظة</h2>
        <p>قد لا تتوفر إلغاءات تلقائية للطلبات المدفوعة من لوحة الإدارة حتى اكتمال مسار الاسترداد.</p>
    @else
        <p>This policy describes general return and refund handling.</p>
        <h2>Eligibility</h2>
        <p>Return requests depend on product condition and the store policy in effect at order time.</p>
        <h2>Refunds</h2>
        <p>Eligible refunds are processed through original payment channels where available.</p>
        <h2>Note</h2>
        <p>Paid-order cancellations may not be available in admin until a dedicated refund workflow is enabled.</p>
    @endif
@endsection
