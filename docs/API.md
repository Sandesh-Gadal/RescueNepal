# RescueNepal.info v2.4 API Reference

Base: `/api/`. Authentication uses the same PHP session cookie as the admin dashboard.

## Existing public API creation
`POST /api/cases`

Public API creation remains intentionally limited to `missing` and `rescue_waiting`. The new rescued-person and DVI workflows use their dedicated validated web endpoints because they write additional linked records and, for DVI, restricted forensic data.

- Missing minimum: `type=missing`, `name`, `family_contact_phone`
- Missing practical fields: `missing_person_context` = `local|worker|tourist|student|other`; `associated_place_name` = work institution / office / destination / school name
- Rescue-request minimum: `type=rescue_waiting`, `family_contact_phone`, `latitude`, `longitude`

## Admin cases
- `GET /api/cases?q=&status=&type=&page=&per_page=`
- `GET /api/cases/{id}`
- `PUT /api/cases/{id}` — normal descriptive fields only; direct status mutation is not allowed in v2.4
- `GET /api/cases/{id}/updates`
- `POST /api/cases/{id}/updates`

The update endpoint is case-type aware. For `deceased`, it only accepts non-conclusive operational states such as identity unknown, potential match, family contacted, identification review and transfer. It cannot set formal forensic confirmation or handover status.

## Closure
`POST /api/cases/{id}/close`

Operator creates approval request; Approver/Superadmin can close according to existing permissions.

## Admin management
- `GET /api/admins` — Approver/Superadmin
- `POST /api/admins` — Approver/Superadmin; only Superadmin can create another Superadmin

## Export / report
- `GET /api/export/xlsx?ids=1,2,3`
- `GET /api/cases/{id}/pdf`
- `GET /api/cases/{id}/idcard`

## Dedicated v2.4 web workflow routes
These are session/CSRF-protected web workflows rather than generic REST endpoints:
- `/rescued-person`
- `/submit-rescued`
- `/find`
- `/family-match/{case-id}`
- `/otp`
- `/admin/new-report/deceased`
- `/admin/reconciliation/{id}`
- `/admin/family-requests`
- `/admin/cases/{id}/custody`
- `/admin/cases/{id}/handover`

This separation is deliberate: DVI approval and handover must not be bypassable through a broad generic API update.
