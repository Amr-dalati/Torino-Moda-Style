@extends('legal.layout')

@section('content')
    @if ($locale === 'ar')
        <p>توضح سياسة الخصوصية هذه كيفية تعامل {{ $companyName }} مع بيانات العملاء في تطبيق Torino Moda Style.</p>
        <h2>البيانات التي نجمعها</h2>
        <ul>
            <li>الاسم ورقم الهاتف والبريد الإلكتروني الاختياري.</li>
            <li>عناوين التوصيل وتفاصيل الطلب.</li>
            <li>سجلات الدفع اللازمة لإتمام المعاملات دون تخزين بيانات بطاقة كاملة في التطبيق.</li>
        </ul>
        <h2>كيفية استخدام البيانات</h2>
        <p>نستخدم البيانات لتقديم الخدمة، ومعالجة الطلبات، ودعم العملاء، وتحسين الأمان.</p>
        <h2>مشاركة البيانات</h2>
        <p>قد تتم مشاركة البيانات الضرورية مع مزودي الدفع والتوصيل فقط لإتمام الطلب.</p>
        <h2>حذف الحساب</h2>
        <p>يمكنك حذف حسابك من الإعدادات داخل التطبيق. للتفاصيل راجع <a href="/legal/account-deletion?lang=ar">صفحة حذف الحساب</a>.</p>
        <h2>التواصل</h2>
        <p>للاستفسارات: {{ $contactEmail }} — {{ $contactPhone }}</p>
    @else
        <p>This Privacy Policy explains how {{ $companyName }} handles customer data in the Torino Moda Style mobile application.</p>
        <h2>Data we collect</h2>
        <ul>
            <li>Name, phone number, and optional email.</li>
            <li>Delivery addresses and order details.</li>
            <li>Payment records required to complete transactions. Full card data is not stored in the app.</li>
        </ul>
        <h2>How we use data</h2>
        <p>We use data to provide the service, process orders, support customers, and improve security.</p>
        <h2>Data sharing</h2>
        <p>Necessary data may be shared with payment and delivery providers only to fulfil an order.</p>
        <h2>Account deletion</h2>
        <p>You can delete your account from in-app Settings. See the <a href="/legal/account-deletion?lang=en">account deletion page</a> for details.</p>
        <h2>Contact</h2>
        <p>Questions: {{ $contactEmail }} — {{ $contactPhone }}</p>
    @endif

    @if (str_contains($companyName, '['))
        <p class="placeholder">{{ $locale === 'ar' ? 'يرجى تحديث تفاصيل الشركة قبل الإطلاق.' : 'Update company details before release.' }}</p>
    @endif
@endsection
