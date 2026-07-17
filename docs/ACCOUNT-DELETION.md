# Account Deletion Architecture (Phase 10H)

## Decision: immediate anonymization (Option A)

There is no scheduled grace period. When a customer confirms deletion with password and `DELETE`, the account is anonymized immediately, all tokens are revoked, and the customer is signed out.

## Flow

1. Customer opens **Settings → Delete account**.
2. Customer reviews deleted vs retained data summary.
3. Customer submits password and types `DELETE`.
4. `DELETE /api/customer/account` validates input and calls `CustomerDeletionService`.
5. Service locks the customer row, verifies password, cancels open unpaid orders with active reservations, deletes addresses, clears active cart items, anonymizes profile fields, sets deletion timestamps, then revokes tokens.
6. Flutter clears secure storage and Riverpod session state, then navigates to login.

## Data retention matrix

| Data | Action | Notes |
|------|--------|-------|
| Saved addresses | **Delete** | Order shipping snapshots remain on orders |
| Sanctum tokens | **Revoke** | Current and all other tokens |
| Active cart items | **Delete** | Only from active carts not linked to retained orders |
| Profile name/email/phone/password | **Anonymize / clear** | Unique placeholder values per customer ID |
| Phoenix external ID | **Clear** | Optional linkage removed |
| Orders | **Preserve** | Still linked to anonymized customer ID |
| Order items | **Preserve** | Unchanged |
| Payments | **Preserve** | Unchanged |
| Payment webhooks | **Preserve** | Unchanged |
| Stock allocations / levels | **Preserve** | Paid orders untouched; open unpaid orders cancelled via `cancelUnpaid` before deletion |
| Operational audit records | **Preserve** | Unchanged |

## Anonymized placeholder format

- Name: `Deleted Customer #{id}`
- Email: `deleted-{id}@example.invalid`
- Phone: `deleted-phone-{id}`

## Re-registration behavior

After anonymization, the original phone and email are no longer stored on the customer row. A new registration with the same phone or email creates a **new customer ID**. Previous orders remain linked to the anonymized retained customer ID. The new account cannot access old orders.

## API endpoint

- **DELETE** `/api/customer/account`
- **Auth**: customer Sanctum token
- **Rate limit**: `account.deletion.strict` (3/minute per customer)
- **Body**:

```json
{
  "password": "current password",
  "confirmation": "DELETE",
  "deletion_reason": "optional"
}
```

- **Success**: standard envelope, `data: null`, message `Account deleted successfully`
- **Validation errors**: 422 for wrong password or confirmation

## Public account deletion URL (Google Play)

`https://<backend-host>/legal/account-deletion`

Use English: `?lang=en` or Arabic: `?lang=ar`.

## Admin behavior (Filament)

- Deleted customers show **Deleted** badge and anonymized display values
- Deletion and anonymization timestamps visible
- Retained order count shown
- Filter for active/deleted customers
- No admin delete/restore action (read-only resource)

## Security

- Password verified with Laravel hashing inside a transaction with `lockForUpdate`
- Tokens are revoked only after password validation and all mutations succeed
- Failed password or confirmation produces zero persistent changes
- Service-level idempotency for already-deleted customers revokes stray tokens only; the public API returns 401 after success because the current token is revoked
- Customer can delete only their own account via authenticated token
- Responses do not expose anonymized internal values
- Passwords and original PII are not logged

## Pending unpaid orders

Before anonymization, open unpaid orders with active stock reservations (not yet committed) are cancelled through `OrderFulfillmentService::cancelUnpaid`. The order record is preserved in `cancelled` status and stock reservations are released safely. Paid orders and committed stock are never modified.

## Testing

### Backend

```bash
php artisan test --filter=CustomerAccountDeletionTest
```

### Flutter

```bash
flutter test test/features/auth/delete_account_request_test.dart
flutter test test/features/auth/delete_account_repository_test.dart
flutter test test/features/settings/delete_account_page_test.dart
```

### Destructive staging integration test (disabled by default)

Requires a dedicated disposable staging customer:

```bash
flutter test integration_test/account_deletion_test.dart \
  --dart-define=APP_ENV=staging \
  --dart-define=ACCOUNT_DELETION_TEST_PHONE=... \
  --dart-define=ACCOUNT_DELETION_TEST_PASSWORD=...
```

**Warning:** this permanently anonymizes the configured account. Never use the shared UAT customer or production.

## Owner / legal review still required

- Exact statutory retention periods for orders and payments — **OWNER / LEGAL**
- Final privacy policy wording referencing deletion — **OWNER / LEGAL**
- Google Play Data safety form answers — **OWNER**

Do not invent retention durations in code or docs.

## Related files

- `app/Services/Customers/CustomerDeletionService.php`
- `app/Http/Controllers/Api/CustomerAccountController.php`
- `resources/views/legal/account-deletion.blade.php`
- `torino-moda-style-mobile/lib/features/settings/presentation/delete_account_page.dart`
