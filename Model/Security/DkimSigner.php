<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Security;

use PHPMailer\PHPMailer\PHPMailer;
use Corals\SMTP\Helper\Config;
use Psr\Log\LoggerInterface;

class DkimSigner
{
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function sign(PHPMailer $mailer): void
    {
        if (!$this->config->isDkimEnabled()) {
            return;
        }

        try {
            $domain = $this->config->getDkimDomain();
            $selector = $this->config->getDkimSelector();
            $privateKey = $this->config->getDkimPrivateKey();

            if (empty($domain) || empty($selector) || empty($privateKey)) {
                $this->logger->warning('DKIM signing enabled but configuration incomplete');
                return;
            }

            $mailer->DKIM_domain = $domain;
            $mailer->DKIM_selector = $selector;
            $mailer->DKIM_identity = $mailer->From;
            $mailer->DKIM_passphrase = '';
            
            if (file_exists($privateKey)) {
                $mailer->DKIM_private = $privateKey;
            } else {
                $tempKey = tempnam(sys_get_temp_dir(), 'dkim_');
                file_put_contents($tempKey, $privateKey);
                $mailer->DKIM_private = $tempKey;
                register_shutdown_function(function() use ($tempKey) {
                    if (file_exists($tempKey)) {
                        unlink($tempKey);
                    }
                });
            }

            $this->logger->debug('DKIM signing configured', [
                'domain' => $domain,
                'selector' => $selector
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to configure DKIM signing', [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function generateKeys(): array
    {
        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        
        openssl_pkey_export($res, $privateKey);
        
        $publicKey = openssl_pkey_get_details($res);
        $publicKey = $publicKey["key"];
        
        $publicKey = str_replace(["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n", "\r"], "", $publicKey);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'dns_record' => $this->generateDnsRecord($publicKey)
        ];
    }

    private function generateDnsRecord(string $publicKey): string
    {
        $selector = $this->config->getDkimSelector();
        $domain = $this->config->getDkimDomain();
        
        return sprintf(
            '%s._domainkey.%s IN TXT "v=DKIM1; k=rsa; p=%s"',
            $selector,
            $domain,
            $publicKey
        );
    }

    public function verifyDkimSetup(): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => []
        ];

        try {
            if (!$this->config->isDkimEnabled()) {
                $result['warnings'][] = 'DKIM is not enabled';
                return $result;
            }

            $domain = $this->config->getDkimDomain();
            $selector = $this->config->getDkimSelector();
            $privateKey = $this->config->getDkimPrivateKey();

            if (empty($domain)) {
                $result['errors'][] = 'DKIM domain is not configured';
            }

            if (empty($selector)) {
                $result['errors'][] = 'DKIM selector is not configured';
            }

            if (empty($privateKey)) {
                $result['errors'][] = 'DKIM private key is not configured';
            } elseif (!$this->validatePrivateKey($privateKey)) {
                $result['errors'][] = 'DKIM private key is invalid';
            }

            if (empty($result['errors'])) {
                $dnsRecord = $selector . '._domainkey.' . $domain;
                $txtRecords = dns_get_record($dnsRecord, DNS_TXT);
                
                if (empty($txtRecords)) {
                    $result['warnings'][] = sprintf(
                        'No DKIM TXT record found for %s',
                        $dnsRecord
                    );
                } else {
                    $result['valid'] = true;
                    $result['dns_record'] = $txtRecords[0]['txt'] ?? '';
                }
            }
        } catch (\Exception $e) {
            $result['errors'][] = 'Failed to verify DKIM setup: ' . $e->getMessage();
        }

        return $result;
    }

    private function validatePrivateKey(string $privateKey): bool
    {
        if (file_exists($privateKey)) {
            $privateKey = file_get_contents($privateKey);
        }

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            return false;
        }

        openssl_pkey_free($key);
        return true;
    }
}