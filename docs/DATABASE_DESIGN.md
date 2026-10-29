# Database design

All migrations target MySQL 8 or MariaDB 10.6+ first. They also run on SQLite, which the test suite uses.

## Conventions

- **Enum columns** are stored as short strings (`varchar`) and cast to PHP enums in `app/Enums`. They are not native MySQL `ENUM`s, so adding a status needs no schema change.
- **Money** is stored as `decimal(12,2)` and **hours** as `decimal`.
- **Soft deletes** are used on clients, projects, tasks and change requests. Users are deactivated instead of deleted.
- **Foreign keys:**
  - Business history is protected with `restrictOnDelete`, for example a client that has projects, or a project that has tasks.
  - Optional people links use `nullOnDelete`.
  - Child rows use `cascadeOnDelete`.
- **Activity log:** every workflow change writes an `activity_logs` row. `is_audit = 1` marks the rows that form the audit trail.

## Tables by migration

### `2026_10_29_100000_add_team_fields_to_users_table.php`

- adds to **users**: role, job_title, skills, weekly_capacity_hours, is_active, last_login_at

### `2026_10_29_100100_create_clients_tables.php`

- creates **clients**: code, name, company_name, primary_contact, email, phone, website, billing_address, source, status, owner_id, notes, last_contacted_at
- creates **client_contacts**: client_id, name, email, phone, role_title

### `2026_10_29_100200_create_projects_tables.php`

- creates **projects**: code, name, client_id, type, description, manager_id, start_date, original_due_date, due_date, launch_date, completed_at, priority, status, handover_status, approved_scope_version_id, approved_quotation_id, estimated_hours, internal_notes, client_summary, repository_url, staging_url, production_url, closed_by, closed_at, closure_notes
- creates **project_members**: project_id, user_id, role
- creates **project_status_histories**: project_id, from_status, to_status, user_id, reason, is_override, created_at
- creates **project_date_changes**: project_id, old_date, new_date, reason, delay_source, user_id, created_at

### `2026_10_29_100300_create_activity_logs_table.php`

- creates **activity_logs**: user_id, project_id, client_id, subject, event, description, changes, is_audit, created_at
- creates **comments**: commentable, user_id, kind, body

### `2026_10_29_100400_create_requirements_tables.php`

- creates **requirements**: project_id, code, title, description, category, priority, status, acceptance_criteria, requested_by, owner_id, source, module, estimated_hours, notes, decided_by, decided_at, decision_notes
- creates **scope_versions**: project_id, version, summary, snapshot, approval_source, approved_by_name, recorded_by, approved_at

### `2026_10_29_100500_create_quotations_tables.php`

- creates **quotations**: number, version, project_id, client_id, scope_version_id, supersedes_id, title, status, currency, valid_until, payment_terms, scope_summary, exclusions, assumptions, delivery_period, notes, subtotal, discount_amount, tax_rate, tax_amount, total, optional_total, internal_cost_total, estimated_hours, sent_at, decided_by, decided_at, decision_source, decision_notes, created_by
- creates **quotation_items**: quotation_id, category, description, quantity, unit, hours, cost_rate, internal_cost, unit_price, line_total, is_optional, notes, sort_order
- adds to **projects**: approved_scope_version_id, approved_quotation_id

### `2026_10_29_100600_create_milestones_tables.php`

- creates **milestones**: project_id, name, description, owner_id, start_date, due_date, status, priority, weight, acceptance_criteria, criteria_met, deliverables, requires_approval, completed_at, notes, override_reason, sort_order
- creates **milestone_dependencies**: milestone_id, depends_on_id

### `2026_10_29_100700_create_change_requests_table.php`

- creates **change_requests**: project_id, number, title, description, reason, source, received_at, requested_by, reviewer_id, priority, classification, classification_rationale, scope_impact, extra_hours, cost_impact, timeline_impact_days, status, internal_reviewed_by, internal_reviewed_at, decided_by, decided_at, approval_evidence, decision_notes, override_reason, created_by
- creates **change_request_milestone**: change_request_id, milestone_id
- creates **change_request_requirement**: change_request_id, requirement_id

### `2026_10_29_100800_create_tasks_tables.php`

- creates **tasks**: project_id, milestone_id, change_request_id, title, description, assignee_id, reviewer_id, creator_id, priority, status, start_date, due_date, estimated_hours, tags, acceptance_criteria, blocker_reason, completed_at, internal_notes, position
- creates **time_entries**: project_id, task_id, user_id, work_date, hours, description, billable

### `2026_10_29_100900_create_client_operations_tables.php`

- creates **meetings**: client_id, project_id, title, type, starts_at, duration_minutes, external_participants, agenda, notes, status, organizer_id, follow_up_date
- creates **meeting_participants**: meeting_id, user_id
- creates **meeting_decisions**: meeting_id, decision, decided_by, owner_id, task_id, recorded_by
- creates **communications**: client_id, project_id, type, direction, subject, summary, occurred_at, owner_id, outcome, next_action
- creates **client_actions**: client_id, project_id, type, title, related, requested_at, last_follow_up_at, next_follow_up_at, owner_id, status, resolved_at, resolution
- creates **previews**: project_id, milestone_id, title, url, version_label, shared_at, shared_by, review_due_at, status, feedback_summary, internal_notes
- creates **approvals**: project_id, approvable, outcome, version_label, decided_by_name, source_reference, notes, decided_at, recorded_by

### `2026_10_29_101000_create_delivery_tables.php`

- creates **test_cases**: project_id, requirement_id, milestone_id, code, title, description, preconditions, steps, expected_result, actual_result, status, tester_id, executed_at, notes
- creates **project_files**: project_id, uploaded_by, original_name, stored_path, mime_type, size, category, visibility, description
- creates **defects**: project_id, test_case_id, file_id, code, title, description, severity, priority, steps, expected_behavior, actual_behavior, assignee_id, reporter_id, status, resolved_at, verified_by, verified_at, resolution_notes
- creates **checklist_items**: project_id, type, label, is_mandatory, status, evidence, responsible_id, completed_by, completed_at, sort_order

### `2026_10_29_101100_create_notifications_table.php`

- creates **notifications**: id, type, notifiable, data, dedupe_key, read_at

## Key relationships

| Relationship | Type |
|---|---|
| client → projects | 1:n |
| project ↔ users through `project_members` | n:n |
| project → requirements, scope_versions, quotations (→ quotation_items), milestones, tasks (→ time_entries), change_requests, meetings, communications, client_actions, previews, approvals, test_cases, defects, project_files, checklist_items | 1:n |
| `projects.approved_scope_version_id`, `approved_quotation_id` → the approved baseline and quotation | foreign keys added in the quotations migration |
| change_requests ↔ milestones, requirements | pivot tables |
| `approvals.approvable` → quotation, change request, preview, milestone or project (final acceptance) | polymorphic |
| `client_actions.related` → the record the client is being chased for | polymorphic |
| `comments.commentable` | polymorphic |
| `notifications.dedupe_key` (indexed), used to stop the daily reminders from duplicating | column |
