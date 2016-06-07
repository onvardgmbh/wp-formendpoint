# Wordpress Form Endpoint

## Example Simple Form
```php
require_once 'path/to/formendpoint.php';

use Onvardgmbh\Formendpoint\Formendpoint;
use Onvardgmbh\Formendpoint\Honeypot;
use Onvardgmbh\Formendpoint\Input;

Formendpoint::make('formentry', 'Form')
	->send_mail_to('admin@example.com')
	->add_honeypots([
		Honeypot::make('text', ''),
		Honeypot::make('moretext', 'Type your message here.'),
	])
	->add_fields([
		Input::make('text', 'firstname')->required(),
		Input::make('text', 'lastname')->required(),
		Input::make('text', 'phone'),
		Input::make('email', 'mail')
	]);
```

## Example Form Options with Carbon Fields
```php
require_once 'path/to/formendpoint.php';

use Onvardgmbh\Formendpoint\Formendpoint;
use Onvardgmbh\Formendpoint\Honeypot;
use Onvardgmbh\Formendpoint\Input;
use Carbon_Fields\Container;
use Carbon_Fields\Field;

Formendpoint::make('formentry', 'Form')
	->send_confirmation_mail('mail',
	function(){
		return carbon_get_theme_option('contactform_subject');
	}, function(){
		return carbon_get_theme_option('contactform_text1');
	}, function(){
		return carbon_get_theme_option('contactform_text2');
	})
	->add_honeypots([
		Honeypot::make('text', ''),
	])
	->add_fields([
		Input::make('email', 'mail')
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

### Formendpoint::make($posttype, $heading)
Creates the formendpoint with a menu item in the wordpress backend.

### send_mail_to($email)
Registers a form action. Sends successfully submitted form to the gives address.

### send_confirmation_mail($email, $subject, $text1, $text2) {
Sends a confirmation email to the form sender. $email is the index of the $_POST array in which the senders email is located: $_POST[$email].
$subject, $text1 and $text2 are callbacks returning a string which are called when the confirmation email is send.

### add_honeypots($honeypots)
```php
	->add_honeypots([
		Honeypot::make($text, $equals),
	])
```
Registers honeypots to the form. The form submission fails if a bot submits a different value than $equals.

### add_fields($inputs)
```php
	->add_fields([
		Input::make('text', 'firstname')->required(),
		Input::make('email', 'mail')
	]);
```
Registers the form input fields. The form only accepts and saves registered inputs. If a required inputs is missing the form fails.

Input types:
 - 'text'
 - 'email'
