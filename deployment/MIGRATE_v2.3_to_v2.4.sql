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
