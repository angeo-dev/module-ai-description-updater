<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Angeo\AiDescriptionUpdater\Model\AttributeConfig;

class Config
{
    // ── General ──────────────────────────────────────────────────────────────
    private const XML_ENABLED    = 'angeo_ai_description/general/enabled';
    private const XML_DRY_RUN    = 'angeo_ai_description/general/dry_run';
    private const XML_LOG        = 'angeo_ai_description/general/log_enabled';
    private const XML_BATCH_SIZE = 'angeo_ai_description/general/batch_size';

    // ── AI Provider ──────────────────────────────────────────────────────────
    private const XML_PROVIDER = 'angeo_ai_description/ai_provider/provider';

    // ── OpenAI ───────────────────────────────────────────────────────────────
    private const XML_OAI_KEY         = 'angeo_ai_description/openai/api_key';
    private const XML_OAI_MODEL       = 'angeo_ai_description/openai/model';
    private const XML_OAI_TEMPERATURE = 'angeo_ai_description/openai/temperature';
    private const XML_OAI_MAX_TOKENS  = 'angeo_ai_description/openai/max_tokens';
    private const XML_OAI_TIMEOUT     = 'angeo_ai_description/openai/timeout';

    // ── Claude ───────────────────────────────────────────────────────────────
    private const XML_CLAUDE_KEY              = 'angeo_ai_description/claude/api_key';
    private const XML_CLAUDE_MODEL            = 'angeo_ai_description/claude/model';
    private const XML_CLAUDE_MAX_TOKENS       = 'angeo_ai_description/claude/max_tokens';
    private const XML_CLAUDE_TIMEOUT          = 'angeo_ai_description/claude/timeout';
    private const XML_CLAUDE_EXT_THINKING     = 'angeo_ai_description/claude/extended_thinking';
    private const XML_CLAUDE_THINKING_BUDGET  = 'angeo_ai_description/claude/thinking_budget';

    // ── Gemini ───────────────────────────────────────────────────────────────
    private const XML_GEMINI_KEY         = 'angeo_ai_description/gemini/api_key';
    private const XML_GEMINI_MODEL       = 'angeo_ai_description/gemini/model';
    private const XML_GEMINI_TEMPERATURE = 'angeo_ai_description/gemini/temperature';
    private const XML_GEMINI_MAX_TOKENS  = 'angeo_ai_description/gemini/max_tokens';
    private const XML_GEMINI_TIMEOUT     = 'angeo_ai_description/gemini/timeout';

    // ── Prompt ───────────────────────────────────────────────────────────────
    private const XML_SYSTEM_ROLE    = 'angeo_ai_description/prompt/system_role';
    private const XML_PROMPT_TPL     = 'angeo_ai_description/prompt/prompt_template';
    private const XML_LANGUAGE       = 'angeo_ai_description/prompt/language';
    private const XML_INCLUDE_SEO    = 'angeo_ai_description/prompt/include_seo';

    // ── Attributes ───────────────────────────────────────────────────────────
    private const XML_ATTR_DESC_ON        = 'angeo_ai_description/attributes/description_enabled';
    private const XML_ATTR_DESC_PROMPT    = 'angeo_ai_description/attributes/description_prompt';
    private const XML_ATTR_SHORT_ON       = 'angeo_ai_description/attributes/short_description_enabled';
    private const XML_ATTR_SHORT_PROMPT   = 'angeo_ai_description/attributes/short_description_prompt';
    private const XML_ATTR_SHORT_LEN      = 'angeo_ai_description/attributes/short_description_length';
    private const XML_ATTR_MTITLE_ON      = 'angeo_ai_description/attributes/meta_title_enabled';
    private const XML_ATTR_MTITLE_PROMPT  = 'angeo_ai_description/attributes/meta_title_prompt';
    private const XML_ATTR_MKW_ON         = 'angeo_ai_description/attributes/meta_keyword_enabled';
    private const XML_ATTR_MKW_PROMPT     = 'angeo_ai_description/attributes/meta_keyword_prompt';
    private const XML_ATTR_MDESC_ON       = 'angeo_ai_description/attributes/meta_description_enabled';
    private const XML_ATTR_MDESC_PROMPT   = 'angeo_ai_description/attributes/meta_description_prompt';

