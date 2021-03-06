<?php

require_once VMOODLE_CLASSES_DIR.'Command.class.php';
require_once($CFG->libdir.'/accesslib.php');
require_once($CFG->dirroot.'/blocks/vmoodle/plugins/plugins/rpclib.php');

global $PAGE;
$PAGE->requires->js('/blocks/vmoodle/plugins/plugins/js/plugins_compare.js');
$PAGE->requires->js('/blocks/vmoodle/plugins/plugins/js/strings.php');

/**
 * Describes a role comparison command.
 * 
 * @package block-vmoodle
 * @category blocks
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
class Vmoodle_Command_Plugins_Compare extends Vmoodle_Command {
	/** The plugintype plugins */
	private $plugins = array();
	/** The html report */
	private $report;

	/**
	 * Constructor.
	 * @throws			Vmoodle_Command_Exception.
	 */
	public function __construct() {
		global $DB, $STANDARD_PLUGIN_TYPES;

		/*
		@TODO : consider using get_plugin_types()
		*/
        
		// Getting command description
		$cmd_name = vmoodle_get_string('cmdcomparename', 'vmoodleadminset_plugins');
		$cmd_desc = vmoodle_get_string('cmdcomparedesc', 'vmoodleadminset_plugins');

		
		$plugin_param = new Vmoodle_Command_Parameter('plugintype', 'enum', vmoodle_get_string('plugintypeparamcomparedesc', 'vmoodleadminset_plugins'), null, $STANDARD_PLUGIN_TYPES);

		// Creating command
		parent :: __construct($cmd_name, $cmd_desc, $plugin_param);
	}

	/**
	 * Execute the command.
	 * @param	$hosts		mixed			The host where run the command (may be wwwroot or an array).
	 * @throws				Vmoodle_Command_Exception.
	 */
	public function run($hosts) {
		global $CFG, $USER;

		// Adding constants
		require_once $CFG->dirroot.'/blocks/vmoodle/rpclib.php';

		// Checking capability to run
		if (!has_capability('block/vmoodle:execute', context_system::instance()))
			throw new Vmoodle_Command_Exception('insuffisantcapabilities');

		// Getting plugin type
		$plugintype = $this->getParameter('plugintype')->getValue();

		// Creating XMLRPC client to read plugins configurations
		$rpc_client = new Vmoodle_XmlRpc_Client();
		$rpc_client->set_method('blocks/vmoodle/plugins/plugins/rpclib.php/mnetadmin_rpc_get_plugins_info');
		$rpc_client->add_param($plugintype, 'string');

		// Initializing responses
		$responses = array();

		// Creating peers
		$mnet_hosts = array();
		foreach($hosts as $host => $name) {
			$mnet_host = new mnet_peer();
			if ($mnet_host->bootstrap($host, null, 'moodle')) {
				$mnet_hosts[] = $mnet_host;
			} else {
				$responses[$host] = (object) array(
					'status' => MNET_FAILURE,
					'error' => get_string('couldnotcreateclient', 'block_vmoodle', $host)
				);
			}
		}

		// Sending requests
		foreach($mnet_hosts as $mnet_host) {
			// Sending request
			if (!$rpc_client->send($mnet_host)) {
				$response = new stdclass;
				$response->status = MNET_FAILURE;
				$response->errors[] = implode('<br/>', $rpc_client->getErrors($mnet_host));
				if (debugging()) {
					echo '<pre>';
					var_dump($rpc_client);
					echo '</pre>';
				}
			} else {
				$response = json_decode($rpc_client->response);
			}

			// Recording response
			$responses[$mnet_host->wwwroot] = $response;

			// Recording plugin descriptors
			if ($response->status == RPC_SUCCESS)
				$this->plugins[$mnet_host->wwwroot] = $response->value;
		}

		// Saving results
		$this->results = $responses + $this->results;

		// Processing results
		$this->_process();
	}

	/**
	 * Get the result of command execution for one host.
	 * @param	$host		string			The host to retrieve result (optional, if null, returns general result).
	 * @param	$key		string			The information to retrieve (ie status, error / optional).
	 * @return				mixed			The result or null if result does not exist.
	 * @throws				Vmoodle_Command_Exception.
	 */
	public function getResult($host = null, $key = null) {

		// Checking if command has been runned
		if (!$this->isRunned())
			throw new Vmoodle_Command_Exception('commandnotrun');

		// Checking host (general result isn't provide in this kind of command)
		if (is_null($host))
			return $this->report;
		else
			if (!array_key_exists($host, $this->results))
				return null;
		$result = $this->results[$host];

		// Checking key
		if (is_null($key))
			return $result;
		else
			if (property_exists($result, $key))
				return $result-> $key;
			else
				return null;
	}

	/**
	 * Process the plugin comparison.
	 * @throws			Vmoodle_Commmand_Exception.
	 */
	private function _process() {
		global $CFG, $DB, $OUTPUT, $STANDARD_PLUGIN_TYPES, $PAGE;
		
		$renderer = $PAGE->get_renderer('block_vmoodle');

		// Checking if command has been runned
		if (!$this->isRunned())
			throw new Vmoodle_Command_Exception('commandnotrun');

		// Getting examined plugintype
		$plugintype = $this->getParameter('plugintype')->getValue();
          
		// Getting hosts
		$hosts = array_keys($this->plugins);
		$host_labels = get_available_platforms();

		// Getting local plugin info
		$pm = plugin_manager::instance();

		$localplugins = $pm->get_plugins();
		$localtypeplugins = $localplugins[$plugintype];

		/*
		 * Creating html report.
		 */

		// Creating header
		$this->report = '<link href="'.$CFG->wwwroot.'/blocks/vmoodle/plugins/plugins/theme/styles.css" rel="stylesheet" type="text/css">';
		$this->report .= '<h3>'.vmoodle_get_string('compareplugins', 'vmoodleadminset_plugins', $STANDARD_PLUGIN_TYPES[$plugintype]).'</h3>';

		// Adding link to plugin management
		/* $this->report.= '<center><p>'.$OUTPUT->single_button(new moodle_url($CFG->wwwroot.'/admin/roles/define.php', array('roleid' => $role->id, 'action' => 'edit')), get_string('editrole', 'vmoodleadminset_roles'), 'get').'</p></center>'; */

		// Creation form
		$this->report.= '<form action="'.$CFG->wwwroot.'/blocks/vmoodle/plugins/plugins/controller.pluginlib.sadmin.php?what=syncplugins" method="post" onsubmit="return validate_syncplugins()">';
		$this->report.= '<input id="id_plugin" type="hidden" name="plugin" value=""/>';
		$this->report.= '<input id="source_platform" type="hidden" name="source_platform" value=""/>';

		// Creating table
		$this->report.= '<table id="plugincompare" cellspacing="1" cellpadding="5" class="generaltable boxaligncenter" style="min-width: 75%;">';
		$this->report.= '<tbody>';

		// Creating header
		$this->report.= '<tr><th scope="col" class="header c0" style="vertical-align: bottom; text-align: left;">&nbsp</th>';
		$col = 1;
		foreach($hosts as $host) {
			$this->report.= '<th id="plug_'.$col.'" scope="col" class="header c'.$col.'" style="vertical-align: bottom; text-align: center;">';
			$this->report.= '<label for="platform_'.$col.'"><img src="'.$CFG->wwwroot.'/blocks/vmoodle/plugins/plugins/draw_platformname.php?caption='.urlencode($host_labels[$host]).'" alt="'.$host_labels[$host].'"/></label><br/>';
			$this->report.= '<input id="platform_'.$col.'" type="checkbox" name="platforms[]" value="'.$host.'" disabled="disabled"/></th>';
			$col++;
		}
		$this->report.= '</tr>';

		// Initializing variables
		$row = 0;

		// Creating table data
		foreach($localtypeplugins as $plugin) {
			
			$col = 1;
			$this->report .= '<tr class="r'.($row % 2).'">';
			$this->report .= '<td id="plug_0_'.$row.'" class="cell c0" style="vertical-align: middle; text-align: left;" onClic="setPLugin('.$col.','.$row.',\''.$plugin->name.'\',\''.$host.'\')">';
			$this->report .= $plugin->displayname;
			$this->report .='</td>';

			foreach($hosts as $host) {
				$extra_class = false;
				$title = $plugin->displayname.' | '.$host_labels[$host];
				if (array_key_exists($host, $this->plugins) && property_exists($this->plugins[$host], $plugin->name)) {
					$remote_plugin = $this->plugins[$host]->{$plugin->name};
					if (is_null($remote_plugin)){
						$cell = '<img src="'.$renderer->pix_url('notinstalled', 'vmoodleadminset_plugins').' alt="Not installed" title="'.$title.'" />';
					} else {
						if ($remote_plugin->enabled){
							$cell = '<img src="'.$renderer->pix_url('enabled', 'vmoodleadminset_plugins').'" title="'.$title.'" />';
						} else {
							$cell = '<img src="'.$renderer->pix_url('disabled', 'vmoodleadminset_plugins').'" title="'.$title.'" />';
						}
						if ($localtypeplugins[$plugin->name]->versiondb > $remote_plugin->versiondb){
							$cell .= '&nbsp;<img src="'.$renderer->pix_url('needsupgrade', 'vmoodleadminset_plugins').'" title="'.$title.'" />';
						}
						if ($remote_plugin->versiondisk > $remote_plugin->versiondb){
							$cell .= '&nbsp;<img src="'.$renderer->pix_url('needslocalupgrade', 'vmoodleadminset_plugins').'" title="'.$title.'" />';
						}
					}
				} else {
					$cell = '<img src="'.$renderer->pix_url('notinstalled', 'vmoodleadminset_plugins').'" alt="Not installed" title="'.$title.'"/>';
				}
				$this->report.= '<td id="plug_'.$col.'_'.$row.'" class="cell c'.$col.($extra_class ? ' '.$extra_class : '').'" style="vertical-align: middle; text-align: center;" onmouseout="cellOut('.$col.','.$row.');" onmouseover="cellOver('.$col.','.$row.');">'.$cell.'</td>';
				$col++;
			}
			$this->report.= '</tr>';
			$row++;
		}

		// Closing table
		$this->report.= '</tboby></table><br/><center><input type="submit" value="'.get_string('synchronize', 'vmoodleadminset_plugins').'"/><div id="plugincompare_validation_message"></div></center></form><br/><br/>';
	}

	/**
	 * Return counter value.
	 * @param	$counter	array			The counter.
	 * @return				int				The counter value.
	 */
	private function _getCounterValue($counter) {
		return $counter['count'];
	}
}