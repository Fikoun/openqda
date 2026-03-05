### Feature Enhancements for Analysis View

**Primary Objective:**
The current features in the "Analysis" view are limited. This project aims to expand them by incorporating functionality from the "Coding" view and introducing new capabilities.

**Key Initiatives:**

1.  **Introduce New Data Models:**
    *   Create new models for "Categories" and "Themes" to organize codes more effectively.
    *   Determine if "Categories" and "Themes" should be implemented as distinct database models, separate from the existing `Code` model.

2.  **Enhance UI/UX in Analysis View:**
    *   **Code Focusing:** In the analysis view, when a user clicks on a code in the left-side panel, the right-side panel should be filtered to display only the selected code and its sub-codes (descendants).
    *   **Top-Level Codes:** Allow codes to exist as parent-level entities, meaning they do not need to be assigned a parent.

3.  **Improve Search and Code Management:**
    *   The current search functionality in the analysis view is basic. The following improvements are needed:
        *   **Hierarchy Editing:** Enable users to modify parent-child relationships (parents and sub-codes) directly from the search results.
        *   **Code Grouping:** Implement a feature that allows users to select multiple codes (e.g., via checkboxes) and group them under a new sub-code.
        *   **Parent Re-assignment with Search:** When changing the parent of a code, provide a search functionality to make it easier to find the new parent.
        *   **Re-assign Code Reference:** Allow a user to change the code associated with a specific text selection. For example, if `code1` is assigned to a selection from `12:20`, the user should be able to re-assign that selection to `code2`. When this happens, the application should ask the user for confirmation on what to do with the original `code1`.

4.  **Bug Fixes:**
    *   **Live Update:** When a code is edited, the list of codes does not update in real-time to reflect the changes. This needs to be fixed to ensure the UI is properly synchronized.
