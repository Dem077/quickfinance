# Finance Control Center — User Manual

**Version:** 2.0  
**Date:** 2026-10-04  
**Format:** Editable Markdown (companion Word file: `Finance_Control_Center_User_Manual.docx`)

Currency in the application is **MVR**.

> To regenerate the Word document after editing this file’s content in the Python generator, run:  
> `python docs/generate_user_manual.py`  
> Or edit the `.docx` directly in Microsoft Word / LibreOffice / Google Docs.

---

## 1. Contents

1. Contents  
2. Getting started  
3. Screen layout and navigation  
4. Roles, permissions, and HOD setup  
5. Dashboard and profile  
6. Purchase Requests  
7. Procure (Purchase Orders)  
8. Petty Cash reimbursements  
9. Asset Management  
10. Budgets (accounts, transfers, history)  
11. Master data  
12. Reports and dashboard charts  
13. Activity log and email status  
14. Users and roles administration  
15. End-to-end process maps  
16. Daily operations checklist  
17. Troubleshooting and escalation  
18. Glossary  
19. Appendix — status reference  

---

## 2. Getting started

### 2.1 Sign in

1. Open the application URL provided by your administrator (path ends with `/app/login`).
2. Enter your work **Email** and **Password**.
3. Optional: tick **Remember me**.
4. Click **Sign in**.

If sign-in fails, confirm the email is correct and ask an administrator to reset your password or verify that your account is active.

### 2.2 Sign out

Use **Sign out** at the bottom of the left sidebar.

### 2.3 Theme

Use the theme toggle (light/dark) on the login screen or in the application header. This only changes appearance; it does not change permissions or data.

### 2.4 What you will see

Menus and action buttons are **permission-based**. Two users can open the same module and see different tabs or buttons. If a menu item is missing, you do not have the required permission — contact your administrator.

---

## 3. Screen layout and navigation

### 3.1 Sidebar

Brand: **Finance** — *Control center*.

| Menu | Purpose |
|------|---------|
| Dashboard | Welcome, charts, quick module links |
| Purchase Requests | Raise and approve purchase requests |
| Procure | Create and manage purchase orders / petty-cash procurements |
| Petty Cash | Reimburse petty cash expenses |
| Assets | Receive procured assets/accessories into Snipe-IT |
| Budgets → Budget Accounts | Accounts, sub-budgets, top-ups, on-hold amounts |
| Budgets → Transfers | Move amount between sub-budgets |
| Budgets → History | Budget transaction timeline |
| Reports → Report Templates | Build and download CSV reports |
| Reports → Dashboard Charts | Configure charts shown on the dashboard |
| Reports → Activity | Who changed what, and when |
| Reports → Email Status | Queued / sent / failed notification emails |
| Master Data | Items, Vendors, Locations, Projects, Departments |
| Configurations → Users / Roles / Profile | Access control and your signature |

On large screens you can collapse the sidebar. On mobile, open it with the menu control.

### 3.2 Common list-screen patterns

- Search and filters at the top of index pages.
- Status tabs on PR, PO, and similar lists (you can pin a preferred tab where supported).
- Create buttons when you have create permission.
- Bulk select + delete where permitted.
- Row actions: view, edit, delete depending on status and permission.

---

## 4. Roles, permissions, and HOD setup

### 4.1 How access works

Access is controlled by **roles**. Each role contains permissions such as view, create, update, delete, and special workflow actions (send for approval, finance approve, MD/DMD approve, close, audit, advance-form actions, petty-cash PV approve, and so on).

The built-in **super_admin** role receives all permissions. Other roles (for example Finance) are configured under **Configurations → Roles**.

### 4.2 Typical business roles (logical)

| Role (business) | Typical duties in the app |
|-----------------|---------------------------|
| Requester | Create/submit PRs and petty cash; maintain own drafts |
| Department HOD | Approve/reject department requests and advance forms for their departments |
| Finance | Finance-approve PRs; manage budgets; finance-approve petty cash |
| MD / DMD | Final PR approval and advance-form MD/DMD approval |
| Procurement | Create/submit POs from MD/DMD-approved PRs; close orders; advance forms |
| Assets / IT | Receive assets and accessories after procurement |
| Administrator | Users, roles, master data, full configuration |

**Important:** Department HOD is not only a permission checkbox. A user must be associated as **HOD of one or more departments** on the user record (**Configurations → Users → edit user → HOD of departments**).

### 4.3 View all PRs

