# Merge Notes: Masterlist Enhancements & Form Builder Overhaul

This document outlines the major features, architectural shifts, and UX improvements implemented in this branch. Please review this before merging to ensure a smooth integration.

## 1. Masterlist Import Enhancements
We overhauled the Masterlist import process to support advanced formatting, intelligent parsing, and better data validation.

* **Advanced Python Parsing Integration:** Added `backend/python/masterlist_extract.py` and updated `MasterlistSpreadsheetParser.php` to handle complex CHED spreadsheet formats natively.
* **Import Detection & Header Mapping:** Added `MasterlistImportDetection` and `MasterlistImportDetectedHeader` models to robustly map spreadsheet columns to our database schemas and detect duplicates.
* **Database Migrations:** Added `2026_08_18_140231_add_detection_info_to_masterlist_imports.php` to store parsed structural metadata for imports.

## 2. Form Builder Architecture Rewrite
The Dynamic Forms architecture was completely rebuilt. We migrated away from storing the entire form schema as a single JSON blob to a fully normalized relational database structure.

* **Relational Schema:** Created models and tables for `FormSection`, `FormField`, `FormFieldOption`, and `FormFieldCondition`. 
* **State Management:** Added the `status` enum column (`draft`, `published`, `closed`, `archived`) to the `forms` table, replacing basic boolean flags.
* **Security First:** Built the `FormAnswer` architecture and implemented robust **encryption at rest** for all form response values via the `2026_08_18_170301_encrypt_form_answers_answer_value.php` migration.

## 3. Form Builder UX & UI Upgrades
We massively improved the user experience of the Form Builder interface, moving to a modern, real-time approach.

* **Optimistic UI & Debounced Saving:** Replaced explicit "Save" modals with instantaneous optimistic local updates and 800ms debounced background API saves.
* **Unified Workspace:** We deleted the standalone `ResponseViewer.vue` and `FieldConfigModal.vue`. The `Builder.vue` component now acts as the central hub:
  * **Edit Mode (Pencil):** Shows the `Settings` and `Builder` tabs.
  * **View Mode (View Data):** Shows the `Responses` and `Analytics` tabs.
* **Responses View:** Added a fully functional paginated data table within the Builder (`FormResponsesTab.vue`) allowing staff to view and export responses to CSV.

## 4. Archiving & List Improvements
* **Archive Over Delete:** Replaced the hard-delete workflow with an Archival system. Forms are now soft-archived to preserve historical response data. Added an "Archives" toggle in the Form Index to easily swap between active and archived views.
* **Accessibility Tweaks:** Increased the hit area of action buttons (`size-9`, `18px` icons) in `Index.vue` and centered the Actions column to ensure the UI is comfortable to use without relying on browser zoom.
* **Smart Previews:** The "Eye" preview icon now exclusively appears on Public forms and directly opens the public-facing URL in a new tab.
