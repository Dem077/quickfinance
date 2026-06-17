# Finance App Documentation

This document explains the app workflow and summarizes major functional changes made across the system.

## 1) Application Overview

The application is a Laravel + Filament admin system used to manage:

- Purchase Requests (PR)
- Purchase Orders (PO / Procure)
- Petty Cash Reimbursements
- Advance Forms
- Budget tracking and transaction history
- Master data (Users, Departments, Locations, Vendors, Items, Projects, Budget accounts)

Core admin resources are in `app/Filament/Admin/Resources`.

## 2) Main Modules

- `PurchaseRequestsResource`: PR creation, approval, cancellation, document upload, and closure.
- `PurchaseOrdersResource`: PO generation from PR, submission, optional advance form, supporting document upload, closure, and budget deduction.
- `PettyCashReimbursmentResource`: petty cash reimbursement request, department approval, finance approval, PV verification, reimbursement posting.
- `AdvanceFormResource`: manage generated advance forms for PO flow.
- `BudgetTransactionHistoryResource`: audit entries for budget deductions and reimbursements.

## 3) Status Enums

### Purchase Requests (`PurchaseRequestsStatus`)

- `draft`
- `submitted`
- `hod_approved`
- `hod_rejected`
- `approved`
- `canceled`
- `document_uploaded`
- `closed`

### Purchase Orders (`PurchaseOrderStatus`)

- `draft`
- `submitted`
- `reimbursement_pending`
- `reimbursed`
- `closed`

### Petty Cash (`PettyCashStatus`)

- `draft`
- `submited` (existing enum value spelling)
- `dep_approved`
- `dep_reject`
- `fin_approved`
- `fin_reject`
- `rembursed` (existing enum value spelling)

## 4) End-to-End Workflow

## 4.1 Purchase Request Workflow

1. User creates PR in `draft`.
2. User submits PR (`submit_for_approval`) -> status `submitted`.
3. Department HOD can:
   - Approve -> `hod_approved`
   - Reject -> `hod_rejected`
4. Finance approver can:
   - Approve -> `approved`
   - Cancel (with reason) -> `canceled`
5. Requester uploads signed document -> `document_uploaded`.
6. Finance closes PR -> `closed`.

Emails/notifications:

- Submission notifies HOD.
- HOD/Finance decisions notify requester.

## 4.2 Purchase Order (Procure) Workflow

1. PO is created from eligible PR and starts as `draft`.
2. Submit action marks PO `submitted` and marks related PR items as utilized.
3. Optional advance form generation:
   - `generate_advance_form` if required and not generated.
   - `regenerate_advance_form` if already generated.
4. If payment method is petty cash, supporting receipt can be uploaded.
5. PO close action:
   - Purchase order payment method -> PO `closed` and budget deducted.
   - Petty cash payment method -> PO `reimbursement_pending` (waiting reimbursement).

## 4.3 Petty Cash Reimbursement Workflow

1. Requester creates record in `draft`.
2. Submit -> `submited`.
3. Department HOD:
   - Approve -> `dep_approved`
   - Reject -> back to `draft`
4. Finance HOD:
   - Approve -> `fin_approved`
   - Reject/send back -> back to `draft`
5. Payables (PV approver) adds PV number(s):
   - status -> `rembursed`
   - `verified_by` set
   - related budget is deducted
   - related PO (if linked) updated as reimbursed
   - transaction history is created

## 5) Roles and Access Behavior

The system uses role/permission checks and query filtering to control visibility and actions:

- Super admin can access all records.
- Department HODs see and act on department-submitted records.
- Finance approvers can view/approve records beyond requester scope.
- Requesters generally see and edit their own drafts or specific allowed statuses.

Important pattern:

- Workflow actions are protected by both status checks and permission checks in resource actions.

## 6) PDFs and Download Routes

Defined in `routes/web.php`:

- PR PDF preview: `purchase-requests.download`
- Advance Form PDF: `purchase-orders.advance-form.download`
- Petty Cash PDF preview: `petty-cash.preview`

Each route loads related models and renders an mPDF document.

## 7) Email Notification Flow (High Level)

- Submission actions send notification emails to next approver group.
- Approval/rejection actions send status emails to requester.
- Notifications are queued via `Mail::to(...)->queue(...)`.

## 8) Major System Changes (From Git History)

Below is a consolidated summary of major updates reflected in commit history.

### 8.1 Workflow and Access Control

- Improved `getEloquentQuery` logic across PR and Petty Cash resources to enforce role-based visibility (super admin, HOD, approver, requester).
- Multiple fixes for action visibility checks to ensure only correct users can approve/reject/edit.
- Added/adjusted policy behavior for edit/update access by status.

### 8.2 Purchase Request Enhancements

- PR statuses standardized and improved through HOD + Finance flow.
- Added record ID, improved table readability (purpose truncation), and approval/close actions.
- Added support for multi-location selection (many-to-many) and visibility updates.
- Improved PR PDF generation with project/location handling.

### 8.3 Purchase Order Enhancements

- PO form behavior locked/unlocked based on status.
- Improved PR item linking/utilization logic.
- Added/updated advance form generation and route handling.
- Amount rounding/formatting improvements in forms and PDFs.
- Added and refined petty-cash receipt upload and close behavior.

### 8.4 Petty Cash Reimbursement Enhancements

- Improved vendor selection/search, PO linkage display, decimal amount handling.
- Refined approval/rejection action visibility and status handling.
- Improved email notification handling with null-safe checks.
- Recent rejection flow fixes:
  - Prevented null email runtime errors by using null-safe checks.
  - Updated Finance HOD `send_back` rejection to avoid dereferencing null verifier.
  - Removed mailing to PV approvers on `send_back` (per current behavior).

### 8.5 Infrastructure and Utility

- Mail worker/command updates (`MailStart` behavior/signature refinements).
- Additional package/config updates and miscellaneous code cleanup for maintainability.

## 9) Current Behavior Notes

- Enum values include legacy spellings (`submited`, `rembursed`) and are used as-is in persisted status values.
- Some action names/labels may be business-specific (`send_back`, `rembursed`) but map to the status transitions above.
- For production safety, continue using null-safe relationship checks for email target lookups in action handlers.

## 10) Recommended Ongoing Documentation Process

For each functional change, update this document with:

1. Module impacted (`PR`, `PO`, `Petty Cash`, `Budget`, `Auth/Access`)
2. Workflow step changed
3. Status transition impacted
4. Notification impact
5. Migration/model/policy/resource files touched

This keeps audit and onboarding documentation aligned with code behavior.

