"""Generate Finance Control Center User Manual (.docx)."""

from datetime import date
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


OUT = Path(__file__).with_name("Finance_Control_Center_User_Manual.docx")


def set_style(doc: Document) -> None:
    style = doc.styles["Normal"]
    style.font.name = "Calibri"
    style.font.size = Pt(11)
    style._element.rPr.rFonts.set(qn("w:eastAsia"), "Calibri")
    for i in range(1, 4):
        h = doc.styles[f"Heading {i}"]
        h.font.color.rgb = RGBColor(0x0F, 0x17, 0x2A)
        h.font.name = "Calibri"


def add_title(doc: Document) -> None:
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run("Finance Control Center")
    run.bold = True
    run.font.size = Pt(28)
    run.font.color.rgb = RGBColor(0x0F, 0x17, 0x2A)

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run("User Manual")
    run.bold = True
    run.font.size = Pt(20)

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run(f"Version 2.0  ·  {date.today().isoformat()}  ·  Editable document").italic = True

    doc.add_paragraph(
        "This manual explains how to use the Finance Control Center for purchase requests, "
        "procurement (purchase orders), petty cash reimbursements, budgets, asset receiving, "
        "reports, and user administration. Currency amounts in the application are shown in MVR."
    )
    doc.add_paragraph(
        "Tip: Edit this Word document directly, or edit the companion Markdown file "
        "(Finance_Control_Center_User_Manual.md) and regenerate the .docx with "
        "python docs/generate_user_manual.py if you prefer Markdown as the source."
    )


def h1(doc, text):
    doc.add_heading(text, level=1)


def h2(doc, text):
    doc.add_heading(text, level=2)


def h3(doc, text):
    doc.add_heading(text, level=3)


def p(doc, text):
    doc.add_paragraph(text)


def bullets(doc, items, style="List Bullet"):
    for item in items:
        doc.add_paragraph(item, style=style)


def numbered(doc, items):
    for item in items:
        doc.add_paragraph(item, style="List Number")


def table(doc, headers, rows):
    t = doc.add_table(rows=1 + len(rows), cols=len(headers))
    t.style = "Table Grid"
    for i, header in enumerate(headers):
        cell = t.rows[0].cells[i]
        cell.text = header
        for paragraph in cell.paragraphs:
            for run in paragraph.runs:
                run.bold = True
    for r_idx, row in enumerate(rows):
        for c_idx, value in enumerate(row):
            t.rows[r_idx + 1].cells[c_idx].text = str(value)
    doc.add_paragraph()


