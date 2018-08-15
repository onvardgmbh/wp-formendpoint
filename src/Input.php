<?php

namespace Onvardgmbh\Formendpoint;

class Input
{
    public $format;
    public $hide;
    public $label;
    public $name;
    public $repeats;
    public $required;
    public $validationFunction;
    public $title;
    public $type;

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

    public function setValidationFunction($validationFunction)
    {
        $this->validationFunction = $validationFunction;

        return $this;
    }

    public function isValid($value, $formData)
    {
        if (is_callable($this->validationFunction)) {
            return ($this->validationFunction)($value, $formData);
        }

        return true;
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
        if ('array' !== $this->type) {
            return new \WP_Error('broke', __("Non array inputs can't be repeated.", 'my_textdomain'));
        }
        foreach ($fields as $field) {
            $this->repeats[$field->name] = $field;
        }

        return $this;
    }
}
