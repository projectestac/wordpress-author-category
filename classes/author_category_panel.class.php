<?php

class author_category_panel extends simple_panel
{
    /** @var string The text domain for translations. */
    public string $txtDomain = 'author_cat';

    /** @var string The hook suffix for the admin page, used as the screen ID. */
    protected string $hook_suffix = '';

    /**
     * Adds the settings page under the "Users" menu and hooks the page load action.
     */
    public function admin_menu(): void
    {
        $this->hook_suffix = add_users_page(
            $this->title,
            $this->name,
            $this->capability,
            $this->slug, // Use the slug from the parent class.
            [$this, 'show_page']
        );

        // This is the correct hook to use for adding metaboxes and enqueueing scripts
        // for a custom admin page. It fires before the page is rendered.
        if ($this->hook_suffix) {
            add_action('load-' . $this->hook_suffix, [$this, 'on_load_page']);
        }
    }

    /**
     * Actions to take when the settings page is loaded.
     */
    public function on_load_page(): void
    {
        // Add the metaboxes for the settings page.
        $this->add_meta_boxes();

        // Required for metabox functionality (drag and drop, etc.).
        wp_enqueue_script('postbox');
    }

    /**
     * Renders the settings page with a metabox layout.
     */
    public function show_page(): void
    {
        ?>
        <div class="wrap">
            <h2><?php echo esc_html($this->name); ?></h2>
            <form action="options.php" method="POST">
                <?php
                // Outputs the hidden fields (nonce, action, option_page) for the settings API.
                foreach ($this->sections as $s) {
                    settings_fields($s['option_group']);
                }
                ?>
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <!-- Main content -->
                        <div id="post-body-content">
                            <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                                <?php do_meta_boxes($this->hook_suffix, 'normal', null); ?>
                            </div>
                        </div>
                        <!-- Sidebar -->
                        <div id="postbox-container-1" class="postbox-container">
                            <div id="side-sortables" class="meta-box-sortables ui-sortable">
                                <?php do_meta_boxes($this->hook_suffix, 'side', null); ?>
                            </div>
                        </div>
                    </div>
                    <br class="clear">
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Adds the metaboxes to our custom settings page.
     */
    public function add_meta_boxes(): void
    {
        add_meta_box(
            'author_category_main_settings',
            __('Author category settings', $this->txtDomain),
            [$this, 'render_main_settings_metabox'],
            $this->hook_suffix,
            'normal',
            'core'
        );

        add_meta_box(
            'author_category_save_changes',
            __('Save changes', $this->txtDomain),
            [$this, 'render_save_changes_metabox'],
            $this->hook_suffix,
            'side',
            'core'
        );
    }

    /**
     * Renders the content of the main settings metabox.
     */
    public function render_main_settings_metabox(): void
    {
        // This renders the sections and fields registered by the parent class
        // for our settings page slug (`$this->slug`).
        do_settings_sections($this->slug);
    }

    /**
     * Renders the content of the "Save Changes" metabox.
     */
    public function render_save_changes_metabox(): void
    {
        submit_button(__('Save Settings', $this->txtDomain), 'primary', 'submit', false);
    }
}
