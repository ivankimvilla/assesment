# Draftroom AI Workflow Note

## Project Goal

Draftroom is a lightweight collaborative document editor inspired by Google Docs. The product focuses on a small, usable workflow for creating, editing, importing, saving, and sharing documents.

## AI-Assisted Workflow

### 1. Understand the existing project

The project was inspected before implementation to identify:

- Laravel routes and application structure
- Existing Blade views and frontend assets
- Database configuration and migrations
- Existing test setup
- Available PHP and JavaScript dependencies

The application began from the Laravel starter page, so the implementation used the existing Laravel structure rather than introducing a new framework.

### 2. Define the smallest complete product slice

The implementation was organized around four connected workflows:

1. Create and edit a document.
2. Import a supported file into a new editable document.
3. Share a document with restricted access or an anyone-with-link URL.
4. Persist documents, formatting, sharing, uploads, and paper size in the database.

This kept the scope practical while covering the required product behavior.

### 3. Build the backend first

The backend was implemented with Laravel controllers, models, routes, and migrations.

Main persistence areas:

- `documents`: owner, title, HTML content, paper size, and uploaded source-file metadata
- `document_shares`: document-to-user access relationships
- document access fields: restricted/anyone mode and secure share token

The application uses seeded users for the demo workspace, while restricted sharing accepts a real Gmail address and creates the recipient record when necessary.

### 4. Build the editing experience

The browser editor uses a `contenteditable` document surface. The toolbar supports:

- Bold
- Italic
- Underline
- Paragraph and heading styles
- Bulleted and numbered lists
- Left and center alignment
- Undo and redo
- Clear formatting
- A4, Short, and Long paper sizes

Changes are autosaved through a JSON PATCH request. The title and HTML content are restored when the document is reopened.

### 5. Build the file workflow

The upload workflow is intentionally limited to formats that can become editable documents:

- `.txt`
- `.md`
- `.docx`

Text and Markdown files are imported as escaped text with preserved line breaks. DOCX files are read from the document XML and converted into editable text. The original uploaded file is also stored with the document record.

Unsupported file types are rejected by backend validation rather than creating a misleading placeholder document.

### 6. Build sharing

The Share dialog supports two modes:

- **Restricted**: grants access to a Gmail recipient through a persisted document share record.
- **Anyone with the link**: creates a secure random token and generates a read-only URL using `https://assesment.laravel.cloud`.

The public shared-document page renders the saved document title, owner, paper size, and sanitized editable content.

### 7. Separate frontend behavior

Interactive behavior was moved out of Blade into:

- `resources/js/workspace.js`
- `resources/js/app.js`

Blade now provides semantic markup and data attributes for routes, CSRF protection, document IDs, and current paper size. The module handles editor formatting, autosave, menus, sharing controls, rename/delete actions, and file upload submission.

## Product Decisions

### Why simulate identity?

A full authentication system was outside the timebox. Seeded demo users and session-based switching demonstrate owned versus shared documents while keeping the collaboration workflow testable.

### Why restrict uploads?

Only TXT, Markdown, and DOCX are currently converted into editable content. Rejecting unsupported formats is safer and clearer than accepting binary files that cannot be displayed meaningfully in the editor.

### Why make public links read-only?

A public link demonstrates sharing without exposing anonymous editing. Editing remains limited to the document owner and explicitly shared users.

## Validation

The project was validated with:

- Laravel migration execution
- Blade view compilation
- Route registration checks
- PHP/Blade/CSS error checks
- Feature tests for the application response
- A regression test proving TXT upload content is persisted as editable document content

Current test result:

```text
Tests: 3 passed, 6 assertions
```

## Known Scope Limits

- There is no real email invitation or notification service.
- Gmail addresses are accepted for the simulated restricted-sharing workflow, but no email is sent.
- DOCX import extracts document text; advanced DOCX styling and embedded media are not imported.
- Real-time simultaneous editing is outside this lightweight implementation.
