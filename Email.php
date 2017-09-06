<?php

namespace Onvardgmbh\Formendpoint;

class Email
{
    public $recipient;
    public $subject;
    public $body;

    public static function make($recipient, $subject, $body)
    {
        $action = new self();
        $action->recipient = $recipient;
        $action->subject = $subject;
        $action->body = $body;

        return $action;
    }
}
