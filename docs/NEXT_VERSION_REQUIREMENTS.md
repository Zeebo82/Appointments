# Planned follow-up: fully configurable texts and form fields

Requested after the 2.8.5.2 HTML placement fix. These changes are **not yet implemented**.

## Editable public-facing texts

Per booking page, expose overrides for all built-in visible labels, notices, placeholder texts, validation messages and confirmation/cancellation UI texts. Examples:
- "Keine Termine verfügbar" / "No Appointments Available".
- Button "Jetzt buchen" / "Book Now" (already configurable via `formButtonLabel` in the current branch; preserve its existing setting).
- Date/time chooser labels, placeholders and "Select Date & Time".
- Name, email, phone placeholders.
- Success, pending, cancellation, session timeout, validation errors and other public-facing texts.

Empty overrides should use current translated defaults. Plain-text labels and placeholders must be HTML-escaped; only dedicated HTML text blocks may render sanitized HTML.

## Per-field visibility and validation

Offer a clear Show / Optional / Required control for each field where technically feasible, e.g. name, email, phone and supported custom fields.
- Phone is already hideable via `hidePhone` / "Hide phone number field".
- `src/form.js` currently marks an **empty phone number invalid**, despite a comment saying "Phone field is optional". Fix this mismatch.
- Align client-side and server-side validation, including when fields are hidden.
- Some fields have functional dependencies: date/time is required for booking; email needs to stay available where email confirmation/validation or sending mail depends on it. Disallow conflicting configurations or adjust the workflow explicitly.
- Preserve all existing settings and sensible defaults in existing pages.

## Acceptance checks

1. Set custom "No Appointments Available" text and see it on an unavailable booking page.
2. "Book Now" / "Jetzt buchen" remains editable, without duplicate settings.
3. Phone supports hidden, optional (empty is accepted) and required.
4. Field validation is enforced consistently in browser and backend.
5. Each public text is editable in the corresponding page's settings with language-aware defaults.
6. An installable app ZIP is built through GitHub Actions and tested on Nextcloud 33.

Status: feature request only; no runtime implementation in 2.8.5.2.
