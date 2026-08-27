# RescueNepal.info v2.4 QA / Acceptance Checklist

## Fresh install / upgrade
- [ ] Fresh `/setup` on an empty DB creates the full schema, one Superadmin and zero cases.
- [ ] Existing database backup is taken before running the correct v2.2→v2.4 or v2.3→v2.4 migration.
- [ ] Writable folders include photos, thumbs, imports, evidence, family, sessions and security.

## Public mobile flows
- [ ] Missing Person flow works in English/Nepali and creates an RN Case ID.
- [ ] Rescue Request captures mobile GPS and creates a case.
- [ ] Rescued Person Safe / सकुशल flow saves name, age, gender, mobile, workplace/destination and contact person.
- [ ] Injured / Semi-conscious / Unconscious flow saves approximate age, gender, document clues and hospital/shelter details.
- [ ] Rescued record saves condition, rescue place/GPS, current institution and institution contact.
- [ ] Rescued submission receipt is labeled Rescued Person, not Missing Person.
- [ ] `/find` searches only Rescued Person / Dead Body public records with type/gender/age/location filters.
- [ ] Unknown rescued-person public card exposes only approved photo/basic identifying information.
- [ ] Deceased public card never renders the uploaded body/recovery photo.
- [ ] Deceased public result never exposes DNA, fingerprint, PM/forensic notes or officer personal details.
- [ ] “This may be my family member” creates an FR request and stores uploaded family photo.
- [ ] With SMS webhook configured, correct OTP verifies and wrong/expired OTP fails.
- [ ] Without SMS webhook, request is saved as phone-unverified for staff review and UI says so.

## Reconciliation / DVI
- [ ] Authorized operator can create a practical dead-body record with Body Trace ID, recovery organization, condition, multiple restricted photos and shifted-to location.
- [ ] DVI record creates initial chain-of-custody recovery event.
- [ ] Matching can compare a rescued/deceased record against active missing cases.
- [ ] Candidate score is visibly labelled as a search aid, not identity proof.
- [ ] Deceased candidate match cannot be confirmed merely from similarity score.
- [ ] Operator can submit formal identification approval with forensic/official basis.
- [ ] Requesting admin cannot approve their own identification request.
- [ ] Approver/Superadmin can approve/reject another user's identification request.
- [ ] Normal case-status page cannot set `forensic_confirmed` or `handed_over`.
- [ ] Normal API update cannot set formal DVI identification/handover status.
- [ ] Official identity remains hidden publicly until public-release setting is explicitly enabled.

## Chain of custody / handover
- [ ] Custody events append without editing previous events.
- [ ] Deceased handover is blocked before formal identity confirmation.
- [ ] Confirmed deceased handover saves recipient, ID, witnesses, officer, office, date/time/location.
- [ ] Deceased handover writes a `released` custody event.
- [ ] Rescued-person reunification links a Missing Case ID or Family Match Request where available, writes a handover/reunification record and resolves the case through the permitted closure flow.

## Admin / reporting
- [ ] Dashboard displays Missing, Rescue Requests, Rescued Persons and DVI/Deceased separately.
- [ ] Family Match Requests queue shows pending requests and manual phone-verification control.
- [ ] DVI identification approvals show pending count to authorized approvers.
- [ ] Individual admin report renders correct fields for all four case types.
- [ ] Excel export contains Cases, RescuedPersons, CaseUpdates, Photos and Handovers.
- [ ] Authorized DVI export additionally contains DVI_Deceased, DVI_Custody and FamilyRequests.
- [ ] Viewer does not receive restricted DVI/family export sheets.

## Security / regression
- [ ] All PHP files pass `php -l`.
- [ ] Public upload MIME/type restrictions still work.
- [ ] CSRF validation rejects missing/invalid tokens.
- [ ] Public rate limit still functions.
- [ ] Admin session timeout/disabled-account checks still work.
- [ ] Internal/deployment/database/config files remain blocked by `.htaccess`.
- [ ] PHP execution remains blocked inside upload folders.
- [ ] Test at ~360px phone width, tablet and desktop with no horizontal form clipping.
