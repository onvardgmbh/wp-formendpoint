<?php
Namespace Onvardgmbh\Formendpoint;

Class Email {

	public $recipient;
	public $subject;
	public $body;

	public static function make($recipient, $subject, $body) {
		$action = new Email();
		$action->recipient = $recipient;
		$action->subject = $subject;
		$action->body = $body;
		return $action;
	}
}