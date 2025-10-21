<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Email;

use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Corals\SMTP\Model\ResourceModel\Email\Tracking\CollectionFactory as TrackingCollectionFactory;

class Tracker
{
    private UrlInterface $urlBuilder;
    private EncryptorInterface $encryptor;
    private DateTime $dateTime;
    private TrackingCollectionFactory $trackingCollectionFactory;

    public function __construct(
        UrlInterface $urlBuilder,
        EncryptorInterface $encryptor,
        DateTime $dateTime,
        TrackingCollectionFactory $trackingCollectionFactory
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->encryptor = $encryptor;
        $this->dateTime = $dateTime;
        $this->trackingCollectionFactory = $trackingCollectionFactory;
    }

    public function generateTrackingPixel(EmailMessageInterface $message): string
    {
        $messageId = $this->generateMessageId($message);
        $trackingData = [
            'message_id' => $messageId,
            'type' => 'open',
            'timestamp' => $this->dateTime->gmtTimestamp()
        ];
        
        $encrypted = $this->encryptor->encrypt(json_encode($trackingData));
        $encodedData = base64_encode($encrypted);
        
        $trackingUrl = $this->urlBuilder->getUrl('corals_smtp/tracking/pixel', [
            'data' => $encodedData
        ]);
        
        return sprintf(
            '<img src="%s" width="1" height="1" border="0" alt="" style="display:none;" />',
            $trackingUrl
        );
    }

    public function wrapLinks(string $htmlContent, EmailMessageInterface $message): string
    {
        $messageId = $this->generateMessageId($message);
        
        $pattern = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/i';
        
        return preg_replace_callback($pattern, function ($matches) use ($messageId) {
            $originalUrl = $matches[2];
            
            if ($this->shouldTrackUrl($originalUrl)) {
                $trackingData = [
                    'message_id' => $messageId,
                    'type' => 'click',
                    'url' => $originalUrl,
                    'timestamp' => $this->dateTime->gmtTimestamp()
                ];
                
                $encrypted = $this->encryptor->encrypt(json_encode($trackingData));
                $encodedData = base64_encode($encrypted);
                
                $trackingUrl = $this->urlBuilder->getUrl('corals_smtp/tracking/click', [
                    'data' => $encodedData
                ]);
                
                return '<a href="' . $trackingUrl . '"';
            }
            
            return $matches[0];
        }, $htmlContent);
    }

    public function trackSent(EmailMessageInterface $message): void
    {
        $messageId = $this->generateMessageId($message);
        
        foreach ($message->getTo() as $address) {
            $this->recordTracking([
                'message_id' => $messageId,
                'email' => $address->getEmail(),
                'type' => 'sent',
                'data' => json_encode([
                    'subject' => $message->getSubject(),
                    'from' => $message->getFrom() ? $message->getFrom()[0]->getEmail() : ''
                ])
            ]);
        }
    }

