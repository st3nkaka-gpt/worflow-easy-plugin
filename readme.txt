=== Workflow easy ===
Contributors: Thomas and Effie
Tags: user roles, capabilities, admin menu, hierarchy
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.9.0
License: GPLv2 or later

Workflow easy lets administrators define custom user levels and control which
admin menus each level can access. When activated, the plugin creates a
`workflow_superadmin` role (copied from the built‑in administrator role) and
assigns the activating user to this new role. Only users with this role can
access the plugin’s settings. Within the settings you can create
additional levels, reorder them, and toggle visibility of admin menu items
per level. The top‑most level is always `workflow_superadmin`.

== Installation ==

1. Download the plugin ZIP file and upload it via **Plugins → Add New → Upload**.
2. Activate the plugin. The activating administrator will be assigned the
   `workflow_superadmin` role.
3. Under **Workflow easy** in the admin menu, configure your user levels and
   menu permissions.

== Frequently Asked Questions ==

*Why create another “superadmin” role?*

The role created by this plugin is unrelated to WordPress multisite’s built‑in
“Super Admin”. We use a separate role so that the plugin can be used on
single‑site installations and the role can be managed independently from
the multisite network administrator capability.

== Changelog ==

= 1.9.0 =
* **Städrutin förbättrad:** Den automatiska uppdateringsfunktionen identifierar nu och tar bort alla roller som börjar med `workflow_` (förutom `workflow_superadmin`) och som inte längre finns i pluginets nivålista. Detta rensar upp gamla nivåer som tidigare versioner kan ha lämnat kvar och tar bort tillhörande menyinställningar.
* **Versionbump och migrering:** `workflow_easy_version` uppdateras nu till 1.9.0. När versionen skiljer sig från den lagrade körs `cleanup_stale_data()` en gång vid nästa sidladdning, vilket rensar bort gamla roller, kapaciteter och options.
* **Standardiserad versionshantering:** Versionsnumret definieras centralt i koden vilket gör det enkelt att höja det och trigga migrering vid behov.

= 1.8.0 =
* **Städrutin och datamigrering:** Pluginet innehåller nu en uppdateringsrutin som körs när versionen ökar. Den städar bort gamla poster i `workflow_easy_levels` och `workflow_easy_menus` som inte längre motsvarar befintliga roller, och tar bort kapaciteter som tidigare versioner kan ha lagt till i andra roller. Versionen sparas i databasen under `workflow_easy_version` så att migreringar bara körs en gång per uppgradering.
* **uninstall.php:** En `uninstall.php`‑fil har lagts till, som WordPress automatiskt kör när pluginet tas bort via admin. Den tar bort alla plugin‑specifika options (`workflow_easy_levels`, `workflow_easy_menus`, `workflow_easy_version`) och raderar alla roller som skapats av pluginet (inklusive `workflow_superadmin`). Detta säkerställer att inga oanvända data finns kvar i databasen när pluginet tas bort.
* **Versionbump:** Versionsnumret har ökats till 1.8.0 för att återspegla dessa strukturförändringar och rensningsfunktioner.

= 1.7.0 =
* Byggt om hur menyer döljs: istället för att ta bort menyalternativ och justera rollernas kapaciteter döljs nu oönskade punkter med CSS. Detta ger en snabbare upplevelse och lämnar rollernas rättigheter orörda.
* Pluginet lägger nu till en klass på `<body>` i admin‐panelen (`workflow-level-{niva}`) baserat på den högst prioriterade nivån användaren har.  De genererade CSS-reglerna döljer sedan de menyalternativ som inte markerats för just den nivån.
* Nya nivåer fortsätter att ärva redaktörens (Editor) kapaciteter som grund.  När menylayouten ändras uppdateras inga kapaciteter längre, utan endast vad som visas med CSS.
* “Adminpanel” (Dashboard) visas alltid för alla användare.  Workflow easy-menyn syns endast för dem som har `workflow_superadmin`-rollen, precis som tidigare.
 = 1.6.0 =
 * Menyvalen i admin-menyn för varje roll synkroniseras nu korrekt med markeringarna i tabellen **Menu Visibility**. För varje sparat val uppdateras rollens kapaciteter på rätt tidpunkt (när admin‑menyn byggs), vilket gör att endast de markerade menyalternativen visas och andra döljs.
 * Rubriker och kolumner i pluginets tabeller är nu centrerade för bättre läsbarhet. Menynamnen i första kolumnen lämnas vänsterjusterade, medan namn på nivåer och deras kryssrutor är centrerade.
 * Implementerat en ny hook som uppdaterar custom roller på `admin_menu`‑hooken, så att den globala `$menu` finns tillgänglig innan kapaciteter justeras.

