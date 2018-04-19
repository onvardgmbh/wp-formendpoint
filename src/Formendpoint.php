<?php

namespace Onvardgmbh\Formendpoint;

class Formendpoint
{
    private $data;
    private $csvExportCallback;
    public $posttype;
    public $heading;
    public $style;
    public $fields;
    public $actions;
    public $honeypots;
    public $entryTitle;
    public $labels;
    public $show_ui;
    public $showInMenu;
    public $validate_function;

    public static function make(string $posttype, string $heading, string $style = 'main'): Formendpoint
    {
        return new self($posttype, $heading, $style);
    }

    public function __construct(string $posttype, string $heading, string $style)
    {
        if (strlen($posttype) > 20 || preg_match('/\s/', $posttype) || preg_match('/[A-Z]/', $posttype)) {
            wp_die('ERROR: The endpoint '.$posttype.' couldn\'nt be created. Please make sure the posttype name contains max 20 chars and no whitespaces or uppercase letters.', '', ['response' => 400]);
        }

        $this->posttype = $posttype;
        $this->heading = $heading;
        $this->style = $style;
        $this->actions = [];
        $this->fields = [];
        $this->honeypots = [];
        $this->entryTitle = '';
        $this->show_ui = true;

        add_action('init', array($this, 'dates_post_type_init'));
        add_action('add_meta_boxes_'.$this->posttype, array($this, 'adding_custom_meta_boxes'));
        add_action('wp_enqueue_scripts', function () {
            wp_localize_script($this->style, $this->posttype, array(
                // URL to wp-admin/admin-ajax.php to process the request
                'ajaxurl' => admin_url('admin-ajax.php'),
                // Generate a nonce with a unique ID "myajax-post-comment-nonce"
                // so that you can check it later when an AJAX request is sent
                'security' => wp_create_nonce($this->posttype), ));
        });
        add_action('wp_ajax_'.$this->posttype, array($this, 'handleformsubmit'));
        add_action('wp_ajax_nopriv_'.$this->posttype, array($this, 'handleformsubmit'));
    }

    /**
     * @param Callback[]|Email[] $actions
     */
    public function add_actions(array $actions): Formendpoint
    {
        foreach ($actions as $action) {
            $this->actions[] = $action;
        }

        return $this;
    }

    /**
     * @param Honeypot[] $honeypots
     */
    public function add_honeypots(array $honeypots): Formendpoint
    {
        foreach ($honeypots as $honeypot) {
            $this->honeypots[] = $honeypot;
        }

        return $this;
    }

    /**
     * @param Input[] $fields
     */
    public function add_fields(array $fields): Formendpoint
    {
        foreach ($fields as $field) {
            $this->fields[$field->name] = $field;
        }

        return $this;
    }

    public function csvExport($callback = null): Formendpoint
    {
        if (!empty($callback)) {
            $this->csvExportCallback = $callback;
        }

        add_action('restrict_manage_posts', [$this, 'addCsvButton']);
        add_action('admin_init', [$this, 'csvExportHandler']);

        return $this;
    }

    public function show_ui(bool $show_ui): Formendpoint
    {
        $this->show_ui = $show_ui;

        return $this;
    }

    public function show_in_menu(bool $showInMenu): Formendpoint
    {
        $this->showInMenu = $showInMenu;

        return $this;
    }

    public function setLabels(array $labels): Formendpoint
    {
        $this->labels = $labels;

        return $this;
    }

    public function validate(callable $function): Formendpoint
    {
        $this->validate_function = $function;

        return $this;
    }

