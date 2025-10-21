<?php
declare(strict_types=1);

namespace Corals\SMTP\Model;

use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Phrase;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Psr\Log\LoggerInterface;
use Corals\SMTP\Helper\Config;
use Corals\SMTP\Model\Email\Queue;
use Corals\SMTP\Model\Email\Logger as EmailLogger;
use Corals\SMTP\Model\Email\Tracker;
use Corals\SMTP\Model\Security\DkimSigner;
use Corals\SMTP\Model\Security\SpfValidator;
use Corals\SMTP\Model\Security\DmarcValidator;

class Transport implements TransportInterface
{
    private EmailMessageInterface $message;
    private Config $config;
    private LoggerInterface $logger;
    private Queue $queue;
    private EmailLogger $emailLogger;
    private Tracker $tracker;
    private DkimSigner $dkimSigner;
    private SpfValidator $spfValidator;
    private DmarcValidator $dmarcValidator;
    private array $providers;
    private ?PHPMailer $mailer = null;

    public function __construct(
        EmailMessageInterface $message,
        Config $config,
        LoggerInterface $logger,
        Queue $queue,
        EmailLogger $emailLogger,
        Tracker $tracker,
        DkimSigner $dkimSigner,
        SpfValidator $spfValidator,
        DmarcValidator $dmarcValidator,
        array $providers = []
    ) {
        $this->message = $message;
        $this->config = $config;
        $this->logger = $logger;
        $this->queue = $queue;
        $this->emailLogger = $emailLogger;
        $this->tracker = $tracker;
        $this->dkimSigner = $dkimSigner;
        $this->spfValidator = $spfValidator;
        $this->dmarcValidator = $dmarcValidator;
        $this->providers = $providers;
    }

    public function sendMessage(): void
    {
        if (!$this->config->isEnabled()) {
            $this->sendDefault();
            return;
        }

        if ($this->config->isQueueEnabled()) {
            $this->queueEmail();
            return;
        }

        try {
            $this->send();
        } catch (\Exception $e) {
            if ($this->config->isFallbackEnabled()) {
                $this->sendWithFallback();
            } else {
                throw new MailException(new Phrase($e->getMessage()), $e);
            }
        }
    }

    private function send(): void
    {
        $this->initializeMailer();
        
        $this->applySecuritySettings();
        
        $this->applyRateLimiting();
        
        $this->applyBlacklistWhitelist();
        
        $this->setRecipients();
        $this->setContent();
        $this->setHeaders();
        
        if ($this->config->isTrackingEnabled()) {
            $this->applyTracking();
        }
        
        $startTime = microtime(true);
        
        try {
            if (!$this->mailer->send()) {
                throw new MailException(new Phrase('Failed to send email: ' . $this->mailer->ErrorInfo));
            }
            
            $this->logSuccess($startTime);
            
            if ($this->config->isTrackingEnabled()) {
                $this->tracker->trackSent($this->message);
            }
        } catch (PHPMailerException $e) {
            $this->logFailure($e, $startTime);
            throw new MailException(new Phrase($e->getMessage()), $e);
        }
    }

