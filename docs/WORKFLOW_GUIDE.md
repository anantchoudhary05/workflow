# Workflow guide

## Roles

| Role | Can do |
|---|---|
| Administrator | Everything, including the rule overrides. Every override requires a written reason and is recorded in the audit log. |
| Management | Read-only view of all projects, clients, financials, reports, team and activity. |
| Developer | Works on the projects they are a member of: tasks, time logging, communications, change-request logging, previews and files. |
| Reviewer | Everything a Developer can do, plus task review, QA (test cases and defects) and the release checklist. |

- Accounts are created by an administrator on **Users**, or with `php artisan doc:make-admin`.
- A deactivated user is signed out on their next request.

## Project lifecycle

The statuses are:

Lead → Planning → Awaiting Approval → Ready to Start → In Progress → In Review / QA → Ready for Launch → Delivered → Closed

The side states are On Hold, Blocked and Cancelled.

**What "active" means.** A project is *active* (in delivery) when its status is Ready to Start, In Progress, Blocked, In Review, QA or Ready for Launch. Lead, Planning and Awaiting Approval are the *pipeline*. The dashboard "Active projects" card, the Projects "Active (in delivery)" filter, client project counts and reports all use this same definition.

**Initial status when creating a project.** The New project form has an "Initial status" field. Lead is the default. Choose Planning, Awaiting Approval, Ready to Start or In Progress for work that is already underway. The chosen status is saved and recorded in the status history. Delivered, Closed and the side states cannot be chosen at creation.

**Fixing a project's status later.** On the project page, use "Change status". Normal moves follow the lifecycle. An administrator can jump to another status by ticking "Admin override" and giving a reason. The override is recorded in the status history and the audit log.

1. **Requirements.** Capture the requirements, then approve them.
2. **Scope baseline.** Create a scope baseline from the approved requirements. After that, removing a baselined requirement requires a change request.
3. **Quotation.** Build the quotation from the scope. The steps are:
   - Draft
   - Internal review
   - Sent. A "waiting on client" item is created automatically.
   - Client decision. Record who decided and the source, for example an email or a meeting.

   Sent quotations cannot be edited. To change one, create a new version.
4. **Ready to Start.** This needs an approved scope and an approved quotation. An administrator can override with a reason.
5. **Blocked, On Hold and Cancelled** all require a reason.
6. **Due-date changes.** Every change to the due date is recorded with a reason. The original due date is kept.
7. **Delivered.** This requires every mandatory item on the release checklist to be done, unless an administrator overrides.

## Milestones and tasks

- **New tasks** start in Backlog by default. On the New task form you can choose Backlog, Ready or In Progress instead. Tasks cannot be added to closed projects.

- **Milestones** can depend on other milestones.
  - A milestone cannot be completed while a prerequisite is still open.
  - Its acceptance criteria must be confirmed.
  - If it requires approval, a client approval must be recorded first.
- **Tasks:**
  - A blocked task needs a blocker reason.
  - A task needs a reviewer before it can be submitted for review.
  - Only the assigned reviewer, or an administrator, can complete a review.

## Change requests

The steps are:

Submitted → Under assessment → (Estimate required) → Awaiting internal review → Awaiting client approval → Approved → Scheduled / In progress → Implemented → Verified

- **Classification.** An administrator classifies each request as one of:
  - Bug
  - In-scope refinement
  - New scope
- **Internal review.** It is recorded on the change request page, and must be recorded before client approval can be requested.
- **Client decision.** It is recorded with the decision-maker's name and a source reference. Approved and Rejected cannot be set directly.
- **New scope** must go through client approval. An administrator can force it to Scheduled or In progress only with a recorded reason.
- **Reasons.** Rejected, Deferred and Cancelled each require a reason.

## Previews and approvals

- The preview steps are:

  Draft → Ready to share → Shared → Awaiting feedback → Feedback received / Changes required → Approved

- Approved is only reached by recording the client's decision.
- The **Approvals** page lists everything still waiting on the client, plus recent decisions.

## QA, release and handover

- **Test cases** record a result: Passed, Failed, Blocked, Needs retest or N/A.
- **Defects** go:

  Open → Triaged → In progress → Fixed → Ready for retest → Verified

  A defect can also be Reopened or set to Won't fix.
- **Release and handover checklists:**
  - They are generated from the templates in `config/doc.php`. You can edit those templates.
  - Mandatory items cannot be marked N/A.
- **Handover** starts once the project is Delivered.
  - Accepted requires a recorded client acceptance.
  - Closing requires both checklists and that acceptance, unless an administrator closes with a reasoned exception.
  - Only an administrator can reopen a closed project.

## Reminders and notifications

`php artisan doc:send-reminders` runs every day at 09:00 and sends these in-app reminders:

- Overdue tasks and tasks due today, to the assignee.
- In-progress tasks with no update for `stale_task_days` (5) days.
- "Waiting on client" items whose follow-up date has arrived, to their owner.

Each reminder is sent at most once per day.

Events also send immediate notifications. The person who made the change never notifies themselves.

| Event | Who is notified |
|---|---|
| Task assigned | Assignee |
| Review requested | Reviewer |
| Changes requested | Assignee |
| Task blocked | Project manager |
| Defect assigned | Assignee |
| Critical defect logged | Project manager |
| Defect ready for retest | Reporter |

## Reports

| Report | Uses the date range? |
|---|---|
| Project status | No |
| Overdue tasks | No |
| Time by project | Yes |
| Client waiting | No |
| Change requests | Yes |
| Defects | No |
| Quotation pipeline (financial roles only) | No |
| Team workload | No |

Every report can be exported to CSV.