= 1.5.0 =
* Custom levels now inherit the capabilities of the built‑in **Editor** role when they are created. Without the appropriate capabilities, menu items will not appear for the new level; copying Editor capabilities ensures that newly created levels can access typical editorial functions immediately.
* When you save **Menu Visibility**, the plugin synchronizes each custom level’s capabilities with the selected menu items. It grants the role all the capabilities required to access the checked menus and removes capabilities that are no longer needed, leaving the role with only the permissions necessary for the menus you allow.
* Built‑in roles remain unchanged; capability updates apply only to roles created by Workflow easy.

= 1.4.0 =
* Excluded the **Comments** menu from the Menu Visibility table. Previously a
  row labelled “Kommentarer 00 kommentarer inväntar granskning” would appear
  in the table whenever there were no pending comments; this row is now
  removed entirely.
* Added the ability to delete custom levels that you create via Workflow easy.
  For each level (except the built‑in roles and `workflow_superadmin`), a
  **Delete** button is shown in the Existing Levels table. Deleting a level
  removes the role from WordPress and cleans up all associated settings to
  prevent unused roles lingering in the database.


= 1.3.1 =
* Removed the blank separator rows that WordPress inserts into the admin menu from the **Menu Visibility** table. Only real menu items now appear in the list of menus that can be toggled.
* Improved how update counts are stripped from menu titles. The plugin now removes both numeric counts and counts wrapped in parentheses (e.g., “Tillägg 1” or “Tillägg (1)”), ensuring that only the menu name is displayed.
* Adjusted the alternating row colour to a lighter shade for a subtler, cleaner look while keeping rows easy to distinguish.

= 1.3.0 =
* Added improved visual styling to the **Menu Visibility** table. Every other row now has a slightly darker background colour for better readability.
* Removed the numeric update badge from the Plugins (Tillägg) menu title in the Menu Visibility table. WordPress automatically adds a count of available updates (e.g., “Tillägg 1”), which cluttered the interface. The plugin now strips this count and shows only the menu name.
* Tweaked spacing in the admin page and table headers to reduce unnecessary blank space and create a more compact layout.

= 1.2.0 =
* The “Existing Levels” and “Menu Visibility” interfaces now show all WordPress user roles (administrator, editor, author, contributor, subscriber, etc.) alongside the custom levels created by this plugin. Built‑in roles cannot be deleted, but their menu access can be configured just like plugin‑defined levels. The plugin now merges these roles internally so that menu visibility restrictions can apply to any role.

= 1.1.3 =
* Standardised the plugin folder name to `workflow-easy` for consistent upgrades. Previously, versioned directories (e.g. `workflow-easy-v1.1.2`) created duplicate plugins, causing fatal errors when functions were redeclared. New releases now overwrite the existing plugin when uploaded. If you have multiple Workflow easy entries, deactivate and remove older versions before activating this one.

= 1.1.2 =
* Fixed a fatal error on activation caused by the use of newer PHP syntax (null coalescing and spaceship operators). The plugin now uses syntax compatible with older PHP versions, ensuring it activates correctly on hosts running PHP 5.x.

= 1.1.1 =
* Added `CHANGELOG.md` file.
* Enhanced the menu visibility table to include alternating row colours for improved readability.

= 1.1.0 =
* Updated the Menu Visibility interface to display all levels in a single matrix with a column per level.
* Added the ability to edit menu visibility for all levels at once.

= 1.0.0 =
* Initial release.