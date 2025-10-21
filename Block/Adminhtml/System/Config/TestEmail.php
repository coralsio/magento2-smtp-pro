<?php
declare(strict_types=1);

namespace Corals\SMTP\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Corals\SMTP\Helper\Config;

class TestEmail extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Corals_SMTP::system/config/test_email.phtml';
    
    protected Config $config;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for test email button
     *
     * @return string
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('corals_smtp/test/send');
    }

    /**
     * Generate test email button html
     *
     * @return string
     */
    public function getButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData(
            [
                'id' => 'test_email_button',
                'label' => __('Send Test Email'),
                'class' => 'primary smtp-test-email-btn',
                'onclick' => 'javascript:testEmailConnection(); return false;',
                'style' => 'margin-top: 5px;'
            ]
        );

        return $button->toHtml();
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
}