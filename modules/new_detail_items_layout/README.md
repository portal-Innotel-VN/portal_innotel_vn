# New Detail Items Layout

Perfex CRM module that changes sales document items table layout in previews
and PDF output.

## Scope

- Applies to estimate, invoice, proposal, and credit note item tables.
- Also applies to subscription invoice views because they render as invoice
  item tables.
- Does not create database tables.
- Does not modify Perfex core files.

## New Columns

`# | Ma hang | Mo ta | So luong | Gia | Thue | Tong`

`Mo ta` is rendered from the item `long_description` field.

## Install

1. Zip the `new_detail_items_layout` folder.
2. Upload the zip from Perfex admin module installer.
3. Activate the module.
4. Open a sales document preview and check the items table.

## Verify

- Estimate preview and PDF show the new split layout.
- Invoice preview and PDF show the new split layout.
- Proposal preview and PDF show the new split layout.
- Credit note preview and PDF show the new split layout.
- No database schema is changed during activation.
