<?php
// This file keeps track of upgrades to 
// the vmoodle block
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_block_vmoodle_upgrade($oldversion = 0) {
    global $CFG, $DB;

    $result = true;
    $dbman = $DB->get_manager();

    // Moodle 2.0 Upgrade break.
    if ($oldversion < 2014081300) {

        // Changing precision of field vdbpass on table block_vmoodle to (32).
        $table = new xmldb_table('block_vmoodle');
        $field = new xmldb_field('vdbpass', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'vdblogin');

        // Launch change of precision for field vdbpass.
        $dbman->change_field_precision($table, $field);

        // Vmoodle savepoint reached.
        upgrade_block_savepoint(true, 2014081300, 'vmoodle');
    }

    return $result;
}