    // ── Google Sheets ────────────────────────────────────────────────────────
    private const XML_GS_ENABLED     = 'angeo_ai_description/google_sheets/enabled';
    private const XML_GS_SHEET_ID    = 'angeo_ai_description/google_sheets/spreadsheet_id';
    private const XML_GS_GID         = 'angeo_ai_description/google_sheets/gid';
    private const XML_GS_SKU_COL     = 'angeo_ai_description/google_sheets/sku_column_index';

    // ── Export ───────────────────────────────────────────────────────────────
    private const XML_EXPORT_CSV     = 'angeo_ai_description/export/csv_enabled';
    private const XML_EXPORT_FILE    = 'angeo_ai_description/export/csv_filename';
    private const XML_EXPORT_EMAIL   = 'angeo_ai_description/export/notify_email';

    // ── Google Drive ─────────────────────────────────────────────────────────
    private const XML_GD_ENABLED     = 'angeo_ai_description/google_drive/enabled';
    private const XML_GD_SA_JSON     = 'angeo_ai_description/google_drive/service_account_json';
    private const XML_GD_SPREADSHEET = 'angeo_ai_description/google_drive/spreadsheet_id';
    private const XML_GD_SHEET_NAME  = 'angeo_ai_description/google_drive/sheet_name';
    private const XML_GD_OVERWRITE   = 'angeo_ai_description/google_drive/overwrite_existing';

    // ── Cron ─────────────────────────────────────────────────────────────────
    private const XML_CRON_ENABLED   = 'angeo_ai_description/cron/enabled';

    public function __construct(
        private readonly ScopeConfigInterface  $scopeConfig,
        private readonly EncryptorInterface    $encryptor,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    // ── General ──────────────────────────────────────────────────────────────

    public function isEnabled(string $scope = ScopeInterface::SCOPE_STORE, $code = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, $scope, $code);
    }

