<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalController extends Controller
{
    protected const PAGES = [
        'privacy' => ['view' => 'legal.privacy', 'title_en' => 'Privacy Policy', 'title_ar' => 'سياسة الخصوصية'],
        'terms' => ['view' => 'legal.terms', 'title_en' => 'Terms and Conditions', 'title_ar' => 'الشروط والأحكام'],
        'returns' => ['view' => 'legal.returns', 'title_en' => 'Return and Refund Policy', 'title_ar' => 'سياسة الإرجاع والاسترداد'],
        'shipping' => ['view' => 'legal.shipping', 'title_en' => 'Shipping and Delivery Policy', 'title_ar' => 'سياسة الشحن والتوصيل'],
        'contact' => ['view' => 'legal.contact', 'title_en' => 'Contact Information', 'title_ar' => 'معلومات التواصل'],
        'account-deletion' => ['view' => 'legal.account-deletion', 'title_en' => 'Account Deletion', 'title_ar' => 'حذف الحساب'],
    ];

    public function privacy(Request $request): View
    {
        return $this->render('privacy', $request);
    }

    public function terms(Request $request): View
    {
        return $this->render('terms', $request);
    }

    public function returns(Request $request): View
    {
        return $this->render('returns', $request);
    }

    public function shipping(Request $request): View
    {
        return $this->render('shipping', $request);
    }

    public function contact(Request $request): View
    {
        return $this->render('contact', $request);
    }

    public function accountDeletion(Request $request): View
    {
        return $this->render('account-deletion', $request);
    }

    protected function render(string $page, Request $request): View
    {
        $definition = self::PAGES[$page];
        $locale = $this->resolveLocale($request);

        return view($definition['view'], [
            'locale' => $locale,
            'pageTitle' => $locale === 'ar' ? $definition['title_ar'] : $definition['title_en'],
            'companyName' => config('legal.company_name'),
            'contactEmail' => config('legal.contact_email'),
            'contactPhone' => config('legal.contact_phone'),
            'contactAddress' => config('legal.contact_address'),
            'lastUpdated' => config('legal.last_updated'),
        ]);
    }

    protected function resolveLocale(Request $request): string
    {
        $requested = strtolower((string) $request->query('lang', ''));

        if (in_array($requested, config('legal.supported_locales', ['en']), true)) {
            return $requested;
        }

        $preferred = $request->getPreferredLanguage(config('legal.supported_locales', ['en']));

        return in_array($preferred, config('legal.supported_locales', ['en']), true)
            ? $preferred
            : (string) config('legal.default_locale', 'en');
    }
}
