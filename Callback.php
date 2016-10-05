<?php
Namespace Onvardgmbh\Formendpoint;

Class Callback {

	public $function;

	public static function make($function) {
		$action = new Callback();
		$action->function = $function;
		return $action;
	}
}