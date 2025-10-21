<?php
declare(strict_types=1);

namespace Corals\SMTP\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config extends AbstractHelper
{
    const XML_PATH_ENABLED = 'corals_smtp/general/enabled';
    const XML_PATH_PROVIDER = 'corals_smtp/general/provider';
    const XML_PATH_HOST = 'corals_smtp/general/custom_host';
    const XML_PATH_PORT = 'corals_smtp/general/custom_port';
    const XML_PATH_AUTHENTICATION = 'corals_smtp/general/authentication';
    const XML_PATH_USERNAME = 'corals_smtp/general/username';
    const XML_PATH_PASSWORD = 'corals_smtp/general/password';
    const XML_PATH_ENCRYPTION = 'corals_smtp/general/encryption';
    const XML_PATH_FROM_EMAIL = 'corals_smtp/general/from_email';
    const XML_PATH_FROM_NAME = 'corals_smtp/general/from_name';
    const XML_PATH_REPLY_TO = 'corals_smtp/general/reply_to';
    
    const XML_PATH_QUEUE_ENABLED = 'corals_smtp/queue/enabled';
    const XML_PATH_QUEUE_BATCH_SIZE = 'corals_smtp/queue/batch_size';
    const XML_PATH_QUEUE_RETRY_ATTEMPTS = 'corals_smtp/queue/retry_attempts';
    const XML_PATH_QUEUE_RETRY_DELAY = 'corals_smtp/queue/retry_delay';
    
    const XML_PATH_TRACKING_ENABLED = 'corals_smtp/tracking/enabled';
    const XML_PATH_TRACKING_OPENS = 'corals_smtp/tracking/track_opens';
    const XML_PATH_TRACKING_CLICKS = 'corals_smtp/tracking/track_clicks';
    const XML_PATH_TRACKING_BOUNCES = 'corals_smtp/tracking/track_bounces';
    
    const XML_PATH_DKIM_ENABLED = 'corals_smtp/security/dkim_enabled';
    const XML_PATH_DKIM_DOMAIN = 'corals_smtp/security/dkim_domain';
    const XML_PATH_DKIM_SELECTOR = 'corals_smtp/security/dkim_selector';
    const XML_PATH_DKIM_PRIVATE_KEY = 'corals_smtp/security/dkim_private_key';
    const XML_PATH_SPF_CHECK = 'corals_smtp/security/spf_check';
    const XML_PATH_DMARC_CHECK = 'corals_smtp/security/dmarc_check';
    
    const XML_PATH_LOGGING_ENABLED = 'corals_smtp/logging/enabled';
    const XML_PATH_LOGGING_LEVEL = 'corals_smtp/logging/log_level';
    const XML_PATH_LOGGING_RETENTION = 'corals_smtp/logging/retention_days';
    const XML_PATH_DEBUG_MODE = 'corals_smtp/logging/debug_mode';
    
    const XML_PATH_RATE_LIMIT = 'corals_smtp/advanced/rate_limit';
    const XML_PATH_CONNECTION_TIMEOUT = 'corals_smtp/advanced/connection_timeout';
    const XML_PATH_BLACKLIST = 'corals_smtp/advanced/blacklist';
    const XML_PATH_WHITELIST = 'corals_smtp/advanced/whitelist';
    const XML_PATH_FALLBACK_ENABLED = 'corals_smtp/advanced/fallback_enabled';
    const XML_PATH_FALLBACK_PROVIDER = 'corals_smtp/advanced/fallback_provider';

    private EncryptorInterface $encryptor;
    private ?string $currentProvider = null;

    public function __construct(
        Context $context,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
    }

