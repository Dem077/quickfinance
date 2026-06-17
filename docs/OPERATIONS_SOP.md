# Operations SOP - Finance App

This SOP is for operations and admin staff. It explains daily process steps in plain language.

## 1. Purpose

Use this guide to process:

- Purchase Requests (PR)
- Purchase Orders (PO / Procure)
- Petty Cash Reimbursements

Follow the steps exactly in the order shown.

## 2. Who Does What

- Requester: creates requests and uploads required documents.
- Department HOD: reviews and approves/rejects department requests.
- Finance Approver: approves/cancels PRs and closes completed PRs/POs.
- Payables Officer (PV role): adds PV number(s) for petty cash reimbursements.

## 3. Before You Start (Daily Checklist)

1. Sign in to the admin panel.
2. Open the module you need:
   - Purchase Requests
   - Procure (Purchase Orders)
   - Petty Cash Reimbursement
3. Check pending items by status.
4. Verify you are acting on the correct record ID.

## 4. Purchase Request (PR) SOP

## 4.1 Create and Submit PR (Requester)

1. Go to `Purchase Requests`.
2. Click `Create`.
3. Fill all required fields:
   - Date
   - Location(s)
   - Project (if applicable)
   - Purpose / Reason
   - Item lines (item, unit, budget account, quantity, estimated cost)
4. Save.
5. Click `Submit for Approval`.
6. Confirm status changed to `Submitted`.

Expected result:

- Department HOD is notified.

## 4.2 Department Review (HOD)

1. Open submitted PR.
2. Review purpose, items, and budget details.
3. Choose one:
   - `Approve` -> status becomes `Department HOD Approved`.
   - `Reject` -> status becomes `Department HOD Rejected`.

Expected result:

- Requester gets an approval/rejection email.

## 4.3 Finance Decision (Finance Approver)

1. Open HOD-approved PR.
2. Review details.
3. Choose one:
   - `Approve` -> status `Finance Approved`.
   - `Cancel` -> enter cancellation reason, status `Finance Rejected`.

Expected result:

- Requester gets the decision email.

## 4.4 Upload Signed Document (Requester)

1. Open PR with status `Finance Approved`.
2. Click `Upload Document`.
3. Upload signed document.
4. Save.

Expected result:

- Status becomes `Document Uploaded`.

## 4.5 Close PR (Finance Approver)

1. Open PR with status `Document Uploaded`.
2. Click `Close`.
3. Confirm action.

Expected result:

- Status becomes `Closed`.

---

## 5. Purchase Order (Procure) SOP

## 5.1 Create PO (Procurement/Operations)

1. Go to `Procure`.
2. Click `Create`.
3. Fill required fields:
   - Record ID (PO number)
   - Vendor
   - Date
   - PR Number
   - Payment Method (`Purchase Order` or `Petty Cash`)
4. Complete item lines.
5. Use `Generate Total` if needed.
6. Save.

## 5.2 Submit PO

1. Open draft PO.
2. Click `Submit`.
3. Confirm status changed to `Submitted`.

## 5.3 If Advance Form Is Required

1. Open submitted PO (payment method = Purchase Order).
2. Click `Generate Advance Form`.
3. Enter:
   - Quotation number
   - Expected delivery days
   - Advance amount %
4. Generate and review PDF.
5. If details change later, use `Regenerate Advance Form`.

## 5.4 If Payment Method Is Petty Cash

1. Open submitted PO (payment method = Petty Cash).
2. Click `Upload Receipt`.
3. Upload supporting document.
4. Verify the receipt is visible via `View Receipt`.

## 5.5 Close PO

1. Open eligible PO.
2. Click `Close`.
3. Confirm.

Expected result:

- For Purchase Order method: PO status becomes `Closed`.
- For Petty Cash method: PO status becomes `Waiting Reimbursement`.

---

## 6. Petty Cash Reimbursement SOP

## 6.1 Create and Submit Request (Requester)

1. Go to `Petty Cash Reimbursement`.
2. Click `Create`.
3. Fill:
   - Date
   - Form Number
   - Item/service details
   - Vendor and amount
   - Linked PO/record (if applicable)
4. Save.
5. Click `Submit`.

Expected result:

- Status becomes `Submitted`.
- Department HOD is notified.

## 6.2 Department HOD Review

1. Open submitted petty cash request.
2. Review details and supporting files.
3. Choose:
   - `Approve` -> status `Department HOD Approved`.
   - `Reject` -> status returns to `Draft`.

## 6.3 Finance HOD Review

1. Open record with status `Department HOD Approved`.
2. Choose:
   - `Approve` -> status `Finance Approved`.
   - `Reject` (Send back) -> status returns to `Draft`.

Note:

- On send back reject, requester is notified.

## 6.4 Payables Verification (PV Approver)

1. Open record with status `Finance Approved`.
2. Click `Add PV`.
3. Enter one or more PV numbers.
4. Save.

Expected result:

- Status becomes `Reimbursed`.
- Budget is adjusted.
- Transaction history entry is created.
- Linked PO is marked reimbursed when applicable.

---

## 7. How to Handle Rejections

If a request is rejected:

1. Open the rejected record.
2. Read remarks/reason (if shown).
3. Correct data or attachment issues.
4. Re-save in draft.
5. Submit again for approval.

## 8. Verification Checklist Before Approval

Use this checklist before any approval:

1. Correct requester and department.
2. Correct budget account selected.
3. Amounts are reasonable and complete.
4. Required files are attached.
5. Related PR/PO links are correct.
6. No duplicate request for the same purpose.

## 9. Common Issues and What to Do

- Missing action button:
  - Check current status and your role permissions.
- Cannot proceed to next step:
  - Required fields or documents may be missing.
- Wrong status path:
  - Confirm you are in the correct module (PR vs PO vs Petty Cash).
- Email not received:
  - Continue process if status changed; report to technical admin for mail queue check.

## 10. Escalation Path

Escalate to technical/admin support when:

- A page shows an error message.
- Status does not update after confirmed action.
- Attachments fail repeatedly.
- Workflow is blocked by permission/access mismatch.

When escalating, provide:

1. Module name
2. Record ID
3. Action attempted
4. Screenshot of error
5. Time of issue

## 11. End-of-Day Operations Checklist

1. Ensure no urgent approvals remain in Submitted states.
2. Ensure all completed PRs have documents uploaded and closed where required.
3. Ensure petty cash records in Finance Approved are handed to PV processing.
4. Ensure all receipts/supporting documents are uploaded.
5. Report blocked records to support with record IDs.

