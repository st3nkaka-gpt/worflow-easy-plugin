<?php
/**
 * Uninstall routine for the Workflow easy plugin.
 *
 * This file is automatically executed by WordPress when a user deletes the
 * plugin from the Plugins screen.  Its purpose is to remove any persistent
 * data created by the plugin, including custom roles and options.  We check
 * for the WP_UNINSTALL_PLUGIN constant to ensure the file is not called
 * directly.
 *
 * @package WorkflowEasy
 */

// Exit if accessed directly or if the uninstall constant is not defined.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options.
delete_option( 'workflow_easy_levels' );
delete_option( 'workflow_easy_menus' );
delete_option( 'workflow_easy_version' );

// Remove the custom superadmin role.  The capabilities of the built-in
// administrator role are unaffected.
if ( get_role( 'workflow_superadmin' ) ) {
    remove_role( 'workflow_superadmin' );
}

// Optionally remove custom roles created by the plugin.  Any role with
// the prefix 'workflow_' is considered a plugin-defined role (except
// workflow_superadmin, which has already been removed above).
global $wp_roles;
if ( isset( $wp_roles ) && is_object( $wp_roles ) ) {
    foreach ( $wp_roles->roles as $role_slug => $role_info ) {
        if ( 0 === strpos( $role_slug, 'workflow_' ) && 'workflow_superadmin' !== $role_slug ) {
            remove_role( $role_slug );
        }
    }
}