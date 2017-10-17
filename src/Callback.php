<?php

namespace Onvardgmbh\Formendpoint;

class Callback
{
    public $function;

    public static function make(callable $function): Callback
    {
        $action = new self();
        $action->function = $function;

        return $action;
    }
}
