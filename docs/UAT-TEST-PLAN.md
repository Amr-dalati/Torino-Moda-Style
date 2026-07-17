# UAT Test Plan — Full Customer Journey

Controlled manual UAT checklist for Torino Moda Style before store release. Mark each item Pass / Fail / N/A with tester name and date.

**Prerequisites:** Thawani UAT configured per [THAWANI-UAT-CHECKLIST.md](./THAWANI-UAT-CHECKLIST.md). Test accounts documented outside git.

---

## 1. Authentication

| # | Scenario | Steps | Expected | Result |
|---|----------|-------|----------|--------|
| A1 | Register | Create new account with valid phone/password | Account created, logged in | |
| A2 | Login | Login with valid credentials | Session restored, products visible | |
| A3 | Logout | Settings → Logout | Returned to login, token cleared | |
| A4 | Invalid credentials | Wrong password | Clear error, no session | |
| A5 | Session restoration | Kill app, reopen while logged in | Still authenticated | |

---

## 2. Catalog

| # | Scenario | Expected | Result |
|---|----------|----------|--------|
| C1 | Products load | Product list with images/prices | |
| C2 | Search | Results match query | |
| C3 | Category filter | Filtered list | |
| C4 | Brand filter | Filtered list | |
| C5 | Product details | Detail page loads | |
| C6 | In-stock variant | Shows in-stock indicator, add-to-cart enabled | |
| C7 | Out-of-stock variant | Shows out-of-stock, add blocked or warned | |

---

## 3. Cart

| # | Scenario | Expected | Result |
|---|----------|----------|--------|
| T1 | Add item | Item appears in cart | |
| T2 | Update quantity | Quantity and totals update | |
| T3 | Remove item | Item removed | |
| T4 | Stock validation | Cannot exceed available stock | |
| T5 | Last-item concurrency | Two devices, one unit — second checkout fails safely | |

---

## 4. Addresses and Delivery

| # | Scenario | Expected | Result |
|---|----------|----------|--------|
| D1 | Add address | Address saved | |
| D2 | Edit address | Changes persisted | |
| D3 | Default address | Default used in checkout | |
| D4 | Region/area selection | Delivery area required | |
| D5 | Delivery fee | Fee shown in quote | |

---

## 5. Checkout

| # | Scenario | Expected | Result |
|---|----------|----------|--------|
| K1 | Quote | Totals match backend | |
| K2 | Order creation | Order created, pending payment | |
| K3 | Duplicate checkout prevention | Cannot double-submit | |
| K4 | Stock reservation | Stock reserved until paid/expired | |

---

## 6. Thawani Payment

| # | Scenario | Expected | Result |
|---|----------|----------|--------|
| P1 | Successful payment | Paid status, stock committed | |
| P2 | Cancelled payment | Unpaid, stock released on expiry | |
| P3 | Failed payment | Failed status shown | |
| P4 | Expired payment | Expired status, stock released | |
| P5 | Duplicate webhook | Idempotent, single payment record | |
| P6 | Webhook delayed after return | Polling eventually confirms | |
| P7 | Browser return to app | Deep link opens app | |
| P8 | Cold-start deep link | App opens to payment result | |
| P9 | Warm-state deep link | Payment result updates | |
| P10 | Polling timeout | User can retry verification | |
| P11 | Retry | Retry verification works | |
| P12 | Invalid payment URL | Blocked, trusted-host error | |

---

## 7. Admin (Filament)

| # | Scenario | Expected | Result |
|---|----------|----------|--------|
| M1 | View order | Order details correct | |
| M2 | Payment status | Matches Thawani/backend | |
| M3 | Process order | Status advances | |
| M4 | Ship | Shipped status | |
| M5 | Deliver | Delivered status | |
| M6 | Cancel unpaid order | Cancelled, stock released | |
| M7 | Reject paid-order cancellation | Blocked safely | |
| M8 | Adjust stock | Adjustment recorded | |
| M9 | View stock audit | Audit trail visible | |

---

## 8. Arabic and English

| # | Scenario | Expected | Result |
|---|----------|----------|--------|
| L1 | RTL layout (Arabic) | Correct alignment | |
| L2 | Translation completeness | No missing keys in primary flows | |
| L3 | Currency formatting | OMR formatted correctly | |
| L4 | Long text handling | No overflow in product/legal pages | |
| L5 | Legal pages (EN) | All five documents load | |
| L6 | Legal pages (AR) | RTL legal content loads | |
| L7 | Settings language persist | Choice survives app restart | |
| L8 | Settings theme persist | Choice survives app restart | |

---

## 9. Failure Scenarios

| # | Scenario | Expected | Result |
|---|----------|----------|--------|
| F1 | Backend unavailable | Graceful error, retry option | |
| F2 | Slow internet | Loading states, no crash | |
| F3 | Webhook unavailable | Deep link + polling recovers | |
| F4 | Scheduler unavailable | Manual expiry command documented | |
| F5 | Invalid payment URL | Not opened | |
| F6 | Expired customer token | Redirect to login | |

---

## 10. Release configuration validation

| # | Check | Command / action | Result |
|---|-------|------------------|--------|
| R1 | Backend production check | `php artisan app:production-check` | |
| R2 | Thawani check | `php artisan payments:thawani-check` | |
| R3 | Readiness endpoint | `GET /api/readiness` | |
| R4 | Flutter analyze clean | `flutter analyze` | |
| R5 | Flutter tests pass | `flutter test` | |

---

## Sign-off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| QA Lead | | | |
| Product Owner | | | |
| Engineering | | | |
