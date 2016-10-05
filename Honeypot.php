<?php
Namespace Onvardgmbh\Formendpoint;

Class Honeypot {

	public $name;
	public $equals;

	public static function make($name, $equals) {
		$input = new Honeypot();
		$input->name = $name;
		$input->equals = $equals;
		return $input;
	}
}