# Account Deletion Readiness Audit (Phase 10G → implemented in Phase 10H)

## Summary

**Outcome: Implemented in Phase 10H — release blocker resolved pending owner/legal sign-off on retention wording.**

Customer account deletion is available in-app and via public legal documentation. Financial and operational records are preserved through anonymization rather than hard deletion.

## Implementation status

| Requirement | Status |
|-------------|--------|
| In-app deletion with password + `DELETE` confirmation | Done |
| Token revocation | Done |
| Address deletion | Done |
| Profile anonymization | Done |
| Orders/payments preserved | Done |
| Public `/legal/account-deletion` page (EN/AR) | Done |
| Filament deleted-customer visibility | Done |
| Flutter session clear + login redirect | Done |
| Backend and Flutter tests | Done |
| Optional destructive staging integration test | Done (disabled by default) |

## Architecture reference

See [ACCOUNT-DELETION.md](./ACCOUNT-DELETION.md) for the full design, API contract, data matrix, and testing instructions.

## Data held per customer (after deletion)

| Data | Storage | After deletion |
|------|---------|----------------|
| Profile (name, phone, email) | `customers` | Anonymized placeholders |
| Password hash | `customers` | Cleared |
| Sanctum tokens | `personal_access_tokens` | Revoked |
| Saved addresses | `customer_addresses` | Deleted |
| Active cart | `carts`, `cart_items` | Items cleared |
| Orders | `orders`, `order_items` | Preserved on anonymized customer ID |
| Payments | `payments` | Preserved |
| Stock reservations | `stock_levels` | Unchanged |

## Play Store impact

Google Play account deletion URL:

`https://<backend-host>/legal/account-deletion`

In-app path: **Profile → Settings → Delete account**

**OWNER actions before production:**

- Confirm legal retention wording on public page and privacy policy
- Complete Data safety form with accurate deleted vs retained data disclosure
- Provide production legal base URL in store listing

## Re-registration

Original phone/email may be reused after anonymization. New accounts receive a new customer ID and are not linked to prior orders.

## Related files

- `app/Services/Customers/CustomerDeletionService.php`
- `app/Http/Controllers/Api/CustomerAccountController.php`
- `torino-moda-style-mobile/lib/features/settings/presentation/delete_account_page.dart`
