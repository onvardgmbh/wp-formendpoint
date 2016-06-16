<?php
Namespace Onvardgmbh\Formendpoint;

Class Formendpoint {

	public $posttype;
	public $heading;
	public $style;
	public $fields;
	public $actions;
	public $honeypots;
	public $entryTitle;
	public $show_ui;


	public static function make($posttype, $heading, $style = 'main') {
		return new Formendpoint($posttype, $heading, $style );
	}

	function __construct($posttype, $heading, $style) {
		$this->posttype = $posttype;
		$this->heading = $heading;
		$this->style = $style;
		$this->actions = [];
		$this->fields = [];
		$this->honeypots = [];
		$this->entryTitle = '';
		$this->show_ui = true;

		add_action( 'init', array($this, 'dates_post_type_init') );
		add_action( 'add_meta_boxes', array($this, 'adding_custom_meta_boxes') );
		add_action( 'wp_enqueue_scripts', function() {
			wp_localize_script( $this->style, $this->posttype, array(
				// URL to wp-admin/admin-ajax.php to process the request
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				// generate a nonce with a unique ID "myajax-post-comment-nonce"
				// so that you can check it later when an AJAX request is sent
				'security' => wp_create_nonce( $this->posttype )
			));
		});
		add_action( 'wp_ajax_' . $this->posttype, array($this, 'handleformsubmit') );
		add_action( 'wp_ajax_nopriv_' . $this->posttype, array($this, 'handleformsubmit') );
	}

	public function add_actions($actions) {
		foreach($actions as $action) {
			$this->actions[] = $action;
		}
		return $this;
	}

	public function add_honeypots($honeypots) {
		foreach($honeypots as $honeypot) {
			$this->honeypots[] = $honeypot;
		}
		return $this;
	}

	public function add_fields($fields) {
		foreach($fields as $field) {
			$this->fields[] = $field;
		}
		return $this;
	}

	public function show_ui($show_ui) {
		$this->show_ui = $show_ui;
		return $this;
	}

	public function handleformsubmit() {
		check_ajax_referer( $this->posttype, 'security' );
		unset($_POST['security']);
		unset($_POST['action']);

		foreach($this->honeypots as $honeypot) {
			if(!isset($_POST[$honeypot->name]) || $_POST[$honeypot->name] !== $honeypot->equals) {
				wp_die($honeypot->name);
			}
			unset($_POST[$honeypot->name]);
		}

		$names = array_map(create_function('$o', 'return $o->name;'), $this->fields);

		foreach ($_POST as $key => $value) {
			if(!in_array($key, $names)) {
				unset($_POST[$key]);
			}
		}
		foreach ($this->fields as $field) {
			if(isset($field->required) && $_POST[$field->name] === '') {
				echo 'Field "' . $field->name . '"is required';
				status_header(400);
				wp_die();
			}
			if($field->type === 'email' && (isset($field->required) || !empty($_POST[$field->name])) && !is_email( $_POST[$field->name] )) {
				echo $_POST[$field->name] . ' is not a valid email address.';
				status_header(400);
				wp_die();
			}
			if(isset($field->title)) {
				$this->entryTitle .= $_POST[$field->name] . ' ';
			}
		}
		$post_id = wp_insert_post( [
			'post_title'    => $this->entryTitle,
			'post_status'   => 'publish',
			'post_type' => $this->posttype,
		] );
		foreach ($_POST as $key => $value) {
            if(is_array($value)) {
                foreach ($value as $index => $item) {
					add_post_meta($post_id, $key . $index, esc_html($item));
                }
            } else {
				add_post_meta($post_id, $key, esc_html($value));
            }
		}

        $subject = 'Formular angereicht';
        $headers = [];
        //$headers[] = 'From: My Name <myname@example.com>' . "\r\n";
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
		foreach($this->actions as $action) {
			if(get_class($action) === 'Onvardgmbh\Formendpoint\Email') {
				if(gettype($action->recipient) === 'object') {
					$recipient = ($action->recipient)();
					if(!$recipient) {
						continue;
					}
				} else {
					$recipient = $action->recipient;
				}
				if(gettype($action->subject) === 'object') {
					$subject = ($action->subject)();
					if(!$subject) {
						continue;
					}
				} else {
					$subject = $action->subject;
				}
				if(gettype($action->body) === 'object') {
					$body = ($action->body)();
					if(!$body) {
						continue;
					}
				} else {
					$body = $action->body;
				}
				wp_mail( $recipient, $subject, $body, $headers);
			} else if(get_class($action) === 'Onvardgmbh\Formendpoint\Callback') {
				($action->function)();
			}
		}
		wp_die();
	}

	public function dates_post_type_init() {
		register_post_type( $this->posttype, [
			'labels'        => array(
				'name'               => _x( 'Eintrag', 'post type general name' ),
				'singular_name'      => _x( 'Eintrag', 'post type singular name' ),
				//'add_new'            => _x( 'Add New', 'book' ),
				'add_new_item'       => __( 'Neuer Eintrag' ),
				'edit_item'          => __( 'Eintrag bearbeiten' ),
				'new_item'           => __( 'Neuer Eintrag' ),
				'all_items'          => __( 'Alle Einträge' ),
				'view_item'          => __( 'Eintrag ansehen' ),
				'search_items'       => __( 'Einträge durchsuchen' ),
				'not_found'          => __( 'Keinen Eintrag gefunden' ),
				'not_found_in_trash' => __( 'Keine Einträge im Papierkorb gefunden' ),
				'parent_item_colon'  => '',
				'menu_name'          => $this->heading
			),
			'public'        => false,
			'show_ui' => $this->show_ui,
			'menu_icon' => 'dashicons-email',
			'supports'      => array(''),
			'has_archive'   => false,
			'capabilities' => array(
				'create_posts' => 'do_not_allow'
			),
			'map_meta_cap' => true, // Set to `false`, if users are not allowed to edit/delete existing posts
		] );
	}
	public function adding_custom_meta_boxes( $post ) {
		add_meta_box(
			'my-meta-box',
			__( 'Eintrag' ),
			function() {
				global $post;
				echo $post->post_content;
				$post_meta = get_post_custom();
				foreach ($post_meta as $key => $value) {
					if($key !== '_edit_lock') {
						$echo = preg_replace('/[A-Z][a-z]/', ' ${0}${1}', $key);
						$echo = preg_replace('/^\s/', '', $echo);
						echo '<h3>'.$echo.'</h3>';
						foreach ($value as $content) {
							echo '<p>'.nl2br($content).'</p>';
						}
					}
				}
			},
			$this->posttype,
			'normal',
			'default'
		);
	}
}

Class Callback {

	public $function;

	public static function make($function) {
		$action = new Callback();
		$action->function = $function;
		return $action;
	}
}

Class Email {

	public $recipient;
	public $subject;
	public $body;

	public static function make($recipient, $subject, $body) {
		$action = new Email();
		$action->recipient = $recipient;
		$action->subject = $subject;
		$action->body = $body;
		return $action;
	}
}

Class Input {

	public $name;
	public $required;
	public $title;

	public static function make($type, $name) {
		$input = new Input();
		$input->name = $name;
		$input->type = $type;
		return $input;
	}

	public function required() {
		$this->required = true;
		return $this;
	}

	public function setTitle() {
		$this->title = true;
		return $this;
	}
}

Class Honeypot {

	public $name;
	public $equals;

	public static function make($name, $equals) {
		$input = new Honeypot();
		$input->name = $name;
		$input->equals = $equals;
		return $input;
	}
}
