<?php

namespace Onvardgmbh\Formendpoint;

class Honeypot
{
    public $name;
    public $equals;

    public static function make(string $name, string $equals)
    {
        $input = new self();
        $input->name = $name;
        $input->equals = $equals;

        return $input;
    }
}
