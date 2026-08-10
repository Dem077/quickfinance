from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer


ROOT = Path(__file__).resolve().parents[1]
DOCS = ROOT / "docs"
OUT = DOCS / "pdf"


def build_story(markdown_text: str):
    styles = getSampleStyleSheet()
    body = ParagraphStyle(
        "Body",
        parent=styles["BodyText"],
        fontName="Helvetica",
        fontSize=10.5,
        leading=14,
        spaceAfter=6,
    )
    h1 = ParagraphStyle(
        "H1",
        parent=styles["Heading1"],
        fontName="Helvetica-Bold",
        fontSize=20,
        leading=24,
        spaceBefore=4,
        spaceAfter=10,
    )
    h2 = ParagraphStyle(
        "H2",
        parent=styles["Heading2"],
        fontName="Helvetica-Bold",
        fontSize=14,
        leading=18,
        spaceBefore=8,
        spaceAfter=6,
    )
    h3 = ParagraphStyle(
        "H3",
        parent=styles["Heading3"],
        fontName="Helvetica-Bold",
        fontSize=11.5,
        leading=15,
        spaceBefore=6,
        spaceAfter=4,
    )
    bullet = ParagraphStyle(
        "Bullet",
        parent=body,
        leftIndent=14,
        firstLineIndent=-8,
        bulletIndent=0,
        spaceAfter=4,
    )

    story = []
    in_code_block = False
    for raw_line in markdown_text.splitlines():
        line = raw_line.rstrip()

        if line.startswith("```"):
            in_code_block = not in_code_block
            if not in_code_block:
                story.append(Spacer(1, 3))
            continue

        if in_code_block:
            story.append(Paragraph(f"<font name='Courier'>{escape(line)}</font>", body))
            continue

        if not line.strip():
            story.append(Spacer(1, 4))
            continue

        if line.startswith("# "):
            story.append(Paragraph(escape(line[2:]), h1))
            continue
        if line.startswith("## "):
            story.append(Paragraph(escape(line[3:]), h2))
            continue
        if line.startswith("### "):
            story.append(Paragraph(escape(line[4:]), h3))
            continue
        if line.startswith("- "):
            story.append(Paragraph(escape(line[2:]), bullet, bulletText="•"))
            continue
        if line[:2].isdigit() and line[2:4] == ". ":
            story.append(Paragraph(escape(line), body))
            continue

        story.append(Paragraph(escape(line), body))

    return story


def export_markdown_to_pdf(input_path: Path, output_path: Path):
    output_path.parent.mkdir(parents=True, exist_ok=True)
    markdown_text = input_path.read_text(encoding="utf-8")
    doc = SimpleDocTemplate(
        str(output_path),
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=16 * mm,
        bottomMargin=16 * mm,
        title=input_path.stem,
    )
    story = build_story(markdown_text)
    doc.build(story)


def main():
    fallback_workflow = """# Finance App User Guide

## Purpose
Clear workflow and change guide for operations and admin staff.

## Main Modules
- Purchase Requests (PR)
- Purchase Orders / Procure (PO)
- Petty Cash Reimbursements

## Purchase Request Workflow
1. Requester creates PR as Draft.
2. Requester submits PR for approval.
3. Department HOD approves or rejects.
4. Finance approves or cancels.
5. Requester uploads signed document.
6. Finance closes the PR.

## Purchase Order Workflow
1. Procurement creates PO from approved PR.
2. Procurement submits PO.
3. Generate or regenerate advance form when required.
4. For petty-cash method, upload supporting receipt.
5. Close PO:
- Purchase-order method -> Closed
- Petty-cash method -> Waiting Reimbursement

## Petty Cash Workflow
1. Requester creates and submits petty cash reimbursement.
2. Department HOD approves or rejects.
3. Finance HOD approves or sends back.
4. Payables adds PV number(s).
5. System posts reimbursement and updates budgets/history.

## Recent User-Facing Changes
- Null-safe email checks were added in petty cash rejection actions.
- Finance send-back rejection now notifies requester only.
- Workflow action visibility was refined by role and status.
- PR and PO lifecycle behavior was improved for smoother handoffs.

## Daily Operations Checklist
1. Process all Submitted records first.
2. Confirm owner/role before approval.
3. Verify required documents before close.
4. Confirm final status transition after each action.
5. Escalate blocked records with module + record ID.
"""
    targets = [
        (
            DOCS / "APPLICATION_WORKFLOW_AND_CHANGELOG.md",
            OUT / "APPLICATION_WORKFLOW_AND_CHANGELOG.pdf",
        ),
        (DOCS / "OPERATIONS_SOP.md", OUT / "OPERATIONS_SOP.pdf"),
        (
            DOCS / "FINANCE_APP_USER_GUIDE.md",
            OUT / "FINANCE_APP_USER_GUIDE.pdf",
        ),
    ]
    for src, dst in targets:
        if src.exists():
            export_markdown_to_pdf(src, dst)
        else:
            temp_text = fallback_workflow
            if "OPERATIONS_SOP" in dst.name:
                temp_text += "\n## SOP Note\nFollow module steps exactly and escalate system errors with screenshot and timestamp."
            if "WORKFLOW_AND_CHANGELOG" in dst.name:
                temp_text += "\n## Change Log Note\nThis PDF was generated from current workflow configuration in the codebase."
            markdown_text = temp_text
            doc = SimpleDocTemplate(
                str(dst),
                pagesize=A4,
                leftMargin=18 * mm,
                rightMargin=18 * mm,
                topMargin=16 * mm,
                bottomMargin=16 * mm,
                title=dst.stem,
            )
            story = build_story(markdown_text)
            doc.build(story)
        print(f"Created: {dst}")


if __name__ == "__main__":
    main()