    public function handleformsubmit()
    {
        $this->data = 'application/json' === $_SERVER['CONTENT_TYPE']
            ? json_decode(file_get_contents('php://input'), true)
            : $_POST;

        unset($this->data['security']);
        unset($this->data['action']);
        if (isset($this->validate_function) && !($this->validate_function)($this->fields)) {
            wp_die('', '', ['response' => 403]);
        }

        foreach ($this->honeypots as $honeypot) {
            if (!isset($this->data[$honeypot->name]) || $this->data[$honeypot->name] !== $honeypot->equals) {
                wp_die($honeypot->name, '', ['response' => 403]);
            }
            unset($this->data[$honeypot->name]);
        }

        foreach ($this->data as $key => $value) {
            $this->sanitizeField($this->data, $this->fields, $key, $value);
        }
        $flatten = [];
        foreach ($this->fields as $field) {
            $this->validateField($field, $this->data[$field->name] ?? null);
            if (!empty($this->data[$field->name])) {
                $flatten[] = $field->name;
            }
        }
        $post_id = wp_insert_post([
            'post_title' => $this->entryTitle,
            'post_status' => 'publish',
            'post_type' => $this->posttype,
        ]);
        $this->data = array_merge(array_flip($flatten), $this->data);
        $this->data['referer'] = $_SERVER['HTTP_REFERER'] ?? '';
        $this->data['referrer'] = $_SERVER['HTTP_REFERER'] ?? '';
        $this->data['referrer_post_id'] = $this->getReferrerId($_SERVER['HTTP_REFERER'] ?? '');
        foreach ($this->data as $key => $value) {
            if (is_array($value)) {
                add_post_meta($post_id, $key, addslashes(json_encode($value)));
            } elseif (is_bool($value)) {
                add_post_meta($post_id, $key, $value ? 'true' : 'false');
            } else {
                add_post_meta($post_id, $key, addslashes($value));
            }
        }

        foreach ($this->actions as $action) {
            if ('Onvardgmbh\Formendpoint\Email' === get_class($action)) {
                $recipient = 'object' === gettype($action->recipient)
                    ? ($action->recipient)($post_id, $this->fields, $this->data)
                    : $action->recipient;

                $subject = 'object' === gettype($action->subject)
                    ? ($action->subject)($post_id, $this->fields, $this->data)
                    : $action->subject;

                $body = 'object' === gettype($action->body)
                    ? ($action->body)($post_id, $this->fields, $this->data)
                    : $action->body;

                if ($recipient && $subject && $body) {
                    wp_mail($recipient, $this->template_replace($subject, $this->data),
                        $this->template_replace($body, $this->data, 'Alle inputs'),
                        array('Content-Type: text/html; charset=UTF-8'));
                }
            } elseif ('Onvardgmbh\Formendpoint\Callback' === get_class($action)) {
                ($action->function)($post_id, $this->fields, $this->data);
            }
        }
        wp_die();
    }

    private function sanitizeField(array &$data, array &$fields, string $key, $value)
    {
        if (!isset($fields[$key]) || (!isset($data[$key]) || '' === $data[$key] || !count($data[$key]))) {
            unset($data[$key]);

            return;
        } elseif ('array' === $fields[$key]->type) {
            if ('application/json' !== $_SERVER['CONTENT_TYPE']) {
                wp_die('Error: Arrays no longer supported for plain form-data requests.', '', ['response' => 400]);
            }
            foreach ($value as $subkey => $value2) {
                foreach ($value2 as $subsubbkey => $value3) {
                    if (!isset($fields[$key]->repeats[$subsubbkey])
                         || !isset($data[$key][$subkey][$subsubbkey])
                         || !count($data[$key][$subkey][$subsubbkey])
                         || $data[$key][$subkey][$subsubbkey] === ''
                    ) {
                        unset($data[$key][$subkey][$subsubbkey]);
                        continue;
                    } elseif (is_array($value3)) {
                        wp_die('Error: Currently array depth is limited to 1.', '', ['response' => 400]);
                    }
                }
            }
        }
        if ('application/json' !== $_SERVER['CONTENT_TYPE'] && 'array' !== $fields[$key]->type && is_string($data[$key])) {
            $data[$key] = stripslashes($data[$key]);
        }
    }

