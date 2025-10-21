<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Provider implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'gmail', 'label' => __('Gmail')],
            ['value' => 'outlook', 'label' => __('Outlook')],
            ['value' => 'office365', 'label' => __('Office 365')],
            ['value' => 'yahoo', 'label' => __('Yahoo Mail')],
            ['value' => 'sendgrid', 'label' => __('SendGrid')],
            ['value' => 'mailgun', 'label' => __('Mailgun')],
            ['value' => 'amazon_ses', 'label' => __('Amazon SES')],
            ['value' => 'postmark', 'label' => __('Postmark')],
            ['value' => 'sparkpost', 'label' => __('SparkPost')],
            ['value' => 'mailjet', 'label' => __('Mailjet')],
            ['value' => 'sendinblue', 'label' => __('Sendinblue')],
            ['value' => 'mandrill', 'label' => __('Mandrill')],
            ['value' => 'elastic_email', 'label' => __('Elastic Email')],
            ['value' => 'mailersend', 'label' => __('MailerSend')],
            ['value' => 'smtp2go', 'label' => __('SMTP2GO')],
            ['value' => 'socketlabs', 'label' => __('SocketLabs')],
            ['value' => 'sendpulse', 'label' => __('SendPulse')],
            ['value' => 'pepipost', 'label' => __('Pepipost')],
            ['value' => 'turbo_smtp', 'label' => __('turboSMTP')],
            ['value' => 'mailchimp', 'label' => __('Mailchimp Transactional')],
            ['value' => 'zoho', 'label' => __('Zoho Mail')],
            ['value' => 'fastmail', 'label' => __('FastMail')],
            ['value' => 'icloud', 'label' => __('iCloud Mail')],
            ['value' => 'protonmail', 'label' => __('ProtonMail Bridge')],
            ['value' => 'yandex', 'label' => __('Yandex Mail')],
            ['value' => 'custom', 'label' => __('Custom SMTP Server')]
        ];
    }
}