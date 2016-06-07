# Wordpress Form Endpoint

## Example
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
## Usage

### Formendpoint::make($posttype, $heading)
Creates the formendpoint with a menu item in the wordpress backend.

### Formendpoint::send_mail_to($email)
Registers a form action. Sends successfully submitted form to the gives address.

### Formendpoint::add_honeypots($honeypots)
```php
	->add_honeypots([
		Honeypot::make($text, $equals),
	])
```
Registers honeypots to the form. The form submission fails if a bot submits a different value than $equals.

### Formendpoint::add_fields($inputs)
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