    private function initializeMailer(): void
    {
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        
        $provider = $this->config->getProvider();
        
        if (isset($this->providers[$provider])) {
            $this->providers[$provider]->configure($this->mailer);
        } else {
            $this->configureCustomProvider();
        }
        
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->config->getUsername();
        $this->mailer->Password = $this->config->getPassword();
        
        $encryption = $this->config->getEncryption();
        if ($encryption !== 'none') {
            $this->mailer->SMTPSecure = $encryption;
        }
        
        $this->mailer->Timeout = $this->config->getConnectionTimeout();
        $this->mailer->SMTPDebug = $this->config->isDebugMode() ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
        
        $this->mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ];
    }

    private function configureCustomProvider(): void
    {
        $this->mailer->Host = $this->config->getHost();
        $this->mailer->Port = $this->config->getPort();
        
        $authMethod = $this->config->getAuthentication();
        switch ($authMethod) {
            case 'plain':
                $this->mailer->AuthType = 'PLAIN';
                break;
            case 'cram-md5':
                $this->mailer->AuthType = 'CRAM-MD5';
                break;
            case 'oauth2':
                $this->configureOAuth2();
                break;
            default:
                $this->mailer->AuthType = 'LOGIN';
        }
    }

    private function configureOAuth2(): void
    {
        $oauth2Provider = $this->config->getOAuth2Provider();
        $this->mailer->setOAuth(
            new \PHPMailer\PHPMailer\OAuth([
                'provider' => $oauth2Provider,
                'clientId' => $this->config->getOAuth2ClientId(),
                'clientSecret' => $this->config->getOAuth2ClientSecret(),
                'refreshToken' => $this->config->getOAuth2RefreshToken(),
                'userName' => $this->config->getUsername()
            ])
        );
    }

    private function applySecuritySettings(): void
    {
        if ($this->config->isDkimEnabled()) {
            $this->dkimSigner->sign($this->mailer);
        }
        
        if ($this->config->isSpfCheckEnabled()) {
            $this->spfValidator->validate($this->config->getFromEmail());
        }
        
        if ($this->config->isDmarcCheckEnabled()) {
            $this->dmarcValidator->validate(
                $this->config->getFromEmail(),
                $this->config->getDkimEnabled(),
                $this->config->getSpfCheckEnabled()
            );
        }
    }

    private function applyRateLimiting(): void
    {
        $rateLimit = $this->config->getRateLimit();
        if ($rateLimit > 0) {
            $sentCount = $this->emailLogger->getHourlyCount();
            if ($sentCount >= $rateLimit) {
                throw new MailException(
                    new Phrase('Rate limit exceeded. Maximum %1 emails per hour.', [$rateLimit])
                );
            }
        }
    }

    private function applyBlacklistWhitelist(): void
    {
        $recipients = $this->getRecipientAddresses();
        $blacklist = $this->config->getBlacklist();
        $whitelist = $this->config->getWhitelist();
        
        foreach ($recipients as $email) {
            if ($this->isBlacklisted($email, $blacklist) && !$this->isWhitelisted($email, $whitelist)) {
                throw new MailException(
                    new Phrase('Email address %1 is blacklisted', [$email])
                );
            }
        }
    }

    private function isBlacklisted(string $email, array $blacklist): bool
    {
        foreach ($blacklist as $pattern) {
            if (fnmatch($pattern, $email)) {
                return true;
            }
        }
        return false;
    }

    private function isWhitelisted(string $email, array $whitelist): bool
    {
        foreach ($whitelist as $pattern) {
            if (fnmatch($pattern, $email)) {
                return true;
            }
        }
        return false;
    }

    private function setRecipients(): void
    {
        $this->mailer->setFrom(
            $this->config->getFromEmail(),
            $this->config->getFromName()
        );
        
        if ($replyTo = $this->config->getReplyTo()) {
            $this->mailer->addReplyTo($replyTo);
        }
        
        foreach ($this->message->getTo() as $address) {
            $email = $address->getEmail();
            $name = $address->getName() ?? '';
            $this->mailer->addAddress($email, $name);
        }
        
        foreach ($this->message->getCc() as $address) {
            $this->mailer->addCC($address->getEmail(), $address->getName() ?? '');
        }
        
        foreach ($this->message->getBcc() as $address) {
            $this->mailer->addBCC($address->getEmail(), $address->getName() ?? '');
        }
    }

    private function setContent(): void
    {
        $this->mailer->Subject = $this->message->getSubject();
        
        $body = $this->message->getBody();
        if ($body) {
            $parts = $body->getParts();
            $htmlContent = null;
            $textContent = null;
            
            foreach ($parts as $part) {
                if ($part->getType() === 'text/html') {
                    $htmlContent = $part->getContent();
                } elseif ($part->getType() === 'text/plain') {
                    $textContent = $part->getContent();
                }
            }
            
            if ($htmlContent) {
                $this->mailer->isHTML(true);
                $this->mailer->Body = $htmlContent;
                if ($textContent) {
                    $this->mailer->AltBody = $textContent;
                }
            } else {
                $this->mailer->Body = $textContent ?? '';
            }
        }
    }

    private function setHeaders(): void
    {
        $headers = $this->message->getHeaders();
        foreach ($headers as $header) {
            if (!in_array(strtolower($header->getName()), ['to', 'from', 'cc', 'bcc', 'subject'])) {
                $this->mailer->addCustomHeader($header->getName(), $header->getValue());
            }
        }
        
        $this->mailer->addCustomHeader('X-Mailer', 'Corals SMTP Pro for Magento 2');
        $this->mailer->MessageID = '<' . uniqid('corals.', true) . '@' . $this->config->getFromDomain() . '>';
    }

    private function applyTracking(): void
    {
        if ($this->config->isTrackOpensEnabled()) {
            $trackingPixel = $this->tracker->generateTrackingPixel($this->message);
            $body = $this->mailer->Body;
            $this->mailer->Body = str_replace('</body>', $trackingPixel . '</body>', $body);
        }
        
        if ($this->config->isTrackClicksEnabled()) {
            $this->mailer->Body = $this->tracker->wrapLinks($this->mailer->Body, $this->message);
        }
    }

    private function queueEmail(): void
    {
        $this->queue->add([
            'message' => serialize($this->message),
            'priority' => $this->getPriority(),
            'attempts' => 0,
            'scheduled_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function sendWithFallback(): void
    {
        $originalProvider = $this->config->getProvider();
        $fallbackProvider = $this->config->getFallbackProvider();
        
        try {
            $this->config->setProvider($fallbackProvider);
            $this->send();
        } catch (\Exception $e) {
            $this->config->setProvider($originalProvider);
            throw new MailException(new Phrase('Both primary and fallback providers failed'), $e);
        } finally {
            $this->config->setProvider($originalProvider);
        }
    }

    private function sendDefault(): void
    {
        $reflection = new \ReflectionClass($this->message);
        $property = $reflection->getProperty('transport');
        $property->setAccessible(true);
        $transport = $property->getValue($this->message);
        
        if ($transport && method_exists($transport, 'sendMessage')) {
            $transport->sendMessage();
        }
    }

    private function getPriority(): int
    {
        $subject = strtolower($this->message->getSubject() ?? '');
        
        if (strpos($subject, 'order') !== false || strpos($subject, 'payment') !== false) {
            return 1;
        }
        
        if (strpos($subject, 'password') !== false || strpos($subject, 'reset') !== false) {
            return 2;
        }
        
        return 3;
    }

    private function getRecipientAddresses(): array
    {
        $addresses = [];
        foreach ($this->message->getTo() as $address) {
            $addresses[] = $address->getEmail();
        }
        foreach ($this->message->getCc() as $address) {
            $addresses[] = $address->getEmail();
        }
        foreach ($this->message->getBcc() as $address) {
            $addresses[] = $address->getEmail();
        }
        return $addresses;
    }

    private function logSuccess(float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        
        $this->emailLogger->logEmail([
            'to' => implode(', ', $this->getRecipientAddresses()),
            'from' => $this->config->getFromEmail(),
            'subject' => $this->message->getSubject(),
            'status' => 'sent',
            'duration' => $duration,
            'provider' => $this->config->getProvider()
        ]);
        
        if ($this->config->isDebugMode()) {
            $this->logger->debug('Email sent successfully', [
                'duration' => $duration,
                'provider' => $this->config->getProvider(),
                'recipients' => $this->getRecipientAddresses()
            ]);
        }
    }

    private function logFailure(\Exception $e, float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        
        $this->emailLogger->logEmail([
            'to' => implode(', ', $this->getRecipientAddresses()),
            'from' => $this->config->getFromEmail(),
            'subject' => $this->message->getSubject(),
            'status' => 'failed',
            'error' => $e->getMessage(),
            'duration' => $duration,
            'provider' => $this->config->getProvider()
        ]);
        
        $this->logger->error('Failed to send email', [
            'error' => $e->getMessage(),
            'duration' => $duration,
            'provider' => $this->config->getProvider(),
            'recipients' => $this->getRecipientAddresses()
        ]);
    }

    public function getMessage(): EmailMessageInterface
    {
        return $this->message;
    }
}