    /**
     * @param Input $field - The input to validate
     */
    private function validateField(Input $field, $value)
    {
        if ('array' !== $field->type) {
            if (isset($field->required) && (!isset($value) || '' === $value || !count($value))) {
                wp_die('Field "'.$field->name.'"is required', '', ['response' => 400]);
            }
            if ('email' === $field->type && (isset($field->required) || !empty($value)) && !is_email($value)) {
                wp_die($value.' is not a valid email address.', '', ['response' => 400]);
            }
            if (isset($field->title)) {
                $this->entryTitle .= ($this->data[$field->name] ?? '').' ';
            }
        } else {
            if (isset($field->required) && !is_array($value)) {
                wp_die('Field "'.$field->name.'"is required', '', ['response' => 400]);
            }
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $userinput) {
                foreach ($field->repeats as $subfield) {
                    if ('array' !== $subfield->type) {
                        $this->validateField($subfield, $userinput[$subfield->name]);
                        if (isset($field->title)) {
                            $this->entryTitle .= $userinput[$subfield->name].' ';
                        }
                    } else {
                        wp_die('Error: Currently array depth is limited to 1.', '', ['response' => 400]);
                    }
                }
            }
        }
    }

    public function dates_post_type_init()
    {
        $options = [
            'labels' => [
                'name' => _x('Eintrag', 'post type general name'),
                'singular_name' => _x('Eintrag', 'post type singular name'),
                'add_new_item' => __('Neuer Eintrag'),
                'edit_item' => __('Eintrag bearbeiten'),
                'new_item' => __('Neuer Eintrag'),
                'all_items' => __('Alle Einträge'),
                'view_item' => __('Eintrag ansehen'),
                'search_items' => __('Einträge durchsuchen'),
                'not_found' => __('Keinen Eintrag gefunden'),
                'not_found_in_trash' => __('Keine Einträge im Papierkorb gefunden'),
                'parent_item_colon' => '',
                'menu_name' => $this->heading,
            ],
            'public' => false,
            'show_ui' => $this->show_ui,
            'menu_icon' => 'dashicons-email',
            'supports' => [''],
            'has_archive' => false,
            'capabilities' => [
                'create_posts' => 'do_not_allow',
            ],
            'map_meta_cap' => true,
        ];

        if (!empty($this->labels)) {
            $options['labels'] = $this->labels;
        }

        if (!empty($this->showInMenu)) {
            $options['show_in_menu'] = $this->showInMenu;
        }

        register_post_type($this->posttype, $options);
    }

    public function addCsvButton()
    {
        if (!isset($_GET['post_type']) || $this->posttype !== $_GET['post_type']) {
            return;
        }

        echo '<input type="submit" class="button" style="margin:1px 8px 0 0" name="'.$this->posttype.'_csv_export" value="'.
            __('CSV export').'">';
    }

    public function csvExportHandler()
    {
        if (empty($_GET[$this->posttype.'_csv_export']) || !current_user_can('edit_others_posts')) {
            return;
        }

        $query = new \WP_Query();
        $query->parse_query($_SERVER['QUERY_STRING']);
        $query->set('posts_per_page', -1);
        $query->set('post_status', 'publish');
        $query->is_search = true;
        $query->get_posts();

        // First line with column headings
        $heading = array_map(function ($input) {
            return $input->label ?? $input->name;
        }, $this->fields);
        $heading[] = 'id';
        $heading[] = 'date';
        $data = [$heading];

        // Collect the posts
        while ($query->have_posts()) {
            $query->the_post();
            global $post;
            $item = array_map(function ($input) use ($post) {
                return get_post_meta($post->ID, $input->name, true);
            }, $this->fields);
            $item['id'] = $post->ID;
            $item['date'] = get_the_date('c');
            $data[] = $item;
        }

        if (is_callable($this->csvExportCallback)) {
            $data = ($this->csvExportCallback)($this->fields, $data);
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename='.$this->posttype.'-export-'.date('Y-m-d_His').'.csv');

        $outstream = fopen('php://output', 'w');
        foreach ($data as $line) {
            fputcsv($outstream, $line, ';', '"');
        }
        fclose($outstream);

        die();
    }

    public function adding_custom_meta_boxes($post)
    {
        add_meta_box(
            'my-meta-box',
            __('Eintrag'),
            function () {
                $data = [];
                foreach (get_post_custom() as $key => $value) { //TODO Iterate over registered inputs instead
                    if (isset($this->fields[$key])) {
                        if ('array' === $this->fields[$key]->type) {
                            $data[$key] = json_decode($value[0], true);
                        } elseif (is_callable($this->fields[$key]->format)) {
                            $data[$key] = ($this->fields[$key]->format)($value[0]);
                        } else {
                            $data[$key] = $value[0];
                        }
                    }
                }

                echo $this->template_replace('{{all}}', $data, 'all'); //TODO Use templating
            },
            $this->posttype,
            'normal'
        );
    }

    /**
     * Takes a template string and replaces variables in double braces.
     *
     * @param $template_string {string} - The string to process
     * @param $data {array} - An instance of Formendpoint->data, or similar, like data from `get_post_custom()`
     * @param $markup_template {string} - If this pattern occurs within double braces in the $template_string,
     *        it is replaced by a HTML representation of all the data
     *
     * @return {string} - The $template_string, with all valid double brace replacement points replaced
     */
    private function template_replace($template_string, $data, $markup_template = null)
    {
        $markup = '';
        $template_content = [];

        foreach ($data as $key => $value) {
            $field = $this->fields[$key] ?? null;
            if (empty($field) || !empty($field->hide)) {
                continue;
            }
            $markup .= '<p>';
            $markup .= '<b>'.esc_html($field->label ?: $field->name).': </b>';

            if ('array' !== $field->type) {
                $markup .= 'textarea' === $field->type ? '<br>' : '';
                $markup .= nl2br(esc_html($value));
                $template_content[$key] = nl2br(esc_html($value));
                $markup .= '</p>';
            } else {
                $markup .= '</p>';
                $tableinput = '<table class="wp-list-table widefat fixed striped" cellspacing="0" style="width: 100%;"><thead><tr>';
                foreach ($field->repeats as $repeated_field) {
                    if (empty($repeated_field->hide)) {
                        $tableinput .= '<th class="manage-column column-columnname" scope="col" valign="top" style="text-align: left;">'
                                       .esc_html($repeated_field->label ?? $repeated_field->name).'</th>';
                    }
                }

                $tableinput .= '</tr></thead><tbody>';
                foreach ($value as $row) {
                    $tableinput .= '<tr>';
                    foreach ($this->fields[$key]->repeats as $repeated_field) {
                        if (empty($repeated_field->hide)) {
                            $tableinput .= '<td class="column-columnname" valign="top">'.esc_html($row[$repeated_field->name] ?? '').'</td>';
                        }
                    }
                    $tableinput .= '</tr>';
                }
                $tableinput .= '</tbody></table>';
                $template_content[$key] = $tableinput;
                $markup .= $tableinput;
            }
        }

        $replaced = preg_replace_callback('/{{\s*('.implode('|', array_keys($this->fields)).')\s*}}/i', function ($matches) use ($template_content) {
            return $template_content[$matches[1]] ?? '';
        }, $template_string);

        if (!empty($markup_template)) {
            $replaced = preg_replace('/{{\s*'.$markup_template.'\s*}}/i', addslashes($markup), $replaced);
        }

        return $replaced;
    }

    /**
     * Returns the ID of the post of the given referrer URL.
     *
     * @param $url {string} - The referrer URL
     *
     * @return {int} - The id of the given URL
     */
    private function getReferrerId(string $url): int
    {
        if ('' === $url) {
            return 0;
        }
        if (untrailingslashit($url) === home_url()) {
            return (int) get_option('page_on_front');
        }
        $ref_url_slug = basename(untrailingslashit(trim(parse_url($url, PHP_URL_PATH), '/')));

        return get_page_by_path($ref_url_slug, OBJECT, get_post_types())->ID ?? 0;
    }
}
