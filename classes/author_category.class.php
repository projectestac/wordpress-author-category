<?php

class author_category
{
    /**
     * The plugin's text domain for translations.
     * @var string
     */
    public string $txtDomain = 'author_cat';

    /**
     * The constructor registers all hooks.
     */
    public function __construct()
    {
        $this->hooks();
        if (is_admin()) {
            $this->adminHooks();
        }
    }

    /**
     * Registers the public-facing hooks.
     */
    public function hooks(): void
    {
        // Save categories when user profile is updated.
        add_action('personal_options_update', [$this, 'save_extra_user_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_extra_user_profile_fields']);

        // Show categories in user profile.
        add_action('show_user_profile', [$this, 'extra_user_profile_fields']);
        add_action('edit_user_profile', [$this, 'extra_user_profile_fields']);

        // Hooks for default category on post creation (XML-RPC, QuickPress).
        add_filter('xmlrpc_wp_insert_post_data', [$this, 'user_default_category'], 2);
        add_filter('pre_option_default_category', [$this, 'user_default_category_option']);

        // Hook for post-by-email category.
        add_filter('publish_phone', [$this, 'post_by_email_cat']);
    }

    /**
     * Registers the admin-specific hooks.
     */
    public function adminHooks(): void
    {
        $this->load_translation();

        // Remove quick and bulk edit if the user is restricted.
        global $pagenow;
        if ('edit.php' === $pagenow) {
            add_action('admin_head', [$this, 'remove_quick_edit_style']);
        }

        // Add the custom category metabox.
        add_action('add_meta_boxes', [$this, 'add_meta_box']);

        // Add the admin settings panel.
        require_once plugin_dir_path(__FILE__) . 'simple_panel.class.php';
        require_once plugin_dir_path(__FILE__) . 'author_category_panel.class.php';

        $p = new author_category_panel([
            'title' => __('Author category settings', $this->txtDomain),
            'name' => __('Author category', $this->txtDomain),
            'capability' => 'manage_options',
            'option' => 'author_cat_option',
        ]);

        $setting = $p->add_section([
            'option_group' => 'author-cat-group',
            'id' => 'author_cat_id',
            'title' => '',
        ]);

        $p->add_field([
            'label' => __('Check none by default', $this->txtDomain),
            'std' => false,
            'id' => 'check_multi',
            'type' => 'checkbox',
            'section' => $setting,
            'desc' => __('When using multiple categories they are all checked by default, check this box to disable that.', $this->txtDomain),
        ]);
    }

    /**
     * Sets the default category for a user when creating a post via interfaces like QuickPress.
     * @return int|false The category ID or false.
     */
    public function user_default_category_option(): bool|int
    {
        $cat = $this->get_user_cat();
        return !empty($cat) ? $cat : false;
    }

    /**
     * Sets the category for posts created via XML-RPC.
     * @param array $post_data Post data.
     * @return array Modified post data.
     */
    public function user_default_category(array $post_data): array
    {
        $cat = $this->get_user_cat($post_data['post_author']);

        if (!empty($cat) && $cat > 0) {
            $post_data['tax_input']['category'] = [$cat];
        }

        return $post_data;
    }

    /**
     * Sets the category for posts created via email.
     * @param int $post_id The post ID.
     */
    public function post_by_email_cat(int $post_id): void
    {
        $p = get_post($post_id);
        $cat = $this->get_user_cat($p->post_author);

        if ($cat) {
            wp_update_post([
                'ID' => $post_id,
                'post_category' => [$cat],
            ]);
        }
    }

    /**
     * Adds inline CSS to the admin head to hide the quick edit category selector.
     */
    public function remove_quick_edit_style(): void
    {
        $user_id = get_current_user_id();
        $cat = $this->get_user_cat($user_id);

        if (!empty($cat)) {
            echo '<style>.inline-edit-categories { display: none !important; }</style>';
        }
    }

    /**
     * Adds the custom metabox to the post editor if the user is restricted.
     */
    public function add_meta_box(): void
    {
        $user_id = get_current_user_id();
        $cats = $this->get_user_cats($user_id);

        if (!empty($cats)) {
            // Remove the default category metabox.
            remove_meta_box('categorydiv', 'post', 'side');

            // Add our custom metabox.
            add_meta_box(
                'author_cat',
                __('Author category', $this->txtDomain),
                [$this, 'render_meta_box_content'],
                'post',
                'side',
                'low'
            );
        }
    }

