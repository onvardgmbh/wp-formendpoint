<?php

namespace Onvardgmbh\Formendpoint;

class Input
{
    public $name;
    public $required;
    public $hide;
    public $title;
    public $format;
    public $repeats;

    public static function make($type, $name, $label = null)
    {
        $input = new self();
        $input->type = $type;
        $input->name = $name;
        $input->label = $label;

        return $input;
    }

    public function required()
    {
        $this->required = true;

        return $this;
    }

    public function setTitle()
    {
        $this->title = true;

        return $this;
    }

    /**
     * Register a function to format the value for displaying.
     *
     * To pretty print numbers for example, you could use something like:
     *     Input::make('text', 'foo')
     *         ->setFormat(function ($value) {
     *             return number_format($value, 2, '.', ',');
     *         });
     *
     * @param callable $callback The formatting function; Should return a string
     *
     * @return $this
     */
    public function setFormat($callback)
    {
        $this->format = $callback;

        return $this;
    }

    public function hide()
    {
        $this->hide = true;

        return $this;
    }

    public function repeats($fields)
    {
        if ($this->type !== 'array') {
            return new WP_Error('broke', __("Non array inputs can't be repeated.", 'my_textdomain'));
        }
        foreach ($fields as $field) {
            $this->repeats[$field->name] = $field;
        }

        return $this;
    }
}
