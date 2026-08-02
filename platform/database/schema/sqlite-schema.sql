-- Consolidated Laravel schema generated on 2026-07-30 after migrations 1-72.
CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "roles_name_guard_name_unique" on "roles"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);
CREATE TABLE IF NOT EXISTS "event_logs"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "actor_user_id" integer,
  "event_code" varchar not null,
  "severity" varchar not null default 'info',
  "entity_type" varchar,
  "entity_id" integer,
  "message" text,
  "context_json" text,
  "before_json" text,
  "after_json" text,
  "ip" varchar,
  "user_agent" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("actor_user_id") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "school_classes"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "year" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "owner_type" varchar not null default 'organization',
  "owner_id" integer,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "student_profiles"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "organization_id" integer not null,
  "registration_number" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "class_student"(
  "id" integer primary key autoincrement not null,
  "school_class_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("school_class_id") references "school_classes"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "question_shares"(
  "id" integer primary key autoincrement not null,
  "question_id" integer not null,
  "shared_with_user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("question_id") references "questions"("id") on delete cascade,
  foreign key("shared_with_user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "exam_questions"(
  "id" integer primary key autoincrement not null,
  "exam_id" integer not null,
  "question_id" integer not null,
  "points" numeric not null default '1',
  "order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("exam_id") references "exams"("id") on delete cascade,
  foreign key("question_id") references "questions"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "exam_school_class"(
  "id" integer primary key autoincrement not null,
  "exam_id" integer not null,
  "school_class_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("exam_id") references "exams"("id") on delete cascade,
  foreign key("school_class_id") references "school_classes"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "exam_submissions"(
  "id" integer primary key autoincrement not null,
  "exam_id" integer not null,
  "user_id" integer not null,
  "status" varchar not null default 'in_progress',
  "started_at" datetime,
  "finished_at" datetime,
  "score" numeric,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "attempt_number" integer not null default '1',
  "deadline_at" datetime,
  "client_token" varchar,
  "feedback" text,
  foreign key("exam_id") references "exams"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "exam_answers"(
  "id" integer primary key autoincrement not null,
  "exam_submission_id" integer not null,
  "question_id" integer not null,
  "answer_data" text,
  "is_correct" tinyint(1),
  "points_awarded" numeric,
  "created_at" datetime,
  "updated_at" datetime,
  "feedback" text,
  "grading_justification" text,
  "rubric_scores" text,
  foreign key("exam_submission_id") references "exam_submissions"("id") on delete cascade,
  foreign key("question_id") references "questions"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "plans"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "price" numeric not null default '0',
  "limits" text,
  "features" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "target_audience" varchar check("target_audience" in('teacher', 'institution', 'both')) not null default 'both',
  "original_price" numeric not null default '0',
  "promotional_price" numeric,
  "promo_starts_at" datetime,
  "promo_ends_at" datetime,
  "is_visible" tinyint(1) not null default '1',
  "is_most_popular" tinyint(1) not null default '0',
  "sort_order" integer not null default '0',
  "status" varchar check("status" in('active', 'inactive')) not null default 'active',
  "tier_level" integer not null default '0'
);
CREATE UNIQUE INDEX "plans_slug_unique" on "plans"("slug");
CREATE TABLE IF NOT EXISTS "plan_limits"(
  "id" integer primary key autoincrement not null,
  "plan_id" integer not null,
  "resource_key" varchar not null,
  "limit_value" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("plan_id") references "plans"("id") on delete cascade
);
CREATE UNIQUE INDEX "plan_limits_plan_id_resource_key_unique" on "plan_limits"(
  "plan_id",
  "resource_key"
);
CREATE TABLE IF NOT EXISTS "plan_features"(
  "id" integer primary key autoincrement not null,
  "plan_id" integer not null,
  "feature_key" varchar not null,
  "enabled" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("plan_id") references "plans"("id") on delete cascade
);
CREATE UNIQUE INDEX "plan_features_plan_id_feature_key_unique" on "plan_features"(
  "plan_id",
  "feature_key"
);
CREATE TABLE IF NOT EXISTS "subscriptions"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "plan_id" integer not null,
  "status" varchar not null default('active'),
  "starts_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "subscriber_type" varchar,
  "subscriber_id" integer,
  foreign key("plan_id") references plans("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action
);
CREATE INDEX "subscriptions_subscriber_type_subscriber_id_index" on "subscriptions"(
  "subscriber_type",
  "subscriber_id"
);
CREATE TABLE IF NOT EXISTS "organizations"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "subdomain" varchar,
  "active" tinyint(1) not null default('1'),
  "settings" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "owner_user_id" integer,
  "allow_class_copy" tinyint(1) not null default '0',
  "can_access_trash" tinyint(1) not null default '0',
  "can_access_logs" tinyint(1) not null default '0',
  "trash_access_users" text,
  "logs_access_users" text,
  "omr_hmac_secret" varchar,
  foreign key("owner_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "organizations_subdomain_unique" on "organizations"(
  "subdomain"
);
CREATE TABLE IF NOT EXISTS "audit_logs"(
  "id" integer primary key autoincrement not null,
  "user_id" integer,
  "action" varchar not null,
  "model_type" varchar,
  "model_id" integer,
  "payload" text,
  "ip_address" varchar,
  "user_agent" varchar,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "audit_logs_model_type_model_id_index" on "audit_logs"(
  "model_type",
  "model_id"
);
CREATE INDEX "audit_logs_user_id_created_at_index" on "audit_logs"(
  "user_id",
  "created_at"
);
CREATE INDEX "audit_logs_action_index" on "audit_logs"("action");
CREATE INDEX "audit_logs_created_at_index" on "audit_logs"("created_at");
CREATE TABLE IF NOT EXISTS "knowledge_areas"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "invites"(
  "id" integer primary key autoincrement not null,
  "inviter_id" integer not null,
  "organization_id" integer,
  "invitee_email" varchar not null,
  "target_role" varchar not null default('teacher'),
  "token" varchar not null,
  "status" varchar not null default('pending'),
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "invite_type" varchar not null default 'org_teacher',
  "invitee_user_id" integer,
  "target_entity_type" varchar,
  "target_entity_id" integer,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("inviter_id") references users("id") on delete cascade on update no action,
  foreign key("invitee_user_id") references "users"("id") on delete set null
);
CREATE INDEX "invites_invitee_email_status_index" on "invites"(
  "invitee_email",
  "status"
);
CREATE INDEX "invites_organization_id_status_index" on "invites"(
  "organization_id",
  "status"
);
CREATE UNIQUE INDEX "invites_token_unique" on "invites"("token");
CREATE INDEX "invites_invite_type_index" on "invites"("invite_type");
CREATE INDEX "invites_invitee_user_id_index" on "invites"("invitee_user_id");
CREATE INDEX "invites_target_entity_type_target_entity_id_index" on "invites"(
  "target_entity_type",
  "target_entity_id"
);
CREATE INDEX "school_classes_owner_type_owner_id_index" on "school_classes"(
  "owner_type",
  "owner_id"
);
CREATE TABLE IF NOT EXISTS "class_teacher"(
  "id" integer primary key autoincrement not null,
  "school_class_id" integer not null,
  "user_id" integer not null,
  "assigned_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("school_class_id") references "school_classes"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "class_teacher_school_class_id_user_id_unique" on "class_teacher"(
  "school_class_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "class_ownership_logs"(
  "id" integer primary key autoincrement not null,
  "school_class_id" integer not null,
  "previous_owner_type" varchar not null,
  "previous_owner_id" integer not null,
  "new_owner_type" varchar not null,
  "new_owner_id" integer not null,
  "initiated_by" integer not null,
  "transferred_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("school_class_id") references "school_classes"("id") on delete cascade,
  foreign key("initiated_by") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "institution_roles"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "institution_roles_organization_id_slug_unique" on "institution_roles"(
  "organization_id",
  "slug"
);
CREATE TABLE IF NOT EXISTS "institution_role_permissions"(
  "id" integer primary key autoincrement not null,
  "institution_role_id" integer not null,
  "permission" varchar not null,
  foreign key("institution_role_id") references "institution_roles"("id") on delete cascade
);
CREATE UNIQUE INDEX "institution_role_permissions_institution_role_id_permission_unique" on "institution_role_permissions"(
  "institution_role_id",
  "permission"
);
CREATE TABLE IF NOT EXISTS "user_organization"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "organization_id" integer not null,
  "role_in_org" varchar not null default('teacher'),
  "status" varchar not null default('active'),
  "joined_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "institution_role_id" integer,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("institution_role_id") references "institution_roles"("id") on delete set null
);
CREATE UNIQUE INDEX "user_organization_user_id_organization_id_unique" on "user_organization"(
  "user_id",
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "difficulty_levels"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "school_years"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "disciplines"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "knowledge_area_id" integer,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("knowledge_area_id") references "knowledge_areas"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "thematic_units"(
  "id" integer primary key autoincrement not null,
  "discipline_id" integer not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("discipline_id") references "disciplines"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "knowledge_objects"(
  "id" integer primary key autoincrement not null,
  "thematic_unit_id" integer not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("thematic_unit_id") references "thematic_units"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "question_answers"(
  "id" integer primary key autoincrement not null,
  "question_id" integer not null,
  "content" text not null,
  "is_correct" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("question_id") references "questions"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "questions"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "owner_id" integer not null,
  "type" varchar not null default('multiple_choice'),
  "content" text not null,
  "visibility_scope" varchar not null default('private'),
  "source_question_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "knowledge_area_id" integer,
  "discipline_id" integer,
  "level" varchar,
  "difficulty_level_id" integer,
  "school_year_id" integer,
  "thematic_unit_id" integer,
  "knowledge_object_id" integer,
  "stage" varchar,
  "grade" varchar,
  foreign key("discipline_id") references disciplines("id") on delete set null on update no action,
  foreign key("knowledge_area_id") references knowledge_areas("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("owner_id") references users("id") on delete cascade on update no action,
  foreign key("source_question_id") references questions("id") on delete set null on update no action,
  foreign key("difficulty_level_id") references "difficulty_levels"("id") on delete set null,
  foreign key("school_year_id") references "school_years"("id") on delete set null,
  foreign key("thematic_unit_id") references "thematic_units"("id") on delete set null,
  foreign key("knowledge_object_id") references "knowledge_objects"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "bncc_nodes"(
  "id" integer primary key autoincrement not null,
  "discipline_id" integer not null,
  "stage" varchar,
  "grade" varchar,
  "type" varchar not null,
  "code" varchar,
  "title" varchar not null,
  "description" text,
  "parent_id" integer,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("discipline_id") references "disciplines"("id") on delete cascade,
  foreign key("parent_id") references "bncc_nodes"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "bncc_component_schemas"(
  "id" integer primary key autoincrement not null,
  "discipline_id" integer not null,
  "stage" varchar not null,
  "schema_json" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("discipline_id") references "disciplines"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "question_bncc_links"(
  "id" integer primary key autoincrement not null,
  "question_id" integer not null,
  "bncc_skill_node_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("question_id") references "questions"("id") on delete cascade,
  foreign key("bncc_skill_node_id") references "bncc_nodes"("id") on delete cascade
);
CREATE UNIQUE INDEX "question_bncc_links_question_id_bncc_skill_node_id_unique" on "question_bncc_links"(
  "question_id",
  "bncc_skill_node_id"
);
CREATE TABLE IF NOT EXISTS "custom_skills"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "question_custom_skill"(
  "id" integer primary key autoincrement not null,
  "question_id" integer not null,
  "custom_skill_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("question_id") references "questions"("id") on delete cascade,
  foreign key("custom_skill_id") references "custom_skills"("id") on delete cascade
);
CREATE UNIQUE INDEX "question_custom_skill_question_id_custom_skill_id_unique" on "question_custom_skill"(
  "question_id",
  "custom_skill_id"
);
CREATE TABLE IF NOT EXISTS "exam_copies"(
  "id" integer primary key autoincrement not null,
  "exam_id" integer not null,
  "school_class_id" integer,
  "copy_number" integer not null,
  "questions_map" text not null,
  "options_map" text not null,
  "validation_hash" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("exam_id") references "exams"("id") on delete cascade,
  foreign key("school_class_id") references "school_classes"("id") on delete set null
);
CREATE UNIQUE INDEX "exam_copies_validation_hash_unique" on "exam_copies"(
  "validation_hash"
);
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" text not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "personal_access_tokens_expires_at_index" on "personal_access_tokens"(
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "omr_calibrations"(
  "id" integer primary key autoincrement not null,
  "exam_id" integer not null,
  "offset_x" numeric not null default '0',
  "offset_y" numeric not null default '0',
  "scale_x" numeric not null default '1',
  "scale_y" numeric not null default '1',
  "rotation_deg" numeric not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("exam_id") references "exams"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "omr_audit_logs"(
  "id" integer primary key autoincrement not null,
  "omr_scan_id" integer not null,
  "user_id" integer,
  "action" varchar not null,
  "previous_data" text,
  "new_data" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("omr_scan_id") references "omr_scans"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "omr_templates"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "organization_id" integer,
  "created_by" integer,
  "width" integer not null default '1000',
  "height" integer not null default '1414',
  "paper_size" varchar not null default 'A4',
  "orientation" varchar not null default 'portrait',
  "corner_points_json" text not null,
  "thresholds_json" text not null,
  "calibration_json" text,
  "qr_region_json" text,
  "total_questions" integer not null default '60',
  "total_pages" integer not null default '1',
  "columns" integer not null default '4',
  "rows_per_column" integer not null default '15',
  "max_options" integer not null default '5',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "owner_type" varchar,
  "owner_id" integer,
  "visibility_scope" varchar not null default 'system',
  "is_default" tinyint(1) not null default '0',
  "is_system" tinyint(1) not null default '0',
  "max_questions" integer,
  "max_columns" integer,
  "logo_path" varchar,
  "header_config" text,
  "layout_config" text,
  "current_version" integer not null default '1',
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "omr_templates_slug_unique" on "omr_templates"("slug");
CREATE TABLE IF NOT EXISTS "omr_template_questions"(
  "id" integer primary key autoincrement not null,
  "omr_template_id" integer not null,
  "question_number" integer not null,
  "option_labels_json" text not null,
  "rois_json" text not null,
  "weight" numeric not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("omr_template_id") references "omr_templates"("id") on delete cascade
);
CREATE UNIQUE INDEX "omr_template_questions_omr_template_id_question_number_unique" on "omr_template_questions"(
  "omr_template_id",
  "question_number"
);
CREATE TABLE IF NOT EXISTS "answer_sheet_types"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "slug" varchar not null,
  "name" varchar not null,
  "description" text,
  "is_system" tinyint(1) not null default '0',
  "is_default" tinyint(1) not null default '0',
  "layout_config" text not null,
  "grading_config" text not null,
  "version" integer not null default '1',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "answer_sheet_types_organization_id_is_active_index" on "answer_sheet_types"(
  "organization_id",
  "is_active"
);
CREATE UNIQUE INDEX "answer_sheet_types_slug_unique" on "answer_sheet_types"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "scan_modes"(
  "id" integer primary key autoincrement not null,
  "slug" varchar not null,
  "name" varchar not null,
  "description" text,
  "is_default" tinyint(1) not null default '0',
  "requires_predownload" tinyint(1) not null default '0',
  "requires_qr_data" tinyint(1) not null default '0',
  "offline_capable" tinyint(1) not null default '0',
  "qr_payload_schema" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "scan_modes_slug_unique" on "scan_modes"("slug");
CREATE TABLE IF NOT EXISTS "config_rules"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "config_key" varchar not null,
  "config_value" varchar not null,
  "scope_type" varchar check("scope_type" in('global', 'user_type', 'role', 'function', 'permission', 'user')) not null,
  "scope_id" varchar,
  "priority" integer not null,
  "is_active" tinyint(1) not null default '1',
  "effective_from" datetime not null default CURRENT_TIMESTAMP,
  "effective_until" datetime,
  "created_by" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id"),
  foreign key("created_by") references "users"("id")
);
CREATE INDEX "idx_scope_lookup" on "config_rules"(
  "organization_id",
  "config_key",
  "scope_type",
  "scope_id",
  "is_active"
);
CREATE INDEX "idx_priority" on "config_rules"(
  "organization_id",
  "config_key",
  "priority"
);
CREATE TABLE IF NOT EXISTS "config_audit_logs"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "config_rule_id" integer,
  "action" varchar check("action" in('created', 'updated', 'deactivated', 'deleted')) not null,
  "config_key" varchar not null,
  "old_value" text,
  "new_value" text not null,
  "changed_by" integer not null,
  "change_reason" text,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("organization_id") references "organizations"("id"),
  foreign key("changed_by") references "users"("id")
);
CREATE INDEX "idx_audit_org_date" on "config_audit_logs"(
  "organization_id",
  "created_at"
);
CREATE INDEX "idx_audit_rule" on "config_audit_logs"("config_rule_id");
CREATE INDEX "omr_templates_owner_type_owner_id_index" on "omr_templates"(
  "owner_type",
  "owner_id"
);
CREATE TABLE IF NOT EXISTS "omr_template_versions"(
  "id" integer primary key autoincrement not null,
  "omr_template_id" integer not null,
  "version" integer not null,
  "layout_config" text not null,
  "header_config" text,
  "logo_path" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("omr_template_id") references "omr_templates"("id") on delete cascade
);
CREATE UNIQUE INDEX "omr_template_versions_omr_template_id_version_unique" on "omr_template_versions"(
  "omr_template_id",
  "version"
);
CREATE TABLE IF NOT EXISTS "exams"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "author_id" integer not null,
  "title" varchar not null,
  "status" varchar not null default('draft'),
  "settings" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "access_code" varchar,
  "answer_sheet_type_slug" varchar not null default('essential'),
  "version" integer not null default('1'),
  "card_template_id" integer,
  "card_template_version" integer,
  foreign key("author_id") references users("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("card_template_id") references "omr_templates"("id") on delete set null
);
CREATE UNIQUE INDEX "exams_access_code_unique" on "exams"("access_code");
CREATE UNIQUE INDEX "exam_submissions_exam_user_attempt_unique" on "exam_submissions"(
  "exam_id",
  "user_id",
  "attempt_number"
);
CREATE UNIQUE INDEX "exam_submissions_client_token_unique" on "exam_submissions"(
  "client_token"
);
CREATE UNIQUE INDEX "exam_answers_submission_question_unique" on "exam_answers"(
  "exam_submission_id",
  "question_id"
);
CREATE TABLE IF NOT EXISTS "omr_scan_pages"(
  "id" integer primary key autoincrement not null,
  "session_id" varchar not null,
  "exam_id" integer not null,
  "copy_id" integer,
  "student_id" integer,
  "page_index" integer not null default('1'),
  "total_pages" integer not null default('1'),
  "image_path" varchar,
  "raw_answers" text,
  "raw_confidences" text,
  "overall_confidence" numeric,
  "status" varchar not null default('pending'),
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  "uploaded_by" integer,
  foreign key("student_id") references users("id") on delete set null on update no action,
  foreign key("copy_id") references exam_copies("id") on delete set null on update no action,
  foreign key("exam_id") references exams("id") on delete cascade on update no action,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("uploaded_by") references "users"("id") on delete set null
);
CREATE INDEX "omr_scan_pages_session_id_index" on "omr_scan_pages"(
  "session_id"
);
CREATE UNIQUE INDEX "omr_scan_pages_session_id_page_index_unique" on "omr_scan_pages"(
  "session_id",
  "page_index"
);
CREATE INDEX "omr_scan_pages_organization_id_session_id_index" on "omr_scan_pages"(
  "organization_id",
  "session_id"
);
CREATE TABLE IF NOT EXISTS "omr_scans"(
  "id" integer primary key autoincrement not null,
  "exam_id" integer not null,
  "organization_id" integer not null,
  "uploaded_by" integer not null,
  "image_path" varchar not null,
  "idempotency_key" varchar not null,
  "status" varchar not null default('pending'),
  "detected_answers" text,
  "confirmed_answers" text,
  "student_id" integer,
  "confidence_score" float,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "copy_id" integer,
  "source" varchar not null default('web'),
  "session_id" varchar,
  "layout_version" integer not null default('0'),
  "total_pages" integer not null default('1'),
  "raw_answers" text,
  "raw_confidences" text,
  "overall_confidence" numeric,
  "score" numeric,
  "total_points" numeric,
  "grading_details" text,
  "omr_template_id" integer,
  "warped_path" varchar,
  "debug_path" varchar,
  "layout_meta" text,
  "quality_json" text,
  "exam_submission_id" integer,
  foreign key("omr_template_id") references omr_templates("id") on delete set null on update no action,
  foreign key("student_id") references users("id") on delete set null on update no action,
  foreign key("uploaded_by") references users("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("exam_id") references exams("id") on delete cascade on update no action,
  foreign key("copy_id") references exam_copies("id") on delete set null on update no action,
  foreign key("exam_submission_id") references "exam_submissions"("id") on delete set null
);
CREATE UNIQUE INDEX "omr_scans_idempotency_key_unique" on "omr_scans"(
  "idempotency_key"
);
CREATE INDEX "omr_scans_session_id_index" on "omr_scans"("session_id");
CREATE UNIQUE INDEX "omr_scans_exam_submission_id_unique" on "omr_scans"(
  "exam_submission_id"
);
CREATE TABLE IF NOT EXISTS "learning_materials"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "author_id" integer not null,
  "title" varchar not null,
  "description" text,
  "body" text,
  "external_url" varchar,
  "discipline_id" integer,
  "custom_skill_id" integer,
  "bncc_node_id" integer,
  "status" varchar not null default 'draft',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("author_id") references "users"("id") on delete cascade,
  foreign key("discipline_id") references "disciplines"("id") on delete set null,
  foreign key("custom_skill_id") references "custom_skills"("id") on delete set null,
  foreign key("bncc_node_id") references "bncc_nodes"("id") on delete set null
);
CREATE INDEX "learning_materials_organization_id_status_index" on "learning_materials"(
  "organization_id",
  "status"
);
CREATE INDEX "learning_materials_organization_id_author_id_index" on "learning_materials"(
  "organization_id",
  "author_id"
);
CREATE TABLE IF NOT EXISTS "learning_material_school_class"(
  "id" integer primary key autoincrement not null,
  "learning_material_id" integer not null,
  "school_class_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("learning_material_id") references "learning_materials"("id") on delete cascade,
  foreign key("school_class_id") references "school_classes"("id") on delete cascade
);
CREATE UNIQUE INDEX "learning_material_class_unique" on "learning_material_school_class"(
  "learning_material_id",
  "school_class_id"
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "type" varchar check("type" in('global_admin', 'institution_admin', 'teacher', 'student', 'guardian')) not null default 'student',
  "status" varchar not null default('active'),
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "link_code" varchar,
  "settings" text,
  foreign key("organization_id") references organizations("id") on delete set null on update no action
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE UNIQUE INDEX "users_link_code_unique" on "users"("link_code");
CREATE TABLE IF NOT EXISTS "guardian_student_links"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "guardian_id" integer not null,
  "student_id" integer not null,
  "created_by" integer,
  "relationship" varchar not null default 'Responsável',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("guardian_id") references "users"("id") on delete cascade,
  foreign key("student_id") references "users"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "guardian_student_org_unique" on "guardian_student_links"(
  "organization_id",
  "guardian_id",
  "student_id"
);
CREATE INDEX "guardian_student_links_guardian_id_deleted_at_index" on "guardian_student_links"(
  "guardian_id",
  "deleted_at"
);
CREATE INDEX "guardian_student_links_student_id_deleted_at_index" on "guardian_student_links"(
  "student_id",
  "deleted_at"
);
CREATE TABLE IF NOT EXISTS "learning_material_progress"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "learning_material_id" integer not null,
  "student_id" integer not null,
  "status" varchar not null default 'opened',
  "view_count" integer not null default '1',
  "opened_at" datetime,
  "last_viewed_at" datetime,
  "completed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("learning_material_id") references "learning_materials"("id") on delete cascade,
  foreign key("student_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "learning_material_student_progress_unique" on "learning_material_progress"(
  "learning_material_id",
  "student_id"
);
CREATE INDEX "learning_material_progress_organization_id_student_id_status_index" on "learning_material_progress"(
  "organization_id",
  "student_id",
  "status"
);

INSERT INTO migrations VALUES(1,'0000_01_01_000000_create_organizations_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(4,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(5,'2026_02_22_190651_create_permission_tables',1);
INSERT INTO migrations VALUES(6,'2026_02_22_191409_create_event_logs_table',1);
INSERT INTO migrations VALUES(7,'2026_02_22_192111_create_school_classes_table',1);
INSERT INTO migrations VALUES(8,'2026_02_22_192111_create_student_profiles_table',1);
INSERT INTO migrations VALUES(9,'2026_02_22_192112_create_class_student_table',1);
INSERT INTO migrations VALUES(10,'2026_02_22_192728_create_questions_table',1);
INSERT INTO migrations VALUES(11,'2026_02_22_192729_create_question_shares_table',1);
INSERT INTO migrations VALUES(12,'2026_02_22_193442_create_exams_table',1);
INSERT INTO migrations VALUES(13,'2026_02_22_193443_create_exam_questions_table',1);
INSERT INTO migrations VALUES(14,'2026_02_22_193631_create_exam_school_class_table',1);
INSERT INTO migrations VALUES(15,'2026_02_22_201039_create_exam_submissions_table',1);
INSERT INTO migrations VALUES(16,'2026_02_22_201040_create_exam_answers_table',1);
INSERT INTO migrations VALUES(17,'2026_02_22_201041_add_access_code_to_exams_table',1);
INSERT INTO migrations VALUES(18,'2026_02_22_204350_create_plans_table',1);
INSERT INTO migrations VALUES(19,'2026_02_22_204350_create_subscriptions_table',1);
INSERT INTO migrations VALUES(20,'2026_02_23_015418_create_omr_scans_table',1);
INSERT INTO migrations VALUES(21,'2026_02_24_220000_evolve_plans_table_for_prd',1);
INSERT INTO migrations VALUES(22,'2026_02_24_220100_create_plan_limits_table',1);
INSERT INTO migrations VALUES(23,'2026_02_24_220200_create_plan_features_table',1);
INSERT INTO migrations VALUES(24,'2026_02_24_220300_evolve_subscriptions_for_morph',1);
INSERT INTO migrations VALUES(25,'2026_02_24_224000_evolve_organizations_for_phase2',1);
INSERT INTO migrations VALUES(26,'2026_02_24_224100_create_user_organization_table',1);
INSERT INTO migrations VALUES(27,'2026_02_24_224200_create_invites_table',1);
INSERT INTO migrations VALUES(28,'2026_02_24_230000_create_audit_logs_table',1);
INSERT INTO migrations VALUES(29,'2026_02_24_232523_create_disciplines_table',1);
INSERT INTO migrations VALUES(30,'2026_02_24_232523_create_knowledge_areas_table',1);
INSERT INTO migrations VALUES(31,'2026_02_24_232534_add_taxonomy_to_questions_table',1);
INSERT INTO migrations VALUES(32,'2026_02_25_000100_add_link_code_to_users',1);
INSERT INTO migrations VALUES(33,'2026_02_25_000200_add_invite_type_to_invites',1);
INSERT INTO migrations VALUES(34,'2026_02_25_080100_add_class_ownership_and_teacher_pivot',1);
INSERT INTO migrations VALUES(35,'2026_02_25_120100_create_institution_roles_tables',1);
INSERT INTO migrations VALUES(36,'2026_02_25_124500_add_trash_logs_access_to_organizations',1);
INSERT INTO migrations VALUES(37,'2026_02_25_230134_add_level_to_questions_table',1);
INSERT INTO migrations VALUES(38,'2026_02_25_233000_build_bncc_curriculum_structure',1);
INSERT INTO migrations VALUES(39,'2026_02_25_235000_create_bncc_structure_tables',1);
INSERT INTO migrations VALUES(40,'2026_02_25_235500_create_custom_skills_table',1);
INSERT INTO migrations VALUES(41,'2026_02_27_160906_create_exam_copies_table',1);
INSERT INTO migrations VALUES(42,'2026_03_01_043954_add_settings_to_users_table',1);
INSERT INTO migrations VALUES(43,'2026_03_01_125439_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(44,'2026_03_01_130000_add_copy_id_and_source_to_omr_scans',1);
INSERT INTO migrations VALUES(45,'2026_03_03_002353_add_layout_fields_to_omr_scans',1);
INSERT INTO migrations VALUES(46,'2026_03_03_002353_create_omr_scan_pages_table',1);
INSERT INTO migrations VALUES(47,'2026_03_03_002403_create_omr_calibrations_table',1);
INSERT INTO migrations VALUES(48,'2026_03_03_002404_add_omr_hmac_secret_to_organizations',1);
INSERT INTO migrations VALUES(49,'2026_03_03_002412_create_omr_audit_logs_table',1);
INSERT INTO migrations VALUES(50,'2026_03_03_002948_add_missing_columns_to_omr_scans',1);
INSERT INTO migrations VALUES(51,'2026_03_04_004026_create_omr_templates_table',1);
INSERT INTO migrations VALUES(52,'2026_03_04_004031_create_omr_template_questions_table',1);
INSERT INTO migrations VALUES(53,'2026_03_04_110808_add_warped_path_to_omr_scans_table',1);
INSERT INTO migrations VALUES(54,'2026_03_05_010859_add_layout_meta_to_omr_scans_table',1);
INSERT INTO migrations VALUES(55,'2026_04_22_000001_create_answer_sheet_types_table',1);
INSERT INTO migrations VALUES(56,'2026_04_22_000002_create_scan_modes_table',1);
INSERT INTO migrations VALUES(57,'2026_04_22_000003_create_config_rules_table',1);
INSERT INTO migrations VALUES(58,'2026_04_22_000004_create_config_audit_logs_table',1);
INSERT INTO migrations VALUES(59,'2026_04_22_000005_add_answer_sheet_type_and_version_to_exams',1);
INSERT INTO migrations VALUES(60,'2026_06_05_100001_evolve_omr_templates_for_card_templates',1);
INSERT INTO migrations VALUES(61,'2026_06_05_100002_create_omr_template_versions_table',1);
INSERT INTO migrations VALUES(62,'2026_06_05_100003_add_card_template_to_exams',1);
INSERT INTO migrations VALUES(63,'2026_06_06_120000_add_quality_json_to_omr_scans',1);
INSERT INTO migrations VALUES(64,'2026_06_07_120000_backfill_true_false_answer_keys',1);
INSERT INTO migrations VALUES(65,'2026_07_29_000001_harden_exam_attempts_and_answers',1);
INSERT INTO migrations VALUES(66,'2026_07_29_200000_add_grading_feedback_to_exam_records',1);
INSERT INTO migrations VALUES(67,'2026_07_29_210000_add_tenant_context_to_omr_scan_pages',1);
INSERT INTO migrations VALUES(68,'2026_07_29_220000_link_omr_scans_to_exam_submissions',1);
INSERT INTO migrations VALUES(69,'2026_07_29_230000_create_learning_materials_tables',2);
INSERT INTO migrations VALUES(70,'2026_07_29_235000_add_guardian_portal',2);
INSERT INTO migrations VALUES(71,'2026_07_29_236000_create_learning_material_progress_table',2);
INSERT INTO migrations VALUES(72,'2026_07_29_236100_ensure_guardian_role_exists',3);
