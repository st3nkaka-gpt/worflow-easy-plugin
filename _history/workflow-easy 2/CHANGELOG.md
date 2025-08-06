# Changelog

## [1.7.0] - 2025-08-05
### Changed
 - Switched from PHP‑based menu filtering and capability updates to a purely CSS‑driven approach.  The admin menu remains intact; the plugin now adds a `workflow-level-{slug}` class to the admin `<body>` and outputs CSS rules to hide unselected menu items for each level.  This eliminates the menu “flash” and avoids modifying role capabilities when saving settings.
 - Custom levels continue to inherit the Editor role’s capabilities on creation, but the plugin no longer adds or removes capabilities when menu visibility is changed.  Menu settings now affect only the visual display of the admin sidebar.
 - The Dashboard (“Adminpanel”) is always visible.  The Workflow easy menu itself is still restricted to users with the `workflow_superadmin` role via its capability.
### Added
 - Added a filter on `admin_body_class` to append the appropriate level class and an action on `admin_head` to output the dynamic CSS.  These hooks ensure that the global `$menu` is available and fully constructed before the plugin generates its style rules.
## [1.6.0] - 2025-08-05
### Changed
- Menu visibility settings and role capabilities are now fully synchronised. Capabilities for custom roles are updated on the `admin_menu` hook after all menus are registered, ensuring that menu items selected in the **Menu Visibility** matrix appear correctly in the admin sidebar and unselected items are hidden.
- Column headings and checkboxes in the plugin’s tables are now centered for improved alignment, while the first column remains left aligned.
### Added
- Added a new `assign_role_capabilities` callback that runs on `admin_menu` to update custom roles based on menu visibility selections, guaranteeing that capability adjustments occur when global `$menu` is available.

## [1.5.0] - 2025-08-05
### Added
- Custom roles created by Workflow easy now inherit the capabilities of the built‑in **Editor** role when they are created. This ensures that new levels can access common editorial functions by default.
- When saving **Menu Visibility**, the plugin synchronizes each custom level’s capabilities with its selected menus. It grants the role all capabilities required to access the chosen menus and removes capabilities that are no longer needed, leaving a minimal and consistent permission set.
- Capability updates are applied only to roles created by Workflow easy; built‑in WordPress roles are left unchanged.

## [1.4.0] - 2025-08-05
### Added
- Ability to remove custom levels from within the **Existing Levels** table. A delete button is now shown for all plugin‑defined levels except `workflow_superadmin`. When a level is deleted, its role is removed and its settings are cleaned up from the database.
### Changed
- The **Menu Visibility** table no longer displays the WordPress comments menu (e.g., “Kommentarer 00 kommentarer inväntar granskning”) since comment moderation is generally handled outside the scope of this plugin.

## [1.3.1] - 2025-08-05
### Changed
- Removed visual separator entries from the **Menu Visibility** table. WordPress adds
  blank separator rows to the admin menu to create spacing; these no longer
  appear in the Workflow easy interface.
- Improved stripping of update counts from menu titles. Update numbers appended
  to titles (for example, “Tillägg 1” or “Tillägg (1)”) are now removed via a
  more robust regular expression. Only the menu name is shown.
- Adjusted the alternating row styling to use a slightly lighter shade for
  better contrast and a more polished appearance.

## [1.3.0] - 2025-08-05
### Added
- Improved the **Menu Visibility** table styling: every other row now uses a slightly darker background to enhance readability.
- Removed the numeric update badge from the Plugins (Tillägg) menu title in the Menu Visibility table. WordPress adds the count of available updates (e.g., “Tillägg 1”) to the menu title; this version strips that count so only the menu name is displayed.
- Minor spacing adjustments to headers and table elements to reduce unwanted whitespace.


All notable changes to this project will be documented in this file. The format
is based on [Keep a Changelog](https://keepachangelog.com/) and this project
adheres to [Semantic Versioning](https://semver.org/).

## [1.8.0] - 2025-08-05
### Added
 - Added a data migration routine that runs when the stored plugin version differs from the current version.  The routine removes levels and menu settings that reference roles no longer present on the site, and strips capabilities previously added by Workflow easy from roles (other than `administrator` and `workflow_superadmin`).  The migration stores the current version in the `workflow_easy_version` option to avoid repeated execution.
 - Added an `uninstall.php` file which WordPress will automatically execute when the plugin is deleted.  It cleans up all Workflow easy options (`workflow_easy_levels`, `workflow_easy_menus`, `workflow_easy_version`) and removes all plugin-defined roles (`workflow_*`), including `workflow_superadmin`, ensuring that no artefacts remain in the database.
### Changed
 - Bumped the version number to 1.8.0 to denote these structural changes and clean-up capabilities.

## [1.1.2] - 2025-08-05
### Fixed
- Resolved a fatal error on activation when running under older PHP versions (prior to PHP 7). The plugin now avoids the use of the null coalescing operator (`??`) and the spaceship operator (`<=>`) by implementing compatible alternatives. This change ensures the plugin can be activated on hosts still running PHP 5.x without syntax errors.

## [1.1.3] - 2025-08-05
### Fixed
 - Standardised the plugin’s root folder name to `workflow-easy` for all releases. Previous packages were versioned (e.g. `workflow-easy-v1.1.2`) which resulted in duplicate plugins being installed side by side and caused “cannot redeclare class” errors on activation. From this version onward, uploads will overwrite the existing plugin rather than create a new one. If you have multiple Workflow easy entries in your Plugins list, deactivate and delete the older versions before activating this release.

## [1.2.0] - 2025-08-05
### Added
 - All built‑in WordPress roles (administrator, editor, author, contributor, subscriber, etc.) now appear in the **Existing Levels** and **Menu Visibility** sections. These roles cannot be deleted, but their menu access can be configured alongside custom levels. They are appended after plugin‑defined levels and have a lower priority in the hierarchy.

## [1.1.1] - 2025-08-05
### Added
- Introduced this `CHANGELOG.md` file so future updates are documented.
- Improved the Menu Visibility table by adding alternating row colours for
  enhanced readability in the WordPress admin.

## [1.1.0] - 2025-08-05
### Added
- Updated the Menu Visibility interface to display all user levels at once. Each
  level now has its own column with checkboxes, allowing the superadmin to
  manage what each level can see in a single matrix.
- Added UI improvements including a striped table style to visually separate
  rows.

## [1.0.0] - 2025-08-05
### Added
- Initial release of Workflow easy. Provided functionality to create a
  `workflow_superadmin` role on activation, create additional levels, reorder
  them, and control per–level visibility of admin menu items.