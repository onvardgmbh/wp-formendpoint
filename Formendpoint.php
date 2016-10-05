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


	public static function make($posttype, $heading, $style = 'main') {
		return new Formendpoint($posttype, $heading, $style );
	}

	function __construct($posttype, $heading, $style) {
		if(strlen($posttype) > 20 || preg_match('/\s/',$posttype) || preg_match('/[A-Z]/',$posttype) ) {
            		echo 'ERROR: The endpoint ' . $posttype . ' couldn\'nt be created. Please make sure the posttype name contains max 20 chars and no whitespaces or uppercase letters.';
        		return;
        	}
        	
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
			$this->fields[$field->name] = $field;
		}
		return $this;
	}

	public function show_ui($show_ui) {
		$this->show_ui = $show_ui;
		return $this;
	}

	public function validate($function) {
		$this->validate_function = $function;
		return $this;
	}

	public function handleformsubmit() {
		if($_SERVER["CONTENT_TYPE"] === 'application/json') {
			$this->data = json_decode(file_get_contents('php://input'), true);
		} else {
			$this->data = $_POST;
		}

		if(!wp_verify_nonce($this->data['security'], $this->posttype)) {
			status_header(403);
			wp_die();
		}

		unset($this->data['security']);
		unset($this->data['action']);
		if(isset($this->validate_function) && !($this->validate_function)($this->fields)) {
			status_header(403);
			wp_die();
		}

		foreach($this->honeypots as $honeypot) {
			if(!isset($this->data[$honeypot->name]) || $this->data[$honeypot->name] !== $honeypot->equals) {
				wp_die($honeypot->name);
			}
			unset($this->data[$honeypot->name]);
		}

		foreach ($this->data as $key => $value) {
			$this->sanitizeField($this->data, $this->fields, $key, $value);
		}
		$flatten = [];
		foreach ($this->fields as $field) {
			$this->validateField($field, $this->data[$field->name]);
			if(!empty($this->data[$field->name])) {
				$flatten[] = $field->name;
			}
		}
		$post_id = wp_insert_post( [
			'post_title'    => $this->entryTitle,
			'post_status'   => 'publish',
			'post_type' => $this->posttype,
		] );
		$this->data = array_merge(array_flip($flatten), $this->data);
		foreach ($this->data as $key => $value) {
            if(is_array($value)) {
	            add_post_meta($post_id, $key, json_encode($value));
            } else {
				add_post_meta($post_id, $key, $value);
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
					$body = ($action->body)($post_id, $this->fields);
					if(!$body) {
						continue;
					}
				} else {
					$body = $action->body;
				}
				$allinputs = '';
				$template_content = [];
				foreach ($this->data as $key => $value) {
					if(isset($this->fields[$key]) && !isset($this->fields[$key]->hide)) {
						if($this->fields[$key]->label) {
							$allinputs .= '<h3>'.$this->fields[$key]->label.'</h3>';
						} else {
							$allinputs .= '<h3>'.$this->fields[$key]->name.'</h3>';
						}

						if($this->fields[$key]->type !== 'array') {
							$allinputs .= '<p>'.nl2br($value).'</p>';
							$template_content[$key] = nl2br($value);
						} else {
							$json = $value;
							$tableinput = '';
							$tableinput .= '<table class="wp-list-table widefat fixed striped" cellspacing="0" style="width: 100%;">';
								$tableinput .= '<thead>';
									$tableinput .= '<tr>';
										foreach ($this->fields[$key]->repeats as $field):
											$tableinput .= '<th class="manage-column column-columnname" scope="col" style="text-align: left;">' . $field->label ?? $field->name . '</th>';
										endforeach;
									$tableinput .= '</tr>';
								$tableinput .= '</thead>';

								$tableinput .= '<tbody>';
									foreach ($json as $row):
										$tableinput .= '<tr>';
											foreach ($this->fields[$key]->repeats as $field):
												$tableinput .= '<td class="column-columnname">' . $row[$field->name] ?? '' . '</td>';
											endforeach;
										$tableinput .= '</tr>';
									endforeach;
								$tableinput .= '</tbody>';
							$tableinput .= '</table>';
							$template_content[$key] = $tableinput;
							$allinputs .= $tableinput;
						}
					}
				}
				foreach ($this->fields as $key => $value) {
					$body = preg_replace('/{{\s*' . $key . '\s*}}/', nl2br($template_content[$key] ?? ''), $body);
					$subject = preg_replace('/{{\s*' . $key . '\s*}}/', nl2br($template_content[$key] ?? ''), $subject);
				}
				$body = preg_replace('/{{\s*Alle Inputs\s*}}/', $allinputs, $body);
				wp_mail( $recipient, $subject, $body, $headers);
			} else if(get_class($action) === 'Onvardgmbh\Formendpoint\Callback') {
				($action->function)($post_id, $this->fields);
			}
		}
		wp_die();

	}

	private function sanitizeField(&$data, &$fields, $key, $value) {
		if(!isset($fields[$key]) || empty($data[$key])) {
			unset($data[$key]);
			return;
		}
		if($fields[$key]->type === 'array') {
			foreach ($value as $subkey => $value2) {
				foreach ($value2 as $subsubbkey => $value3) {
					if(!isset($fields[$key]->repeats[$subsubbkey]) || empty($data[$key][$subkey][$subsubbkey])) {
						unset($data[$key][$subkey][$subsubbkey]);
						continue;
					} elseif(is_array($value3)) {
//						foreach ($value3 as $data_key => $data_value) {
//							$this->sanitizeField($data[$key][$subkey][$subsubbkey], $value3, $data_key, $data_value);
//						}
						return new WP_Error( 'broke', __( "Currently array depth is limited to 1", "my_textdomain" ) );
					} else {
						$data[$key][$subkey][$subsubbkey] = htmlentities($data[$key][$subkey][$subsubbkey]);
					}
				}
			}
		} else {
			$data[$key] = htmlentities($value);
		}
	}

	private function validateField($field, $lookup) {
		if($field->type !== 'array') {
			if(isset($field->required) && empty($lookup)) {
				echo 'Field "' . $field->name . '"is required';
				status_header(400);
				wp_die();
			}
			if($field->type === 'email' && (isset($field->required) || !empty($lookup)) && !is_email( $lookup )) {
				echo $lookup . ' is not a valid email address.';
				status_header(400);
				wp_die();
			}
			if(isset($field->title)) {
				$this->entryTitle .= $this->data[$field->name] . ' ';
			}
		} else {
			if(isset($field->required) && !is_array($lookup)) {
				echo 'Field "' . $field->name . '"is required';
				status_header(400);
				wp_die();
			}
			if(!is_array($lookup)) {
				return;
			}
			foreach ($lookup as $userinput) {
				foreach ($field->repeats as $subfield) {
					if($subfield->type !== 'array') {
						$this->validateField($subfield, $userinput[$subfield->name]);
						if(isset($field->title)) {
							$this->entryTitle .= $userinput[$subfield->name] . ' ';
						}
					} else{
//						$this->validateField($subfield, $userinput[$subfield->name]);
						return new WP_Error( 'broke', __( "Currently array depth is limited to 1", "my_textdomain" ) );
					}
				}
			}
		}
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
					if(isset($this->fields[$key]) && !isset($this->fields[$key]->hide)) {
						if($this->fields[$key]->label) {
							echo '<h3>'.$this->fields[$key]->label.'</h3>';
						} else {
							echo '<h3>'.$this->fields[$key]->name.'</h3>';
						}
						foreach ($value as $content) {
							if($this->fields[$key]->type !== 'array') {
								echo '<p>'.nl2br($content).'</p>';
							} else {
								$json = json_decode( $content, true);
								?>
								<table class="wp-list-table widefat fixed striped" cellspacing="0">
									<thead>
									<tr>
										<?php foreach ($this->fields[$key]->repeats as $field):  ?>
											<th class="manage-column column-columnname" scope="col"><?= $field->label ?? $field->name?></th>
										<?php endforeach; ?>
									</tr>
									</thead>
									<?php if(sizeof($json) !== 1) : ?>
									<tfoot>
									<tr>
										<?php foreach ($this->fields[$key]->repeats as $field):  ?>
											<th class="manage-column column-columnname" scope="col"><?= $field->label ?? $field->name?></th>
										<?php endforeach; ?>
									</tr>
									</tfoot>
									<?php endif; ?>

									<tbody>
									<?php foreach ($json as $row): ?>
										<tr>
											<?php foreach ($this->fields[$key]->repeats as $field):
												?>
												<td class="column-columnname"><?= $row[$field->name] ?? ''; ?></td>
											<?php endforeach; ?>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
								<?php
							}
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
