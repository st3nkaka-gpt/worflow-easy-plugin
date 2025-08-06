<?php
/**
 * Plugin Name: Workflow easy
 * Plugin URI:  https://example.com
 * Description: Adds hierarchical user levels and admin menu controls. The plugin creates a `workflow_superadmin` role on activation and allows the creation of additional levels, reordering, and per‑level admin menu visibility.
 * Version:     1.9.0
 * Author:      Thomas & Effie
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: workflow-easy
 *
 * @package WorkflowEasy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Activation hook callback.
 *
 * Creates the `workflow_superadmin` role based on the administrator capabilities.
 * Assigns the activating administrator user to the superadmin role. Initializes
 * default settings for level order and menu visibility. According to WordPress
 * documentation, new roles should be created on activation and not on every
 * page load【709623388398262†L141-L160】.
 */
function workflow_easy_activate() {
    // Create superadmin role if it doesn't exist.
    $admin_role = get_role( 'administrator' );
    $caps       = $admin_role ? $admin_role->capabilities : array( 'read' => true );
    if ( ! get_role( 'workflow_superadmin' ) ) {
        add_role( 'workflow_superadmin', 'Workflow Superadmin', $caps );
    }

    // Initialize level structure if not already present.
    $levels = get_option( 'workflow_easy_levels' );
    if ( ! is_array( $levels ) ) {
        // The superadmin level is always at the top with order 0.
        $levels = array(
            'workflow_superadmin' => array(
                'name'  => 'Workflow Superadmin',
                'order' => 0,
            ),
        );
        update_option( 'workflow_easy_levels', $levels );
    }

    // Initialize menu visibility settings if not present. Each level will have
    // an associative array keyed by menu slug with boolean show/hide flags.
    $menus = get_option( 'workflow_easy_menus' );
    if ( ! is_array( $menus ) ) {
        $menus = array();
        update_option( 'workflow_easy_menus', $menus );
    }

    // Assign current user (activating user) to superadmin role if they were admin.
    if ( function_exists( 'wp_get_current_user' ) ) {
        $user = wp_get_current_user();
        if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
            $user->add_role( 'workflow_superadmin' );
        }
    }
}
register_activation_hook( __FILE__, 'workflow_easy_activate' );

/**
 * Main plugin class.
 */
class Workflow_Easy {

    /**
     * Singleton instance.
     *
     * @var Workflow_Easy|null
     */
    protected static $instance = null;

