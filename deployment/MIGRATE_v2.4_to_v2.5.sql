SET NAMES utf8mb4;

-- RescueNepal.info v2.4 -> v2.5
-- Back up the database before running this migration.
-- Adds a traceable link from a Formal Identification Approval Request back to the
-- public Family Match Request that supplied the evidence, so admins can submit an
-- identification even when no formal Missing Person case was separately filed,
-- and so the family's request status auto-updates when identification is approved.

ALTER TABLE identification_approval_requests
  ADD COLUMN family_match_request_id BIGINT UNSIGNED NULL AFTER proposed_missing_case_id,
  ADD CONSTRAINT fk_ident_family_request FOREIGN KEY(family_match_request_id) REFERENCES family_match_requests(id) ON DELETE SET NULL;
