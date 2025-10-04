<?php
// This file keeps track of upgrades to the menteesummary module.
//
// Sometimes, changes between versions involve alterations to database
// structures and other major things that may break installations.
//
// The upgrade function in this file will attempt to perform all the
// necessary actions to upgrade your older installation to the current version.
//
// For more information on how to write upgrade steps, see:
// https://moodledev.io/docs/apis/core/dml/ddl/upgrade

defined('MOODLE_INTERNAL') || die();

/**
 * Execute menteesummary upgrade steps between versions.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_menteesummary_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Example: New column added in version 2025100500.
    if ($oldversion < 2025100500) {

        // Define field examplefield to be added to menteesummary.
        $table = new xmldb_table('menteesummary');
        $field = new xmldb_field('examplefield', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field examplefield.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached.
        upgrade_mod_savepoint(true, 2025100500, 'menteesummary');
    }

    return true;
}
