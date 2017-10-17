<?php

namespace Onvardgmbh\Formendpoint;

class Honeypot
{
    public $name;
    public $equals;

    public static function make($name, $equals)
    {
        $input = new self();
        $input->name = $name;
        $input->equals = $equals;

        return $input;
    }
}
