# Changelog

All notable changes to `angeo/module-ai-description-updater` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-06-13

### Security
- **Stored XSS (critical)** — AI output for the HTML `description` attribute was
  saved unsanitised. Added `Service\Security\HtmlSanitizer`, a whitelist-based
  sanitiser (allowed tags/attributes only; strips `<script>`, event handlers and
  `javascript:` URLs). `AiProviderService::postProcess()` now sanitises all HTML
  attributes before they are stored.
- **CSV / formula injection (critical)** — CSV export wrote raw values, and the
  Google Sheets export used `valueInputOption=USER_ENTERED`, allowing values such
  as `=IMPORTXML(...)` to execute on open / server-side. Added
  `Service\Security\SpreadsheetValueSanitizer` (prefixes `= + - @` with `'`) for
  CSV, and switched the Sheets export to `valueInputOption=RAW`.
- **SSRF / blind redirect** — `GoogleSheetsService::downloadCsv()` now restricts
  requests to `docs.google.com` over HTTPS only, with `CURLOPT_PROTOCOLS` /
  `CURLOPT_REDIR_PROTOCOLS` set to HTTPS, bounded redirects, and TLS verification
  enforced. The full sheet URL is no longer written to the log.
- **Spreadsheet/Drive ID validation** — IDs are validated against
  `^[A-Za-z0-9_-]{10,}$` before use in URLs.
- **Path traversal** — the CSV filename pattern is reduced to a safe basename and
  stripped of unsafe characters.
- **CSRF hardening** — both admin controllers now implement
  `HttpPostActionInterface` (POST-only), so the admin form key is enforced.
- **Information disclosure** — admin controllers return generic error messages;
  full exception detail is logged only.
- **Drive query escaping** — `findExistingFile()` escapes `\` and `'` correctly
  for the Drive query language instead of using `addslashes()`.

### Added
- Run-summary **email notification** (`Notify Email`) via `TransportBuilder` and
  a new `angeo_ai_description_summary` email template — previously documented but
  not implemented.
- **Google Drive CSV upload** is now wired into the run: when Sheets export is
  enabled, the generated CSV is also uploaded via the Service Account
  (`GoogleDriveService` was previously dead code).
- Per-store **batch offset tracking**: each run processes at most `batch_size`
  SKUs per store and advances a persisted offset, so the whole catalogue is
  eventually covered instead of always re-processing the first N products.
- Unit tests for `HtmlSanitizer` and `SpreadsheetValueSanitizer`.

### Changed
- **Per-store prompts/language now actually apply.** Generation runs inside store
  area emulation and reads `getEnabledAttributes()` / `buildPromptForAttribute()`
  / `getSystemRole()` in the current store-view scope (previously always the
  default scope).
- **Bounded memory on large catalogues.** SKU resolution uses a SKU-only product
  collection (`addAttributeToSelect('sku')`) instead of fully hydrating every
  product via `getList()->getItems()`.
- The Google Sheets SKU source now also respects `batch_size` (previously it
  processed all SKUs with no cap).
- `str_getcsv()` is called with explicit `separator`, `enclosure` and `escape`
  arguments to avoid the PHP 8.4 deprecation.
- CSV/offset directory created with `0750` permissions instead of `0755`.

### Fixed
- `fopen()` failure in CSV export is now checked and handled.

## [1.0.0] - 2026-05-16
- Initial release: OpenAI, Anthropic Claude, Google Gemini and Groq providers;
  Google Sheets SKU source; Google Sheets export; dry-run mode; per-store prompts;
  CLI and cron automation.
