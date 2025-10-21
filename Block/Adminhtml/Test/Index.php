<?php
declare(strict_types=1);

namespace Corals\SMTP\Block\Adminhtml\Test;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Corals\SMTP\Helper\Config;

class Index extends Template
{
    protected Config $config;

    public function __construct(
        Context $context,
        Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Get SMTP configuration status
     */
    public function isSmtpEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Get current provider
     */
    public function getCurrentProvider(): string
    {
        return $this->config->getProvider() ?: 'not_configured';
    }

    /**
     * Get provider display name
     */
    public function getProviderDisplayName(): string
    {
        $provider = $this->getCurrentProvider();
        $providers = [
            'gmail' => 'Gmail',
            'sendgrid' => 'SendGrid',
            'mailgun' => 'Mailgun',
            'amazon_ses' => 'Amazon SES',
            'postmark' => 'Postmark',
            'custom' => 'Custom SMTP',
            'not_configured' => 'Not Configured'
        ];
        
        return $providers[$provider] ?? $provider;
    }

    /**
     * Get SMTP host
     */
    public function getSmtpHost(): string
    {
        return $this->config->getHost() ?: 'Not configured';
    }

    /**
     * Get SMTP port
     */
    public function getSmtpPort(): string
    {
        $port = $this->config->getPort();
        return $port ? (string)$port : 'Not configured';
    }

    /**
     * Get configuration URL
     */
    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit/section/corals_smtp');
    }

    /**
     * Get test email send URL
     */
    public function getTestEmailUrl(): string
    {
        return $this->getUrl('smtp-pro/test/send');
    }

    /**
     * Get dashboard URL
     */
    public function getDashboardUrl(): string
    {
        return $this->getUrl('smtp-pro/dashboard/index');
    }
}