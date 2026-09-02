# angeo/module-ai-description-updater

[![Packagist Version](https://img.shields.io/packagist/v/angeo/module-ai-description-updater.svg)](https://packagist.org/packages/angeo/module-ai-description-updater)
[![Packagist Downloads](https://img.shields.io/packagist/dt/angeo/module-ai-description-updater.svg)](https://packagist.org/packages/angeo/module-ai-description-updater)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-blue.svg)](https://php.net)
[![Magento](https://img.shields.io/badge/Magento-2.4.x-orange.svg)](https://magento.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**Automatically generate and update Magento 2 product descriptions across all store views using AI — via CLI, cron, or Admin Panel.**

`angeo/module-ai-description-updater` is an open-source Magento 2 module for bulk AI-powered product description generation across multiple store views and languages. Supports OpenAI, Anthropic Claude, Google Gemini, and Groq (free, no credit card required). Results can be exported to Google Sheets automatically.

---

## Table of contents

- [Why this module vs alternatives](#why-this-module-vs-alternatives)
- [Supported AI providers](#supported-ai-providers)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start — Groq (free, 5 minutes)](#quick-start--groq-free-5-minutes)
- [Full configuration reference](#full-configuration-reference)
    - [General](#general)
    - [AI Provider](#ai-provider)
    - [OpenAI settings](#openai-settings)
    - [Claude settings](#claude-settings)
    - [Gemini settings](#gemini-settings)
    - [Groq settings](#groq-settings)
    - [Prompt](#prompt)
    - [Attributes](#attributes)
    - [Google Sheets — SKU source](#google-sheets--sku-source)
    - [Google Sheets — export](#google-sheets--export)
    - [Export / CSV](#export--csv)
    - [Cron](#cron)
- [CLI usage](#cli-usage)
- [Multi-store behaviour](#multi-store-behaviour)
- [Setup guides](#setup-guides)
    - [OpenAI API key](#openai-api-key)
    - [Anthropic Claude API key](#anthropic-claude-api-key)
    - [Google Gemini API key](#google-gemini-api-key)
    - [Groq API key (free)](#groq-api-key-free)
    - [Google Sheets — SKU source setup](#google-sheets--sku-source-setup)
    - [Google Sheets — export setup (Service Account)](#google-sheets--export-setup-service-account)
- [Log file](#log-file)
- [Related modules](#related-modules)

---

## Why this module vs alternatives

| Feature | This module | Commercial alternatives |
|---|---|---|
| CLI + Cron automation | ✓ | Rarely |
| Multi-store (all store views) | ✓ | Rarely |
| Groq — free tier, no card | ✓ | Nowhere |
| Google Sheets as SKU source | ✓ | Nowhere |
| Google Sheets export | ✓ | Nowhere |
| Dry-run mode | ✓ | Rarely |
| 4 AI providers | ✓ | Usually 1 |
| MIT license | ✓ | Rarely |
| Price | **Free** | $99–$299/year |

---

## Supported AI providers

| Provider | Models | Cost | Notes |
|---|---|---|---|
| **OpenAI** | gpt-4.1, gpt-4.1-mini, gpt-4.1-nano, gpt-4o | Paid | Best quality/cost: gpt-4.1-mini |
| **Anthropic Claude** | claude-sonnet-4-6, claude-opus-4-6, claude-haiku-4-5 | Paid | Extended thinking supported |
| **Google Gemini** | gemini-2.5-flash-preview, gemini-2.0-flash, gemini-2.0-flash-lite | Free tier + paid | Free tier has daily limits |
| **Groq** | llama-3.3-70b-versatile, llama-3.1-8b-instant, mixtral-8x7b | **Free** | 14,400 req/day, no credit card |

---

## Requirements

- PHP 8.2+
- Magento 2.4.x / Adobe Commerce / Mage-OS
- `ext-curl`, `ext-openssl`

---

## Installation

```bash
composer require angeo/module-ai-description-updater
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

---

## Quick start — Groq (free, 5 minutes)

The fastest way to get started — Groq is free with no credit card required.

**Step 1 — Get a free API key**

Go to [console.groq.com](https://console.groq.com), create an account, go to **API Keys → Create API key**. The key starts with `gsk_`.

**Step 2 — Configure Magento**

Go to **Stores → Configuration → Angeo AEO → AI Description Updater**:

1. **General → Enable Module** → `Yes`
2. **General → Dry Run Mode** → `Yes` *(safe for first test)*
3. **AI Provider → AI Provider** → `Groq (Free — Llama / Mixtral)`
4. **Groq Settings → Groq API Key** → paste your `gsk_...` key
5. **Groq Settings → Model** → `Llama 3.3 70B Versatile (recommended)`
6. **Attributes → Generate Description** → `Yes`
7. Click **Save Config**

**Step 3 — Test with dry-run**

```bash
bin/magento angeo:ai-description:run --dry-run
```

Check `var/log/angeo_ai_description_updater.log` to preview what would be generated.

**Step 4 — Run on a single product first**

Disable Dry Run in admin, then:

```bash
bin/magento angeo:ai-description:run --sku=YOUR-TEST-SKU
```

Verify the result in the product edit page. If it looks good, run the full batch:

```bash
bin/magento angeo:ai-description:run
```

---

## Full configuration reference

**Path:** Stores → Configuration → Angeo AEO → AI Description Updater

---

### General

| Field | Default | Description |
|---|---|---|
| Enable Module | No | Master on/off switch |
| Dry Run Mode | Yes | Generate but do not save to Magento. Always test with this enabled first. |
| Enable Detailed Logging | Yes | Writes to `var/log/angeo_ai_description_updater.log` |
| Batch Size | 50 | Max products processed per run or cron execution |

---

### AI Provider

| Field | Default | Description |
|---|---|---|
| AI Provider | OpenAI | Select one of: OpenAI (ChatGPT), Anthropic (Claude), Google (Gemini), Groq (Free) |

Only the selected provider's settings section is used. Switch providers at any time.

---

### OpenAI settings

*Used when AI Provider is set to OpenAI.*

| Field | Default | Description |
|---|---|---|
| OpenAI API Key | — | Starts with `sk-`. Stored encrypted. |
| GPT Model | gpt-4.1 | `gpt-4.1-mini` is the best quality/cost balance. |
| Temperature | 0.7 | 0.0 = deterministic, 2.0 = very creative. 0.7 recommended for product copy. |
| Max Tokens | 800 | Maximum length of generated response. |
| Request Timeout (s) | 30 | HTTP timeout. Increase if you get timeout errors. |

---

### Claude settings

*Used when AI Provider is set to Anthropic Claude.*

| Field | Default | Description |
|---|---|---|
| Claude API Key | — | Starts with `sk-ant-`. Stored encrypted. |
| Claude Model | claude-sonnet-4-6 | `claude-haiku-4-5` is fastest and cheapest. `claude-opus-4-6` is highest quality. |
| Max Output Tokens | 1024 | Maximum response length. |
| Request Timeout (s) | 60 | Extended thinking may need more — increase to 120 if needed. |
| Enable Extended Thinking | No | Lets Claude reason before writing. Higher quality, more tokens. Claude 4.x only. |
| Thinking Token Budget | 2048 | Minimum 1024. Internal reasoning tokens — billed as output tokens. |

---

### Gemini settings

*Used when AI Provider is set to Google Gemini.*

| Field | Default | Description |
|---|---|---|
| Gemini API Key | — | Stored encrypted. |
| Gemini Model | gemini-2.5-flash-preview-05-20 | `gemini-2.0-flash` is fast with a free tier. |
| Temperature | 0.7 | 0.0–2.0. |
| Max Output Tokens | 1024 | Gemini 2.x supports up to 8192. |
| Request Timeout (s) | 30 | |

> **Note:** If you get `limit: 0` errors, create a new Google Cloud project and generate a fresh API key.

---

### Groq settings

*Used when AI Provider is set to Groq.*

| Field | Default | Description |
|---|---|---|
| Groq API Key | — | Starts with `gsk_`. Stored encrypted. No credit card required. |
| Model | llama-3.3-70b-versatile | `llama-3.3-70b-versatile` gives the best quality. `llama-3.1-8b-instant` is fastest. |
| Temperature | 0.7 | |
| Max Output Tokens | 1024 | |
| Request Timeout (s) | 30 | |

Free tier: **30 requests/minute, 14,400 requests/day**.

---

### Prompt

| Field | Default | Description |
|---|---|---|
| System Role | "You are a professional product copywriter..." | The AI model's persona and instructions. |
| Prompt Template | *(see config)* | Supports: `{{product_name}}`, `{{product_sku}}`, `{{store_name}}` |
| Language | en | Target language. Set per store view for multi-language stores. |
| Include SEO Keywords | Yes | Appends SEO keyword optimisation instruction to the prompt. |

**Example prompt template:**

```
Write a professional and SEO-friendly product description for "{{product_name}}"
(SKU: {{product_sku}}) for our {{store_name}} online store.
Include key benefits, features, and use cases.
Format using HTML paragraphs. Keep it between 150 and 250 words.
```

---

### Attributes

| Field | Default | Description |
|---|---|---|
| Generate Description | Yes | Updates the main `description` attribute (HTML) |
| Generate Short Description | Yes | Updates `short_description` |
| Short Description Max Length | 150 | Character limit for short description |
| Generate Meta Title | No | Updates `meta_title` |
| Generate Meta Keywords | No | Updates `meta_keyword` |
| Generate Meta Description | No | Updates `meta_description` |

> **Tip:** Enable meta attributes only after validating description quality. Test with `--dry-run` first.

---

### Google Sheets — SKU source

Use a Google Spreadsheet as the source of SKUs to process instead of all active products.

| Field | Default | Description |
|---|---|---|
| Enable | No | Read SKU list from a Google Spreadsheet |
| Spreadsheet ID | — | From the URL: `/spreadsheets/d/[SPREADSHEET_ID]/edit` |
| Sheet GID | 0 | Tab ID from URL `?gid=...`. Default `0` = first tab. |
| SKU Column Index | 0 | Zero-based: `0` = column A, `1` = column B, etc. |

*No Service Account required — the spreadsheet must be set to "Anyone with the link can view".*

See [Google Sheets SKU source setup](#google-sheets--sku-source-setup) for step-by-step instructions.

---

### Google Sheets — export

Write generated descriptions back to a Google Spreadsheet after each run.

| Field | Default | Description |
|---|---|---|
| Enable | No | Export results to Google Sheets |
| Service Account JSON | — | Full JSON key from Google Cloud Console. Stored encrypted. |
| Spreadsheet ID | — | Target spreadsheet. Must be shared with the Service Account `client_email`. |
| Sheet Tab Name | Sheet1 | Tab to write to. |
| Clear Sheet Before Write | No | Yes = overwrite all rows. No = append new rows. |

See [Google Sheets export setup](#google-sheets--export-setup-service-account) for step-by-step instructions.

---

### Export / CSV

| Field | Default | Description |
|---|---|---|
| Save CSV Report | Yes | Save results to `var/angeo/ai_description_updater/`. All cell values are sanitised against CSV/formula injection. |
| CSV Filename Pattern | `ai_descriptions_{{date}}.csv` | Supports `{{date}}` placeholder. Sanitised to a safe filename (no path traversal). |
| Notify Email | — | If set to a valid address, a run-summary email (status, processed count, errors, dry-run flag) is sent after each run. |

When **Google Sheets — export → Enable** is on, the saved CSV is also uploaded to Google Drive via the configured Service Account, in addition to the structured Sheets export.

### Batching and cost control

Each run processes at most **Batch Size** SKUs per store view (including when the SKU source is Google Sheets). A per-store offset is persisted in `var/angeo/ai_description_updater/offset_store_<id>.txt`, so consecutive runs advance through the catalogue instead of repeatedly re-processing the first N products. The offset wraps back to the start once the whole catalogue has been covered. This keeps AI API spend bounded and predictable.

To reset progress for a store, delete its offset file.

---

### Cron

| Field | Default | Description |
|---|---|---|
| Enable Cron | No | Enable automatic scheduled runs |
| Schedule | `0 2 * * *` | Standard cron expression. Default: daily at 2:00 AM. |

---

## CLI usage

```bash
# Run for all active store views
bin/magento angeo:ai-description:run

# Single SKU across all stores
bin/magento angeo:ai-description:run --sku=MY-SKU-001

# Single store view only
bin/magento angeo:ai-description:run --store=2

# Dry-run — generate but do not save
bin/magento angeo:ai-description:run --dry-run

# Combine options
bin/magento angeo:ai-description:run --sku=MY-SKU --store=2 --dry-run
```

| Option | Description |
|---|---|
| `--sku=VALUE` | Process a single SKU only. Bypasses batch size limit. |
| `--store=VALUE` | Process a single Magento store ID. Default: all active stores. |
| `--dry-run` | Generate but do not save. Overrides config setting. |

---

## Multi-store behaviour

When run without `--store`, the module processes **every active non-admin store view** sequentially.

For each store view:
- Product is loaded in that store's scope
- Per-store configuration (prompt template, language, enabled attributes) is read in that store view's scope — set different prompts or languages per store view and they are honoured
- Generation runs inside store-area emulation so store-scoped settings resolve correctly
- `{{store_name}}` in the prompt is replaced with the store view name
- Generated content is saved in that store's scope — not the global default scope

```bash
# List your store IDs
bin/magento store:list

# Run for a specific store only
bin/magento angeo:ai-description:run --store=3
```

---

## Setup guides

### OpenAI API key

1. Go to [platform.openai.com](https://platform.openai.com) → sign in or create account
2. Click your profile → **API keys** → **Create new secret key**
3. Copy the key — shown only once (starts with `sk-`)
4. In Magento: **Stores → Configuration → Angeo AEO → AI Description Updater → OpenAI Settings → API Key**

> Add a payment method at [platform.openai.com/account/billing](https://platform.openai.com/account/billing) for production use.

---

### Anthropic Claude API key

1. Go to [console.anthropic.com](https://console.anthropic.com) → create account
2. **API Keys → Create Key**
3. Copy the key (starts with `sk-ant-`)
4. In Magento: **... → Claude Settings → API Key**

---

### Google Gemini API key

1. Go to [aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey)
2. Click **Create API key in new project**
3. Copy the key
4. In Magento: **... → Gemini Settings → API Key**

> If you get `limit: 0` errors: create a new Google Cloud project and generate a new key there.

---

### Groq API key (free)

1. Go to [console.groq.com](https://console.groq.com) → create account — **no credit card required**
2. **API Keys → Create API key**
3. Copy the key (starts with `gsk_`)
4. In Magento: **... → Groq Settings → API Key**

Free tier: 30 requests/minute, 14,400 requests/day.

---

### Google Sheets — SKU source setup

**Step 1 — Create the spreadsheet**

Create a new spreadsheet at [sheets.google.com](https://sheets.google.com). Add SKUs in column A, one per row:

```
SKU
MY-SKU-001
MY-SKU-002
MY-SKU-003
```

**Step 2 — Make it publicly readable**

**Share** → **Change to anyone with the link** → **Viewer** → **Done**

**Step 3 — Get the Spreadsheet ID**

Copy the ID from the URL:

```
https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms/edit
                                       ↑ this is the Spreadsheet ID
```

**Step 4 — Configure in Magento**

- **Enable** → `Yes`
- **Spreadsheet ID** → paste ID
- **Sheet GID** → `0` (first tab) or `?gid=` value from URL for other tabs
- **SKU Column Index** → `0` for column A

---

### Google Sheets — export setup (Service Account)

**Step 1 — Create a Google Cloud project**

Go to [console.cloud.google.com](https://console.cloud.google.com):

1. Click the project dropdown (top left) → **New Project**
2. Name it (e.g. `angeo-sheets`) → **Create**

**Step 2 — Enable Google Sheets API**

Left menu → **APIs & Services → Library** → search `Google Sheets API` → **Enable**

**Step 3 — Create a Service Account**

Left menu → **IAM & Admin → Service Accounts** → **Create Service Account**:

1. Choose **Application data** when asked what data you will access
2. Service account name: `angeo-ai-updater`
3. Click **Create and Continue** → skip optional role → **Done**

**Step 4 — Generate a JSON key**

Click the service account → **Keys** tab → **Add Key → Create new key** → **JSON** → **Create**

A JSON file downloads automatically. It looks like this:

```json
{
  "type": "service_account",
  "project_id": "angeo-sheets",
  "client_email": "angeo-ai-updater@angeo-sheets.iam.gserviceaccount.com",
  "private_key": "-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----\n",
  "token_uri": "https://oauth2.googleapis.com/token"
}
```

Copy the `client_email` value — you need it in the next step.

**Step 5 — Share the spreadsheet with the Service Account**

1. Create a new spreadsheet at [sheets.google.com](https://sheets.google.com)
2. Click **Share**
3. Paste the `client_email` from the JSON (e.g. `angeo-ai-updater@angeo-sheets.iam.gserviceaccount.com`)
4. Set role to **Editor**
5. Click **Send**

**Step 6 — Configure in Magento**

Go to **Stores → Configuration → Angeo AEO → AI Description Updater → Google Sheets Export**:

1. **Enable** → `Yes`
2. **Service Account JSON** → paste the **entire contents** of the downloaded JSON file
3. **Spreadsheet ID** → copy from the spreadsheet URL
4. **Sheet Tab Name** → `Sheet1` (or your tab name)
5. **Clear Sheet Before Write** → `Yes` to overwrite, `No` to append
6. **Save Config**

**Verify:**

```bash
bin/magento angeo:ai-description:run --sku=ONE-SKU --dry-run
```

Dry-run does not export. Disable dry-run and run again — a row should appear in the spreadsheet.

---

## Log file

```bash
# Watch in real time
tail -f var/log/angeo_ai_description_updater.log

# Last 50 lines
tail -50 var/log/angeo_ai_description_updater.log
```

Log entries include: run start/complete, per-SKU status (`updated` / `dry_run` / `error`), AI API errors, Google Sheets export result.

---

## Related modules

| Module | Purpose |
|---|---|
| [`angeo/module-aeo-audit`](https://packagist.org/packages/angeo/module-aeo-audit) | 8-signal CLI audit — checks ChatGPT, Gemini and Perplexity visibility |
| [`angeo/module-llms-txt`](https://packagist.org/packages/angeo/module-llms-txt) | Auto-generates llms.txt and llms.jsonl from Magento catalogue |
| [`angeo/module-rich-data`](https://packagist.org/packages/angeo/module-rich-data) | Injects Product, Organization, FAQPage JSON-LD schema |
| [`angeo/module-openai-product-feed`](https://packagist.org/packages/angeo/module-openai-product-feed) | ChatGPT Shopping product feed generator |
| [`angeo/module-aeo-brand-visibility`](https://packagist.org/packages/angeo/module-aeo-brand-visibility) | Live brand visibility audit across ChatGPT, Gemini, Perplexity |

---

## Security

This module treats AI output and external data sources as untrusted:

- **Stored XSS protection** — content generated for the HTML `description` attribute is passed through a whitelist HTML sanitiser before saving. Only a safe subset of formatting tags is kept; `<script>`, event-handler attributes (`onerror`, `onclick`, …) and `javascript:` URLs are stripped.
- **CSV / formula injection protection** — every cell written to a CSV export is escaped (values starting with `=`, `+`, `-`, `@` are prefixed with `'`). The Google Sheets export uses `valueInputOption=RAW`, so values are never evaluated as formulas server-side.
- **SSRF hardening** — the Google Sheets CSV fetch is restricted to `docs.google.com` over HTTPS only, with bounded redirects that may not downgrade to non-HTTPS targets. Spreadsheet/Drive IDs are validated against `[A-Za-z0-9_-]`.
- **Path-traversal protection** — the CSV filename pattern is reduced to a safe basename.
- **CSRF** — admin controllers are POST-only (`HttpPostActionInterface`), so Magento enforces the admin form key.
- **Secrets** — all API keys and the Service Account JSON are stored encrypted (`backend_model = Encrypted`) and never written to logs.
- **Error handling** — admin endpoints return generic messages; full exception detail goes to the dedicated log only.

If you discover a security issue, please email [info@angeo.dev](mailto:info@angeo.dev) rather than opening a public issue.

---

## License

MIT — free to use, modify, and distribute.

---

## Author

**Ievgenii Gryshkun** · [angeo.dev](https://angeo.dev) · [info@angeo.dev](mailto:info@angeo.dev)
