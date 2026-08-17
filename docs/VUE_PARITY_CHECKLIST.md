# Vue / Filament parity checklist

Tick each row when the Inertia Vue module matches Filament behavior (including permissions and side effects). Prefer **View-page** behavior where list and view diverge.

## Phase 0 — Foundation
- [x] Inertia + Vue installed; `/app` shell
- [x] Session login/logout
- [x] Shield permissions shared to Vue (`auth.permissions`)
- [x] Filament remains at `/admin`
- [x] Profile page (signature form placeholder)
- [x] `App\Actions\Action` base; per-module Actions extracted during each phase
- [x] Items CRUD (list/create/edit/delete/bulk) in Vue
- [x] Vendors / Locations / Projects / Departments CRUD
- [x] Users CRUD + HOD associate/dissociate/bulk dissociate

## Purchase Requests
- [x] List scoping (role/HOD/`view_all_pr`)
- [x] Create (auto `pr_no` + lines)
- [x] Edit / Delete (Draft)
- [x] Submit for Approval
- [x] HOD Approve / Reject (`HODRejected` + email)
- [x] Finance Approve / Reject (+ reason)
- [x] Cancel / Send Back To Draft
- [x] MD/DMD Approve / Reject
- [x] Close + GRN for open POs
- [x] View Document / Download PDF
- [x] Line RM Add/Edit + budget validation

## Purchase Orders (Procure)
- [x] Create from MD/DMD-approved PR; line calc; petty cash `po_no`
- [x] Submit (+ utilization + `syncAssetReceipts`)
- [x] Close (GRN / WaitingReimbursement + budget + history)
- [x] Advance Form generate/regenerate/submit/HOD/MD-DMD/view/PDF
- [x] Receipt upload/view
- [x] Line RM CRUD + calculate

## Petty Cash
- [x] List scoping
- [x] Submit / Dept Approve·Reject / Add PV / Fin Approve·Reject
- [x] Finance approve budget deduct + PO reimbursed
- [x] PDF download
- [x] Detail lines RM

## Asset Management
- [x] List Submitted/Closed POs with asset/accessory lines
- [x] View `syncAssetReceipts`
- [x] Item Received / Accessory Received
- [x] Bulk receive assets / accessories
- [x] Serial uniqueness check

## Budgets / Master / Reports / Users
- [x] Budget accounts + sub Top Up
- [x] Transfers + history
- [x] Items / Vendors / Locations / Projects / Departments
- [x] Users + HOD associate
- [x] Report templates + CSV
- [x] Roles UI equivalent
- [x] Activity log list (Filament retained for reference)