A user flag **View all PRs** (intended for procurement HOD-style visibility) allows seeing non-draft purchase requests across departments, subject to other rules.

### 4.4 Signature

Signatures are printed on purchase-request and advance-form PDFs. Draw and save a signature under **Profile** (or an admin can set it on the user form). Accounts without a signature show **Signature missing**.

---

## 5. Dashboard and profile

### 5.1 Dashboard

- Welcome message with your name.
- Configured charts (those marked show on dashboard).
- Module quick links with short descriptions (filtered by your permissions).

### 5.2 Profile

1. Open **Configurations → Profile**.
2. Review your name and email (read-only).
3. Use **Draw signature**, then **Save signature**.

---

## 6. Purchase Requests

Purchase Requests (PR) are the starting point for procurement. A PR holds purpose, locations, optional project, supporting document, and line items with estimated cost and budget allocation.

### 6.1 Happy-path status flow

**Draft → Submitted → Department HOD Approved → Finance Approved → MD / DMD Approved → Closed**

Alternate outcomes: Department HOD Rejected, Finance Rejected, MD / DMD Rejected, Canceled.  
*Document Uploaded* may appear as a legacy/internal status and is not part of the normal filter set.

### 6.2 Create a purchase request

1. Go to **Purchase Requests → Create**.
2. Set **Date**.
3. Optional: choose **Project**.
4. Optional: upload a supporting **Document**.
5. Enter **Purpose**.
6. Select one or more **Locations**.
7. Add line items: **Item** (search by code/name), **Unit**, **Quantity**, **Est. cost (MVR)**, **Budget** (sub-budget account).
8. Save. Status is **Draft**.

Units include: Kg, Case, Pcs, Ltr, Each, Bottle, Bags, Feet, Meter, and `-`.

**Budget rule:** on submit, estimated costs are held against the requester department’s allocation on the chosen sub-budget. Submit is blocked if there are no lines, no department allocation, or insufficient available budget (allocated − on hold).

### 6.3 Edit and maintain lines

- Full line add/edit/delete while **Draft** (subject to update permission).
- While status is **Department HOD Approved**, Finance may **Change budget** on lines only.
- After **Finance Approved**, editing is restricted by policy.

### 6.4 Approval actions (Show page)

| Button | When | Who |
|--------|------|-----|
| Submit for approval | Draft | Permission: send for approval |
| HOD approve / HOD reject | Submitted | User who is HOD of the requester’s department |
| Send back to draft | Submitted (HOD) or HOD Approved (Finance) | HOD or Finance approve permission |
| Finance approve / Finance reject | HOD Approved | Finance approve permission |
| Cancel PR | HOD Approved | Finance approve permission |
| MD/DMD approve / MD/DMD reject | Finance Approved | MD/DMD approve permission |
| Close PR | MD / DMD Approved | Close permission |
| Delete | Draft | Delete permission |
| PDF | Finance Approved or MD/DMD Approved | Requester with send-for-approval (typical) |
| Audit | Any stage (if permitted) | Audit permission |

- Reject, cancel, and send-back require a **remark**.
- **Close PR** may collect **GRN numbers** for related open purchase orders.

### 6.5 Index tabs (role-aware)

- **Requester:** All, Draft, Awaiting Approval, Pending Procurement, Completed, Rejected  
- **HOD:** All, Awaiting Approval, Rejected, Pending Procurement, Completed  
- **Finance:** All, Pending Approval, Rejected, Approved, Completed  
- **MD/DMD:** All, Pending Approval, MD / DMD Approved, Rejected, Completed  

### 6.6 Visibility rules (summary)

- Requesters typically see their own PRs.
- Department HODs see PRs for departments they head.
- Finance sees non-draft PRs in the finance path.
- MD/DMD sees PRs from finance-approved stage onward.
- Procurement-focused users see MD/DMD Approved and Closed for buying.
- **super_admin** sees all.

### 6.7 Show page summary fields

PR number, Date, Requester, Department, Locations, Status, Project, approvers (HOD / Finance / MD/DMD), and budget summary (allocated / on hold / this PR / available).

---

## 7. Procure (Purchase Orders)

Procure creates purchase orders (or petty-cash procurements) against **MD / DMD Approved** purchase requests.

### 7.1 Payment methods

- **Purchase Order** — formal PO number; optional advance form; close with GRN.
- **Petty Cash** — record number; upload receipt; close moves to **Pending Reimbursement** until petty-cash finance reimbursement completes.