    /**
     * Renders the content of the custom category metabox.
     * @param WP_Post $post The post object being edited.
     */
    public function render_meta_box_content(WP_Post $post): void
    {
        $user_id = get_current_user_id();
        $author_cats = $this->get_user_cats($user_id);

        // Use nonce for verification.
        wp_nonce_field('author_cat_save_metabox', 'author_cat_noncename');

        if (empty($author_cats)) {
            return;
        }

        if (count($author_cats) === 1) {
            $c = get_category($author_cats[0]);
            echo esc_html__('This will be posted in: ', $this->txtDomain) . '<strong>' . esc_html($c->name) . '</strong>';
            echo '<input name="post_category[]" type="hidden" value="' . esc_attr($c->term_id) . '">';
        } else {
            echo '<span style="color: #f00;">' .
                    esc_html__('Make Sure you select only the categories you want:', $this->txtDomain) .
                    '</span><br />';

            // Get the categories this post is currently in.
            $post_cats = wp_get_post_categories($post->ID, ['fields' => 'ids']);
            $options = get_option('author_cat_option');

            foreach ($author_cats as $cat_id) {
                $c = get_category($cat_id);
                // If creating a new post, check all allowed categories by default (unless disabled in settings).
                // If editing an existing post, only check the categories the post is actually in.
                if ($post->post_status === 'auto-draft') {
                    $is_checked = !isset($options['check_multi']);
                } else {
                    $is_checked = in_array($cat_id, $post_cats, true);
                }

                echo '<label><input name="post_category[]" type="checkbox"' . checked($is_checked, true, false) . ' value="' . esc_attr($c->term_id) . '"> ' . esc_html($c->name) . '</label><br />';
            }
        }

        do_action('in_author_category_metabox', $user_id);
    }

    /**
     * Renders the category selection fields on the user profile page.
     * @param WP_User $user The user object.
     */
    public function extra_user_profile_fields(WP_User $user): void
    {
        if (!current_user_can('manage_options') || get_current_user_id() === $user->ID) {
            return;
        }

        // Add a nonce for security.
        wp_nonce_field('save_author_category', 'author_cat_nonce');

        $select = wp_dropdown_categories([
            'orderby' => 'name',
            'show_count' => false,
            'hierarchical' => true,
            'hide_empty' => false,
            'echo' => false,
            'name' => 'author_cat[]',
        ]);

        $saved_cats = $this->get_user_cats($user->ID);

        foreach ($saved_cats as $c) {
            $select = str_replace('value="' . $c . '"', 'value="' . $c . '" selected="selected"', $select);
        }

        $select = str_replace('<select', '<select multiple="multiple" style="width:100%; height:300px;"', $select);

        echo '<h3>' . esc_html__('Author Category', 'author_cat') . '</h3>
            <table class="form-table">
                <tr>
                    <th><label for="author_cat">' . esc_html__('Category', $this->txtDomain) . '</label></th>
                    <td>
                        ' . $select . '
                        <br />
                        <span class="description">' . esc_html__('select a category to limit an author to post just in that category (use Crtl to select more then one).', $this->txtDomain) . '</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="author_cat_clear">' . esc_html__('Clear Category', $this->txtDomain) . '</label></th>
                    <td>
                        <input type="checkbox" name="author_cat_clear" value="1" />
                        <br />
                        <span class="description">' . esc_html__('Check if you want to clear the limitation for this user.', $this->txtDomain) . '</span>
                    </td>
                </tr>
            </table>';
    }

    /**
     * Saves the custom user profile fields.
     * @param int $user_id The user ID being updated.
     */
    public function save_extra_user_profile_fields(int $user_id): void
    {
        // Security checks: user capability and nonce.
        if (!isset($_POST['author_cat_nonce']) || !current_user_can('manage_options') || !wp_verify_nonce($_POST['author_cat_nonce'], 'save_author_category')) {
            return;
        }

        // If the clear checkbox is checked, delete the meta field.
        if (isset($_POST['author_cat_clear']) && $_POST['author_cat_clear'] === '1') {
            delete_user_meta($user_id, '_author_cat');
            return;
        }

        // Sanitize and save the selected categories.
        if (isset($_POST['author_cat']) && is_array($_POST['author_cat'])) {
            $sanitized_cats = array_map('intval', $_POST['author_cat']);
            update_user_meta($user_id, '_author_cat', $sanitized_cats);
        } else {
            // If nothing is selected, but we didn't clear, it might mean removing the restriction.
            delete_user_meta($user_id, '_author_cat');
        }
    }

    /**
     * Gets the first restricted category for a user.
     * Used for features that only support a single category.
     * @param int|null $user_id User ID. Defaults to the current user.
     * @return int The first category ID, or 0 if none.
     */
    public function get_user_cat(?int $user_id = null): int
    {
        $cats = $this->get_user_cats($user_id);
        return $cats[0] ?? 0;
    }

    /**
     * Gets all restricted categories for a user.
     * @param int|null $user_id User ID. Defaults to the current user.
     * @return array An array of category IDs.
     */
    public function get_user_cats(?int $user_id = null): array
    {
        $user_id = $user_id ?? get_current_user_id();

        if (!$user_id) {
            return [];
        }

        $cats = get_user_meta($user_id, '_author_cat', true);

        return is_array($cats) ? $cats : [];
    }

    /**
     * Loads the plugin's translated strings.
     */
    public function load_translation(): void
    {
        load_plugin_textdomain($this->txtDomain, false, dirname(plugin_basename(__FILE__), 2) . '/languages/');
    }
}
