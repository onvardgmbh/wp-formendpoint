<?php
namespace Onvardgmbh\Formendpoint;

class Input
{

    public $name;
    public $required;
    public $hide;
    public $title;
    public $repeats;

    public static function make($type, $name, $label = null)
    {
        $input = new Input();
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

    public function hide()
    {
        $this->hide = true;
        return $this;
    }

    public function repeats($fields)
    {
        if ($this->type !== 'array') {
            return new WP_Error( 'broke', __( "Non array inputs can't be repeated.", "my_textdomain" ) );
        }
        foreach ($fields as $field) {
            $this->repeats[$field->name] = $field;
        }
        return $this;
    }
}