def build() -> None:
    doc = Document()
    set_style(doc)
    section = doc.sections[0]
    section.top_margin = Inches(0.9)
    section.bottom_margin = Inches(0.9)
    section.left_margin = Inches(1)
    section.right_margin = Inches(1)

    add_title(doc)
    doc.add_page_break()

    # TOC-style outline
    h1(doc, "1. Contents")
    bullets(
        doc,
        [
            "2. Getting started",
            "3. Screen layout and navigation",
            "4. Roles, permissions, and HOD setup",
            "5. Dashboard and profile",
            "6. Purchase Requests",
            "7. Procure (Purchase Orders)",
            "8. Petty Cash reimbursements",
            "9. Asset Management",
            "10. Budgets (accounts, transfers, history)",
            "11. Master data",
            "12. Reports and dashboard charts",
            "13. Activity log and email status",
            "14. Users and roles administration",
            "15. End-to-end process maps",
            "16. Daily operations checklist",
            "17. Troubleshooting and escalation",
            "18. Glossary",
            "19. Appendix — status reference",
        ],
    )

    # 2 Getting started
    h1(doc, "2. Getting started")
    h2(doc, "2.1 Sign in")
    numbered(
        doc,
        [
            "Open the application URL provided by your administrator (path ends with /app/login).",
            "Enter your work email and password.",
            "Optional: tick Remember me.",
            "Click Sign in.",
        ],
    )
    p(
        doc,
        "If sign-in fails, confirm the email is correct and ask an administrator to reset your password "
        "or verify that your account is active.",
    )

    h2(doc, "2.2 Sign out")
    p(doc, "Use Sign out at the bottom of the left sidebar.")

    h2(doc, "2.3 Theme")
    p(
        doc,
        "Use the theme toggle (light/dark) on the login screen or in the application header. "
        "This only changes appearance; it does not change permissions or data.",
    )

    h2(doc, "2.4 What you will see")
    p(
        doc,
        "Menus and action buttons are permission-based. Two users can open the same module "
        "and see different tabs or buttons. If a menu item is missing, you do not have the "
        "required permission — contact your administrator.",
    )

    # 3 Layout
    h1(doc, "3. Screen layout and navigation")
    h2(doc, "3.1 Sidebar")
    p(doc, "Brand: Finance — Control center. Primary links and grouped modules:")
    table(
        doc,
        ["Menu", "Purpose"],
        [
            ["Dashboard", "Welcome, charts, quick module links"],
            ["Purchase Requests", "Raise and approve purchase requests"],
            ["Procure", "Create and manage purchase orders / petty-cash procurements"],
            ["Petty Cash", "Reimburse petty cash expenses"],
            ["Assets", "Receive procured assets/accessories into Snipe-IT"],
            ["Budgets → Budget Accounts", "Accounts, sub-budgets, top-ups, on-hold amounts"],
            ["Budgets → Transfers", "Move amount between sub-budgets"],
            ["Budgets → History", "Budget transaction timeline"],
            ["Reports → Report Templates", "Build and download CSV reports"],
            ["Reports → Dashboard Charts", "Configure charts shown on the dashboard"],
            ["Reports → Activity", "Who changed what, and when"],
            ["Reports → Email Status", "Queued / sent / failed notification emails"],
            ["Master Data", "Items, Vendors, Locations, Projects, Departments"],
            ["Configurations → Users / Roles / Profile", "Access control and your signature"],
        ],
    )
    p(
        doc,
        "On large screens you can collapse the sidebar. On mobile, open it with the menu control.",
    )

    h2(doc, "3.2 Common list-screen patterns")
    bullets(
        doc,
        [
            "Search and filters at the top of index pages.",
            "Status tabs on PR, PO, and similar lists (you can pin a preferred tab where supported).",
            "Create buttons when you have create permission.",
            "Bulk select + delete where delete_any (or equivalent) is granted.",
            "Row actions: view, edit, delete depending on status and permission.",
        ],
    )

    # 4 Roles
    h1(doc, "4. Roles, permissions, and HOD setup")
    h2(doc, "4.1 How access works")
    p(
        doc,
        "Access is controlled by roles. Each role contains permissions such as view, create, update, "
        "delete, and special workflow actions (submit for approval, finance approve, MD/DMD approve, "
        "close, audit, advance-form actions, petty-cash PV approve, and so on).",
    )
    p(
        doc,
        "The built-in super_admin role receives all permissions. Other roles (for example Finance) "
        "are configured by administrators under Configurations → Roles.",
    )

    h2(doc, "4.2 Typical business roles (logical)")
    table(
        doc,
        ["Role (business)", "Typical duties in the app"],
        [
            ["Requester", "Create/submit PRs and petty cash; maintain own drafts"],
            ["Department HOD", "Approve/reject department requests and advance forms for their departments"],
            ["Finance", "Finance-approve PRs; manage budgets; finance-approve petty cash"],
            ["MD / DMD", "Final PR approval and advance-form MD/DMD approval"],
            ["Procurement", "Create/submit POs from MD/DMD-approved PRs; close orders; advance forms"],
            ["Assets / IT", "Receive assets and accessories after procurement"],
            ["Administrator", "Users, roles, master data, full configuration"],
        ],
    )
    p(
        doc,
        "Important: Department HOD is not only a permission checkbox. A user must be associated as "
        "HOD of one or more departments on the user record (Configurations → Users → edit user → "
        "HOD of departments).",
    )

    h2(doc, "4.3 View all PRs")
    p(
        doc,
        "A user flag View all PRs (intended for procurement HOD-style visibility) allows seeing "
        "non-draft purchase requests across departments, subject to other rules.",
    )

    h2(doc, "4.4 Signature")
    p(
        doc,
        "Signatures are printed on purchase-request and advance-form PDFs. Users should draw and "
        "save a signature under Profile (or an admin can set it on the user form). Accounts without "
        "a signature show Signature missing.",
    )

    # 5 Dashboard / Profile
    h1(doc, "5. Dashboard and profile")
    h2(doc, "5.1 Dashboard")
    bullets(
        doc,
        [
            "Welcome message with your name.",
            "Configured charts (those marked show on dashboard).",
            "Module quick links with short descriptions (filtered by your permissions).",
        ],
    )

    h2(doc, "5.2 Profile")
    numbered(
        doc,
        [
            "Open Configurations → Profile (or Profile in the sidebar group).",
            "Review your name and email (read-only).",
            "Use Draw signature, then Save signature.",
        ],
    )

    # 6 Purchase Requests
    h1(doc, "6. Purchase Requests")
    p(
        doc,
        "Purchase Requests (PR) are the starting point for procurement. A PR holds purpose, "
        "locations, optional project, supporting document, and line items with estimated cost "
        "and budget allocation. Amounts are in MVR.",
    )

    h2(doc, "6.1 Happy-path status flow")
    p(
        doc,
        "Draft → Submitted → Department HOD Approved → Finance Approved → MD / DMD Approved → Closed",
    )
    p(
        doc,
        "Alternate outcomes: Department HOD Rejected, Finance Rejected, MD / DMD Rejected, Canceled. "
        "Document Uploaded may appear as a legacy/internal status and is not part of the normal "
        "filter set.",
    )

    h2(doc, "6.2 Create a purchase request")
    numbered(
        doc,
        [
            "Go to Purchase Requests → Create (or equivalent New button).",
            "Set Date.",
            "Optional: choose Project.",
            "Optional: upload a supporting Document.",
            "Enter Purpose.",
            "Select one or more Locations.",
            "Add line items: Item (search by code/name), Unit, Quantity, Est. cost (MVR), Budget (sub-budget account).",
            "Save. Status is Draft.",
        ],
    )
    p(doc, "Units available include Kg, Case, Pcs, Ltr, Each, Bottle, Bags, Feet, Meter, and “-”.")
    p(
        doc,
        "Budget rule: on submit, estimated costs are held against the requester department’s "
        "allocation on the chosen sub-budget. Submit is blocked if there are no lines, no department "
        "allocation, or insufficient available budget (allocated minus on hold).",
    )

    h2(doc, "6.3 Edit and maintain lines")
    bullets(
        doc,
        [
            "Full line add/edit/delete while Draft (subject to update permission).",
            "While status is Department HOD Approved, Finance may Change budget on lines only.",
            "After Finance Approved, editing is restricted by policy.",
        ],
    )

    h2(doc, "6.4 Approval actions (Show page)")
    table(
        doc,
        ["Button", "When", "Who"],
        [
            ["Submit for approval", "Draft", "Permission: send for approval"],
            ["HOD approve / HOD reject", "Submitted", "User who is HOD of the requester’s department"],
            ["Send back to draft", "Submitted (HOD) or HOD Approved (Finance)", "HOD or Finance approve permission"],
            ["Finance approve / Finance reject", "HOD Approved", "Finance approve permission"],
            ["Cancel PR", "HOD Approved", "Finance approve permission"],
            ["MD/DMD approve / MD/DMD reject", "Finance Approved", "MD/DMD approve permission"],
            ["Close PR", "MD / DMD Approved", "Close permission"],
            ["Delete", "Draft", "Delete permission"],
            ["PDF", "Finance Approved or MD/DMD Approved", "Requester with send-for-approval (typical)"],
            ["Audit", "Any stage (if permitted)", "Audit permission"],
        ],
    )
    bullets(
        doc,
        [
            "Reject, cancel, and send-back require a remark.",
            "Close PR may collect GRN numbers for related open purchase orders.",
        ],
    )

    h2(doc, "6.5 Index tabs (role-aware)")
    p(doc, "Tabs change with your role so you see the work that needs you:")
    bullets(
        doc,
        [
            "Requester: All, Draft, Awaiting Approval, Pending Procurement, Completed, Rejected",
            "HOD: All, Awaiting Approval, Rejected, Pending Procurement, Completed",
            "Finance: All, Pending Approval, Rejected, Approved, Completed",
            "MD/DMD: All, Pending Approval, MD / DMD Approved, Rejected, Completed",
        ],
    )

    h2(doc, "6.6 Visibility rules (summary)")
    bullets(
        doc,
        [
            "Requesters typically see their own PRs.",
            "Department HODs see PRs for departments they head.",
            "Finance sees non-draft PRs in the finance path.",
            "MD/DMD sees PRs from finance-approved stage onward.",
            "Procurement-focused users see MD/DMD Approved and Closed for buying.",
            "super_admin sees all.",
        ],
    )

    h2(doc, "6.7 Show page summary fields")
    p(
        doc,
        "Typical summary: PR number, Date, Requester, Department, Locations, Status, Project, "
        "approvers (HOD / Finance / MD/DMD), and budget summary (allocated / on hold / this PR / available).",
    )

    # 7 Procure
    h1(doc, "7. Procure (Purchase Orders)")
    p(
        doc,
        "Procure creates purchase orders (or petty-cash procurements) against MD / DMD Approved "
        "purchase requests.",
    )

    h2(doc, "7.1 Payment methods")
    bullets(
        doc,
        [
            "Purchase Order — formal PO number; optional advance form; close with GRN.",
            "Petty Cash — record number; upload receipt; close moves to Pending Reimbursement until petty-cash finance reimbursement completes.",
        ],
    )

    h2(doc, "7.2 PO statuses")
    table(
        doc,
        ["Status", "Meaning"],
        [
            ["Draft", "Being prepared"],
            ["Submitted", "Submitted for fulfillment / further processing"],
            ["GRN Created", "Goods receipt related status"],
            ["Pending Reimbursement", "Petty-cash path awaiting reimbursement"],
            ["Reimbursed", "Linked petty-cash reimbursement completed"],
            ["Closed", "Order closed (purchase-order path)"],
        ],
    )

    h2(doc, "7.3 Create a purchase order")
    numbered(
        doc,
        [
            "Open Procure → Create.",
            "Choose payment method: Purchase Order or Petty Cash.",
            "Select Vendor and Date.",
            "Search and select an MD/DMD approved Purchase request.",
            "For PO: enter PO number; optionally tick Advance form required.",
            "For Petty Cash: record number is generated/entered per form rules.",
            "Complete lines from PR items: unit, quantity, GST %, Unit price (MVR), tax amount.",
            "Save as Draft, then Submit order when ready.",
        ],
    )

    h2(doc, "7.4 Key actions on a PO")
    table(
        doc,
        ["Action", "Notes"],
        [
            ["Submit order", "From Draft"],
            ["Upload receipt", "Petty cash method; before close"],
            ["Close order", "PO: requires GRN → Closed; Petty cash: receipt required → Pending Reimbursement"],
            ["Generate / Regenerate advance form", "PO with advance required, Submitted, advance still draft"],
            ["Submit advance form", "By the user who generated it"],
            ["HOD approve / reject advance", "HOD of generator’s department"],
            ["MD/DMD approve / reject advance", "Requires MD/DMD advance-form permission"],
            ["Edit/delete lines", "Draft only"],
            ["Audit", "If permitted"],
        ],
    )

    h2(doc, "7.5 Advance form")
    p(doc, "When generating an advance form, provide:")
    bullets(
        doc,
        [
            "Quotation number",
            "Expected delivery (days)",
            "Advance amount %",
        ],
    )
    p(
        doc,
        "Advance form statuses: Draft → Submitted → HOD Approved → DMD / MD Approved "
        "(or HOD / MD rejected). PDFs are available from the PO show page when generated.",
    )

    h2(doc, "7.6 Closing and budgets")
    bullets(
        doc,
        [
            "Closing a Purchase Order (method) with GRN syncs asset receipts and deducts budget via system observers.",
            "Closing a Petty Cash procurement does not deduct at close; deduction happens when Finance approves the related petty-cash reimbursement (with PV numbers).",
        ],
    )

    # 8 Petty cash
    h1(doc, "8. Petty Cash reimbursements")
    h2(doc, "8.1 Status flow")
    p(
        doc,
        "Draft → Submitted → Department approved → (PV numbers) → Finance approved → Reimbursed",
    )
    p(doc, "Reject paths: Department rejected; Finance rejected (returns toward draft / clears PV as configured).")

    h2(doc, "8.2 Create and submit")
    numbered(
        doc,
        [
            "Open Petty Cash → Create.",
            "Set Date and attach supporting documents as required.",
            "Add lines: Date, optional PO (pending reimbursement), Item, Vendor, Bill no, Details, Sub-budget, Amount (MVR).",
            "Save, then Submit.",
        ],
    )

    h2(doc, "8.3 Approval chain")
    table(
        doc,
        ["Step", "Button", "Who"],
        [
            ["1", "Submit", "Requester / updater"],
            ["2", "Department approve / reject", "HOD of requester’s department"],
            ["3", "Add PV numbers", "User with PV approve permission"],
            ["4", "Finance approve / reject", "User with Finance HOD approve permission"],
        ],
    )
    p(
        doc,
        "Finance approve requires PV numbers. On success the system deducts department budgets, "
        "marks linked POs Reimbursed, sets status to Reimbursed, and makes the PDF available.",
    )

    # 9 Assets
    h1(doc, "9. Asset Management")
    p(
        doc,
        "After procurement, Assets receives lines that are Item type Asset or Accessory into "
        "Snipe-IT. Items typed Other do not sync for receiving.",
    )
    h2(doc, "9.1 List and filters")
    bullets(
        doc,
        [
            "Orders show Pending or Completed at order level.",
            "Receipt lines: Pending / Received.",
            "Filters: All / Pending / Received; All types / Assets / Accessories.",
        ],
    )
    h2(doc, "9.2 Receive an asset")
    numbered(
        doc,
        [
            "Open Assets and select the purchase order.",
            "Click Receive item on a pending asset line (or use bulk receive).",
            "Complete fields: Status, Model (Snipe), Asset name, Serial (use Check), Location, Order number, Purchase date, Purchase cost, Supplier, CAO asset code (required), optional Finance old asset tag, Asset class, MAC address, Notes.",
            "Click Mark received. Asset tag is assigned by Snipe-IT.",
        ],
    )
    h2(doc, "9.3 Receive an accessory")
    p(
        doc,
        "Use Receive accessory. Typical fields: Accessory name, Category, Quantity, Location, "
        "Order number, Purchase date/cost, Supplier, Model number, Notes.",
    )

    # 10 Budgets
    h1(doc, "10. Budgets")
    h2(doc, "10.1 Budget Accounts")
    p(doc, "Create accounts with Expenditure type, Account, and Name.")
    p(doc, "On the account edit screen manage Sub budget accounts:")
    bullets(
        doc,
        [
            "Columns: Code, Name, Display name, Allocations (department / location : amount), Allocated, On hold, Usage %, Actions.",
            "On hold is clickable and lists purchase requests holding budget.",
            "Add sub budget: Code, Name, Display name, Department allocations (Department, Location, Amount).",
            "Top Up Account: enter new amounts per allocation and Apply top up.",
            "Bulk delete sub-budgets when permitted.",
        ],
    )

    h2(doc, "10.2 Transfers")
    numbered(
        doc,
        [
            "Open Budgets → Transfers → Create.",
            "Choose From sub-budget and To sub-budget.",
            "Enter Amount (MVR) and Reason.",
            "Review before/after balances, then save.",
        ],
    )

    h2(doc, "10.3 History")
    p(
        doc,
        "Budgets → History shows a day-grouped timeline of top-ups, transfers, deductions, and "
        "related events. Use tabs/filters by transaction type, search, and date as available.",
    )

    h2(doc, "10.4 Budget hold rule (PR)")
    p(
        doc,
        "Unutilized PR line estimated costs are held while the PR is in active approval / "
        "procurement-ready statuses (for example Submitted, HOD Approved, Finance Approved, "
        "MD/DMD Approved). Available budget = allocated − on hold for the department allocation.",
    )

    # 11 Master data
    h1(doc, "11. Master data")
    table(
        doc,
        ["Module", "Key fields", "Notes"],
        [
            ["Items", "Item code, Name, Type (Asset / Accessory / Other)", "Asset/Accessory sync to Snipe-IT"],
            ["Vendors", "Name, Address, Account no, Mobile, GST no, Bank", "Used on POs and petty cash"],
            ["Locations", "Name", "Used on PRs and allocations"],
            ["Projects", "Name", "Optional on PRs"],
            ["Departments", "Name, Petty cash float amount", "Assign HOD on the User screen"],
        ],
    )
    p(doc, "Standard pattern: Index list → Create / Edit / Delete (and bulk delete where enabled).")

    # 12 Reports
    h1(doc, "12. Reports and dashboard charts")
    h2(doc, "12.1 Report Templates")
    numbered(
        doc,
        [
            "Open Reports → Report Templates → Create.",
            "Enter Name and Description.",
            "Choose a data source: Purchase Requests, Purchase Orders, Petty Cash Reimbursements, Sub Budget Accounts, Budget Transactions, or Advance Forms.",
            "Select columns (optional CSV heading overrides).",
            "Add filters (Equals, Contains, In list, date ranges, and similar).",
            "Save, then Download CSV when needed.",
        ],
    )

    h2(doc, "12.2 Dashboard Charts")
    numbered(
        doc,
        [
            "Open Reports → Dashboard Charts → Create.",
            "Choose model / data set.",
            "Group by a field (dates can group by month/year).",
            "Choose aggregation: How many, sum, or average.",
            "Choose chart type: Bars, line, pie, or doughnut.",
            "Optional filters; enable show on dashboard.",
            "Save. Charts appear on the Dashboard for users who can view them.",
        ],
    )

    # 13 Activity / Emails
    h1(doc, "13. Activity log and email status")
    h2(doc, "13.1 Activity")
    p(
        doc,
        "Reports → Activity shows who changed records, signed in, and when. Entries are grouped by day "
        "with event badges, user, and expandable field changes. Record-level Audit pages also exist "
        "on Purchase Requests and Purchase Orders.",
    )

    h2(doc, "13.2 Email Status")
    p(
        doc,
        "Reports → Email Status tracks notification emails with tabs such as Pending, Sent, and Failed. "
        "Use this when someone says they did not receive an approval email.",
    )

    # 14 Admin
    h1(doc, "14. Users and roles administration")
    h2(doc, "14.1 Users")
    p(doc, "Configurations → Users. Create/edit fields typically include:")
    bullets(
        doc,
        [
            "Name, Designation, Department, Location",
            "Email, Mobile",
            "Password / Confirm password",
            "View all PRs",
            "Roles (multi-select)",
            "Petty cash assigned",
            "Bank account name / number",
            "Draw signature",
            "HOD of departments (associate / dissociate)",
        ],
    )
    p(doc, "Filter users by department, role, and signature presence.")

    h2(doc, "14.2 Roles")
    numbered(
        doc,
        [
            "Open Configurations → Roles → Create or Edit.",
            "Set the role Name.",
            "Tick permissions by group. Use Select all or search as needed.",
            "Save, then assign the role to users.",
        ],
    )

    # 15 Process maps
    h1(doc, "15. End-to-end process maps")
    h2(doc, "15.1 Standard procurement (Purchase Order)")
    numbered(
        doc,
        [
            "Requester creates and submits PR.",
            "Department HOD approves.",
            "Finance approves (may adjust line budgets while HOD Approved).",
            "MD/DMD approves.",
            "Procurement creates PO (method Purchase Order) from the PR and submits.",
            "If needed: generate and approve advance form (HOD → MD/DMD).",
            "Close PO with GRN → Closed; assets become receivable; budgets deduct.",
            "Assets team receives Asset/Accessory lines in Assets.",
            "Close the PR when procurement is complete (GRN collection as prompted).",
        ],
    )

    h2(doc, "15.2 Petty-cash procurement and reimbursement")
    numbered(
        doc,
        [
            "PR follows the same approval path through MD/DMD Approved.",
            "Procurement creates a Procure record with payment method Petty Cash and submits.",
            "Upload receipt; Close order → Pending Reimbursement.",
            "Requester (or authorized user) creates Petty Cash reimbursement lines (optionally linking the PO).",
            "Department HOD approves → Add PV numbers → Finance approves → Reimbursed.",
            "Linked PO becomes Reimbursed; budgets deduct on finance approval.",
        ],
    )

    # 16 Checklist
    h1(doc, "16. Daily operations checklist")
    numbered(
        doc,
        [
            "Clear Submitted / Awaiting Approval queues for PR and Petty Cash (HOD first).",
            "Finance processes HOD-approved PRs and department-approved petty cash (with PV).",
            "MD/DMD clears Finance Approved PRs and advance forms waiting for final approval.",
            "Procurement creates POs only from MD/DMD Approved PRs.",
            "Close POs with correct GRN / receipt before expecting budget and asset updates.",
            "Assets receives pending Asset/Accessory lines promptly; use serial Check to avoid duplicates.",
            "Review Budget Accounts On hold and History for unexpected holds or deductions.",
            "Check Email Status for Failed notifications when users report missing alerts.",
            "Ensure new users have roles, department, HOD links (if applicable), and signatures.",
        ],
    )

    # 17 Troubleshooting
    h1(doc, "17. Troubleshooting and escalation")
    table(
        doc,
        ["Symptom", "Likely cause / what to check"],
        [
            ["Menu item missing", "Role lacks view_any (or equivalent) permission"],
            ["Cannot submit PR", "No lines; insufficient available budget; missing department allocation on sub-budget"],
            ["HOD buttons missing", "User not associated as HOD of the requester’s department"],
            ["Cannot create PO from PR", "PR not yet MD / DMD Approved"],
            ["Cannot close PO (petty cash)", "Receipt not uploaded"],
            ["Cannot finance-approve petty cash", "PV numbers not added"],
            ["PDF looks wrong / no signature", "User signature missing on Profile"],
            ["Asset not listed for receiving", "Item type is Other, or PO not ready/closed path incomplete"],
            ["SSH-style host warnings N/A", "—"],
            ["Email not received", "Reports → Email Status (Failed/Pending); spam filters"],
        ],
    )
    h2(doc, "17.1 Escalation format")
    p(doc, "When contacting support, include:")
    numbered(
        doc,
        [
            "Module name (Purchase Request / Procure / Petty Cash / Assets / Budgets / other)",
            "Record number or ID",
            "Your user email and role",
            "Action attempted",
            "Exact error message or screenshot",
            "Date and time",
        ],
    )

    # 18 Glossary
    h1(doc, "18. Glossary")
    table(
        doc,
        ["Term", "Meaning"],
        [
            ["PR", "Purchase Request"],
            ["PO / Procure", "Purchase Order module and records"],
            ["HOD", "Head of Department — approves department requests"],
            ["MD / DMD", "Managing Director / Deputy Managing Director final approval"],
            ["PV", "Payment voucher number(s) required before petty-cash finance approval"],
            ["GRN", "Goods Received Note number used when closing POs / PRs"],
            ["Sub-budget", "Detailed budget bucket under a budget account, with department/location allocations"],
            ["On hold", "Estimated PR cost reserved against an allocation before final deduction"],
            ["MVR", "Maldivian Rufiyaa — currency used in the UI"],
            ["Snipe-IT", "External asset inventory system used for receiving"],
            ["Advance form", "Optional advance-payment form linked to a PO"],
        ],
    )

    # 19 Appendix
    h1(doc, "19. Appendix — status reference")
    h2(doc, "19.1 Purchase Request statuses")
    table(
        doc,
        ["Status label", "Typical meaning"],
        [
            ["Draft", "Editable; not in approval"],
            ["Submitted", "Awaiting department HOD"],
            ["Department HOD Approved", "Awaiting Finance"],
            ["Department HOD Rejected", "Rejected by HOD"],
            ["Finance Approved", "Awaiting MD/DMD"],
            ["Finance Rejected", "Rejected by Finance"],
            ["MD / DMD Approved", "Ready for procurement"],
            ["MD / DMD Rejected", "Rejected by MD/DMD"],
            ["Canceled", "Canceled (finance path)"],
            ["Closed", "Completed / closed"],
        ],
    )

    h2(doc, "19.2 Purchase Order statuses")
    table(
        doc,
        ["Status label", "Typical meaning"],
        [
            ["Draft", "Being prepared"],
            ["Submitted", "Active order"],
            ["GRN Created", "GRN-related state"],
            ["Pending Reimbursement", "Awaiting petty-cash reimbursement"],
            ["Reimbursed", "Reimbursement completed"],
            ["Closed", "Order closed"],
        ],
    )

    h2(doc, "19.3 Petty Cash statuses")
    table(
        doc,
        ["Status label", "Typical meaning"],
        [
            ["Draft", "Editable"],
            ["Submitted", "Awaiting department HOD"],
            ["Department approved", "Awaiting PV + Finance"],
            ["Department rejected", "Rejected by department"],
            ["Finance approved", "Intermediate finance state (as shown in UI)"],
            ["Finance rejected", "Rejected by finance"],
            ["Reimbursed", "Complete; budgets posted"],
        ],
    )

    h2(doc, "19.4 Advance form statuses")
    table(
        doc,
        ["Status label", "Typical meaning"],
        [
            ["Draft", "Generated, not submitted"],
            ["Submitted", "Awaiting HOD"],
            ["HOD Approved", "Awaiting MD/DMD"],
            ["HOD Rejected", "Rejected by HOD"],
            ["DMD / MD Approved", "Advance approved"],
            ["DMD / MD Rejected", "Rejected by MD/DMD"],
        ],
    )

    doc.add_paragraph()
    p(
        doc,
        "— End of manual — Source: Finance Control Center application workflows and screens. "
        "Update this document when statuses, permissions, or screens change.",
    )

    doc.save(OUT)
    print(f"Wrote {OUT}")


if __name__ == "__main__":
    build()
