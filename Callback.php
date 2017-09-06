<?php

namespace Onvardgmbh\Formendpoint;

class Callback
{
    public $function;

    public static function make($function)
    {
        $action = new self();
        $action->function = $function;

        return $action;
    }
}
