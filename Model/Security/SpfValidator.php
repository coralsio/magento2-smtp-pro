<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Security;

use Magento\Framework\Exception\MailException;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;

class SpfValidator
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function validate(string $fromEmail): void
    {
        if (empty($fromEmail) || strpos($fromEmail, '@') === false) {
            throw new MailException(new Phrase('Invalid from email address'));
        }

        $domain = substr($fromEmail, strpos($fromEmail, '@') + 1);
        $spfRecord = $this->getSpfRecord($domain);

        if (empty($spfRecord)) {
            $this->logger->warning('No SPF record found for domain', ['domain' => $domain]);
            return;
        }

        $serverIp = $this->getServerIp();
        if (!$this->isIpAuthorized($spfRecord, $serverIp, $domain)) {
            throw new MailException(
                new Phrase('Server IP %1 is not authorized to send email for domain %2', [$serverIp, $domain])
            );
        }

        $this->logger->debug('SPF validation passed', [
            'domain' => $domain,
            'ip' => $serverIp,
            'spf' => $spfRecord
        ]);
    }

    public function getSpfRecord(string $domain): ?string
    {
        try {
            $txtRecords = dns_get_record($domain, DNS_TXT);
            
            foreach ($txtRecords as $record) {
                if (isset($record['txt']) && strpos($record['txt'], 'v=spf1') === 0) {
                    return $record['txt'];
                }
            }

            $txtRecords = dns_get_record('_spf.' . $domain, DNS_TXT);
            foreach ($txtRecords as $record) {
                if (isset($record['txt']) && strpos($record['txt'], 'v=spf1') === 0) {
                    return $record['txt'];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve SPF record', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    public function isIpAuthorized(string $spfRecord, string $ip, string $domain): bool
    {
        $mechanisms = $this->parseSpfRecord($spfRecord);

        foreach ($mechanisms as $mechanism) {
            if ($this->checkMechanism($mechanism, $ip, $domain)) {
                return !$this->isFailMechanism($mechanism);
            }
        }

        $all = $this->getAllMechanism($spfRecord);
        return $all !== '-all' && $all !== '~all';
    }

    private function parseSpfRecord(string $spfRecord): array
    {
        $parts = preg_split('/\s+/', $spfRecord);
        $mechanisms = [];

        foreach ($parts as $part) {
            if ($part === 'v=spf1') {
                continue;
            }
            $mechanisms[] = $part;
        }

        return $mechanisms;
    }

    private function checkMechanism(string $mechanism, string $ip, string $domain): bool
    {
        $qualifier = '';
        if (in_array($mechanism[0], ['+', '-', '~', '?'])) {
            $qualifier = $mechanism[0];
            $mechanism = substr($mechanism, 1);
        }

        if (strpos($mechanism, 'ip4:') === 0) {
            $ipRange = substr($mechanism, 4);
            return $this->ipInRange($ip, $ipRange);
        }

        if (strpos($mechanism, 'ip6:') === 0) {
            $ipRange = substr($mechanism, 4);
            return $this->ipInRange($ip, $ipRange);
        }

        if (strpos($mechanism, 'a') === 0) {
            $checkDomain = $domain;
            if (strpos($mechanism, 'a:') === 0) {
                $checkDomain = substr($mechanism, 2);
            }
            return $this->checkARecord($ip, $checkDomain);
        }

        if (strpos($mechanism, 'mx') === 0) {
            $checkDomain = $domain;
            if (strpos($mechanism, 'mx:') === 0) {
                $checkDomain = substr($mechanism, 3);
            }
            return $this->checkMxRecord($ip, $checkDomain);
        }

        if (strpos($mechanism, 'include:') === 0) {
            $includeDomain = substr($mechanism, 8);
            $includeSpf = $this->getSpfRecord($includeDomain);
            if ($includeSpf) {
                return $this->isIpAuthorized($includeSpf, $ip, $includeDomain);
            }
        }

        return false;
    }

    private function isFailMechanism(string $mechanism): bool
    {
        return $mechanism[0] === '-';
    }

    private function getAllMechanism(string $spfRecord): string
    {
        if (preg_match('/([+\-~?])?all/', $spfRecord, $matches)) {
            return $matches[0];
        }
        return '';
    }

    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($rangeIp, $netmask) = explode('/', $range);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $rangeIpLong = ip2long($rangeIp);
            $netmaskLong = (-1 << (32 - (int)$netmask)) & 0xFFFFFFFF;
            
            return ($ipLong & $netmaskLong) === ($rangeIpLong & $netmaskLong);
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InRange($ip, $rangeIp, (int)$netmask);
        }
        
        return false;
    }

    private function ipv6InRange(string $ip, string $rangeIp, int $netmask): bool
    {
        $ipBin = inet_pton($ip);
        $rangeIpBin = inet_pton($rangeIp);
        
        $byteCount = $netmask / 8;
        $bitCount = $netmask % 8;
        
        for ($i = 0; $i < $byteCount; $i++) {
            if ($ipBin[$i] !== $rangeIpBin[$i]) {
                return false;
            }
        }
        
        if ($bitCount > 0) {
            $mask = 0xFF << (8 - $bitCount);
            return (ord($ipBin[$byteCount]) & $mask) === (ord($rangeIpBin[$byteCount]) & $mask);
        }
        
        return true;
    }

    private function checkARecord(string $ip, string $domain): bool
    {
        try {
            $aRecords = dns_get_record($domain, DNS_A);
            foreach ($aRecords as $record) {
                if (isset($record['ip']) && $record['ip'] === $ip) {
                    return true;
                }
            }

            $aaaaRecords = dns_get_record($domain, DNS_AAAA);
            foreach ($aaaaRecords as $record) {
                if (isset($record['ipv6']) && $record['ipv6'] === $ip) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('Failed to check A record', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    private function checkMxRecord(string $ip, string $domain): bool
    {
        try {
            $mxRecords = dns_get_record($domain, DNS_MX);
            
            foreach ($mxRecords as $record) {
                if (isset($record['target'])) {
                    if ($this->checkARecord($ip, $record['target'])) {
                        return true;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('Failed to check MX record', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    private function getServerIp(): string
    {
        $ip = gethostbyname(gethostname());
        
        if (isset($_SERVER['SERVER_ADDR'])) {
            $ip = $_SERVER['SERVER_ADDR'];
        }
        
        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.ipify.org');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $externalIp = curl_exec($ch);
            curl_close($ch);
            
            if (filter_var($externalIp, FILTER_VALIDATE_IP)) {
                $ip = $externalIp;
            }
        }
        
        return $ip;
    }

    public function generateSpfRecord(string $domain): string
    {
        $serverIp = $this->getServerIp();
        
        $spfRecord = sprintf(
            'v=spf1 ip4:%s include:_spf.google.com include:spf.protection.outlook.com ~all',
            $serverIp
        );
        
        return sprintf('%s IN TXT "%s"', $domain, $spfRecord);
    }
}