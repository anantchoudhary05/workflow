# Implementation status

Last updated: 29 Oct 2026. This file reflects what is in the code. It does not describe plans as if they were done.

## Summary

All five planned phases have a working first version: screens, server-side rules and authorization. Automated feature tests cover the core flows (66 tests, all passing on SQLite in memory). Two things have **not** been done yet:

- The migrations have not been run against MySQL.
- The UI has not been reviewed by hand in a browser.

See "Not verified" below.

## Modules

| Phase | Module | Status | Notes |
|---|---|---|---|
| 1 | Login / logout, roles, inactive-user lockout | Done | No public registration. The first admin is created with `php artisan doc:make-admin`. Login is rate-limited to 5 attempts per minute per email and IP. |
| 1 | App shell, navigation, global search | Done | Search covers projects, clients, tasks, requirements and change requests, scoped to what the user may see. |
| 1 | Dashboard and My Work | Done | Figures are calculated from real records. Nothing is a placeholder. |
| 1 | Clients and contacts | Done | Clients can be archived but not deleted. |
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
| 5 | User management | Done | Admin only. Admins set passwords and can deactivate accounts. |

## Deferred, not built

These are not built. No link or button anywhere points to them.

- Email delivery of notifications. Notifications are in-app only.
- Self-service password reset by email. An admin resets passwords on the Users screen.
- A client portal or client logins.
- Two-factor authentication.
- Server-side PDF generation.

## Not verified

- **MySQL 8 itself:** the full test suite (migrations included) passes on SQLite and on MariaDB 11.4 in strict mode with ONLY_FULL_GROUP_BY. It has not been run against MySQL 8, so run the migrations on a staging MySQL database before production.
- **Browser review:** the login page (desktop and 360px), the dashboard (expanded and collapsed sidebar, mobile) and the New project form were checked in headless Chrome with a production Vite build. Other screens are covered only by the rendering tests.

## Known limitations

- Sign-in is limited to 5 attempts per minute per email address and IP. After that, the form shows "Too many sign-in attempts" until the minute is up.