    public function trackOpen(string $encryptedData): void
    {
        try {
            $decrypted = $this->encryptor->decrypt(base64_decode($encryptedData));
            $data = json_decode($decrypted, true);
            
            if ($data && isset($data['message_id'])) {
                $this->recordTracking([
                    'message_id' => $data['message_id'],
                    'email' => $data['email'] ?? '',
                    'type' => 'open',
                    'data' => json_encode([
                        'timestamp' => $this->dateTime->gmtTimestamp()
                    ]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
            }
        } catch (\Exception $e) {
        }
    }

    public function trackClick(string $encryptedData): ?string
    {
        try {
            $decrypted = $this->encryptor->decrypt(base64_decode($encryptedData));
            $data = json_decode($decrypted, true);
            
            if ($data && isset($data['message_id']) && isset($data['url'])) {
                $this->recordTracking([
                    'message_id' => $data['message_id'],
                    'email' => $data['email'] ?? '',
                    'type' => 'click',
                    'data' => json_encode([
                        'url' => $data['url'],
                        'timestamp' => $this->dateTime->gmtTimestamp()
                    ]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
                
                return $data['url'];
            }
        } catch (\Exception $e) {
        }
        
        return null;
    }

    public function trackBounce(string $email, string $reason, string $type = 'hard'): void
    {
        $this->recordTracking([
            'message_id' => 'bounce_' . uniqid(),
            'email' => $email,
            'type' => 'bounce',
            'data' => json_encode([
                'bounce_type' => $type,
                'reason' => $reason,
                'timestamp' => $this->dateTime->gmtTimestamp()
            ])
        ]);
    }

    public function getTrackingStats(string $messageId): array
    {
        $collection = $this->trackingCollectionFactory->create();
        $collection->addFieldToFilter('message_id', $messageId);
        
        $stats = [
            'sent' => 0,
            'opens' => 0,
            'unique_opens' => 0,
            'clicks' => 0,
            'unique_clicks' => 0,
            'bounces' => 0,
            'open_rate' => 0,
            'click_rate' => 0
        ];
        
        $uniqueOpens = [];
        $uniqueClicks = [];
        
        foreach ($collection as $tracking) {
            switch ($tracking->getType()) {
                case 'sent':
                    $stats['sent']++;
                    break;
                case 'open':
                    $stats['opens']++;
                    $uniqueOpens[$tracking->getEmail()] = true;
                    break;
                case 'click':
                    $stats['clicks']++;
                    $uniqueClicks[$tracking->getEmail()] = true;
                    break;
                case 'bounce':
                    $stats['bounces']++;
                    break;
            }
        }
        
        $stats['unique_opens'] = count($uniqueOpens);
        $stats['unique_clicks'] = count($uniqueClicks);
        
        if ($stats['sent'] > 0) {
            $stats['open_rate'] = round(($stats['unique_opens'] / $stats['sent']) * 100, 2);
            $stats['click_rate'] = round(($stats['unique_clicks'] / $stats['sent']) * 100, 2);
        }
        
        return $stats;
    }

    public function getEmailEngagement(string $email): array
    {
        $collection = $this->trackingCollectionFactory->create();
        $collection->addFieldToFilter('email', $email);
        $collection->setOrder('created_at', 'DESC');
        
        $engagement = [
            'total_emails' => 0,
            'opens' => 0,
            'clicks' => 0,
            'last_open' => null,
            'last_click' => null,
            'engagement_score' => 0
        ];
        
        foreach ($collection as $tracking) {
            if ($tracking->getType() === 'sent') {
                $engagement['total_emails']++;
            } elseif ($tracking->getType() === 'open') {
                $engagement['opens']++;
                if (!$engagement['last_open']) {
                    $engagement['last_open'] = $tracking->getCreatedAt();
                }
            } elseif ($tracking->getType() === 'click') {
                $engagement['clicks']++;
                if (!$engagement['last_click']) {
                    $engagement['last_click'] = $tracking->getCreatedAt();
                }
            }
        }
        
        if ($engagement['total_emails'] > 0) {
            $openRate = $engagement['opens'] / $engagement['total_emails'];
            $clickRate = $engagement['clicks'] / $engagement['total_emails'];
            $engagement['engagement_score'] = round(($openRate * 0.3 + $clickRate * 0.7) * 100, 2);
        }
        
        return $engagement;
    }

    private function generateMessageId(EmailMessageInterface $message): string
    {
        $to = $message->getTo() ? $message->getTo()[0]->getEmail() : '';
        $subject = $message->getSubject() ?? '';
        $timestamp = $this->dateTime->gmtTimestamp();
        
        return md5($to . $subject . $timestamp . uniqid());
    }

    private function shouldTrackUrl(string $url): bool
    {
        $skipPatterns = [
            '#^mailto:#i',
            '#^tel:#i',
            '#^javascript:#i',
            '#^#',
            '#unsubscribe#i',
            '#opt-out#i'
        ];
        
        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }
        
        return true;
    }

    private function recordTracking(array $data): void
    {
        $tracking = $this->trackingCollectionFactory->create()->getNewEmptyItem();
        $tracking->setData($data);
        $tracking->save();
    }
}