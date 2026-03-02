<?php
declare(strict_types=1);

/**
 * A simple class to create option panels for WordPress themes and plugins
 * using the native Settings API.
 *
 * @version 0.5
 * @author Ohad Raz <admin@bainternet.info>
 * @author Toni Ginard
 * @copyright 2023 Ohad Raz
 */
class simple_panel
{
    /** @var string The title displayed on the settings page. */
    public string $title = '';

    /** @var string The name of the menu item. */
    public string $name = '';

    /** @var string The capability required to access the settings page. */
    public string $capability = 'manage_options';

    /** @var string The key used to store the options in the database. */
    public string $option = '';

    /** @var array The registered fields for the settings page. */
    public array $fields = [];

    /** @var array The registered sections for the settings page. */
    public array $sections = [];

    /** @var string The unique slug for the settings page. */
    public string $slug = '';

    /** @var array|null A cache for the retrieved options. */
    private ?array $options_cache = null;

    /**
     * Constructor.
     * @param array $args Arguments to set up the panel.
     */
    public function __construct(array $args = [])
    {
        $this->setProperties($args);
        if (empty($this->slug)) {
            $this->slug = sanitize_title($this->name);
        }
        $this->hooks();
    }

    /**
     * Sets the class properties from an array.
     * @param array $args
     */
    public function setProperties(array $args = []): void
    {
        foreach ($args as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Registers WordPress hooks.
     */
    public function hooks(): void
    {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Adds the settings page to the WordPress admin menu.
     */
    public function admin_menu(): void
    {
        add_options_page(
            $this->title,
            $this->name,
            $this->capability,
            $this->slug,
            [$this, 'show_page']
        );
    }

    /**
     * Registers all settings, sections, and fields.
     */
    public function register_settings(): void
    {
        foreach ($this->sections as $s) {
            add_settings_section($s['id'], $s['title'], [$this, 'section_callback'], $this->slug);
            register_setting($s['option_group'], $this->option, [$this, 'sanitize_callback']);
        }
        foreach ($this->fields as $f) {
            add_settings_field($f['id'], $f['label'], [$this, 'show_field'], $this->slug, $f['section'], $f);
        }
    }

    /**
     * Renders the settings page wrapper and form.
     */
    public function show_page(): void
    {
        ?>
        <div class="wrap">
            <h2><?php echo esc_html($this->name); ?></h2>
            <form action="options.php" method="POST">
                <?php
                foreach ($this->sections as $s) {
                    settings_fields($s['option_group']);
                }
                do_settings_sections($this->slug);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Sanitizes the input from the settings form.
     * @param array $input The raw input from the form.
     * @return array The sanitized and merged options.
     */
    public function sanitize_callback(array $input): array
    {
        $options = get_option($this->option, []);
        if (!is_array($options)) {
            $options = [];
        }

        // Allow filtering for custom sanitization.
        $sanitized_input = apply_filters('simple_panel_sanitize', $input, $this->option, $this);

        // Return merged options.
        return array_merge($options, $sanitized_input);
    }

    /**
     * Renders a settings field by calling the appropriate method based on its type.
     * @param array $args The arguments for the field.
     */
    public function show_field(array $args): void
    {
        $method_name = '_setting_' . $args['type'];
        if (method_exists($this, $method_name)) {
            $this->{$method_name}($args);
            $this->_settings_field_desc($args);
        }
    }

    /**
     * Retrieves a single option value by its key.
     * @param string $key The key of the option.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The option value.
     */
    public function get_value(string $key = '', $default = ''): mixed
    {
        if ($this->options_cache === null) {
            $options = get_option($this->option, []);
            // If the stored option is not an array (e.g., it's an old value or corrupted),
            // default to an empty array to prevent a TypeError.
            if (!is_array($options)) {
                $options = [];
            }
            $this->options_cache = $options;
        }

        return $this->options_cache[$key] ?? $default;
    }

    /**
     * Renders the description for a settings field.
     * @param array $args
     */
    private function _settings_field_desc(array $args): void
    {
        if (!empty($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }

    /**
     * Renders a 'text' input field.
     * @param array $args
     */
    private function _setting_text(array $args): void
    {
        $std = $args['std'] ?? '';
        $value = $this->get_value($args['id'], $std);
        printf(
            '<input type="text" name="%s" value="%s" />',
            esc_attr($args['name']),
            esc_attr($value)
        );
    }

    /**
     * Renders a 'select' dropdown field.
     * @param array $args
     */
    private function _setting_select(array $args): void
    {
        $std = $args['std'] ?? '';
        $value = $this->get_value($args['id'], $std);
        $items = $args['options'] ?? [];

        printf('<select name="%s">', esc_attr($args['name']));
        foreach ($items as $label => $option_value) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    /**
     * Renders a 'textarea' field.
     * @param array $args
     */
    private function _setting_textarea(array $args): void
    {
        $std = $args['std'] ?? '';
        $value = $this->get_value($args['id'], $std);
        printf(
            '<textarea name="%s" rows="7" cols="50">%s</textarea>',
            esc_attr($args['name']),
            esc_textarea($value)
        );
    }

    /**
     * Renders a 'checkbox' field.
     * @param array $args
     */
    private function _setting_checkbox(array $args): void
    {
        $std = $args['std'] ?? false;
        $value = (bool)$this->get_value($args['id'], $std);
        printf(
            '<input type="checkbox" name="%s" value="1" %s />',
            esc_attr($args['name']),
            checked($value, true, false)
        );
    }

    /**
     * Adds a field to the panel.
     * @param array $field_args The arguments for the field.
     */
    public function add_field(array $field_args): void
    {
        $field_args['name'] = sprintf('%s[%s]', $this->option, $field_args['id']);
        $this->fields[] = $field_args;
    }

    /**
     * Adds a section to the panel.
     * @param array $section_args The arguments for the section.
     * @return string The ID of the added section.
     */
    public function add_section(array $section_args): string
    {
        $this->sections[] = $section_args;
        return $section_args['id'];
    }

    /**
     * A callback function to render the section's description.
     * Can be overridden in a child class.
     * @param array $args
     */
    public function section_callback(array $args): void
    {
        // Child classes can implement this to show a description for the section.
    }
}
