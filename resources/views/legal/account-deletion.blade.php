@extends('legal.layout')

@section('content')
    @if ($locale === 'ar')
        <p>توضح هذه الصفحة كيفية حذف حسابك في تطبيق Torino Moda Style وما يحدث لبياناتك.</p>

        <h2>كيفية حذف حسابك داخل التطبيق</h2>
        <ol>
            <li>سجّل الدخول إلى حسابك.</li>
            <li>افتح <strong>الملف الشخصي</strong> ثم <strong>الإعدادات</strong>.</li>
            <li>اختر <strong>حذف الحساب</strong>.</li>
            <li>اقرأ ملخص البيانات المحذوفة والمحتفَظ بها.</li>
            <li>أدخل كلمة المرور الحالية واكتب <strong>DELETE</strong> للتأكيد.</li>
            <li>بعد النجاح، يُلغى وصولك نهائياً ويتم تسجيل خروجك من التطبيق.</li>
        </ol>

        <h2>البيانات التي تُحذف</h2>
        <ul>
            <li>عناوين التوصيل المحفوظة.</li>
            <li>جلسات تسجيل الدخول النشطة (الرموز المميزة).</li>
            <li>محتوى السلة النشطة.</li>
            <li>الحقول الشخصية غير الضرورية في الملف الشخصي (الاسم، البريد، الهاتف، وغيرها).</li>
        </ul>

        <h2>البيانات التي قد تُحتفَظ بها</h2>
        <ul>
            <li>الطلبات وسجل الدفع المرتبط بها.</li>
            <li>لقطات التوصيل المخزّنة على الطلبات.</li>
            <li>سجلات المخزون والتدقيق التشغيلي المرتبطة بالطلبات.</li>
            <li>سجلات تكامل الدفع (webhooks) اللازمة للعمليات والمحاسبة.</li>
        </ul>

        <h2>لماذا قد تُحتفَظ الطلبات والمدفوعات؟</h2>
        <p>قد نحتفظ بسجلات الطلبات والمدفوعات لأسباب محاسبية وقانونية وتشغيلية، بما في ذلك حل النزاعات والتدقيق. يتم فصل هويتك الشخصية عن هذه السجلات عبر إخفاء هوية حسابك مع الإبقاء على معرّف الحساب الداخلي للربط التشغيلي.</p>

        <h2>الوصول بعد الحذف</h2>
        <p>بعد حذف الحساب، يُلغى وصولك نهائياً ولا يمكنك تسجيل الدخول مرة أخرى باستخدام نفس الحساب. يمكنك إنشاء حساب جديد لاحقاً باستخدام نفس رقم الهاتف أو البريد إذا كان متاحاً، ولن يرتبط الحساب الجديد تلقائياً بطلبات الحساب السابق.</p>

        <h2>التواصل</h2>
        <p>للاستفسارات: {{ $contactEmail }} — {{ $contactPhone }}</p>
    @else
        <p>This page explains how to delete your Torino Moda Style account and what happens to your data.</p>

        <h2>How to delete your account in the app</h2>
        <ol>
            <li>Sign in to your account.</li>
            <li>Open <strong>Profile</strong> then <strong>Settings</strong>.</li>
            <li>Choose <strong>Delete account</strong>.</li>
            <li>Review the summary of data that will be deleted and retained.</li>
            <li>Enter your current password and type <strong>DELETE</strong> to confirm.</li>
            <li>After success, your access is permanently revoked and you are signed out.</li>
        </ol>

        <h2>Data that is deleted</h2>
        <ul>
            <li>Saved delivery addresses.</li>
            <li>Active sign-in sessions (tokens).</li>
            <li>Active cart contents.</li>
            <li>Non-essential personal profile fields (name, email, phone, and similar).</li>
        </ul>

        <h2>Data that may be retained</h2>
        <ul>
            <li>Orders and related payment records.</li>
            <li>Delivery snapshots stored on orders.</li>
            <li>Inventory and operational audit records linked to orders.</li>
            <li>Payment integration logs (webhooks) required for operations and accounting.</li>
        </ul>

        <h2>Why orders and payments may be retained</h2>
        <p>We may retain order and payment records for accounting, legal, and operational reasons, including dispute resolution and audits. Your personal identity is separated from these records by anonymizing your account while keeping the internal account identifier for operational linkage.</p>

        <h2>Access after deletion</h2>
        <p>After deletion, your access is permanently revoked and you cannot sign in again with the same account. You may create a new account later using the same phone or email if available; the new account is not automatically linked to previous orders.</p>

        <h2>Contact</h2>
        <p>Questions: {{ $contactEmail }} — {{ $contactPhone }}</p>
    @endif

    @if (str_contains($companyName, '['))
        <p class="placeholder">{{ $locale === 'ar' ? 'يرجى تحديث تفاصيل الشركة قبل الإطلاق.' : 'Update company details before release.' }}</p>
    @endif
@endsection
