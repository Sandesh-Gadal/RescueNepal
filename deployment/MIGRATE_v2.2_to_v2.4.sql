SET NAMES utf8mb4;

ALTER TABLE cases MODIFY type ENUM('missing','rescue_waiting','rescued','deceased') NOT NULL;
ALTER TABLE cases MODIFY status ENUM('draft','open','under_review','searching','located','found_alive','found_injured','found_deceased','rescue_dispatched','rescued_safe','rescued_injured','identity_unknown','potential_match','family_contacted','identification_review','forensic_confirmed','ready_handover','handed_over','reunited','shifted','close_requested','closed') NOT NULL DEFAULT 'open';
ALTER TABLE cases MODIFY current_condition ENUM('unknown','safe','stable','alive','minor_injury','injured','serious','critical','unconscious','unable_communicate','deceased') NULL;

CREATE TABLE IF NOT EXISTS rescued_person_details (
 case_id INT UNSIGNED PRIMARY KEY,
 identity_status ENUM('known','unknown','claimed','verified') NOT NULL DEFAULT 'known',
 name_np VARCHAR(150) NULL, nickname VARCHAR(120) NULL,
 estimated_age_min TINYINT UNSIGNED NULL, estimated_age_max TINYINT UNSIGNED NULL,
 permanent_address VARCHAR(300) NULL, person_phone VARCHAR(30) NULL,
 identity_document_type VARCHAR(50) NULL, identity_document_number VARCHAR(120) NULL,
 rescue_datetime_gregorian DATETIME NULL, rescue_date_bs VARCHAR(20) NULL,
 rescue_location VARCHAR(300) NOT NULL, rescue_latitude DECIMAL(10,7) NULL, rescue_longitude DECIMAL(10,7) NULL,
 rescued_by_name VARCHAR(150) NULL, rescued_by_type VARCHAR(80) NULL,
 rescuing_institution_name VARCHAR(190) NULL, rescuing_institution_phone VARCHAR(30) NULL,
 condition_level ENUM('safe','stable','minor_injury','moderate_injury','serious','critical','unconscious','unable_communicate','unknown') NOT NULL DEFAULT 'unknown',
 conscious TINYINT(1) NULL, can_communicate TINYINT(1) NULL,
 medical_attention ENUM('not_required','first_aid','referred_hospital','admitted','icu','discharged','unknown') NOT NULL DEFAULT 'unknown',
 injury_summary TEXT NULL, special_assistance TEXT NULL,
 current_place_type ENUM('family','rescue_shelter','hospital','police','army_apf','local_government','ngo_ingo','temporary_camp','other') NULL,
 current_institution_name VARCHAR(190) NULL, current_institution_address VARCHAR(300) NULL,
 current_latitude DECIMAL(10,7) NULL, current_longitude DECIMAL(10,7) NULL,
 institution_contact_name VARCHAR(150) NULL, institution_contact_post VARCHAR(120) NULL,
 institution_contact_phone VARCHAR(30) NULL, institution_alt_phone VARCHAR(30) NULL,
 institution_office_phone VARCHAR(30) NULL, institution_email VARCHAR(190) NULL,
 language_spoken VARCHAR(120) NULL, height_cm DECIMAL(6,2) NULL, clothing TEXT NULL,
 distinguishing_marks TEXT NULL, belongings TEXT NULL,
 public_photo_allowed TINYINT(1) NOT NULL DEFAULT 1,
 reunification_status ENUM('not_started','identity_found','family_contacted','waiting_family','reunited','left_on_request','transferred') NOT NULL DEFAULT 'not_started',
 family_contacted_at DATETIME NULL, reunited_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_rescued_detail_case FOREIGN KEY(case_id) REFERENCES cases(id) ON DELETE CASCADE,
 INDEX idx_rescued_identity(identity_status), INDEX idx_rescued_institution(current_institution_name), INDEX idx_rescued_location(rescue_location(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deceased_details (
 case_id INT UNSIGNED PRIMARY KEY,
 body_id VARCHAR(50) NOT NULL UNIQUE,
 identity_status ENUM('unidentified','potential_match','under_review','confirmed','ready_handover','handed_over') NOT NULL DEFAULT 'unidentified',
 suspected_name VARCHAR(150) NULL,
 estimated_age_min TINYINT UNSIGNED NULL, estimated_age_max TINYINT UNSIGNED NULL,
 height_cm DECIMAL(6,2) NULL, build_description VARCHAR(120) NULL,
 hair_description VARCHAR(200) NULL, facial_hair_description VARCHAR(200) NULL,
 clothing TEXT NULL, footwear TEXT NULL, jewellery TEXT NULL, tattoos TEXT NULL, scars TEXT NULL, birthmarks TEXT NULL,
 documents_found TEXT NULL, devices_found TEXT NULL, other_belongings TEXT NULL,
 recovery_datetime_gregorian DATETIME NULL, recovery_date_bs VARCHAR(20) NULL,
 recovery_location VARCHAR(300) NOT NULL, recovery_latitude DECIMAL(10,7) NULL, recovery_longitude DECIMAL(10,7) NULL,
 river_waterbody VARCHAR(190) NULL,
 recovered_by_name VARCHAR(150) NULL, recovered_by_organization VARCHAR(190) NULL,
 police_office VARCHAR(190) NULL, recovery_officer_name VARCHAR(150) NULL, recovery_officer_post VARCHAR(120) NULL, recovery_officer_phone VARCHAR(30) NULL,
 body_bag_no VARCHAR(80) NULL, seal_no VARCHAR(80) NULL,
 current_mortuary VARCHAR(190) NULL, current_storage_location VARCHAR(300) NULL,
 fingerprint_collected TINYINT(1) NOT NULL DEFAULT 0, fingerprint_reference VARCHAR(120) NULL, fingerprint_result TEXT NULL,
 dna_collected TINYINT(1) NOT NULL DEFAULT 0, dna_sample_id VARCHAR(120) NULL, dna_lab VARCHAR(190) NULL, dna_reference VARCHAR(120) NULL, dna_result TEXT NULL,
 dental_summary TEXT NULL, postmortem_reference VARCHAR(120) NULL,
 official_identification_method VARCHAR(190) NULL,
 official_identity_name VARCHAR(150) NULL, official_identity_age TINYINT UNSIGNED NULL, official_identity_address VARCHAR(300) NULL,
 public_identity_release TINYINT(1) NOT NULL DEFAULT 0,
 identification_approved_by INT UNSIGNED NULL, identification_approved_at DATETIME NULL,
 forensic_notes MEDIUMTEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_deceased_detail_case FOREIGN KEY(case_id) REFERENCES cases(id) ON DELETE CASCADE,
 CONSTRAINT fk_deceased_ident_approver FOREIGN KEY(identification_approved_by) REFERENCES admins(id) ON DELETE SET NULL,
 INDEX idx_deceased_identity(identity_status), INDEX idx_deceased_recovery(recovery_location(120)), INDEX idx_deceased_mortuary(current_mortuary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS body_custody_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_id INT UNSIGNED NOT NULL,
 event_type ENUM('recovered','bagged','sealed','received','transferred','pm_started','pm_completed','sample_collected','stored','released','other') NOT NULL,
 event_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 event_date_bs VARCHAR(20) NULL,
 from_location VARCHAR(300) NULL, to_location VARCHAR(300) NULL,
 body_bag_no VARCHAR(80) NULL, seal_no VARCHAR(80) NULL,
 handled_by_name VARCHAR(150) NULL, handled_by_post VARCHAR(120) NULL, handled_by_office VARCHAR(190) NULL,
 admin_id INT UNSIGNED NULL, notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_custody_case FOREIGN KEY(case_id) REFERENCES cases(id) ON DELETE CASCADE,
 CONSTRAINT fk_custody_admin FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE SET NULL,
 INDEX idx_custody_case_time(case_id,event_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS family_match_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_code VARCHAR(40) NOT NULL UNIQUE,
 target_case_id INT UNSIGNED NULL,
 requester_name VARCHAR(150) NOT NULL, relationship VARCHAR(100) NOT NULL,
 requester_phone VARCHAR(30) NOT NULL, requester_alt_phone VARCHAR(30) NULL,
 requester_district VARCHAR(120) NULL, requester_municipality VARCHAR(150) NULL,
 missing_person_name VARCHAR(150) NOT NULL, missing_person_nickname VARCHAR(120) NULL,
 missing_person_age TINYINT UNSIGNED NULL, missing_person_gender ENUM('Male','Female','Other','Unknown') NOT NULL DEFAULT 'Unknown',
 missing_person_address VARCHAR(300) NULL, last_seen_date_bs VARCHAR(20) NULL, last_seen_location VARCHAR(300) NULL,
 clothing TEXT NULL, distinguishing_marks TEXT NULL,
 photo_url VARCHAR(255) NULL,
 phone_verified TINYINT(1) NOT NULL DEFAULT 0,
 status ENUM('submitted','under_review','possible_match','verified','rejected','closed') NOT NULL DEFAULT 'submitted',
 review_notes TEXT NULL, reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_family_target FOREIGN KEY(target_case_id) REFERENCES cases(id) ON DELETE SET NULL,
 CONSTRAINT fk_family_review FOREIGN KEY(reviewed_by) REFERENCES admins(id) ON DELETE SET NULL,
 INDEX idx_family_phone(requester_phone), INDEX idx_family_target(target_case_id), INDEX idx_family_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS phone_otps (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 phone VARCHAR(30) NOT NULL, purpose VARCHAR(50) NOT NULL,
 code_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, verified_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_otp_phone(phone,purpose,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_matches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source_case_id INT UNSIGNED NOT NULL, candidate_case_id INT UNSIGNED NOT NULL,
 match_score DECIMAL(5,2) NOT NULL DEFAULT 0, reasons_json TEXT NULL,
 status ENUM('suggested','reviewed','rejected','confirmed') NOT NULL DEFAULT 'suggested',
 created_by_admin INT UNSIGNED NULL, reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_case_match(source_case_id,candidate_case_id),
 CONSTRAINT fk_match_source FOREIGN KEY(source_case_id) REFERENCES cases(id) ON DELETE CASCADE,
 CONSTRAINT fk_match_candidate FOREIGN KEY(candidate_case_id) REFERENCES cases(id) ON DELETE CASCADE,
 CONSTRAINT fk_match_creator FOREIGN KEY(created_by_admin) REFERENCES admins(id) ON DELETE SET NULL,
 CONSTRAINT fk_match_reviewer FOREIGN KEY(reviewed_by) REFERENCES admins(id) ON DELETE SET NULL,
 INDEX idx_match_source_score(source_case_id,match_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS identification_approval_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_id INT UNSIGNED NOT NULL,
 proposed_missing_case_id INT UNSIGNED NULL,
 proposed_identity_name VARCHAR(150) NOT NULL,
 identification_basis TEXT NOT NULL,
 requested_by INT UNSIGNED NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, review_notes TEXT NULL,
 CONSTRAINT fk_ident_case FOREIGN KEY(case_id) REFERENCES cases(id) ON DELETE CASCADE,
 CONSTRAINT fk_ident_missing FOREIGN KEY(proposed_missing_case_id) REFERENCES cases(id) ON DELETE SET NULL,
 CONSTRAINT fk_ident_req FOREIGN KEY(requested_by) REFERENCES admins(id),
 CONSTRAINT fk_ident_review FOREIGN KEY(reviewed_by) REFERENCES admins(id) ON DELETE SET NULL,
 INDEX idx_ident_pending(status,requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS handover_records (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_id INT UNSIGNED NOT NULL,
 handover_type ENUM('rescued_reunification','deceased_handover') NOT NULL,
 recipient_name VARCHAR(150) NOT NULL, relationship VARCHAR(100) NOT NULL,
 recipient_phone VARCHAR(30) NOT NULL, recipient_address VARCHAR(300) NULL,
 recipient_id_type VARCHAR(80) NULL, recipient_id_number VARCHAR(120) NULL,
 handover_datetime DATETIME NOT NULL, handover_location VARCHAR(300) NOT NULL,
 witness_1 VARCHAR(150) NULL, witness_2 VARCHAR(150) NULL,
 handover_officer_name VARCHAR(150) NOT NULL, handover_officer_post VARCHAR(120) NULL, handover_office VARCHAR(190) NULL,
 approved_by INT UNSIGNED NULL, admin_id INT UNSIGNED NOT NULL,
 notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_handover_case FOREIGN KEY(case_id) REFERENCES cases(id) ON DELETE CASCADE,
 CONSTRAINT fk_handover_admin FOREIGN KEY(admin_id) REFERENCES admins(id),
 CONSTRAINT fk_handover_approver FOREIGN KEY(approved_by) REFERENCES admins(id) ON DELETE SET NULL,
 INDEX idx_handover_case(case_id,handover_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET NAMES utf8mb4;

-- RescueNepal.info v2.3 -> v2.4
-- Back up the database before running this migration.

ALTER TABLE cases
  ADD COLUMN missing_person_context ENUM('local','worker','tourist','student','other') NULL AFTER from_location,
  ADD COLUMN associated_place_name VARCHAR(255) NULL AFTER missing_person_context;

ALTER TABLE cases
  MODIFY current_condition ENUM('unknown','safe','stable','alive','minor_injury','injured','semi_conscious','serious','critical','unconscious','unable_communicate','deceased') NULL;

ALTER TABLE cases ADD INDEX idx_cases_context(missing_person_context);

ALTER TABLE rescued_person_details
  ADD COLUMN workplace VARCHAR(190) NULL AFTER person_phone,
  ADD COLUMN destination VARCHAR(255) NULL AFTER workplace,
  ADD COLUMN emergency_contact_name VARCHAR(150) NULL AFTER destination,
  ADD COLUMN emergency_contact_phone VARCHAR(30) NULL AFTER emergency_contact_name,
  ADD COLUMN documents_found TEXT NULL AFTER emergency_contact_phone;

ALTER TABLE rescued_person_details
  MODIFY condition_level ENUM('safe','injured','semi_conscious','unconscious','stable','minor_injury','moderate_injury','serious','critical','unable_communicate','unknown') NOT NULL DEFAULT 'unknown';

ALTER TABLE rescued_person_details
  ADD INDEX idx_rescued_phone(person_phone),
  ADD INDEX idx_rescued_doc(identity_document_number);

ALTER TABLE deceased_details
  ADD COLUMN body_condition TEXT NULL AFTER identification_approved_at,
  ADD COLUMN shifted_to_type VARCHAR(80) NULL AFTER body_condition;

CREATE TABLE IF NOT EXISTS case_media (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 case_id INT UNSIGNED NOT NULL,
 media_kind ENUM('deceased_photo') NOT NULL DEFAULT 'deceased_photo',
 file_path VARCHAR(255) NOT NULL,
 label VARCHAR(120) NULL,
 is_primary TINYINT(1) NOT NULL DEFAULT 0,
 uploaded_by_admin INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_case_media_case FOREIGN KEY(case_id) REFERENCES cases(id) ON DELETE CASCADE,
 CONSTRAINT fk_case_media_admin FOREIGN KEY(uploaded_by_admin) REFERENCES admins(id) ON DELETE SET NULL,
 INDEX idx_case_media_case(case_id,media_kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carry the existing v2.3 primary deceased photo into the multi-photo table.
INSERT INTO case_media(case_id,media_kind,file_path,label,is_primary,uploaded_by_admin)
SELECT c.id,'deceased_photo',c.photo_url,'Recovery photo 1',1,c.created_by_admin_id
FROM cases c
LEFT JOIN case_media cm ON cm.case_id=c.id AND cm.media_kind='deceased_photo' AND cm.is_primary=1
WHERE c.type='deceased' AND c.photo_url IS NOT NULL AND c.photo_url<>'' AND cm.id IS NULL;

ALTER TABLE handover_records
  ADD COLUMN linked_missing_case_id INT UNSIGNED NULL AFTER admin_id,
  ADD COLUMN linked_family_request_id BIGINT UNSIGNED NULL AFTER linked_missing_case_id,
  ADD COLUMN procedure_notes TEXT NULL AFTER linked_family_request_id,
  ADD COLUMN close_after_handover TINYINT(1) NOT NULL DEFAULT 0 AFTER procedure_notes,
  ADD CONSTRAINT fk_handover_missing FOREIGN KEY(linked_missing_case_id) REFERENCES cases(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_handover_family_request FOREIGN KEY(linked_family_request_id) REFERENCES family_match_requests(id) ON DELETE SET NULL;

-- Preserve old Purpose fields while providing sensible values to the new fields where possible.
UPDATE cases
SET missing_person_context = CASE
    WHEN type<>'missing' THEN missing_person_context
    WHEN LOWER(purpose_en) LIKE '%tour%' OR LOWER(purpose_en) LIKE '%travel%' THEN 'tourist'
    WHEN LOWER(purpose_en) LIKE '%student%' OR LOWER(purpose_en) LIKE '%school%' OR LOWER(purpose_en) LIKE '%college%' THEN 'student'
    WHEN LOWER(purpose_en) LIKE '%work%' OR LOWER(purpose_en) LIKE '%office%' OR LOWER(purpose_en) LIKE '%job%' THEN 'worker'
    ELSE missing_person_context
END,
associated_place_name = COALESCE(NULLIF(associated_place_name,''), NULLIF(purpose_np,''))
WHERE type='missing';
