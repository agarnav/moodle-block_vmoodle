<?php

require_once VMOODLE_CLASSES_DIR.'Command.class.php';

/**
 * Describes meta-administration plugin's command for Maintenance setup.
 * 
 * @package block-vmoodle
 * @category blocks
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
class Vmoodle_Command_PurgeCaches extends Vmoodle_Command {	

	/** maintenance message. Sets maintenance mode off if empty */
	private $message;
	
	/** If command's result should be returned */
	private $returned;
	
	/**
	 * Constructor.
	 * @param	$name				string				Command's name.
	 * @param	$description		string				Command's description.
	 * @param	$sql				string				SQL command.
	 * @param	$parameters			mixed				Command's parameters (optional / could be null, Vmoodle_Command_Parameter object or Vmoodle_Command_Parameter array).
	 * @param	$rpcommand			Vmoodle_Command			Retrieve platforms command (optional / could be null or Vmoodle_Command object).
	 * @throws	Vmoodle_Command_Exception
	 */
	public function __construct($name, $description, $parameters=null, $rpcommand=null) {
		global $vmcommands_constants;
		
		// Creating Vmoodle_Command
		parent::__construct($name, $description, $parameters, $rpcommand);
			
	}
	
	/**
	 * Execute the command.
	 * @param	$host		mixed			The hosts where run the command (may be wwwroot or an array).
	 * @throws				Vmoodle_Command_Maintenance_Exception
	 */
	public function run($hosts) {
		global $CFG, $USER;
		
		// Adding constants
		require_once $CFG->dirroot.'/blocks/vmoodle/rpclib.php';
		
		// Checking host
		if (!is_array($hosts))
			$hosts = array($hosts => 'Unnamed host');
		
		// Checking capabilities
		if (!has_capability('block/vmoodle:execute', get_context_instance(CONTEXT_SYSTEM)))
			throw new Vmoodle_Command_PurgeCaches_Exception('insuffisantcapabilities');
			
		// Initializing responses
		$responses = array();
		
		// Creating peers
		$mnet_hosts = array();
		foreach ($hosts as $host => $name) {
			$mnet_host = new mnet_peer();
			if ($mnet_host->bootstrap($host, null, 'moodle')){
				$mnet_hosts[] = $mnet_host;
			} else {
				$responses[$host] = (object) array('status' => RPC_FAILURE, 'error' => get_string('couldnotcreateclient', 'block_vmoodle', $host));
			}
		}
		
		// Getting command
		$command = $this->isReturned();
		
		// Creating XMLRPC client
		$rpc_client = new Vmoodle_XmlRpc_Client();
		$rpc_client->set_method('blocks/vmoodle/plugins/generic/rpclib.php/mnetadmin_rpc_purge_caches');
		
		// Sending requests
		foreach($mnet_hosts as $mnet_host) {
			// Sending request
			if (!$rpc_client->send($mnet_host)) {
				$response = new StdClass();
				$response->status = RPC_FAILURE;
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
		}
		
		// Saving results
		$this->results = $responses + $this->results;
	}
	
	/**
	 * Get the result of command execution for one host.
	 * @param	$host		string			The host to retrieve result (optional, if null, returns general result).
	 * @param	$key		string			The information to retrieve (ie status, error / optional).
	 * @throws				Vmoodle_Command_Sql_Exception
	 */
	public function getResult($host=null, $key=null) {
		// Checking if command has been runned
		if (is_null($this->results))
			throw new Vmoodle_Command_Exception('commandnotrun');
		
		// Checking host (general result isn't provide in this kind of command)
		if (is_null($host) || !array_key_exists($host, $this->results))
			return null;
		$result = $this->results[$host];
		
		// Checking key
		if (is_null($key))
			return $result;
		else if (property_exists($result, $key))
			return $result->$key;
		else
			return null;
	}
		
	/**
	 * Get if the command's result is returned.
	 * @return						boolean				True if the command's result should be returned, false otherwise.
	 */
	public function isReturned() {
		return $this->returned;
	}
	
	/**
	 * Set if the command's result is returned.
	 * @param	$returned			boolean				True if the command's result should be returned, false otherwise.
	 */
	public function setReturned($returned) {
		$this->returned = $returned;
	}
		
}