### 7.2 PO statuses

| Status | Meaning |
|--------|---------|
| Draft | Being prepared |
| Submitted | Submitted for fulfillment / further processing |
| GRN Created | Goods receipt related status |
| Pending Reimbursement | Petty-cash path awaiting reimbursement |
| Reimbursed | Linked petty-cash reimbursement completed |
| Closed | Order closed (purchase-order path) |

### 7.3 Create a purchase order

1. Open **Procure → Create**.
2. Choose payment method: **Purchase Order** or **Petty Cash**.
3. Select **Vendor** and **Date**.
4. Search and select an MD/DMD approved **Purchase request**.
5. For PO: enter **PO number**; optionally tick **Advance form required**.
6. For Petty Cash: record number per form rules.
7. Complete lines from PR items: unit, quantity, **GST %**, **Unit price (MVR)**, tax amount.
8. Save as Draft, then **Submit order** when ready.

### 7.4 Key actions on a PO

| Action | Notes |
|--------|-------|
| Submit order | From Draft |
| Upload receipt | Petty cash method; before close |
| Close order | PO: requires GRN → Closed; Petty cash: receipt required → Pending Reimbursement |
| Generate / Regenerate advance form | PO with advance required, Submitted, advance still draft |
| Submit advance form | By the user who generated it |
| HOD approve / reject advance | HOD of generator’s department |
| MD/DMD approve / reject advance | Requires MD/DMD advance-form permission |
| Edit/delete lines | Draft only |
| Audit | If permitted |

### 7.5 Advance form

When generating an advance form, provide:

- Quotation number  
- Expected delivery (days)  
- Advance amount %  

Statuses: **Draft → Submitted → HOD Approved → DMD / MD Approved** (or HOD / MD rejected). PDFs are available from the PO show page when generated.

### 7.6 Closing and budgets

- Closing a **Purchase Order** with GRN syncs asset receipts and deducts budget via system observers.
- Closing a **Petty Cash** procurement does **not** deduct at close; deduction happens when Finance approves the related petty-cash reimbursement (with PV numbers).

---

## 8. Petty Cash reimbursements

### 8.1 Status flow

**Draft → Submitted → Department approved → (PV numbers) → Finance approved → Reimbursed**

Reject paths: Department rejected; Finance rejected (returns toward draft / clears PV as configured).

### 8.2 Create and submit

1. Open **Petty Cash → Create**.
2. Set **Date** and attach supporting documents as required.
3. Add lines: Date, optional PO (pending reimbursement), Item, Vendor, Bill no, Details, Sub-budget, Amount (MVR).
4. Save, then **Submit**.

### 8.3 Approval chain

| Step | Button | Who |
|------|--------|-----|
| 1 | Submit | Requester / updater |
| 2 | Department approve / reject | HOD of requester’s department |
| 3 | Add PV numbers | User with PV approve permission |
| 4 | Finance approve / reject | User with Finance HOD approve permission |

Finance approve requires **PV numbers**. On success the system deducts department budgets, marks linked POs **Reimbursed**, sets status to **Reimbursed**, and makes the PDF available.

---

## 9. Asset Management

After procurement, **Assets** receives lines that are Item type **Asset** or **Accessory** into Snipe-IT. Items typed **Other** do not sync for receiving.

### 9.1 List and filters

- Orders show **Pending** or **Completed** at order level.
- Receipt lines: **Pending** / **Received**.
- Filters: All / Pending / Received; All types / Assets / Accessories.

### 9.2 Receive an asset

1. Open **Assets** and select the purchase order.
2. Click **Receive item** on a pending asset line (or use bulk receive).
3. Complete fields: Status, Model (Snipe), Asset name, Serial (use **Check**), Location, Order number, Purchase date, Purchase cost, Supplier, **CAO asset code** (required), optional Finance old asset tag, Asset class, MAC address, Notes.
4. Click **Mark received**. Asset tag is assigned by Snipe-IT.

### 9.3 Receive an accessory

Use **Receive accessory**. Typical fields: Accessory name, Category, Quantity, Location, Order number, Purchase date/cost, Supplier, Model number, Notes.

---

## 10. Budgets

### 10.1 Budget Accounts

Create accounts with **Expenditure type**, **Account**, and **Name**.

On the account edit screen manage **Sub budget accounts**:

