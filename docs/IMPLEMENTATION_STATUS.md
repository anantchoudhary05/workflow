# Implementation status

Last updated: 30 Oct 2026. This file reflects what is in the code. It does not describe plans as if they were done.

## Summary

All five planned phases have a working first version, plus the department roles update (30 Oct 2026): seven roles, private to-dos, client documents, a manager review queue, urgency, Marketing → dev handoff and role-specific dashboards, admin-only soft delete with a Trash page (30 Oct 2026), a per-project My work panel (30 Oct 2026), and a milestone journey on the project overview with a calmer interface (30 Oct 2026). Screens, server-side rules and authorization are in place. The automated feature tests (205 tests, 1,158 assertions) pass on SQLite in memory and on MariaDB 11.4 in strict mode.

See "Not verified" below.

## Modules

| Phase | Module | Status | Notes |
|---|---|---|---|
| 1 | Login / logout, roles, inactive-user lockout | Done | No public registration. The first admin is created with `php artisan doc:make-admin`. Login is rate-limited to 5 attempts per minute per email and IP. |
| 1 | App shell, navigation, global search | Done | Search covers projects, clients, tasks, requirements and change requests, scoped to what the user may see. |
| 1 | Dashboard and My Work | Done | Figures are calculated from real records. Nothing is a placeholder. |
| 1 | Clients and contacts | Done | Clients can be archived. An administrator can also delete (soft delete) a client that has no projects. The client page shows who created it and a history card. |
| 1 | Projects (CRUD, members, status workflow, due-date history) | Done | Ready to Start needs an approved scope and an approved quotation, unless an admin overrides with a reason. |
| 1 | Activity and audit log | Done | Filters: project, user, event, date range, audit-only. |
| 2 | Requirements and scope baselines | Done | Removing a baselined requirement requires a change request. |
| 2 | Quotations (versions, items, internal cost, print view) | Done | The print view is HTML for the browser's "Save as PDF". There is no server-side PDF library. |
| 2 | Milestones (dependencies, approval, acceptance criteria) | Done | |
| 2 | Tasks (list, kanban board, comments, time logging, review) | Done | The board supports drag and drop. The server rules still apply to every move. |
| 2 | Team workload | Done | Load is shown against each person's weekly capacity. |
| 3 | Meetings (calendar, decisions, decision → task) | Done | |
| 3 | Communications log and "waiting on client" actions | Done | Includes follow-up dates. |
| 3 | Previews and approvals | Done | Approval needs a recorded client decision with a source reference. |
| 3 | Change requests | Done | Steps: classification, internal review, client decision with evidence, admin override with reason. |
| 4 | QA test cases and defects | Done | Defect status rules include retest and verification. |
| 4 | Project files | Done | Stored on the private disk and downloaded only through an authorized route. |
| 4 | Release and handover checklists | Done | Generated from `config/doc.php` templates. Mandatory items cannot be marked N/A. |
| 4 | Handover, client acceptance, closure, reopen | Done | Closing needs both checklists and recorded acceptance, or an admin exception with a reason. |
| 5 | Reports with CSV export | Done | 8 reports. CSV cells are protected against formula injection. |
| 5 | In-app notifications and daily reminders | Done | `doc:send-reminders` runs daily at 09:00 through the scheduler. Each reminder is sent at most once per day. |
| 5 | User management | Done | Admin only. Admins set passwords, assign roles and can deactivate accounts. The user form lists every role with a one-line description. |
| 6 | Department roles | Done | Administrator, Manager, Team Lead, Developer, QA / Reviewer, Marketing, Management (read-only). Abilities live in `App\Enums\Role::abilities()` and are checked by gates and policies on the server. A data migration keeps existing users' roles unchanged and maps unknown values to Developer. |
| 6 | Private to-dos | Done | Every user has a personal list (title, notes, due date, priority, optional project link). Shown on the dashboard, on the project page and on **My to-dos**. Only the owner can see or change them; administrators cannot. Not written to the activity log. |
| 6 | Client documents | Done | Marketing, Manager, Team Lead and Admin upload PDF, Word, Excel, PowerPoint, images, text and ZIP files (size limit from `config/doc.php`) with a category and description. Stored on the private disk; download and inline preview (PDF/images) go through an authorized route. Instead of a file they can add a cloud link (Google Drive, OneDrive, Dropbox and so on): http/https only, up to 2,048 characters, title required. Link documents show a link icon and the site name and open in a new tab. Shown on the client page and on every project of that client. Deleting is a soft delete. |
| 6 | Manager review queue | Done | A Team Lead requests a review on a project; a Manager marks it **Ready for preview** or **Needs corrections** (notes required). Both sides are notified. |
| 6 | Urgent flag | Done | Team Lead, Manager and Admin set task priority, Urgent included (create, edit and the quick priority control); each change is in the activity log and an urgent task notifies its assignee. Only Manager and Admin mark a project urgent or set project deadlines. Urgent tasks and projects appear in a dashboard widget and KPI. |
| 6 | Marketing handoff | Done | Marketing hands a project to the dev team with notes; Team Leads are notified and acknowledge the pickup. |
| 6 | Role dashboards | Done | KPIs and widgets are chosen per role (see the role matrix in `WORKFLOW_GUIDE.md`). |
| 7 | My work per project | Done | The project page shows a **My work** panel with tabs **Assigned tasks** (open tasks assigned to you in that project, overdue and urgent first and marked, status and priority badges, a **Start** button for Backlog/Ready tasks, plus reviews waiting on you and client follow-ups you own there) and **My to-dos**. The tabs work with mouse and keyboard (arrow keys, Home, End). The sidebar **My work** page keeps all projects, shows the project on every task and has a project filter (`?project=`). Both use `App\Services\MyWork`. Each task is listed in one group only. No migration. |
| 7 | Soft delete, Trash and deletion history | Done | Administrators only. Projects, clients, tasks and users have a **Delete** button that asks for a reason (at least 5 characters). The record gets `deleted_at`, `deleted_by` and `deletion_reason` and is hidden from lists, dashboards, reports, search and reminders; its pages return 404. Delete and restore are written to the audit log (who, when, reason, restore note) and shown in the record's history. **Trash** (Administration menu) lists deleted records per type with who deleted them, when (IST) and why, and restores them with an optional note. There is no permanent delete. Deleting a project hides its tasks, milestones and other records, and restoring brings them back. A client with projects cannot be deleted. Admins cannot delete themselves or the last active administrator. A deleted user cannot sign in, their sessions are rejected, and their name shows as "Deleted user (Name)". Projects and clients now record `created_by`, shown as "Created by" (older records fall back to the first activity-log entry, otherwise it is hidden). Migration: `2026_10_30_130000_add_deletion_history_columns`. |
| 8 | Milestone journey and completed log | Done | The project overview shows each milestone as a stop on a line that fills with its done/total tasks (cancelled tasks excluded), with Completed, You are here (with %), Upcoming and Overdue states, and an **Unplanned work** bucket for tasks without a milestone. Clicking or keyboard-activating a stop opens a panel listing the tasks completed in that milestone (assignee initials, completed date, link), with open tasks folded below; `?milestone=ID` opens it from a link. A **Completed log** lists completed tasks and milestones with who and when (from the activity log, otherwise the assignee). Built by `App\Services\MilestoneTrack` from real records only; plain CSS and JavaScript (`resources/js/modules/milestone-track.js`); respects reduced motion. Empty state with **Add the first milestone** only for roles that may manage milestones. No migration. |
| 8 | Interface pass | Done | Shared design tokens in `resources/css/app.css` (surfaces, softer shadows, radii, motion timings, focus ring, gradients) and restyled buttons (primary, secondary, ghost, danger; hover lift, press, focus ring, busy spinner), cards, badges, tables (sticky header), forms, dialogs (centred, scale and fade, blurred backdrop), tabs, empty states and toasts. Dashboards show at most four KPIs in one strip plus chips, and group widgets into two tabbed cards. Page sections fade in with a short stagger (150–450 ms), off under reduced motion. The login page and the sidebar scroll area were not changed. |