    public function isDryRun(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_DRY_RUN);
    }

    public function isLogEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_LOG);
    }

    public function getBatchSize(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_BATCH_SIZE) ?: 50;
    }

    // ── AI Provider ──────────────────────────────────────────────────────────

    public function getAiProvider(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PROVIDER) ?: 'openai';
    }

    // ── OpenAI ───────────────────────────────────────────────────────────────

    public function getOpenAiApiKey(): string
    {
        return $this->encryptor->decrypt((string) $this->scopeConfig->getValue(self::XML_OAI_KEY));
    }

    public function getOpenAiModel(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_OAI_MODEL) ?: 'gpt-4o';
    }

    public function getOpenAiTemperature(): float
    {
        return (float) $this->scopeConfig->getValue(self::XML_OAI_TEMPERATURE) ?: 0.7;
    }

    public function getOpenAiMaxTokens(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_OAI_MAX_TOKENS) ?: 800;
    }

    public function getOpenAiTimeout(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_OAI_TIMEOUT) ?: 30;
    }

    // ── Claude ───────────────────────────────────────────────────────────────

    public function getClaudeApiKey(): string
    {
        return $this->encryptor->decrypt((string) $this->scopeConfig->getValue(self::XML_CLAUDE_KEY));
    }

    public function getClaudeModel(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_CLAUDE_MODEL) ?: 'claude-sonnet-4-6';
    }

    public function getClaudeMaxTokens(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_CLAUDE_MAX_TOKENS) ?: 1024;
    }

    public function getClaudeTimeout(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_CLAUDE_TIMEOUT) ?: 60;
    }

    public function isClaudeExtendedThinkingEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_CLAUDE_EXT_THINKING);
    }

    public function getClaudeThinkingBudget(): int
    {
        return max(1024, (int) $this->scopeConfig->getValue(self::XML_CLAUDE_THINKING_BUDGET) ?: 2048);
    }

    // ── Gemini ───────────────────────────────────────────────────────────────

    public function getGeminiApiKey(): string
    {
        return $this->encryptor->decrypt((string) $this->scopeConfig->getValue(self::XML_GEMINI_KEY));
    }

    public function getGeminiModel(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_GEMINI_MODEL) ?: 'gemini-2.0-flash';
    }

    public function getGeminiTemperature(): float
    {
        return (float) $this->scopeConfig->getValue(self::XML_GEMINI_TEMPERATURE) ?: 0.7;
    }

    public function getGeminiMaxTokens(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_GEMINI_MAX_TOKENS) ?: 1024;
    }

    public function getGeminiTimeout(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_GEMINI_TIMEOUT) ?: 30;
    }

    // ── Prompt ───────────────────────────────────────────────────────────────

    public function getSystemRole(string $scope = ScopeInterface::SCOPE_STORE, $code = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_SYSTEM_ROLE, $scope, $code)
            ?: 'You are a professional product copywriter specialising in e-commerce.';
    }

    public function getPromptTemplate(string $scope = ScopeInterface::SCOPE_STORE, $code = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PROMPT_TPL, $scope, $code);
    }

    public function getLanguage(string $scope = ScopeInterface::SCOPE_STORE, $code = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_LANGUAGE, $scope, $code) ?: 'en';
    }

    public function isIncludeSeo(string $scope = ScopeInterface::SCOPE_STORE, $code = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_INCLUDE_SEO, $scope, $code);
    }

    /**
     * Build a prompt for a specific attribute, using its override or the built-in default.
     */
    public function buildPromptForAttribute(
        AttributeConfig $attrConfig,
        string $productName,
        string $productSku,
        string $storeName = ''
    ): string {
        $template = $attrConfig->promptOverride !== ''
            ? $attrConfig->promptOverride
            : $this->defaultPromptForAttribute($attrConfig->attributeCode);

        $prompt = str_replace(
            ['{{product_name}}', '{{product_sku}}', '{{store_name}}'],
            [$productName, $productSku, $storeName],
            $template
        );

        if ($this->isIncludeSeo()) {
            $prompt .= ' Optimise for SEO.';
        }

        $lang = $this->getLanguage();
        if ($lang !== 'en') {
            $prompt .= " Write in language code: {$lang}.";
        }

        if (!$attrConfig->isHtml && $attrConfig->maxLength > 0) {
            $prompt .= " Maximum {$attrConfig->maxLength} characters. Plain text only, no HTML.";
        } elseif (!$attrConfig->isHtml) {
            $prompt .= ' Plain text only, no HTML.';
        }

        return $prompt;
    }

    private function defaultPromptForAttribute(string $attributeCode): string
    {
        return match ($attributeCode) {
            'description'       => 'Write a professional and SEO-friendly product description for "{{product_name}}". Include key benefits, features, and use cases. Format using HTML paragraphs.',
            'short_description' => 'Write a concise and compelling short product description for "{{product_name}}" in plain text. No HTML. Focus on the main benefit.',
            'meta_title'        => 'Write an SEO meta title for "{{product_name}}". Plain text only. Maximum 60 characters. Include the product name and a key benefit.',
            'meta_keyword'      => 'Generate a comma-separated list of 8–12 SEO keywords for "{{product_name}}". Plain text only.',
            'meta_description'  => 'Write an SEO meta description for "{{product_name}}". Plain text only. Maximum 160 characters. Compelling and keyword-rich.',
            default             => 'Write content for the "{{product_name}}" product attribute: ' . $attributeCode . '.',
        };
    }

    // ── Attributes ───────────────────────────────────────────────────────────

    /**
     * @return AttributeConfig[]
     */
    public function getEnabledAttributes(
        string $scope = ScopeInterface::SCOPE_STORE,
        $code = null
    ): array {
        $attrs = [];

        if ($this->scopeConfig->isSetFlag(self::XML_ATTR_DESC_ON, $scope, $code)) {
            $attrs[] = new AttributeConfig(
                attributeCode:  'description',
                promptOverride: (string) $this->scopeConfig->getValue(self::XML_ATTR_DESC_PROMPT, $scope, $code),
                maxLength:      0,
                isHtml:         true,
            );
        }

        if ($this->scopeConfig->isSetFlag(self::XML_ATTR_SHORT_ON, $scope, $code)) {
            $attrs[] = new AttributeConfig(
                attributeCode:  'short_description',
                promptOverride: (string) $this->scopeConfig->getValue(self::XML_ATTR_SHORT_PROMPT, $scope, $code),
                maxLength:      (int) $this->scopeConfig->getValue(self::XML_ATTR_SHORT_LEN, $scope, $code) ?: 150,
                isHtml:         false,
            );
        }

        if ($this->scopeConfig->isSetFlag(self::XML_ATTR_MTITLE_ON, $scope, $code)) {
            $attrs[] = new AttributeConfig(
                attributeCode:  'meta_title',
                promptOverride: (string) $this->scopeConfig->getValue(self::XML_ATTR_MTITLE_PROMPT, $scope, $code),
                maxLength:      60,
                isHtml:         false,
            );
        }

        if ($this->scopeConfig->isSetFlag(self::XML_ATTR_MKW_ON, $scope, $code)) {
            $attrs[] = new AttributeConfig(
                attributeCode:  'meta_keyword',
                promptOverride: (string) $this->scopeConfig->getValue(self::XML_ATTR_MKW_PROMPT, $scope, $code),
                maxLength:      255,
                isHtml:         false,
            );
        }

        if ($this->scopeConfig->isSetFlag(self::XML_ATTR_MDESC_ON, $scope, $code)) {
            $attrs[] = new AttributeConfig(
                attributeCode:  'meta_description',
                promptOverride: (string) $this->scopeConfig->getValue(self::XML_ATTR_MDESC_PROMPT, $scope, $code),
                maxLength:      160,
                isHtml:         false,
            );
        }

        return $attrs;
    }

    // ── Google Sheets ────────────────────────────────────────────────────────

    public function isGoogleSheetsEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_GS_ENABLED);
    }

    public function getSpreadsheetId(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_GS_SHEET_ID);
    }

    public function getSheetGid(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_GS_GID) ?: '0';
    }

    public function getSkuColumnIndex(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_GS_SKU_COL);
    }

    public function getGoogleSheetCsvUrl(): string
    {
        $id  = $this->getSpreadsheetId();
        $gid = $this->getSheetGid();
        return "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&id={$id}&gid={$gid}";
    }

    // ── Export ───────────────────────────────────────────────────────────────

    public function isCsvExportEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_EXPORT_CSV);
    }

    public function getCsvFilename(): string
    {
        $pattern = (string) $this->scopeConfig->getValue(self::XML_EXPORT_FILE) ?: 'ai_descriptions_{{date}}.csv';
        return str_replace('{{date}}', date('Y-m-d_His'), $pattern);
    }

    public function getNotifyEmail(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_EXPORT_EMAIL);
    }

    // ── Google Sheets Export ─────────────────────────────────────────────────

    public function isGoogleDriveEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_GD_ENABLED);
    }

    public function getGoogleDriveServiceAccountJson(): string
    {
        return $this->encryptor->decrypt((string) $this->scopeConfig->getValue(self::XML_GD_SA_JSON));
    }

    public function getGoogleSheetsSpreadsheetId(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_GD_SPREADSHEET);
    }

    public function getGoogleSheetsSheetName(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_GD_SHEET_NAME) ?: 'Sheet1');
    }

    public function isGoogleDriveOverwriteExisting(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_GD_OVERWRITE);
    }

    // ── Cron ─────────────────────────────────────────────────────────────────

    public function isCronEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_CRON_ENABLED);
    }

    // ── Groq ─────────────────────────────────────────────────────────────────

    private const XML_GROQ_KEY         = 'angeo_ai_description/groq/api_key';
    private const XML_GROQ_MODEL       = 'angeo_ai_description/groq/model';
    private const XML_GROQ_TEMPERATURE = 'angeo_ai_description/groq/temperature';
    private const XML_GROQ_MAX_TOKENS  = 'angeo_ai_description/groq/max_tokens';
    private const XML_GROQ_TIMEOUT     = 'angeo_ai_description/groq/timeout';

    public function getGroqApiKey(): string
    {
        return $this->encryptor->decrypt(
            (string) $this->scopeConfig->getValue(self::XML_GROQ_KEY)
        );
    }

    public function getGroqModel(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_GROQ_MODEL) ?: 'llama-3.3-70b-versatile';
    }

    public function getGroqTemperature(): float
    {
        return (float) ($this->scopeConfig->getValue(self::XML_GROQ_TEMPERATURE) ?? 0.7);
    }

    public function getGroqMaxTokens(): int
    {
        return (int) ($this->scopeConfig->getValue(self::XML_GROQ_MAX_TOKENS) ?? 1024);
    }

    public function getGroqTimeout(): int
    {
        return (int) ($this->scopeConfig->getValue(self::XML_GROQ_TIMEOUT) ?? 30);
    }
}
