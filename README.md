<h1 align="center"><img src="https://user-images.githubusercontent.com/16560273/52669014-5f3a8480-2f15-11e9-99eb-7d99b82c6ef7.png" alt="WP Formendpoint"></h1>


## Examples
### Simple Form
```php
require_once __DIR__ . '/../vendor/autoload.php';

use Onvardgmbh\Formendpoint\Formendpoint;
use Onvardgmbh\Formendpoint\Honeypot;
use Onvardgmbh\Formendpoint\Input;
use Onvardgmbh\Formendpoint\Email;

Formendpoint::make( 'formentry', 'Form' )
    ->add_honeypots( [
        Honeypot::make( 'text', '' ),
        Honeypot::make( 'moretext', 'Type your message here.' ),
    ] )
    ->add_fields( [
        Input::make( 'text', 'firstname', 'Firstname' )->required(),
        Input::make( 'text', 'lastname', 'Lastname' )->required(),
        Input::make( 'text', 'phone', 'Phone' ),
        Input::make( 'email', 'mail', 'Mail' )
    ] )
    ->add_actions( [
        Email::make( 'admin@example.de', 'Contactform Subject', 'A new contactform was submitted.' )
    ] );
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

Formendpoint::make( 'formentry', 'Form' )
    ->add_honeypots( [
        Honeypot::make( 'text', '' ),
    ] )
    ->add_fields( [
        Input::make('text', 'firstname', __('First name', 'wptheme'))->required()->setTitle(),
        Input::make('text', 'name', __('Name', 'wptheme'))->required()->setTitle(),
        Input::make('email', 'email', __('Email', 'wptheme'))->required(),
    ] )
    ->add_actions( [
        Email::make( function() {
            if ( isset( $_POST['mail'] ) && is_email( $_POST['mail'] ) ) {
                return $_POST['mail'];
            }
        }, function() {
            return carbon_get_theme_option( 'crb_contact_form_subject' );
        }, function($postId, $fields, $rawData, $formattedData) {
            $rows = collect($fields)
                ->filter(function ($field) use ($formattedData) {
                    return isset($formattedData[$field->name]);
                })
                ->mapWithKeys(function ($field) use ($formattedData) {
                    $value = $formattedData[$field->name];
                    return [$field->label => __($value, 'wptheme')];
                });
            return bladerunner('emails.contact-form-customer-success', [
                'rows' => $rows,
                'heading' => $this->themeOptions->getOption('crb_contact_form_heading'),
                'intro' => $this->themeOptions->getOption('crb_contact_form_intro'),
                'footer' => $this->themeOptions->getOption('crb_contact_form_footer'),
            ], false);
        } )
    ] );

Container::make( 'theme_options', 'Settings' )
    ->set_page_parent( 'edit.php?post_type=formentry' )
    ->add_fields( [
        Field::make( 'text', 'crb_contact_form_subject', 'Subject' ),
        Field::make('rich_text', 'crb_contact_form_heading', __('ContactForm Heading', 'wptheme')),
        Field::make('rich_text', 'crb_contact_form_intro', __('ContactForm Intro', 'wptheme')),
        Field::make('rich_text', 'crb_contact_form_footer', __('ContactForm Footer', 'wptheme')),
    ] );
```


## APIs
### Formendpoint::make( $posttype, $heading, $handle = 'main' )
Creates the formendpoint with a menu item in the wordpress backend.

 - $posttype: string - Name of the post type that will be used for the form entries.
 - $heading: string - Name of the Menu Item for displaying the form entries.
 - $handle: string (optional) - Name of the registered script that handles the form submit in the frontend.

### add_fields( $inputs )
```php
    ->add_fields( [
        Input::make( 'text', 'firstname' )->required(),
        Input::make( 'email', 'mail' )
    ] );
```

### show_in_menu( $showInMenu )
```php
    ->show_in_menu('edit.php?post_type=event');
```

