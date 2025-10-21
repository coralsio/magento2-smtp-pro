<?php
namespace Corals\SMTP\Model\Provider;

class Mailgun
{
    public function getConfig()
    {
        return [
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'auth' => 'login',
            'ssl' => 'tls'
        ];
    }
}