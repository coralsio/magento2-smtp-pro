<?php
namespace Corals\SMTP\Model\Provider;

class SendGrid
{
    public function getConfig()
    {
        return [
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'auth' => 'login',
            'ssl' => 'tls'
        ];
    }
}