### setLabels( $labels )
```php
    ->setLabels( [
        'name'               => _x( 'Eintrag', 'post type general name' ),
        'singular_name'      => _x( 'Eintrag', 'post type singular name' ),
        'add_new_item'       => __( 'Neuer Eintrag' ),
        'edit_item'          => __( 'Eintrag bearbeiten' ),
        'new_item'           => __( 'Neuer Eintrag' ),
        'all_items'          => __( 'Alle Einträge' ),
        'view_item'          => __( 'Eintrag ansehen' ),
        'search_items'       => __( 'Einträge durchsuchen' ),
        'not_found'          => __( 'Keinen Eintrag gefunden' ),
        'not_found_in_trash' => __( 'Keine Einträge im Papierkorb gefunden' ),
        'parent_item_colon'  => '',
        'menu_name'          => $this->heading,
    ] );
```
Registers the form input fields. The form only accepts and saves registered inputs. If a required inputs is missing the form fails.

#### Input::make( $type, $name, $label=null )
 - $type: accepts:
   - 'text': normal text input 
   - 'email': checks for valid email addresses
   - 'file': single uploaded file (form-data requests only)
   - 'files': multiple uploaded files (form-data requests only)
 - $name: string - Name of the input field.
 - $label: string (optional) - Label displayed when viewing the form entry in the backend.

### add_honeypots( $honeypots )
```php
    ->add_honeypots( [
        Honeypot::make( $text, $equals )
    ] )
```
Registers honeypots to the form. The form submission fails if a bot submits a different value than $equals.

### add_actions( $actions )
```php
    ->add-actions( [
        Email::make( $recipient, $subject, $body )
    ] )
```
Adds form actions which will be called when the form is successfully submitted.

### csvExport($callback = null)
```php
    ->csvExport()
```
Adds a CSV download button to the post type overview page.

```php
    ->csvExport(function(array $fields, array $data) {
        return collect($data)->map(function($item) {
            $item['index'] = 'value';
            return $item;
        })->toArray();
    })
```
Modify the content of the CSV file.

#### Email::make( $recipient, $subject, $body )
 - $recipient: string || array || anonymous function returning string or array
 - $subject: string || anonymous function returning string
 - $body: string || anonymous function returning string

##### ReplyTo
Register ReplyTo address via callable or string
```php
    Email::make(...)->replyTo(function($post_id, $fields, $data){
        return 'max@mustermann.de, peter@pan.de, ...';
    });
```
OR
```php
    Email::make(...)->replyTo('max@mustermann.de, peter@pan.de, ...');
```

#### Callback::make( $function )
 - $function: anonymous function


## Examples
### Fileupload
> Fileuploads are only possible in "multipart/form-data" mode. The "application/json" mode is not supported.

#### Upload single file
Add a file type input element to the form. 
```html
<form enctype="multipart/form-data">
    <!-- [..] -->
    <input type="file" name="avatar">
    <!-- [..] -->
</form>
```

Use the string `'file'` for the input type.
```php
Formendpoint::make('application_form', __('ApplicationForm', 'wptheme'), 'bundlejs')
    ->add_fields([
        // [..]
        Input::make('file', 'avatar', __('Avatar', 'wptheme')),
    ])
    //Define allowed mime types (this is optional)
    ->setAllowedMimeTypes([
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
    ]);
```

#### Upload multiple files
Add a file type input element to the form. Append a `[]` to the name of the element and add a `multiple` attribute.
```html
<form enctype="multipart/form-data">
    <!-- [..] -->
    <input type="file" name="avatars[]" multiple>
    <!-- [..] -->
</form>
```

Use the string `'files'` (plural!) if you want to allow multiple files.
```php
Formendpoint::make('application_form', __('ApplicationForm', 'wptheme'), 'bundlejs')
    ->add_fields([
        // [..]
        Input::make('files', 'avatars', __('Avatars', 'wptheme')),
    ]);
```
