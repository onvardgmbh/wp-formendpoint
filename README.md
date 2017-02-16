# WordPress Form Endpoint

## Examples
### Simple Form
```php
require_once __DIR__ . '/../vendor/autoload.php';

use Onvardgmbh\Formendpoint\Formendpoint;
use Onvardgmbh\Formendpoint\Honeypot;
use Onvardgmbh\Formendpoint\Input;
use Onvardgmbh\Formendpoint\Email;

Formendpoint::make('formentry', 'Form')
    ->add_honeypots([
        Honeypot::make('text', ''),
        Honeypot::make('moretext', 'Type your message here.'),
    ])
    ->add_fields([
        Input::make('text', 'firstname', 'Firstname')->required(),
        Input::make('text', 'lastname', 'Lastname')->required(),
        Input::make('text', 'phone', 'Phone'),
        Input::make('email', 'mail', 'Mail')
    ])
    ->add_actions([
        Email::make('admin@example.de', 'Contactform Subject', 'A new contactform was submitted.')
    ]);
```

### Form Options with Carbon Fields
```php
require_once __DIR__ . '/../vendor/autoload.php';

use Onvardgmbh\Formendpoint\Formendpoint;
use Onvardgmbh\Formendpoint\Honeypot;
use Onvardgmbh\Formendpoint\Input;
use Onvardgmbh\Formendpoint\Email;
use Carbon_Fields\Container;
use Carbon_Fields\Field;

Formendpoint::make('formentry', 'Form')
    ->add_honeypots([
        Honeypot::make('text', ''),
    ])
    ->add_fields([
        Input::make('email', 'mail')
    ])
    ->add_actions([
        Email::make(function() {
            if (isset($_POST['mail']) && is_email( $_POST['mail'] )) {
                return $_POST['mail'];
            }
        }, function() {
            return carbon_get_theme_option('contactform_subject');
        }, function() {
            $message = carbon_get_theme_option('contactform_text1');
            foreach ($_POST as $key => $value) {
                $message .= '<p><b>'. esc_html($key) . ':</b> ' . nl2br(esc_html($value)) . '</p>';
            }
            $message .= carbon_get_theme_option('contactform_text2');
            return $message;
        })
    ]);

Container::make('theme_options', 'Settings')
    ->set_page_parent('edit.php?post_type=formentry')
    ->add_fields(array(
        Field::make('text', 'contactform_subject', 'Subject'),
        Field::make('textarea', 'contactform_text1', 'Text before input'),
        Field::make('textarea', 'contactform_text2', 'Text after input')
    ));
```
## APIs

### Formendpoint::make($posttype, $heading, $handle = 'main')
Creates the formendpoint with a menu item in the wordpress backend.

 - $posttype: string - Name of the post type that will be used for the form entries.
 - $heading: string - Name of the Menu Item for displaying the form entries.
 - $handle: string (optional) - Name of the registered script that handles the form submit in the frontend.

### add_fields($inputs)
```php
    ->add_fields([
        Input::make('text', 'firstname')->required(),
        Input::make('email', 'mail')
    ]);
```
Registers the form input fields. The form only accepts and saves registered inputs. If a required inputs is missing the form fails.
#### Input::make($type, $name, $label=null)

 - $type: string ('text' or 'email') - Type 'text' accepts all text input. Type 'email' checks for valid email addresses
 - $name: string - Name of the input field.
 - $label: string (optional) - Label displayed when viewing the form entry in the backend.

### add_honeypots($honeypots)
```php
    ->add_honeypots([
        Honeypot::make($text, $equals)
    ])
```
Registers honeypots to the form. The form submission fails if a bot submits a different value than $equals.

### add_actions($actions)
```php
    ->add-actions([
        Email::make($recipient, $subject, $body)
    ])
```
Adds form actions which will be called when the form is successfully submitted.


#### Email::make($recipient, $subject, $body)

 - $recipient: string || array || anonymous function returning string or array
 - $subject: string || anonymous function returning string
 - $body: string || anonymous function returning string

#### Callback::make($function)

 - $function: anonymous function
