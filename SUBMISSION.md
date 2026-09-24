# Draftroom Submission

## Included Product

Draftroom is a Laravel collaborative document editor with the following delivered functionality:

### Document Creation and Editing

- Create a new document from the sidebar `+ New` menu.
- Require a filename before creating a document.
- Rename an existing document.
- Edit document content in the browser with a `contenteditable` editor.
- Autosave title, content, and paper size through a JSON update request.
- Reopen saved documents from the workspace document list.
- Rich-text controls:
  - Bold
  - Italic
  - Underline
  - Paragraph style
  - Heading
  - Subheading
  - Bulleted lists
  - Numbered lists
  - Undo
  - Redo
  - Left alignment
  - Center alignment
  - Clear formatting
- Paper sizes:
  - A4
  - Short
  - Long

### File Upload

- Upload exactly these file types:
  - `.txt`
  - `.md`
  - `.docx`
- Maximum upload size: 2 MB.
- TXT and Markdown content becomes editable document content.
- DOCX paragraph text is extracted from the document XML and becomes editable content.
- Original uploaded files are stored with document metadata.
- Unsupported file types are rejected by backend validation.

### Sharing

- Every document has an owner.
- Restricted sharing accepts a real `@gmail.com` address.
- Restricted recipients are persisted as document-share records.
- Anyone-with-the-link sharing generates a secure tokenized URL.
- Generated public links use:
  `https://assesment.laravel.cloud/shared/{token}`
- Public shared documents are read-only.
- Shared documents are visibly labeled in the document list.
- Login and registration are required before entering the workspace. Ivan is included as a seeded account for demonstration.

### Document Actions

Each document has a three-dot menu with:

- Edit
- Change file name
- Delete

Delete is owner-protected, confirmation-protected, and also removes the stored source upload.

## Included Files

### Backend

- `app/Http/Controllers/WorkspaceController.php`
- `app/Models/Document.php`
- `app/Models/DocumentShare.php`
- `app/Models/User.php`
- `routes/web.php`

### Database

- `database/migrations/2026_09_24_000003_create_documents_tables.php`
- `database/migrations/2026_09_24_000004_add_paper_size_to_documents_table.php`
- `database/migrations/2026_09_24_000005_add_source_file_to_documents_table.php`
- `database/migrations/2026_09_24_000006_add_share_access_to_documents_table.php`
- `database/seeders/DatabaseSeeder.php`

### Frontend

- `resources/views/workspace.blade.php`
- `resources/views/shared-document.blade.php`
- `resources/css/app.css`
- `resources/js/app.js`
- `resources/js/workspace.js`

### Documentation and Tests

- `README.md`
- `AI_WORKFLOW.md`
- `SUBMISSION.md`
- `tests/Feature/ExampleTest.php`
- `tests/Unit/ExampleTest.php`
- `tests/TestCase.php`

## Validation Completed

- Database migrations run successfully.
- Blade templates compile successfully.
- Application routes register successfully.
- PHP, Blade, CSS, and JavaScript error checks pass.
- Feature and unit tests pass.

Latest test result:

```text
Tests: 3 passed, 6 assertions
```

## Setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open `http://localhost:8000` after starting the Laravel server.

## Scope Notes

- Authentication uses Laravel session login and registration. The seeded Ivan account is `ivan@gmail.com` with password `password123`.
- Restricted sharing stores access for the Gmail recipient but does not send email invitations.
- DOCX import extracts text and paragraphs; advanced DOCX styling, images, and embedded media are not imported.
- Real-time simultaneous editing is outside the current lightweight scope.