    /**
     * Retrieve singleton instance.
     *
     * @return Workflow_Easy
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct plugin object. Hooks into WordPress actions and filters.
     */
    private function __construct() {
        // Add plugin settings page for superadmins.
        add_action( 'admin_menu', array( $this, 'register_admin_page' ) );

        /*
         * In earlier versions of this plugin we removed admin menu items and
         * updated role capabilities in PHP. While functional, that approach
         * caused menus to "flash" on page load and required complex role
         * management.  Starting with version 1.7.0 we adopt a CSS-based
         * strategy: we leave the admin menu intact and instead hide unwanted
         * items visually for each level.  This makes the UI feel snappier and
         * avoids unexpected capability changes.
         */

        // Add a unique class to the <body> tag in wp-admin based on the
        // highest‑priority level for the current user.  This class will be
        // used to scope CSS rules that hide menu items for that level.
        add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ) );

        // Output inline CSS in the admin <head> tag to hide menu items for
        // each level based on the saved menu visibility settings.  We use
        // admin_head because by this point the global $menu has been
        // constructed by WordPress and other plugins.
        add_action( 'admin_head', array( $this, 'output_visibility_css' ) );

        // Run clean‑up routines when the plugin version changes.  This ensures
        // that old data structures, options and roles from previous versions
        // are removed or migrated.  The cleanup only runs once per version.
        // We hook this to admin_menu with a very high priority so that it runs
        // after WordPress and all plugins have populated the global $menu.  At
        // this point we can safely inspect $menu to determine which
        // capabilities were associated with menu items in older versions.
        add_action( 'admin_menu', array( $this, 'maybe_run_update' ), 9999 );
    }

    /**
     * Register the plugin's admin menu page.
     */
    public function register_admin_page() {
        // Only allow superadmins to access settings. Use capability slug equal to our role.
        $capability = 'workflow_superadmin';
        add_menu_page(
            __( 'Workflow easy', 'workflow-easy' ),
            __( 'Workflow easy', 'workflow-easy' ),
            $capability,
            'workflow-easy',
            array( $this, 'render_admin_page' ),
            'dashicons-admin-generic',
            90
        );
    }

    /**
     * Render the plugin's admin page with forms for managing levels and menus.
     */
    public function render_admin_page() {
        // Only superadmins may proceed.
        if ( ! current_user_can( 'workflow_superadmin' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'workflow-easy' ) );
        }

        // Handle form submissions.
        $this->handle_form_submission();

        // Retrieve plugin-defined levels and menu visibility settings.
        $plugin_levels = get_option( 'workflow_easy_levels', array() );
        $menus         = get_option( 'workflow_easy_menus', array() );

        // Sort plugin-defined levels by order ascending.
        uasort( $plugin_levels, function ( $a, $b ) {
            if ( $a['order'] == $b['order'] ) {
                return 0;
            }
            return ( $a['order'] < $b['order'] ) ? -1 : 1;
        } );

        // Collect built-in WordPress roles that are not already managed by the plugin.
        $builtin_levels = array();
        if ( function_exists( 'get_editable_roles' ) ) {
            $editable_roles = get_editable_roles();
            foreach ( $editable_roles as $role_slug => $role_info ) {
                // Skip the workflow_superadmin role and roles already defined by the plugin.
                if ( 'workflow_superadmin' === $role_slug || isset( $plugin_levels[ $role_slug ] ) ) {
                    continue;
                }
                // Use the translated role name for display.
                $role_name = isset( $role_info['name'] ) ? translate_user_role( $role_info['name'] ) : $role_slug;
                $builtin_levels[ $role_slug ] = array(
                    'name'  => $role_name,
                    'order' => 999, // built‑in roles get a default high order value
                );
            }
            // Sort built‑in levels alphabetically by name for consistent display.
            uasort( $builtin_levels, function ( $a, $b ) {
                return strnatcasecmp( $a['name'], $b['name'] );
            } );
        }

        // Merge plugin levels and built‑in levels for display. Plugin levels come first.
        $display_levels = $plugin_levels;
        foreach ( $builtin_levels as $slug => $data ) {
            $display_levels[ $slug ] = $data;
        }

        // Collect available admin menu slugs and labels.
        // We want to strip any update count badges that WordPress appends to menu titles
        // (e.g., "Tillägg <span class=\"update-plugins\"><span class=\"plugin-count\">1</span></span>")
        // and skip separator menu entries (WordPress uses slugs like 'separator1', 'separator2' etc.)
        global $menu;
        $available_menus = array();
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                // Each menu entry is an array with index 2 containing the slug.
                if ( isset( $item[2] ) && ! empty( $item[2] ) ) {
                    $slug = $item[2];
                    // Skip menu separators (they have slugs like 'separator1', 'separator2' etc.).
                    if ( false !== strpos( $slug, 'separator' ) ) {
                        continue;
                    }
                    // Skip the comments menu. WordPress labels the comments menu with the
                    // slug 'edit-comments.php' (or sometimes 'edit-comments' depending on version).
                    // We don't need to expose the comments menu in our visibility table because
                    // its only capability is manage comments which typically should not be
                    // toggled via this plugin.
                    if ( in_array( $slug, array( 'edit-comments.php', 'edit-comments' ), true ) ) {
                        continue;
                    }
                    // Build a human‑readable title.  Item index 0 may contain the HTML label
                    // including update counts and badges.
                    if ( isset( $item[0] ) ) {
                        // Remove all HTML tags from the title.
                        $raw_title = wp_strip_all_tags( $item[0] );
                        // Remove any trailing digits (optionally wrapped in parentheses) at the end of the title.
                        // This will strip update counts such as " 1" or "(1)" appended to menu labels.
                        $title = preg_replace( '/\s*[\d()]+$/', '', $raw_title );
                        // Trim any remaining whitespace.
                        $title = trim( $title );
                    } else {
                        $title = $slug;
                    }
                    $available_menus[ $slug ] = $title;
                }
            }
        }

        ?>
        <div class="wrap">
            <?php
            // Output custom styles for the plugin admin page.  We add a darker
            // background colour to every other row in the menu visibility table
            // to improve readability.  Using a unique class on the table allows
            // these styles to apply without affecting other admin tables.
            ?>
            <style>
                /* Alternate row shading for Workflow easy menu visibility table. */
                .workflow-easy-table tr:nth-child(even) td {
                    background-color: #f2f2f2;
                }
                /* Remove extra bottom margin from headers to tighten spacing and center headings. */
                .workflow-easy-table thead th {
                    padding-top: 8px;
                    padding-bottom: 8px;
                    text-align: center;
                }
                /* Center align all table headings and data cells. The first column (menu names)
                   remains left aligned. */
                .workflow-easy-table th,
                .workflow-easy-table td {
                    text-align: center;
                }
                .workflow-easy-table th:first-child,
                .workflow-easy-table td:first-child {
                    text-align: left;
                }
            </style>
            <h1><?php esc_html_e( 'Workflow easy – Manage User Levels', 'workflow-easy' ); ?></h1>

            <h2><?php esc_html_e( 'Add New Level', 'workflow-easy' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'workflow_easy_add_level', 'workflow_easy_add_level_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="level_name"><?php esc_html_e( 'Level name', 'workflow-easy' ); ?></label></th>
                        <td><input type="text" name="level_name" id="level_name" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Add Level', 'workflow-easy' ), 'primary', 'workflow_easy_add_level' ); ?>
            </form>

            <h2><?php esc_html_e( 'Existing Levels', 'workflow-easy' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'workflow_easy_update_levels', 'workflow_easy_update_levels_nonce' ); ?>
                <table class="widefat fixed" style="max-width: 600px;">
                    <thead>
                    <tr>
                        <th><?php esc_html_e( 'Level', 'workflow-easy' ); ?></th>
                        <th><?php esc_html_e( 'Order', 'workflow-easy' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'workflow-easy' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $display_levels as $slug => $data ) : ?>
                        <tr>
                            <td><?php echo esc_html( $data['name'] ); ?></td>
                            <td>
                                <?php if ( isset( $plugin_levels[ $slug ] ) ) : ?>
                                    <input type="number" name="level_order[<?php echo esc_attr( $slug ); ?>]" value="<?php echo intval( $data['order'] ); ?>" style="width: 60px;">
                                <?php else : ?>
                                    <input type="number" value="<?php echo intval( $data['order'] ); ?>" style="width: 60px;" disabled>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                // Delete allowed only for plugin-defined roles except superadmin.
                                if ( isset( $plugin_levels[ $slug ] ) && 'workflow_superadmin' !== $slug ) {
                                    ?>
                                    <button type="submit" name="workflow_easy_delete_level" value="<?php echo esc_attr( $slug ); ?>" class="button-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this level?', 'workflow-easy' ); ?>');">
                                        <?php esc_html_e( 'Delete', 'workflow-easy' ); ?>
                                    </button>
                                    <?php
                                } elseif ( 'workflow_superadmin' === $slug ) {
                                    esc_html_e( 'Fixed', 'workflow-easy' );
                                } else {
                                    // Built-in role: cannot delete.
                                    esc_html_e( 'Built-in', 'workflow-easy' );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button( __( 'Save Order', 'workflow-easy' ), 'primary', 'workflow_easy_save_order' ); ?>
            </form>

            <h2><?php esc_html_e( 'Menu Visibility', 'workflow-easy' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'workflow_easy_update_menus', 'workflow_easy_update_menus_nonce' ); ?>
                <table class="widefat fixed striped workflow-easy-table">
                    <thead>
                    <tr>
                        <th><?php esc_html_e( 'Menu Item', 'workflow-easy' ); ?></th>
                        <?php foreach ( $display_levels as $slug => $data ) : ?>
                            <th><?php echo esc_html( $data['name'] ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $row_idx = 0; foreach ( $available_menus as $slug => $title ) : ?>
                        <?php
                        // Add alternate class on every other row for better readability.
                        $row_class = ( $row_idx % 2 ) ? 'alternate' : '';
                        $row_idx++;
                        ?>
                        <tr class="<?php echo esc_attr( $row_class ); ?>">
                            <td><?php echo esc_html( $title ); ?></td>
                            <?php foreach ( $display_levels as $level_slug => $data ) : ?>
                                <td style="text-align: center;">
                                    <?php
                                    // For superadmin we always show, so no checkbox.
                                    if ( 'workflow_superadmin' === $level_slug ) {
                                        echo '&#x2713;';
                                    } else {
                                        $checked = ( isset( $menus[ $level_slug ] ) && isset( $menus[ $level_slug ][ $slug ] ) && $menus[ $level_slug ][ $slug ] ) ? 'checked' : '';
                                        echo '<input type="checkbox" name="workflow_easy_menu[' . esc_attr( $level_slug ) . '][' . esc_attr( $slug ) . ']" value="1" ' . $checked . ' />';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button( __( 'Save Menu Visibility', 'workflow-easy' ), 'primary', 'workflow_easy_save_menus' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle form submissions for adding, deleting and ordering levels, and menu visibility.
     */
    private function handle_form_submission() {
        // Add new level.
        if ( isset( $_POST['workflow_easy_add_level'] ) && check_admin_referer( 'workflow_easy_add_level', 'workflow_easy_add_level_nonce' ) ) {
            // Retrieve the posted level name without using the null coalescing operator for wider PHP compatibility.
            $posted_name = isset( $_POST['level_name'] ) ? $_POST['level_name'] : '';
            $name = sanitize_text_field( wp_unslash( $posted_name ) );
            if ( $name ) {
                $slug = 'workflow_' . sanitize_title( $name );
                $levels = get_option( 'workflow_easy_levels', array() );
                if ( ! isset( $levels[ $slug ] ) ) {
                    // New role inherits capabilities from the built‑in Editor role by default.
                    // This allows users assigned to custom levels to access typical editorial
                    // functionality (creating/editing content, managing media, etc.). If the
                    // Editor role cannot be found (unlikely), fall back to minimal
                    // capabilities (read only). Note: additional capabilities may be
                    // granted based on menu visibility settings saved later.
                    $editor_role = get_role( 'editor' );
                    if ( $editor_role ) {
                        $base_caps = $editor_role->capabilities;
                    } else {
                        $base_caps = array( 'read' => true );
                    }
                    add_role( $slug, $name, $base_caps );
                    // Determine next order (highest order + 1).
                    $orders = wp_list_pluck( $levels, 'order' );
                    $max_order = $orders ? max( $orders ) : 0;
                    $levels[ $slug ] = array( 'name' => $name, 'order' => (int) $max_order + 1 );
                    update_option( 'workflow_easy_levels', $levels );
                }
            }
        }
        // Save ordering of levels.
        if ( isset( $_POST['workflow_easy_save_order'] ) && check_admin_referer( 'workflow_easy_update_levels', 'workflow_easy_update_levels_nonce' ) ) {
            if ( isset( $_POST['level_order'] ) && is_array( $_POST['level_order'] ) ) {
                $levels = get_option( 'workflow_easy_levels', array() );
                foreach ( $_POST['level_order'] as $slug => $order ) {
                    if ( isset( $levels[ $slug ] ) ) {
                        $levels[ $slug ]['order'] = (int) $order;
                    }
                }
                update_option( 'workflow_easy_levels', $levels );
            }
        }
        // Delete level.
        if ( isset( $_POST['workflow_easy_delete_level'] ) ) {
            $slug_to_delete = sanitize_text_field( wp_unslash( $_POST['workflow_easy_delete_level'] ) );
            if ( $slug_to_delete && 'workflow_superadmin' !== $slug_to_delete ) {
                // Remove role and associated menu settings.
                remove_role( $slug_to_delete );
                $levels = get_option( 'workflow_easy_levels', array() );
                unset( $levels[ $slug_to_delete ] );
                update_option( 'workflow_easy_levels', $levels );
                $menus = get_option( 'workflow_easy_menus', array() );
                unset( $menus[ $slug_to_delete ] );
                update_option( 'workflow_easy_menus', $menus );
            }
        }
        // Save menu visibility settings.
        if ( isset( $_POST['workflow_easy_save_menus'] ) && check_admin_referer( 'workflow_easy_update_menus', 'workflow_easy_update_menus_nonce' ) ) {
            $new_settings = array();
            if ( isset( $_POST['workflow_easy_menu'] ) && is_array( $_POST['workflow_easy_menu'] ) ) {
                foreach ( $_POST['workflow_easy_menu'] as $level_slug => $menu_slugs ) {
                    $new_settings[ sanitize_key( $level_slug ) ] = array();
                    foreach ( $menu_slugs as $slug => $value ) {
                        $new_settings[ $level_slug ][ sanitize_text_field( $slug ) ] = true;
                    }
                }
            }
            update_option( 'workflow_easy_menus', $new_settings );
            // Capability updates are now performed on the admin_menu hook to ensure
            // that the global $menu is fully constructed. See assign_role_capabilities().
        }
    }

    /**
     * Hide admin menu items for users based on the plugin's settings.
     * Runs late in the admin_menu hook to ensure other plugins have registered
     * their menus. According to the WordPress developer documentation the
     * remove_menu_page() function should be called on the admin_menu hook and
     * not before【160043855559898†L94-L99】.
     */
    public function filter_admin_menu() {
        // Superadmins see everything.
        if ( current_user_can( 'workflow_superadmin' ) ) {
            return;
        }

        // Load plugin-defined levels and menu settings.
        $levels = get_option( 'workflow_easy_levels', array() );
        $menus  = get_option( 'workflow_easy_menus', array() );
        // Merge built-in roles into the levels array so that default roles can be
        // restricted via menu visibility settings. Built-in roles get a very
        // high order value so that plugin-defined levels take precedence in the
        // hierarchy.
        if ( function_exists( 'get_editable_roles' ) ) {
            $editable_roles = get_editable_roles();
            foreach ( $editable_roles as $role_slug => $role_info ) {
                if ( 'workflow_superadmin' === $role_slug || isset( $levels[ $role_slug ] ) ) {
                    continue;
                }
                    // Add built-in role with a large order so it is always lower
                    // priority than any plugin-defined level.
                    $levels[ $role_slug ] = array(
                        'name'  => isset( $role_info['name'] ) ? translate_user_role( $role_info['name'] ) : $role_slug,
                        'order' => PHP_INT_MAX,
                    );
            }
        }

        // Determine the highest priority level for the current user. The level
        // with the lowest order number has highest priority.
        $user   = wp_get_current_user();
        $roles  = (array) $user->roles;
        $level_slug = null;
        $current_order = PHP_INT_MAX;
        foreach ( $levels as $slug => $info ) {
            if ( in_array( $slug, $roles, true ) && $info['order'] < $current_order ) {
                $level_slug    = $slug;
                $current_order = $info['order'];
            }
        }
        if ( ! $level_slug ) {
            return;
        }
        // Get the list of menu slugs to show (checked) for this level.
        $allowed = isset( $menus[ $level_slug ] ) ? $menus[ $level_slug ] : array();
        // Build global $menu to remove those not allowed.
        global $menu;
        if ( ! is_array( $menu ) ) {
            return;
        }
        foreach ( $menu as $index => $item ) {
            $slug = isset( $item[2] ) ? $item[2] : '';
            // If slug not allowed, remove it for this role.
            if ( ! empty( $slug ) && 'index.php' !== $slug ) { // always keep Dashboard
                if ( ! isset( $allowed[ $slug ] ) ) {
                    remove_menu_page( $slug );
                }
            }
        }
    }

    /**
     * Update capabilities for custom levels based on selected menu items.
     *
     * When menu visibility is saved, each custom level (those created via this
     * plugin) must have the capabilities necessary to view the chosen menu
     * items. WordPress hides menu items automatically if the current user
     * lacks the required capability. This method ensures that the role has
     * exactly the capabilities required for the selected menus (plus 'read').
     * Built‑in roles are not modified.
     *
     * @param array $menu_settings Associative array of level_slug => menu_slug => bool.
     */
    private function update_role_caps_for_custom_levels( $menu_settings ) {
        global $menu;
        // Get the plugin-defined levels (custom roles) from the database.
        $plugin_levels = get_option( 'workflow_easy_levels', array() );
        if ( empty( $plugin_levels ) ) {
            return;
        }
        // For each custom level, build a list of required capabilities and update the role.
        foreach ( $plugin_levels as $level_slug => $info ) {
            // Determine which menu slugs are selected for this level.
            $selected_slugs = isset( $menu_settings[ $level_slug ] ) ? $menu_settings[ $level_slug ] : array();
            // Always include 'read' capability so the user can access their profile and dashboard.
            $required_caps = array( 'read' => true );
            // Map selected menu slugs to capabilities defined in global $menu.
            if ( is_array( $selected_slugs ) && ! empty( $selected_slugs ) && is_array( $menu ) ) {
                foreach ( $selected_slugs as $menu_slug => $val ) {
                    // Look up the capability for this menu slug.
                    foreach ( $menu as $item ) {
                        if ( isset( $item[2] ) && $item[2] === $menu_slug ) {
                            if ( isset( $item[1] ) && ! empty( $item[1] ) ) {
                                $cap = $item[1];
                                // Normalise capability string (ensure no HTML or whitespace).
                                $cap = trim( wp_strip_all_tags( $cap ) );
                                $required_caps[ $cap ] = true;
                            }
                            break;
                        }
                    }
                }
            }
            // Get the role object and update its capabilities.
            $role_obj = get_role( $level_slug );
            if ( ! $role_obj ) {
                continue;
            }
            // Remove capabilities not required.
            foreach ( $role_obj->capabilities as $cap => $val ) {
                if ( ! isset( $required_caps[ $cap ] ) ) {
                    $role_obj->remove_cap( $cap );
                }
            }
            // Add required capabilities.
            foreach ( $required_caps as $cap => $val ) {
                if ( ! $role_obj->has_cap( $cap ) ) {
                    $role_obj->add_cap( $cap );
                }
            }
        }
    }

    /**
     * Hook callback to assign capabilities to custom roles based on saved menu visibility.
     *
     * This method is hooked into the `admin_menu` action with a priority lower than
     * filter_admin_menu. It retrieves the stored menu settings from the database
     * and calls update_role_caps_for_custom_levels() to ensure that each custom
     * role has exactly the capabilities required to access the menus selected
     * for that role. Built‑in roles are not modified.
     */
    public function assign_role_capabilities() {
        // Do not adjust capabilities for superadmins or on network admin pages.
        if ( is_network_admin() ) {
            return;
        }
        // Load the saved menu visibility settings.
        $menu_settings = get_option( 'workflow_easy_menus', array() );
        $this->update_role_caps_for_custom_levels( $menu_settings );
    }

    /**
     * Run update/cleanup routines when the stored plugin version differs from
     * the current version.  This method should be hooked early (e.g. on
     * `init`) so it runs before the admin interface is rendered.
     */
    public function maybe_run_update() {
        // Bump the plugin version here whenever a structural change requires a cleanup or migration.
        // Updating this string triggers cleanup routines on next page load after update.
        $current_version = '1.9.0';
        $stored_version  = get_option( 'workflow_easy_version' );
        if ( $stored_version === $current_version ) {
            return;
        }
        // Run cleanup for upgrades from older versions.
        $this->cleanup_stale_data( $stored_version );
        // Store the new version.
        update_option( 'workflow_easy_version', $current_version );
    }

    /**
     * Remove stale data from previous versions.  This method attempts to
     * eliminate old roles, capabilities and options that may no longer be
     * relevant, thereby preventing conflicts and clutter in the database.
     *
     * @param string|false $previous_version The previous stored version of the plugin.
     */
    private function cleanup_stale_data( $previous_version ) {
        // Remove any entries in workflow_easy_levels and workflow_easy_menus
        // that no longer correspond to existing roles.  This prevents
        // orphaned settings from lingering when roles have been deleted.
        $levels = get_option( 'workflow_easy_levels', array() );
        $menus  = get_option( 'workflow_easy_menus', array() );
        $dirty  = false;
        foreach ( $levels as $slug => $info ) {
            if ( ! get_role( $slug ) ) {
                unset( $levels[ $slug ] );
                if ( isset( $menus[ $slug ] ) ) {
                    unset( $menus[ $slug ] );
                }
                $dirty = true;
            }
        }
        if ( $dirty ) {
            update_option( 'workflow_easy_levels', $levels );
            update_option( 'workflow_easy_menus', $menus );
        }

        /**
         * Remove orphaned workflow_* roles and associated data.
         *
         * In earlier versions of the plugin it was possible to create custom
         * roles that were never deleted from the database or options when
         * removing levels.  To ensure no stale roles remain, iterate all
         * registered roles and remove any that start with our prefix
         * (`workflow_`) that are not the superadmin or present in the
         * workflow_easy_levels option.  Removing a role via remove_role()
         * automatically removes its capabilities from the internal role
         * registry.  We also purge any menu visibility settings for those
         * roles.
         */
        global $wp_roles;
        if ( isset( $wp_roles ) ) {
            foreach ( array_keys( $wp_roles->roles ) as $role_slug ) {
                if ( 0 === strpos( $role_slug, 'workflow_' ) && 'workflow_superadmin' !== $role_slug ) {
                    // If the role is not defined in our levels option, remove it.
                    if ( ! isset( $levels[ $role_slug ] ) ) {
                        remove_role( $role_slug );
                        // Remove any lingering menu settings for this role.
                        if ( isset( $menus[ $role_slug ] ) ) {
                            unset( $menus[ $role_slug ] );
                            update_option( 'workflow_easy_menus', $menus );
                        }
                    }
                }
            }
        }

        // If upgrading from a version earlier than 1.7.0, remove custom
        // capabilities added to built‑in roles by older versions of this
        // plugin.  Prior to 1.7.0 the plugin modified role capabilities
        // based on menu selections.  From 1.7.0 onward we use CSS to hide
        // menu items, so these extra capabilities are no longer needed.
        if ( version_compare( (string) $previous_version, '1.7.0', '<' ) ) {
            // List of capabilities that Workflow easy may have added.
            // Add more here if earlier versions introduced additional caps.
            $possible_caps = array();
            // Determine all caps assigned via menu mappings in the past.
            global $menu;
            if ( is_array( $menu ) ) {
                foreach ( $menu as $item ) {
                    if ( isset( $item[1] ) && ! empty( $item[1] ) ) {
                        $cap = trim( wp_strip_all_tags( $item[1] ) );
                        // Skip the `read` capability which should never be removed.
                        if ( 'read' !== $cap ) {
                            $possible_caps[ $cap ] = true;
                        }
                    }
                }
            }
            // Remove these caps from all roles except the built‑in administrator
            // and our workflow_superadmin.  We do not modify the admin role.
            foreach ( wp_roles()->roles as $role_slug => $role_info ) {
                if ( in_array( $role_slug, array( 'administrator', 'workflow_superadmin' ), true ) ) {
                    continue;
                }
                $role = get_role( $role_slug );
                if ( ! $role ) {
                    continue;
                }
                foreach ( $possible_caps as $cap => $val ) {
                    if ( $role->has_cap( $cap ) ) {
                        $role->remove_cap( $cap );
                    }
                }
            }
        }
    }

    /**
     * Determine the most important level (lowest order) for the current user.
     *
     * Users may have multiple roles assigned.  Levels defined by this
     * plugin (and built‑in roles) are ordered.  The level with the lowest
     * numeric order takes precedence.  This helper encapsulates the logic
     * previously used in filter_admin_menu() so that it can be reused by
     * other methods (e.g. for CSS-based hiding).
     *
     * @return string|null The slug of the chosen level or null if none apply.
     */
    private function determine_current_level_slug() {
        // Retrieve plugin-defined levels from the database.
        $levels = get_option( 'workflow_easy_levels', array() );
        // Add built-in roles to the level list with a very high order so
        // plugin-defined levels take precedence.  This mirrors the logic
        // previously used in filter_admin_menu().
        if ( function_exists( 'get_editable_roles' ) ) {
            $editable_roles = get_editable_roles();
            foreach ( $editable_roles as $role_slug => $role_info ) {
                if ( 'workflow_superadmin' === $role_slug || isset( $levels[ $role_slug ] ) ) {
                    continue;
                }
                $levels[ $role_slug ] = array(
                    'name'  => isset( $role_info['name'] ) ? translate_user_role( $role_info['name'] ) : $role_slug,
                    'order' => PHP_INT_MAX,
                );
            }
        }
        // Determine the level with the lowest order among the current user's roles.
        $user   = wp_get_current_user();
        $roles  = (array) $user->roles;
        $current_slug  = null;
        $current_order = PHP_INT_MAX;
        foreach ( $levels as $slug => $info ) {
            if ( in_array( $slug, $roles, true ) && $info['order'] < $current_order ) {
                $current_slug  = $slug;
                $current_order = $info['order'];
            }
        }
        return $current_slug;
    }

    /**
     * Filter callback for admin_body_class.
     *
     * Adds a class of the form `workflow-level-{slug}` to the body element
     * based on the current user's highest priority level.  This class can
     * then be used in CSS selectors to hide menu items for that level.
     *
     * @param string $classes Existing body classes.
     * @return string Modified body classes.
     */
    public function add_admin_body_class( $classes ) {
        // Do not add the class on network admin pages or if the user is a superadmin.
        if ( is_network_admin() || current_user_can( 'workflow_superadmin' ) ) {
            return $classes;
        }
        $level_slug = $this->determine_current_level_slug();
        if ( $level_slug ) {
            $classes .= ' workflow-level-' . sanitize_html_class( $level_slug );
        }
        return $classes;
    }

    /**
     * Output inline CSS rules in the admin head to hide menu items per level.
     *
     * This method gathers all saved menu visibility settings and constructs
     * CSS rules targeting the body class added via add_admin_body_class().
     * Each rule hides top-level menu items whose slugs are not selected for
     * a given level.  The Dashboard (index.php) is always shown.  The
     * Workflow Easy menu is also hidden for non-superadmin levels by virtue of
     * the capability on add_menu_page().
     */
    public function output_visibility_css() {
        // Superadmins see everything; no CSS needed.
        if ( current_user_can( 'workflow_superadmin' ) ) {
            return;
        }
        // Retrieve levels and menu visibility settings.
        $levels = get_option( 'workflow_easy_levels', array() );
        // Merge built-in roles as in determine_current_level_slug().
        if ( function_exists( 'get_editable_roles' ) ) {
            $editable_roles = get_editable_roles();
            foreach ( $editable_roles as $role_slug => $role_info ) {
                if ( 'workflow_superadmin' === $role_slug || isset( $levels[ $role_slug ] ) ) {
                    continue;
                }
                $levels[ $role_slug ] = array(
                    'name'  => isset( $role_info['name'] ) ? translate_user_role( $role_info['name'] ) : $role_slug,
                    'order' => PHP_INT_MAX,
                );
            }
        }
        $menus  = get_option( 'workflow_easy_menus', array() );
        // Build a list of available menu slugs and a mapping to their HTML IDs from global $menu.
        global $menu;
        $available_slugs = array();
        $slug_to_id      = array();
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && ! empty( $item[2] ) ) {
                    $slug = $item[2];
                    // Skip separators and comments as before.
                    if ( false !== strpos( $slug, 'separator' ) ) {
                        continue;
                    }
                    if ( in_array( $slug, array( 'edit-comments.php', 'edit-comments' ), true ) ) {
                        continue;
                    }
                    $available_slugs[] = $slug;
                    // Determine the menu item's ID. In the global $menu array the HTML
                    // ID is stored at index 5 (if set). If not present, fall back to
                    // prefixing the slug with `menu-`. This covers custom menu pages.
                    $menu_id = '';
                    if ( isset( $item[5] ) && ! empty( $item[5] ) ) {
                        $menu_id = $item[5];
                    } else {
                        // Sanitize slug for CSS ID (replace invalid characters).
                        $menu_id = 'menu-' . preg_replace( '/[^a-zA-Z0-9_-]/', '-', $slug );
                    }
                    $slug_to_id[ $slug ] = $menu_id;
                }
            }
        }
        // Always keep Dashboard visible.
        $dashboard_slug = 'index.php';
        // Build CSS rules for each level.
        $css = '';
        foreach ( $levels as $level_slug => $info ) {
            // Determine which menus are allowed (checked) for this level.
            $allowed = isset( $menus[ $level_slug ] ) ? $menus[ $level_slug ] : array();
            // Determine slugs to hide: those available but not allowed.
            $hide_ids = array();
            foreach ( $available_slugs as $slug ) {
                // Do not hide dashboard.
                if ( $slug === $dashboard_slug ) {
                    continue;
                }
                // Skip plugin menu slug; this is hidden by capability but we still hide it for clarity.
                if ( 'workflow-easy' === $slug ) {
                    continue;
                }
                if ( ! isset( $allowed[ $slug ] ) ) {
                    // Look up ID for this slug.
                    if ( isset( $slug_to_id[ $slug ] ) ) {
                        $hide_ids[] = $slug_to_id[ $slug ];
                    }
                }
            }
            if ( ! empty( $hide_ids ) ) {
                // Build CSS selector: target body with level class and each menu <li> by its ID.
                $selector_parts = array();
                foreach ( $hide_ids as $id ) {
                    $selector_parts[] = '.workflow-level-' . esc_attr( $level_slug ) . ' #' . esc_attr( $id );
                }
                if ( ! empty( $selector_parts ) ) {
                    $css .= implode( ',', $selector_parts ) . '{display:none !important;}\n';
                }
            }
        }
        if ( ! empty( $css ) ) {
            echo "<style id='workflow-easy-visibility-css'>\n" . $css . "</style>\n";
        }
    }
}

// Initialize plugin.
Workflow_Easy::get_instance();