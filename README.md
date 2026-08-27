# RescueNepal.info — Complete Fresh-Install Source Code v2.4

A practical PHP 8 + MySQL/MariaDB disaster rescue, missing-person, rescued-person and deceased-body tracing registry designed for BabalHost/cPanel shared hosting.

This is a **fresh installation package**. It contains the complete application source and latest schema, with **no seeded/demo cases and no production database password**.

## Main public workflow

- `/` — public home
- `/missing-person` — report a missing person
- `/rescue-request` — person currently waiting for rescue
- `/rescued-person` — officials/rescuers/shelters/hospitals can register a person already rescued
- `/find` — Universal Search for **Rescued Person or Dead Body**
- `/track/{CASE-ID}` — public status where permitted
- `/family-match/{CASE-ID}` — submit a family match request

## v2.4 practical field model

### Missing Person
Instead of an open-ended “Purpose of Going” field, record:
- **Person Category**: Local / Worker / Tourist / Student / Other
- **Work Institution / Office / Destination / School Name**

### Rescued Person
Choose condition first:
- Safe / सकुशल
- Injured / घाइते
- Semi-conscious / अर्धचेत
- Unconscious / अचेत

For **Safe**, collect the fast identity/contact set: name, age, gender, mobile, workplace, destination and family/contact person.

For **Injured / Semi-conscious / Unconscious**, collect approximate age, gender, documents found, document details, clothing/marks, condition and current hospital/shelter/institution.

The registration also records the official/rescuer/institution that rescued or reported the person and its phone/contact details.

### Dead Body / DVI
Authorized staff can record approximate gender/age, found location, recovery organization, body condition, restricted photos, documents and belongings, clothes/birthmarks/tattoos/scars, and the hospital/mortuary/dead-body facility to which the body was shifted.

Every deceased case receives a separate **Body Trace ID (`RN-DVI-...`)** that remains attached to the body throughout recovery, transfer, identification and handover.

Advanced fingerprint, DNA, dental and post-mortem fields remain available under restricted forensic details; public users never see body photographs or forensic data.

### Universal Search
The public `/find` search is intentionally limited to:
- **Rescued Person:** name, mobile number, document number/details, workplace/destination, location and identifying marks.
- **Dead Body:** Body Trace ID, huliya/appearance, document clues, clothes, tattoo/birthmark/scars and recovery location.

A search result is for tracing only and does not itself prove identity.

### Reunification / Handover
At handover staff can:
- link to an existing Missing Case ID suggested by matching;
- manually enter a Missing Case ID if it was not suggested;
- link a Family Match Request when no missing case exists;
- record direct family details and verification/procedure notes;
- reunite a rescued person or hand over an identified deceased person;
- close the resolved case when the user role has approval authority, otherwise create the normal closure request.

## Target deployment
Upload/extract the contents directly into the document root for `https://rescuenepal.info`.

### Requirements
- PHP 8.1+
- MariaDB/MySQL
- PDO MySQL
- ZipArchive
- SimpleXML
- GD
- Fileinfo
- Mbstring
- OpenSSL
- Apache mod_rewrite / cPanel `.htaccess`
- HTTPS

## Fresh installation
1. Create a **new empty** MySQL/MariaDB database and user in cPanel and grant ALL PRIVILEGES.
2. Extract this package into the `rescuenepal.info` document root.
3. Ensure PHP can write to `uploads/photos`, `uploads/thumbs`, `uploads/imports`, `uploads/evidence`, `uploads/family`, `storage/sessions`, and `storage/security`.
4. Open `https://rescuenepal.info/setup`.
5. Read the Setup Access Code from `deployment/SETUP_ACCESS_CODE.txt`.
6. Enter the base URL, DB credentials and first Superadmin details.
7. After successful setup, login at `/admin/login` and verify the dashboard starts with zero cases.

Do not reuse a production database for a fresh install.

## Upgrade an existing installation
**Back up the database, source files, `config.php`, uploads and storage first.**

### From v2.3
1. Preserve production `config.php` and existing uploads/storage.
2. Upload the v2.4 application files.
3. Run `deployment/MIGRATE_v2.3_to_v2.4.sql` exactly once on the existing database.
4. Verify rescued registration, dead-body registration, Universal Search, handover and Excel export.
5. Do **not** run `/setup` against the existing production database.

### Directly from v2.2
Use `deployment/MIGRATE_v2.2_to_v2.4.sql` instead. It contains the v2.2→v2.3 and v2.3→v2.4 schema changes in sequence.

## Restricted media
- `uploads/evidence` — deceased/DVI evidence photographs; denied to direct web access.
- `uploads/family` — family-match photographs; denied to direct web access.
- Authorized staff retrieve restricted media through `/admin/media/...`.

## Admin/staff functions
- case dashboard and filters
- rescued-person registration/review
- deceased/DVI registration and reconciliation
- family match requests
- identification approvals
- reunification/handover and closure controls
- chain of custody
- Excel export/import
- audit/activity history
- user/role management

## Main database tables
`cases`, `rescued_person_details`, `deceased_details`, `case_media`, `case_matches`, `family_match_requests`, `family_match_otps`, `identification_approvals`, `custody_events`, `handover_records`, `case_updates`, `case_photos`, `rescue_locations`, `admins`, `closure_requests`, `audit_logs`, `imported_files`.

See `docs/V2.4_CHANGELOG.md` and `docs/QA_CHECKLIST.md` before production deployment.
