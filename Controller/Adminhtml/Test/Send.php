<?php
declare(strict_types=1);

namespace Corals\SMTP\Controller\Adminhtml\Test;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Corals\SMTP\Helper\Config;
use Psr\Log\LoggerInterface;
use Magento\Framework\Mail\MessageInterface;

class Send extends Action
{
    const ADMIN_RESOURCE = 'Corals_SMTP::test';

    private JsonFactory $resultJsonFactory;
    private TransportBuilder $transportBuilder;
    private StateInterface $inlineTranslation;
    private StoreManagerInterface $storeManager;
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        
        try {
            $testEmail = $this->getRequest()->getParam('test_email');
            
            if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Please provide a valid email address.')
                ]);
            }

            if (!$this->config->isEnabled()) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('SMTP is not enabled. Please enable SMTP in configuration first.')
                ]);
            }

            $this->sendTestEmail($testEmail);
            
            return $resultJson->setData([
                'success' => true,
                'message' => __('Test email sent successfully to %1', $testEmail)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('SMTP Test Email Error: ' . $e->getMessage());
            
            // Provide more meaningful error messages
            $errorMessage = $this->getHumanReadableErrorMessage($e);
            
            return $resultJson->setData([
                'success' => false,
                'message' => $errorMessage,
                'technical_details' => $e->getMessage() // For debugging
            ]);
        }
    }

    private function sendTestEmail(string $testEmail): void
    {
        $this->inlineTranslation->suspend();
        
        try {
            $this->sendSimpleTestEmail($testEmail);
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    private function sendSimpleTestEmail(string $testEmail): void
    {
        $store = $this->storeManager->getStore();
        $fromEmail = $this->config->getFromEmail() ?: 'noreply@' . parse_url($store->getBaseUrl(), PHP_URL_HOST);
        $fromName = $this->config->getFromName() ?: $store->getName();
        
        $subject = 'SMTP Test Email from ' . $store->getName();
        
        // Create a simple HTML email
        $htmlBody = "<html><body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>";
        $htmlBody .= "<div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 20px;'>";
        $htmlBody .= "<h1 style='margin: 0; font-size: 24px;'>🚀 SMTP Test Email</h1>";
        $htmlBody .= "<p style='margin: 10px 0 0 0; opacity: 0.9;'>Sent from " . htmlspecialchars($store->getName()) . "</p>";
        $htmlBody .= "</div>";
        
        $htmlBody .= "<div style='background: #f8fafc; padding: 30px; border-radius: 10px; margin-bottom: 20px;'>";
        $htmlBody .= "<h2 style='color: #374151; margin-top: 0;'>✅ Configuration Test Successful!</h2>";
        $htmlBody .= "<p style='color: #6b7280; line-height: 1.6;'>This test email was sent successfully using <strong>Corals SMTP Pro</strong>. Your SMTP configuration is working correctly.</p>";
        $htmlBody .= "</div>";
        
        $htmlBody .= "<div style='background: white; border: 1px solid #e5e7eb; padding: 25px; border-radius: 10px; margin-bottom: 20px;'>";
        $htmlBody .= "<h3 style='color: #374151; margin-top: 0; margin-bottom: 15px;'>📊 Configuration Details</h3>";
        $htmlBody .= "<table style='width: 100%; border-collapse: collapse;'>";
        $htmlBody .= "<tr><td style='padding: 8px 0; color: #6b7280; font-weight: 600;'>Provider:</td><td style='padding: 8px 0; color: #111827;'>" . htmlspecialchars($this->config->getProvider() ?: 'Default') . "</td></tr>";
        $htmlBody .= "<tr><td style='padding: 8px 0; color: #6b7280; font-weight: 600;'>SMTP Host:</td><td style='padding: 8px 0; color: #111827;'>" . htmlspecialchars($this->config->getHost() ?: 'Default') . "</td></tr>";
        $htmlBody .= "<tr><td style='padding: 8px 0; color: #6b7280; font-weight: 600;'>SMTP Port:</td><td style='padding: 8px 0; color: #111827;'>" . htmlspecialchars((string)($this->config->getPort() ?: 'Default')) . "</td></tr>";
        $htmlBody .= "<tr><td style='padding: 8px 0; color: #6b7280; font-weight: 600;'>Encryption:</td><td style='padding: 8px 0; color: #111827;'>" . htmlspecialchars($this->config->getEncryption() ?: 'None') . "</td></tr>";
        $htmlBody .= "<tr><td style='padding: 8px 0; color: #6b7280; font-weight: 600;'>Sent Time:</td><td style='padding: 8px 0; color: #111827;'>" . date('Y-m-d H:i:s') . "</td></tr>";
        $htmlBody .= "</table>";
        $htmlBody .= "</div>";
        
        $htmlBody .= "<div style='background: #10b981; color: white; padding: 20px; border-radius: 10px; text-align: center;'>";
        $htmlBody .= "<p style='margin: 0; font-weight: 600; font-size: 16px;'>🎉 Test Complete - Your SMTP is working perfectly!</p>";
        $htmlBody .= "</div>";
        
        $htmlBody .= "<div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;'>";
        $htmlBody .= "<p style='color: #9ca3af; font-size: 12px; margin: 0;'>Powered by Corals SMTP Pro for Magento 2</p>";
        $htmlBody .= "</div>";
        
        $htmlBody .= "</body></html>";

        // Create basic text email content as template variables
        $templateVars = [
            'subject' => $subject,
            'content' => $htmlBody,
            'store_name' => $store->getName(),
            'provider' => $this->config->getProvider() ?: 'Default',
            'host' => $this->config->getHost() ?: 'Default',
            'port' => $this->config->getPort() ?: 'Default',
            'encryption' => $this->config->getEncryption() ?: 'None',
            'sent_time' => date('Y-m-d H:i:s')
        ];

        // Skip template approach and directly use basic email sending
        $this->sendBasicTestEmail($testEmail, $subject, $htmlBody, $fromEmail, $fromName);
    }

    private function sendBasicTestEmail(string $testEmail, string $subject, string $htmlBody, string $fromEmail, string $fromName): void
    {
        // Create a very simple text version
        $textBody = "SMTP Test Email from " . $this->storeManager->getStore()->getName() . "\n\n";
        $textBody .= "Configuration Test Successful!\n";
        $textBody .= "This test email was sent successfully using Corals SMTP Pro.\n\n";
        $textBody .= "Configuration Details:\n";
        $textBody .= "Provider: " . ($this->config->getProvider() ?: 'Default') . "\n";
        $textBody .= "SMTP Host: " . ($this->config->getHost() ?: 'Default') . "\n";
        $textBody .= "SMTP Port: " . ($this->config->getPort() ?: 'Default') . "\n";
        $textBody .= "Encryption: " . ($this->config->getEncryption() ?: 'None') . "\n";
        $textBody .= "Sent Time: " . date('Y-m-d H:i:s') . "\n\n";
        $textBody .= "Test Complete - Your SMTP is working perfectly!\n";
        $textBody .= "Powered by Corals SMTP Pro for Magento 2";

        // Use the new MailSender class with PHPMailer
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $mailSender = $objectManager->get(\Corals\SMTP\Model\MailSender::class);
        
        $mailSender->sendMail(
            $testEmail,
            $subject,
            $htmlBody,
            $textBody,
            $fromEmail,
            $fromName
        );
    }

    /**
     * Convert technical error messages to user-friendly ones
     */
    private function getHumanReadableErrorMessage(\Exception $e): string
    {
        $message = $e->getMessage();
        $lowerMessage = strtolower($message);

        // SMTP Connection errors
        if (strpos($lowerMessage, 'connection refused') !== false || 
            strpos($lowerMessage, 'connection timed out') !== false) {
            return (string)__('❌ Cannot connect to SMTP server. Please check your host and port settings.');
        }

        // Authentication errors
        if (strpos($lowerMessage, 'authentication failed') !== false ||
            strpos($lowerMessage, 'invalid credentials') !== false ||
            strpos($lowerMessage, 'username and password not accepted') !== false ||
            strpos($lowerMessage, '535') !== false) {
            return (string)__('🔐 SMTP authentication failed. Please check your username and password.');
        }

        // SSL/TLS errors
        if (strpos($lowerMessage, 'ssl') !== false || 
            strpos($lowerMessage, 'tls') !== false ||
            strpos($lowerMessage, 'certificate') !== false) {
            return (string)__('🔒 SSL/TLS connection error. Please check your encryption settings or try a different encryption method.');
        }

        // Port errors
        if (strpos($lowerMessage, 'port') !== false) {
            return (string)__('🔌 Cannot connect to the specified port. Please verify your SMTP port setting (common ports: 587, 465, 25).');
        }

        // Host resolution errors
        if (strpos($lowerMessage, 'could not resolve host') !== false ||
            strpos($lowerMessage, 'name resolution') !== false) {
            return (string)__('🌐 Cannot resolve SMTP host. Please check your SMTP host setting.');
        }

        // Rate limiting
        if (strpos($lowerMessage, 'rate limit') !== false ||
            strpos($lowerMessage, 'too many') !== false) {
            return (string)__('⏱️ Rate limit exceeded. Please wait a moment before sending another test email.');
        }

        // Quota exceeded
        if (strpos($lowerMessage, 'quota') !== false ||
            strpos($lowerMessage, 'limit exceeded') !== false) {
            return (string)__('📊 Email quota exceeded. Please check your email service provider limits.');
        }

        // Invalid recipient
        if (strpos($lowerMessage, 'invalid recipient') !== false ||
            strpos($lowerMessage, 'mailbox unavailable') !== false) {
            return (string)__('📧 Invalid recipient email address. Please check the email address you entered.');
        }

        // SMTP not enabled
        if (strpos($lowerMessage, 'smtp is not enabled') !== false) {
            return (string)__('⚙️ SMTP is disabled. Please enable SMTP in the configuration first.');
        }

        // Template errors
        if (strpos($lowerMessage, 'template') !== false) {
            return (string)__('📄 Email template error. The system is trying to send a basic text email instead.');
        }

        // Zend Mail errors
        if (strpos($lowerMessage, 'zend_mail') !== false) {
            return (string)__('📮 Email sending library error. Please check your SMTP configuration settings.');
        }

        // Configuration errors
        if (strpos($lowerMessage, 'configuration') !== false) {
            return (string)__('⚙️ SMTP configuration error. Please review your SMTP settings.');
        }

        // Network errors
        if (strpos($lowerMessage, 'network') !== false ||
            strpos($lowerMessage, 'socket') !== false) {
            return (string)__('🌐 Network connectivity issue. Please check your internet connection and firewall settings.');
        }

        // Gmail specific errors
        if (strpos($lowerMessage, 'gmail') !== false) {
            if (strpos($lowerMessage, 'app password') !== false || strpos($lowerMessage, 'less secure') !== false) {
                return (string)__('🔑 Gmail authentication issue. Please use an App Password instead of your regular password, or enable 2-factor authentication.');
            }
        }

        // Generic SMTP errors with helpful suggestions
        if (strpos($lowerMessage, 'smtp') !== false) {
            return (string)__('📧 SMTP error occurred. Please verify your SMTP settings: host, port, authentication method, and credentials.');
        }

        // If no specific error pattern matches, provide a helpful generic message
        return __('❌ Failed to send test email. Common solutions:') . "\n\n" .
               __('• Check SMTP host and port settings') . "\n" .
               __('• Verify username and password') . "\n" .
               __('• Ensure correct encryption method (SSL/TLS)') . "\n" .
               __('• Check if SMTP is enabled') . "\n\n" .
               __('Technical details: %1', $message);
    }
}