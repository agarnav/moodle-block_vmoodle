<?php

defined('VMOODLE_CLASSSES_DIR') || require_once $CFG->dirroot.'/blocks/vmoodle/locallib.php';
require_once(VMOODLE_CLASSES_DIR.'XmlRpc_Client.class.php');

/**
 * This library provides SQL commands for the meta-administration.
 * 
 * @package block-vmoodle
 * @category blocks
 * @author Bruce Bujon (bruce.bujon@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

/** Adding the SQL commands' constants */
/*
if (isset($vmcommands_constants))
	$vmcommands_constants = array_merge(
		$vmcommands_constants,
		array(
			'prefix' => $CFG->prefix
		)
	);
*/

/**
 * Get fields values of a virtual platform via MNET service.
 * @param	host			string				The virtual platform to aim.
 * @param	table			string				The table to read.
 * @param	select			mixed				The value of id or alternative field.
 * @param	fields			string				The fileds to retrieve (optional).
 * @throws					Vmoodle_Command_Sql_Exception.
 */
function vmoodle_get_field($host, $table, $select, $fields='*') {
	global $CFG, $USER,$DB;
	// Checking capabilities
	if (!has_capability('block/vmoodle:execute', context_system::instance()))
		throw new Vmoodle_Command_Sql_Exception('unsiffisantcapability');
	// Checking host
	if (!$DB->get_record('mnet_host', array('wwwroot' => $host)))
		throw new Vmoodle_Command_Sql_Exception('invalidhost');
	// Checking table
	if (empty($table) || !is_string($table))
		throw new Vmoodle_Command_Sql_Exception('invalidtable');
	// Checkig select
	if (empty($select) || (!is_array($select) && !is_int($select)))
		throw new Vmoodle_Command_Sql_Exception('invalidselect');
	if (!is_array($select)) 
		$select = array('id' => $select);
	// Checking field
	if (empty($fields))
		throw new Vmoodle_Command_Sql_Exception('invalidfields');
	if (!is_array($fields))
		$fields = array($fields);
	// Creating peer
	$mnet_host = new mnet_peer();
	if (!$mnet_host->bootstrap($host, null, 'moodle')) {
		return (object) array(
							'status' => MNET_FAILURE,
							'error' => get_string('couldnotcreateclient', 'vmoodleadminset_sql', $host)
						);
	}
	// Creating XMLRPC client
	$rpc_client = new Vmoodle_XmlRpc_Client();
	$rpc_client->add_param($table, 'string');
	$rpc_client->add_param($fields, 'array');
	$rpc_client->add_param($select, 'array');
	// Sending request
	if (!$rpc_client->send($mnet_host)) {
		if (debugging()) {
			echo '<pre>';
			var_dump($rpc_client);
			echo '</pre>';
		}
	}
	// Returning result
	return $rpc_client->response;
}

/**
 * Install sqllib plugin library.
 * @return					boolean				TRUE if the installation is successfull, FALSE otherwise.
 */
function sqllib_install() {
    global $DB, $OUTPUT;

	$result = true;
	$rpc = new stdclass;
	$rpcmap = new stdclass;
	// Retrieve service

	// Returning result
	return $result;
}

/**
 * Uninstall sqlib plugin library.
 * @return					boolean				TRUE if the uninstallation is successfull, FALSE otherwise.
 */
function sqllib_uninstall() {
	// Initializing
    global $DB, $OUTPUT;

	$result = true;

	// Returning result

	return $result;
}