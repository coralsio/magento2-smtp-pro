<?php
namespace Corals\SMTP\Model\Provider;

class Postmark
{
    public function getConfig()
    {
        return [
            'host' => 'smtp.postmarkapp.com',
            'port' => 587,
            'auth' => 'login',
            'ssl' => 'tls'
        ];
    }
}