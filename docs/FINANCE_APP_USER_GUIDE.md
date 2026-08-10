# Finance App User Documentation

Clear workflow guide and change summary for operations and admin staff.

**Modules:** Purchase Requests (PR), Purchase Orders / Procure (PO), Petty Cash Reimbursements
**Primary roles:** Requester, Department HOD, Finance, Payables (PV)

## How to use this guide

Start with the workflow for your module, then confirm the final status transition before moving to the next team.

## Status transition map (at a glance)

### Purchase Request
Draft → Submitted → HOD Approved → Finance Approved → Document Uploaded → Closed

### Purchase Order
Draft → Submitted → Closed / Waiting Reimbursement → Reimbursed (linked via petty cash flow)

### Petty Cash
Draft → Submitted → Department Approved → Finance Approved → Reimbursed

## Purchase Request workflow

1. Requester — Create PR draft and fill item details → Status = Draft
2. Requester — Submit for approval → Status = Submitted, HOD notified
3. Department HOD — Approve or Reject → Approved: HOD Approved; Reject: HOD Rejected
4. Finance — Approve or Cancel → Approved: Finance Approved; Cancel: Canceled
5. Requester — Upload signed document → Status = Document Uploaded
6. Finance — Close PR → Status = Closed

## Purchase Order (Procure) workflow

1. Procurement/Ops — Create PO from eligible PR → Status = Draft
2. Procurement/Ops — Submit PO → Status = Submitted, PR items marked utilized
3. Procurement/Ops — Generate or Regenerate Advance Form (if required) → Advance form PDF available
4. Procurement/Ops — Upload receipt (petty cash payment method only) → Supporting document stored
5. Finance or Procurement/Ops — Close PO → Purchase order: Closed; Petty cash: Waiting Reimbursement

## Petty Cash reimbursement workflow

1. Requester — Create petty cash reimbursement and submit → Draft → Submitted
2. Department HOD — Approve or Reject → Approve: Department Approved; Reject: Draft
3. Finance HOD — Approve or Send Back → Approve: Finance Approved; Send Back: Draft
4. Payables (PV) — Add PV number(s) → Finance Approved → Reimbursed
5. System — Post reimbursement → Budget adjusted, history logged, linked PO reimbursed when present

**Key rule:** Finance send-back rejection notifies the requester and returns the record to Draft.

## What changed across the app (user-facing)

### Petty Cash Rejection Safety
- What changed: Null-safe email handling added to reject flows
- Benefit: Prevents runtime errors when related user is missing
- Impact: Higher reliability during rejection actions

### Finance Send-Back Notification
- What changed: Finance send-back now notifies requester only
- Benefit: Cleaner communication path for correction
- Impact: No unnecessary PV approver email on this path

### Workflow Visibility Rules
- What changed: Action visibility refined by role + status checks
- Benefit: Only valid users see approve/reject actions
- Impact: Less process confusion and fewer wrong clicks

### Purchase Request Enhancements
- What changed: Improved query filtering and lifecycle handling
- Benefit: Users see relevant requests at correct stages
- Impact: Smoother PR progression and approval routing

### Purchase Order Enhancements
- What changed: Advance form and close behavior improved
- Benefit: Better control for PO and petty-cash settlement
- Impact: Cleaner downstream reimbursement handling

## Daily operations checklist

1. Process all Submitted items first (PR and Petty Cash).
2. Confirm role-based approvals are done by the correct owner.
3. Ensure required documents are uploaded before closure.
4. Verify the final status moved correctly after each action.
5. Escalate any blocked record with module + record ID.

## Escalation format (when issues happen)

Send these five details to support:

1. Module name (PR / PO / Petty Cash)
2. Record ID
3. Action attempted
4. Error message or screenshot
5. Date and time of issue

---

Source: current Finance app workflows and recent implemented changes in this repository. Guide version v1.