- Columns: Code, Name, Display name, Allocations (department / location : amount), Allocated, **On hold**, Usage %, Actions.
- **On hold** is clickable and lists purchase requests holding budget.
- **Add sub budget:** Code, Name, Display name, Department allocations (Department, Location, Amount).
- **Top Up Account:** enter new amounts per allocation and **Apply top up**.
- Bulk delete sub-budgets when permitted.

### 10.2 Transfers

1. Open **Budgets → Transfers → Create**.
2. Choose **From** sub-budget and **To** sub-budget.
3. Enter **Amount (MVR)** and **Reason**.
4. Review before/after balances, then save.

### 10.3 History

**Budgets → History** shows a day-grouped timeline of top-ups, transfers, deductions, and related events. Use tabs/filters by transaction type, search, and date as available.

### 10.4 Budget hold rule (PR)

Unutilized PR line estimated costs are held while the PR is in active approval / procurement-ready statuses (for example Submitted, HOD Approved, Finance Approved, MD/DMD Approved).  

**Available budget = allocated − on hold** for the department allocation.

---

## 11. Master data

| Module | Key fields | Notes |
|--------|------------|-------|
| Items | Item code, Name, Type (Asset / Accessory / Other) | Asset/Accessory sync to Snipe-IT |
| Vendors | Name, Address, Account no, Mobile, GST no, Bank | Used on POs and petty cash |
| Locations | Name | Used on PRs and allocations |
| Projects | Name | Optional on PRs |
| Departments | Name, Petty cash float amount | Assign HOD on the User screen |

Standard pattern: Index list → Create / Edit / Delete (and bulk delete where enabled).

---

## 12. Reports and dashboard charts

### 12.1 Report Templates

1. Open **Reports → Report Templates → Create**.
2. Enter **Name** and **Description**.
3. Choose a data source: Purchase Requests, Purchase Orders, Petty Cash Reimbursements, Sub Budget Accounts, Budget Transactions, or Advance Forms.
4. Select columns (optional CSV heading overrides).
5. Add filters (Equals, Contains, In list, date ranges, and similar).
6. Save, then **Download** CSV when needed.

### 12.2 Dashboard Charts

1. Open **Reports → Dashboard Charts → Create**.
2. Choose model / data set.
3. Group by a field (dates can group by month/year).
4. Choose aggregation: How many, sum, or average.
5. Choose chart type: Bars, line, pie, or doughnut.
6. Optional filters; enable **show on dashboard**.
7. Save. Charts appear on the Dashboard for users who can view them.

---

## 13. Activity log and email status

### 13.1 Activity

**Reports → Activity** shows who changed records, signed in, and when. Entries are grouped by day with event badges, user, and expandable field changes. Record-level **Audit** pages also exist on Purchase Requests and Purchase Orders.

### 13.2 Email Status

**Reports → Email Status** tracks notification emails with tabs such as Pending, Sent, and Failed. Use this when someone says they did not receive an approval email.

---

## 14. Users and roles administration

### 14.1 Users

**Configurations → Users.** Create/edit fields typically include:

- Name, Designation, Department, Location  
- Email, Mobile  
- Password / Confirm password  
- View all PRs  
- Roles (multi-select)  
- Petty cash assigned  
- Bank account name / number  
- Draw signature  
- HOD of departments (associate / dissociate)  

Filter users by department, role, and signature presence.

### 14.2 Roles

1. Open **Configurations → Roles → Create or Edit**.
2. Set the role **Name**.
3. Tick permissions by group. Use **Select all** or search as needed.
4. Save, then assign the role to users.

---

## 15. End-to-end process maps

### 15.1 Standard procurement (Purchase Order)

1. Requester creates and submits PR.  
2. Department HOD approves.  
3. Finance approves (may adjust line budgets while HOD Approved).  
4. MD/DMD approves.  
5. Procurement creates PO (method Purchase Order) from the PR and submits.  
6. If needed: generate and approve advance form (HOD → MD/DMD).  
7. Close PO with GRN → Closed; assets become receivable; budgets deduct.  
8. Assets team receives Asset/Accessory lines in **Assets**.  
9. Close the PR when procurement is complete (GRN collection as prompted).  

### 15.2 Petty-cash procurement and reimbursement

1. PR follows the same approval path through MD/DMD Approved.  
2. Procurement creates a Procure record with payment method **Petty Cash** and submits.  
3. Upload receipt; **Close order** → Pending Reimbursement.  
4. Create **Petty Cash** reimbursement lines (optionally linking the PO).  
5. Department HOD approves → **Add PV numbers** → Finance approves → **Reimbursed**.  
6. Linked PO becomes Reimbursed; budgets deduct on finance approval.  

