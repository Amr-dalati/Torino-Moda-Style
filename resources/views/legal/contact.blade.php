@extends('legal.layout')

@section('content')
    @if ($locale === 'ar')
        <p>للدعم أو الاستفسارات التجارية، يرجى التواصل مع {{ $companyName }}.</p>
        <h2>البريد الإلكتروني</h2>
        <p>{{ $contactEmail }}</p>
        <h2>الهاتف</h2>
        <p>{{ $contactPhone }}</p>
        <h2>العنوان</h2>
        <p>{{ $contactAddress }}</p>
    @else
        <p>For support or business enquiries, contact {{ $companyName }}.</p>
        <h2>Email</h2>
        <p>{{ $contactEmail }}</p>
        <h2>Phone</h2>
        <p>{{ $contactPhone }}</p>
        <h2>Address</h2>
        <p>{{ $contactAddress }}</p>
    @endif

    @if (str_contains($contactEmail, '[') || str_contains($contactPhone, '['))
        <p class="placeholder">{{ $locale === 'ar' ? 'يرجى تحديث بيانات التواصل قبل الإطلاق.' : 'Update contact details before release.' }}</p>
    @endif
@endsection
