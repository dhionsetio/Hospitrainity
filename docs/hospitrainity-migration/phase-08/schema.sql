-- Hospitrainity canonical package — PostgreSQL physical model (CP-08, additive design).
-- Generated deterministically by hsp-datamodel.mjs from the JSON schemas + package.
-- Pattern: each entity is stored as its full canonical JSON in doc JSONB, with extracted
-- scalar columns for indexing/constraints, plus code-based foreign keys and lifecycle CHECKs.
CREATE SCHEMA IF NOT EXISTS hospitrainity;

-- Framework singletons + id-registry stored as versioned documents.
CREATE TABLE hospitrainity.framework_document (
  kind text PRIMARY KEY,
  content_version text,
  doc jsonb NOT NULL
);

-- activity (24 rows in current package)
CREATE TABLE hospitrainity.activity (
  accessibility jsonb,
  cefr_activity text,
  channel text,
  code text NOT NULL,
  content_version text NOT NULL,
  entity_type text NOT NULL,
  id uuid NOT NULL,
  lesson_code text,
  outcome_codes jsonb,
  participation text,
  pedagogical_function text,
  replaced_by jsonb,
  replaces jsonb,
  response_form text,
  scoring_mode text,
  source_locator jsonb,
  status text NOT NULL,
  timing text,
  title text,
  doc jsonb NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (code),
  CHECK (status IN ('draft','draft_pending_qualified_review','in_review','approved','published','archived','superseded'))
);

-- answer-model (102 rows in current package)
CREATE TABLE hospitrainity.answer_model (
  accepted jsonb,
  code text NOT NULL,
  content_version text NOT NULL,
  entity_type text NOT NULL,
  id uuid NOT NULL,
  prompt_code text,
  replaced_by jsonb,
  replaces jsonb,
  scoring_mode text,
  source_locator jsonb,
  status text NOT NULL,
  doc jsonb NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (code),
  CHECK (status IN ('draft','draft_pending_qualified_review','in_review','approved','published','archived','superseded'))
);

-- chapter (7 rows in current package)
CREATE TABLE hospitrainity.chapter (
  code text NOT NULL,
  content_version text NOT NULL,
  entity_type text NOT NULL,
  id uuid NOT NULL,
  module text,
  outcome_codes jsonb,
  replaced_by jsonb,
  replaces jsonb,
  source_locator jsonb,
  status text NOT NULL,
  title text,
  doc jsonb NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (code),
  CHECK (status IN ('draft','draft_pending_qualified_review','in_review','approved','published','archived','superseded'))
);

-- feedback-model (102 rows in current package)
CREATE TABLE hospitrainity.feedback_model (
  code text NOT NULL,
  content_version text NOT NULL,
  entity_type text NOT NULL,
  id uuid NOT NULL,
  messages jsonb,
  prompt_code text,
  replaced_by jsonb,
  replaces jsonb,
  source_locator jsonb,
  status text NOT NULL,
  doc jsonb NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (code),
  CHECK (status IN ('draft','draft_pending_qualified_review','in_review','approved','published','archived','superseded'))
);

-- lesson-section (85 rows in current package)
CREATE TABLE hospitrainity.lesson_section (
  chapter_code text,
  code text NOT NULL,
  content_version text NOT NULL,
  entity_type text NOT NULL,
  id uuid NOT NULL,
  order integer,
  replaced_by jsonb,
  replaces jsonb,
  source_locator jsonb,
  status text NOT NULL,
  title text,
  doc jsonb NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (code),
  CHECK (status IN ('draft','draft_pending_qualified_review','in_review','approved','published','archived','superseded'))
);

-- migration-edge (8 rows in current package)
CREATE TABLE hospitrainity.migration_edge (
  canonical_code text,
  disposition text,
  entity_type text NOT NULL,
  legacy_ref text,
  note text,
  doc jsonb NOT NULL,
  UNIQUE (code),
  CHECK (status IN ('draft','draft_pending_qualified_review','in_review','approved','published','archived','superseded'))
);

-- prompt-item (102 rows in current package)
CREATE TABLE hospitrainity.prompt_item (
  activity_code text,
  code text NOT NULL,
  content_version text NOT NULL,
  entity_type text NOT NULL,
  id uuid NOT NULL,
  replaced_by jsonb,
  replaces jsonb,
  response_form text,
  source_locator jsonb,
  status text NOT NULL,
  stem text,
  doc jsonb NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (code),
  CHECK (status IN ('draft','draft_pending_qualified_review','in_review','approved','published','archived','superseded'))
);

-- rubric (6 rows in current package)
CREATE TABLE hospitrainity.rubric (
  activity_code text,
  code text NOT NULL,
  content_version text NOT NULL,
  criteria jsonb,
  entity_type text NOT NULL,
  id uuid NOT NULL,
  replaced_by jsonb,
  replaces jsonb,
  source_locator jsonb,
  status text NOT NULL,
  doc jsonb NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (code),
  CHECK (status IN ('draft','draft_pending_qualified_review','in_review','approved','published','archived','superseded'))
);

-- source-provenance (7 rows in current package)
CREATE TABLE hospitrainity.source_provenance (
  code text NOT NULL,
  content_version text NOT NULL,
  entity_type text NOT NULL,
  excerpt_sha256 text,
  id uuid NOT NULL,
  replaced_by jsonb,
  replaces jsonb,
  source_locator jsonb,
  status text NOT NULL,
  target_code text,
  doc jsonb NOT NULL,
  PRIMARY KEY (id),
  UNIQUE (code),
  CHECK (status IN ('draft','draft_pending_qualified_review','in_review','approved','published','archived','superseded'))
);

-- Foreign keys (code-based). Polymorphic + array refs are validated by the importer/validator.
ALTER TABLE hospitrainity.lesson_section ADD CONSTRAINT fk_lesson_section_chapter_code FOREIGN KEY (chapter_code) REFERENCES hospitrainity.chapter(code);
ALTER TABLE hospitrainity.activity ADD CONSTRAINT fk_activity_lesson_code FOREIGN KEY (lesson_code) REFERENCES hospitrainity.lesson_section(code);
-- activity.outcome_codes (jsonb array) -> framework_document('outcome-alignments').outcomes[].id (validated on import)
-- chapter.outcome_codes (jsonb array) -> framework_document('outcome-alignments').outcomes[].id (validated on import)
ALTER TABLE hospitrainity.prompt_item ADD CONSTRAINT fk_prompt_item_activity_code FOREIGN KEY (activity_code) REFERENCES hospitrainity.activity(code);
ALTER TABLE hospitrainity.answer_model ADD CONSTRAINT fk_answer_model_prompt_code FOREIGN KEY (prompt_code) REFERENCES hospitrainity.prompt_item(code);
ALTER TABLE hospitrainity.feedback_model ADD CONSTRAINT fk_feedback_model_prompt_code FOREIGN KEY (prompt_code) REFERENCES hospitrainity.prompt_item(code);
ALTER TABLE hospitrainity.rubric ADD CONSTRAINT fk_rubric_activity_code FOREIGN KEY (activity_code) REFERENCES hospitrainity.activity(code);
-- source_provenance.target_code -> any entity code (polymorphic; validated on import)
-- migration_edge.canonical_code -> any entity code (polymorphic; validated on import)
