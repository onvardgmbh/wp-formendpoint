<?php
Namespace Onvardgmbh\Formendpoint;

Class Formendpoint {

	private $data;
	public $posttype;
	public $heading;
	public $style;
	public $fields;
	public $actions;
	public $honeypots;
	public $entryTitle;
	public $show_ui;
	public $validate_function;


	public static function make( $posttype, $heading, $style = 'main' ) {
		return new Formendpoint( $posttype, $heading, $style );
	}

	function __construct( $posttype, $heading, $style ) {
		if ( strlen( $posttype ) > 20 || preg_match( '/\s/', $posttype ) || preg_match( '/[A-Z]/', $posttype ) ) {
			wp_die('ERROR: The endpoint ' . $posttype . ' couldn\'nt be created. Please make sure the posttype name contains max 20 chars and no whitespaces or uppercase letters.', '', ["response" => 400]);
		}

		$this->posttype   = $posttype;
		$this->heading    = $heading;
		$this->style      = $style;
		$this->actions    = [];
		$this->fields     = [];
		$this->honeypots  = [];
		$this->entryTitle = '';
		$this->show_ui    = true;

		add_action( 'init', array( $this, 'dates_post_type_init' ) );
		add_action( 'add_meta_boxes', array( $this, 'adding_custom_meta_boxes' ) );
		add_action( 'wp_enqueue_scripts', function () {
			wp_localize_script( $this->style, $this->posttype, array(
				// URL to wp-admin/admin-ajax.php to process the request
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				// Generate a nonce with a unique ID "myajax-post-comment-nonce"
				// so that you can check it later when an AJAX request is sent
				'security' => wp_create_nonce( $this->posttype ) ) );
		} );
		add_action( 'wp_ajax_' .        $this->posttype, array( $this, 'handleformsubmit' ) );
		add_action( 'wp_ajax_nopriv_' . $this->posttype, array( $this, 'handleformsubmit' ) );
	}

	public function add_actions( $actions ) {
		foreach ( $actions as $action ) {
			$this->actions[] = $action;
		}
		return $this;
	}

	public function add_honeypots( $honeypots ) {
		foreach ( $honeypots as $honeypot ) {
			$this->honeypots[] = $honeypot;
		}
		return $this;
	}

	public function add_fields( $fields ) {
		foreach ( $fields as $field ) {
			$this->fields[ $field->name ] = $field;
		}
		return $this;
	}

	public function show_ui( $show_ui ) {
		$this->show_ui = $show_ui;
		return $this;
	}

	public function validate( $function ) {
		$this->validate_function = $function;
		return $this;
	}

	public function handleformsubmit() {
		$this->data = $_SERVER["CONTENT_TYPE"] === 'application/json'
			? json_decode( file_get_contents( 'php://input' ), true )
			: $_POST;

		if ( ! wp_verify_nonce( $this->data['security'], $this->posttype ) ) {
    			wp_die('', '', ["response" => 403]);
		}

		unset( $this->data['security'] );
		unset( $this->data['action'] );
		if ( isset( $this->validate_function ) && ! ( $this->validate_function )( $this->fields ) ) {
            		wp_die('', '', ["response" => 403]);
		}

		foreach ( $this->honeypots as $honeypot ) {
			if ( ! isset( $this->data[ $honeypot->name ] ) || $this->data[ $honeypot->name ] !== $honeypot->equals ) {
				wp_die($honeypot->name, '', ["response" => 403]);
			}
			unset( $this->data[ $honeypot->name ] );
		}

		foreach ( $this->data as $key => $value ) {
			$this->sanitizeField( $this->data, $this->fields, $key, $value );
		}
		$flatten = [];
		foreach ( $this->fields as $field ) {
			$this->validateField( $field, $this->data[ $field->name ] ?? null );
			if ( ! empty( $this->data[ $field->name ] ) ) {
				$flatten[] = $field->name;
			}
		}
		$post_id    = wp_insert_post( [
			'post_title'  => $this->entryTitle,
			'post_status' => 'publish',
			'post_type'   => $this->posttype,
		] );
		$this->data = array_merge( array_flip( $flatten ), $this->data );
		$this->data['referer'] = $_SERVER['HTTP_REFERER'] ?? '';
		foreach ( $this->data as $key => $value ) {
			if ( is_array( $value ) ) {
				add_post_meta( $post_id, $key, addslashes( json_encode( $value ) ) );
			} elseif ( is_bool( $value ) ) {
				add_post_meta( $post_id, $key,  $value ? 'true' : 'false'  );
			} else {
				add_post_meta( $post_id, $key, addslashes( $value ) );
			}
		}


		foreach ( $this->actions as $action ) {
			if ( get_class( $action ) === 'Onvardgmbh\Formendpoint\Email' ) {
				$recipient = gettype( $action->recipient ) === 'object'
					? ( $action->recipient )( $post_id, $this->fields, $this->data )
					: $action->recipient;

				$subject = gettype( $action->subject ) === 'object'
					? ( $action->subject )( $post_id, $this->fields, $this->data )
					: $action->subject;

				$body = gettype( $action->body ) === 'object'
					? ( $action->body )( $post_id, $this->fields, $this->data )
					: $action->body;

				if ( $recipient && $subject && $body ) {
					wp_mail( $recipient, $this->template_replace( $subject, $this->data ),
						$this->template_replace( $body, $this->data, 'Alle inputs' ),
						array( 'Content-Type: text/html; charset=UTF-8' ) );
				}
			} else if ( get_class( $action ) === 'Onvardgmbh\Formendpoint\Callback' ) {
				( $action->function )( $post_id, $this->fields, $this->data );
			}
		}
		wp_die();

	}

	private function sanitizeField( &$data, &$fields, $key, $value ) {
		if ( ! isset( $fields[ $key ] ) || ( ! isset( $data[ $key ] ) || $data[ $key ] === '' || ! count( $data[ $key ] ) ) ) {
			unset( $data[ $key ] );
			return;
		} elseif ( $fields[ $key ]->type === 'array' ) {
			if($_SERVER["CONTENT_TYPE"] !== 'application/json') {
				wp_die('Error: Arrays no longer supported for plain form-data requests.', '', ["response" => 400]);
			}
			foreach ( $value as $subkey => $value2 ) {
				foreach ( $value2 as $subsubbkey => $value3 ) {
					if ( ! isset( $fields[ $key ]->repeats[ $subsubbkey ] )
						 || ! isset( $data[ $key ][ $subkey ][ $subsubbkey ] )
						 || ! count( $data[ $key ][ $subkey ][ $subsubbkey ] )
						 ||          $data[ $key ][ $subkey ][ $subsubbkey ] === ''
					) {
						unset( $data[ $key ][ $subkey ][ $subsubbkey ] );
						continue;
					} elseif ( is_array( $value3 ) ) {
						wp_die('Error: Currently array depth is limited to 1.', '', ["response" => 400]);
					}
				}
			}
		}
		if($_SERVER["CONTENT_TYPE"] !== 'application/json' && $fields[ $key ]->type !== 'array') {
            		$data[ $key ] = stripslashes($data[ $key ]);
		}
	}

	private function validateField( $field, $value ) {
		if ( $field->type !== 'array' ) {
			if ( isset( $field->required ) && ( ! isset( $value ) || $value === '' || ! count( $value ) ) ) {
                		wp_die('Field "' . $field->name . '"is required', '', ["response" => 400]);
			}
			if ( $field->type === 'email' && ( isset( $field->required ) || ! empty( $value ) ) && ! is_email( $value ) ) {
                		wp_die($value . ' is not a valid email address.', '', ["response" => 400]);
			}
			if ( isset( $field->title ) ) {
				$this->entryTitle .= ( $this->data[ $field->name ] ?? '' ) . ' ';
			}
		} else {
			if ( isset( $field->required ) && ! is_array( $value ) ) {
                		wp_die('Field "' . $field->name . '"is required', '', ["response" => 400]);
			}
			if ( ! is_array( $value ) ) {
				return;
			}

			foreach ( $value as $userinput ) {
				foreach ( $field->repeats as $subfield ) {
					if ( $subfield->type !== 'array' ) {
						$this->validateField( $subfield, $userinput[ $subfield->name ] );
						if ( isset( $field->title ) ) {
							$this->entryTitle .= $userinput[ $subfield->name ] . ' ';
						}
					} else {
						wp_die('Error: Currently array depth is limited to 1.', '', ["response" => 400]);
					}
				}
			}
		}
	}

	public function dates_post_type_init() {
		register_post_type( $this->posttype, array(
			'labels'       => array(
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
				'menu_name'          => $this->heading
			),
			'public'       => false,
			'show_ui'      => $this->show_ui,
			'menu_icon'    => 'dashicons-email',
			'supports'     => array( '' ),
			'has_archive'  => false,
			'capabilities' => array(
				'create_posts' => 'do_not_allow'
			),
			'map_meta_cap' => true
		) );
	}

	public function adding_custom_meta_boxes( $post ) {
		add_meta_box(
			'my-meta-box',
			__( 'Eintrag' ),
			function () {
				global $post;
				$data = [];
				foreach ( get_post_custom() as $key => $value ) {
					if ( isset( $this->fields[ $key ] ) ) {
						if ( $this->fields[ $key ]->type === 'array' ) {
							$data[ $key ] = json_decode( $value[0], true );
						} else {
							$data[ $key ] = $value[0];
						}
					}
				}

				echo $post->post_content;
				echo $this->template_replace( '{{all}}', $data, 'all' );
			},
			$this->posttype,
			'normal'
		);
	}

	/**
	 * Takes a template string and replaces variables in double braces
	 * @param $template_string {string} - The string to process
	 * @param $data {array} - An instance of Formendpoint->data, or similar, like data from `get_post_custom()`
	 * @param $markup_template {string} - If this pattern occurs within double braces in the $template_string,
	 *        it is replaced by a HTML representation of all the data
	 * @return {string} - The $template_string, with all valid double brace replacement points replaced
	 */
	private function template_replace( $template_string, $data, $markup_template = null ) {
		$markup = '';
		$template_content = [];

		foreach ( $data as $key => $value ) {
			$field = $this->fields[ $key ] ?? null;
			if ( empty( $field ) || ! empty( $field->hide ) ) {
				continue;
			}
			$markup .= '<p>';
			$markup .= '<b>' . esc_html( $field->label ?: $field->name ) . ': </b>';

			if ( $field->type !== 'array' ) {
				$markup .= $field->type === 'textarea' ? '<br>' : '';
				$markup .= nl2br( esc_html( $value ) );
				$template_content[ $key ] = nl2br( esc_html( $value ) );
				$markup .= '</p>';
			} else {
				$markup .= '</p>';
				$tableinput = '<table class="wp-list-table widefat fixed striped" cellspacing="0" style="width: 100%;"><thead><tr>';
				foreach ( $field->repeats as $repeated_field ) {
					if ( empty( $repeated_field->hide ) ) {
						$tableinput .= '<th class="manage-column column-columnname" scope="col" valign="top" style="text-align: left;">'
									   . esc_html( $repeated_field->label ?? $repeated_field->name ) . '</th>';
					}
				}

				$tableinput .= '</tr></thead><tbody>';
				foreach ( $value as $row ) {
					$tableinput .= '<tr>';
					foreach ( $this->fields[ $key ]->repeats as $repeated_field ) {
						if ( empty( $repeated_field->hide ) ) {
							$tableinput .= '<td class="column-columnname" valign="top">' . esc_html( $row[ $repeated_field->name ] ?? '' ) . '</td>';
						}
					}
					$tableinput .= '</tr>';
				}
				$tableinput .= '</tbody></table>';
				$template_content[ $key ] = $tableinput;
				$markup .= $tableinput;
			}
		}

		$replaced = preg_replace_callback( '/{{\s*(' . implode( '|', array_keys( $this->fields ) ) . ')\s*}}/i', function( $matches ) use ( $template_content ) {
			return $template_content[ $matches[1] ] ?? '';
		}, $template_string );

		if ( ! empty( $markup_template ) ) {
			$replaced = preg_replace( '/{{\s*' . $markup_template . '\s*}}/i', addslashes( $markup ), $replaced );
		}

		return $replaced;
	}
}