    public function isEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProvider($storeId = null): string
    {
        if ($this->currentProvider !== null) {
            return $this->currentProvider;
        }
        
        return $this->scopeConfig->getValue(
            self::XML_PATH_PROVIDER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? 'custom';
    }

    public function setProvider(string $provider): void
    {
        $this->currentProvider = $provider;
    }

    public function getHost($storeId = null): string
    {
        $provider = $this->getProvider($storeId);
        
        $providerHosts = [
            'gmail' => 'smtp.gmail.com',
            'outlook' => 'smtp-mail.outlook.com',
            'office365' => 'smtp.office365.com',
            'yahoo' => 'smtp.mail.yahoo.com',
            'sendgrid' => 'smtp.sendgrid.net',
            'mailgun' => 'smtp.mailgun.org',
            'amazon_ses' => 'email-smtp.us-east-1.amazonaws.com',
            'postmark' => 'smtp.postmarkapp.com',
            'sparkpost' => 'smtp.sparkpostmail.com',
            'mailjet' => 'in-v3.mailjet.com',
            'sendinblue' => 'smtp-relay.sendinblue.com',
            'mandrill' => 'smtp.mandrillapp.com',
            'smtp2go' => 'mail.smtp2go.com',
            'elastic_email' => 'smtp.elasticemail.com',
            'mailersend' => 'smtp.mailersend.net',
            'socketlabs' => 'smtp.socketlabs.com',
            'sendpulse' => 'smtp-pulse.com',
            'pepipost' => 'smtp.pepipost.com',
            'turbo_smtp' => 'pro.turbo-smtp.com',
            'mailchimp' => 'smtp.mandrillapp.com',
            'zoho' => 'smtp.zoho.com',
            'fastmail' => 'smtp.fastmail.com',
            'icloud' => 'smtp.mail.me.com',
            'protonmail' => '127.0.0.1',
            'yandex' => 'smtp.yandex.com'
        ];
        
        if (isset($providerHosts[$provider])) {
            return $providerHosts[$provider];
        }
        
        return $this->scopeConfig->getValue(
            self::XML_PATH_HOST,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '';
    }

    public function getPort($storeId = null): int
    {
        $provider = $this->getProvider($storeId);
        
        $providerPorts = [
            'gmail' => 587,
            'outlook' => 587,
            'office365' => 587,
            'yahoo' => 587,
            'sendgrid' => 587,
            'mailgun' => 587,
            'amazon_ses' => 587,
            'postmark' => 587,
            'sparkpost' => 587,
            'mailjet' => 587,
            'sendinblue' => 587,
            'mandrill' => 587,
            'smtp2go' => 2525,
            'elastic_email' => 2525,
            'mailersend' => 587,
            'socketlabs' => 2525,
            'sendpulse' => 587,
            'pepipost' => 587,
            'turbo_smtp' => 587,
            'mailchimp' => 587,
            'zoho' => 587,
            'fastmail' => 587,
            'icloud' => 587,
            'protonmail' => 1025,
            'yandex' => 587
        ];
        
        if (isset($providerPorts[$provider])) {
            return $providerPorts[$provider];
        }
        
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_PORT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 587;
    }

    public function getAuthentication($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_AUTHENTICATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? 'login';
    }

    public function getUsername($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_USERNAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '';
    }

    public function getPassword($storeId = null): string
    {
        $encrypted = $this->scopeConfig->getValue(
            self::XML_PATH_PASSWORD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $encrypted ? $this->encryptor->decrypt($encrypted) : '';
    }

    public function getEncryption($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_ENCRYPTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? 'tls';
    }

    public function getFromEmail($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_FROM_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '';
    }

    public function getFromName($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_FROM_NAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '';
    }

    public function getReplyTo($storeId = null): ?string
    {
        $replyTo = $this->scopeConfig->getValue(
            self::XML_PATH_REPLY_TO,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $replyTo ?: null;
    }

    public function isQueueEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_QUEUE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getQueueBatchSize($storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_QUEUE_BATCH_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 50;
    }

    public function getRetryAttempts($storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_QUEUE_RETRY_ATTEMPTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 3;
    }

    public function getRetryDelay($storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_QUEUE_RETRY_DELAY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 5;
    }

    public function isTrackingEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACKING_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isTrackOpensEnabled($storeId = null): bool
    {
        return $this->isTrackingEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACKING_OPENS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isTrackClicksEnabled($storeId = null): bool
    {
        return $this->isTrackingEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACKING_CLICKS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isTrackBouncesEnabled($storeId = null): bool
    {
        return $this->isTrackingEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACKING_BOUNCES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDkimEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DKIM_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getDkimDomain($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_DKIM_DOMAIN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? $this->getFromDomain();
    }

    public function getDkimSelector($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_DKIM_SELECTOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? 'default';
    }

    public function getDkimPrivateKey($storeId = null): string
    {
        $encrypted = $this->scopeConfig->getValue(
            self::XML_PATH_DKIM_PRIVATE_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $encrypted ? $this->encryptor->decrypt($encrypted) : '';
    }

    public function isSpfCheckEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SPF_CHECK,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDmarcCheckEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DMARC_CHECK,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isLoggingEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_LOGGING_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getLogLevel($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_LOGGING_LEVEL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? 'info';
    }

    public function getLogRetentionDays($storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_LOGGING_RETENTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 30;
    }

    public function isDebugMode($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getRateLimit($storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_RATE_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 0;
    }

    public function getConnectionTimeout($storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_CONNECTION_TIMEOUT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 30;
    }

    public function getBlacklist($storeId = null): array
    {
        $blacklist = $this->scopeConfig->getValue(
            self::XML_PATH_BLACKLIST,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $blacklist ? array_map('trim', explode(',', $blacklist)) : [];
    }

    public function getWhitelist($storeId = null): array
    {
        $whitelist = $this->scopeConfig->getValue(
            self::XML_PATH_WHITELIST,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $whitelist ? array_map('trim', explode(',', $whitelist)) : [];
    }

    public function isFallbackEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FALLBACK_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getFallbackProvider($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_FALLBACK_PROVIDER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? 'custom';
    }

    public function getFromDomain(): string
    {
        $fromEmail = $this->getFromEmail();
        if ($fromEmail && strpos($fromEmail, '@') !== false) {
            return substr($fromEmail, strpos($fromEmail, '@') + 1);
        }
        return $_SERVER['HTTP_HOST'] ?? 'localhost';
    }

    public function getOAuth2Provider(): string
    {
        return 'League\\OAuth2\\Client\\Provider\\Google';
    }

    public function getOAuth2ClientId($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            'corals_smtp/oauth2/client_id',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '';
    }

    public function getOAuth2ClientSecret($storeId = null): string
    {
        $encrypted = $this->scopeConfig->getValue(
            'corals_smtp/oauth2/client_secret',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $encrypted ? $this->encryptor->decrypt($encrypted) : '';
    }

    public function getOAuth2RefreshToken($storeId = null): string
    {
        $encrypted = $this->scopeConfig->getValue(
            'corals_smtp/oauth2/refresh_token',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $encrypted ? $this->encryptor->decrypt($encrypted) : '';
    }
}