---

## 16. Daily operations checklist

1. Clear Submitted / Awaiting Approval queues for PR and Petty Cash (HOD first).  
2. Finance processes HOD-approved PRs and department-approved petty cash (with PV).  
3. MD/DMD clears Finance Approved PRs and advance forms waiting for final approval.  
4. Procurement creates POs only from MD/DMD Approved PRs.  
5. Close POs with correct GRN / receipt before expecting budget and asset updates.  
6. Assets receives pending Asset/Accessory lines promptly; use serial **Check** to avoid duplicates.  
7. Review Budget Accounts **On hold** and History for unexpected holds or deductions.  
8. Check **Email Status** for Failed notifications when users report missing alerts.  
9. Ensure new users have roles, department, HOD links (if applicable), and signatures.  

---

## 17. Troubleshooting and escalation

| Symptom | Likely cause / what to check |
|---------|------------------------------|
| Menu item missing | Role lacks view permission |
| Cannot submit PR | No lines; insufficient available budget; missing department allocation on sub-budget |
| HOD buttons missing | User not associated as HOD of the requester’s department |
| Cannot create PO from PR | PR not yet MD / DMD Approved |
| Cannot close PO (petty cash) | Receipt not uploaded |
| Cannot finance-approve petty cash | PV numbers not added |
| PDF looks wrong / no signature | User signature missing on Profile |
| Asset not listed for receiving | Item type is Other, or PO not ready/closed path incomplete |
| Email not received | Reports → Email Status (Failed/Pending); spam filters |

### 17.1 Escalation format

When contacting support, include:

1. Module name (Purchase Request / Procure / Petty Cash / Assets / Budgets / other)  
2. Record number or ID  
3. Your user email and role  
4. Action attempted  
5. Exact error message or screenshot  
6. Date and time  

---

## 18. Glossary

| Term | Meaning |
|------|---------|
| PR | Purchase Request |
| PO / Procure | Purchase Order module and records |
| HOD | Head of Department — approves department requests |
| MD / DMD | Managing Director / Deputy Managing Director final approval |
| PV | Payment voucher number(s) required before petty-cash finance approval |
| GRN | Goods Received Note number used when closing POs / PRs |
| Sub-budget | Detailed budget bucket under a budget account, with department/location allocations |
| On hold | Estimated PR cost reserved against an allocation before final deduction |
| MVR | Maldivian Rufiyaa — currency used in the UI |
| Snipe-IT | External asset inventory system used for receiving |
| Advance form | Optional advance-payment form linked to a PO |

---

## 19. Appendix — status reference

### 19.1 Purchase Request statuses

| Status label | Typical meaning |
|--------------|-----------------|
| Draft | Editable; not in approval |
| Submitted | Awaiting department HOD |
| Department HOD Approved | Awaiting Finance |
| Department HOD Rejected | Rejected by HOD |
| Finance Approved | Awaiting MD/DMD |
| Finance Rejected | Rejected by Finance |
| MD / DMD Approved | Ready for procurement |
| MD / DMD Rejected | Rejected by MD/DMD |
| Canceled | Canceled (finance path) |
| Closed | Completed / closed |

### 19.2 Purchase Order statuses

| Status label | Typical meaning |
|--------------|-----------------|
| Draft | Being prepared |
| Submitted | Active order |
| GRN Created | GRN-related state |
| Pending Reimbursement | Awaiting petty-cash reimbursement |
| Reimbursed | Reimbursement completed |
| Closed | Order closed |

### 19.3 Petty Cash statuses

| Status label | Typical meaning |
|--------------|-----------------|
| Draft | Editable |
| Submitted | Awaiting department HOD |
| Department approved | Awaiting PV + Finance |
| Department rejected | Rejected by department |
| Finance approved | Intermediate finance state (as shown in UI) |
| Finance rejected | Rejected by finance |
| Reimbursed | Complete; budgets posted |

### 19.4 Advance form statuses

| Status label | Typical meaning |
|--------------|-----------------|
| Draft | Generated, not submitted |
| Submitted | Awaiting HOD |
| HOD Approved | Awaiting MD/DMD |
| HOD Rejected | Rejected by HOD |
| DMD / MD Approved | Advance approved |
| DMD / MD Rejected | Rejected by MD/DMD |

---

*End of manual — Source: Finance Control Center application workflows and screens. Update this document when statuses, permissions, or screens change.*
