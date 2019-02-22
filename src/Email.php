<?php

namespace Onvardgmbh\Formendpoint;

class Email
{
    public $recipient;
    public $subject;
    public $body;
    public $replyTo;

    /**
     * @param string|callable $recipient
     * @param string|callable $subject
     * @param string|callable $body
     */
    public static function make($recipient, $subject, $body): Email
    {
        $action = new self();
        $action->recipient = $recipient;
        $action->subject = $subject;
        $action->body = $body;

        return $action;
    }

    /**
     * @param string|callable $replyTo
     */
    public function replyTo($replyTo)
    {
        $this->replyTo = $replyTo;

        return $this;
    }
}
