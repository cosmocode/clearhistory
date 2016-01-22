<?php
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_DATA')) define('DOKU_DATA',DOKU_INC.'data/');

require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_PLUGIN.'clearhistory/admin.php');

/**
 * Cleanup Action Plugin:   Clean up the history once per day
 *
 * @author     Dominik Eckelmann <eckelmann@cosmocode.de>
 */
class action_plugin_clearhistory extends DokuWiki_Action_Plugin {

	/**
	 * if true a cleanup process is already running
	 * or done in the last 24h
	 */
    var $run = false;

	/**
	 * Constructor - get some config details and check if a check runs in the last 24h
	 */
    function action_plugin_clearhistory() {
        global $conf;

		// check if the autocleaner is enabled
        if ($this->getConf('autoclearenabled') == 0) $this->run = true;

		// check if a runfile exists - if not -> there is no last run
        if (!is_file($conf['cachedir'].'/lastclean')) return;

		// check last run
        $get = fileatime($conf['cachedir'].'/lastclean');
        $get = intval($get);
        if ($get+(60*60*24) > time()) $this->run = true;
    }

    /**
	 * return some in
	 * @return array
     */
    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    /**
     * Register its handlers with the dokuwiki's event controller
	 *
	 * we need hook the indexer to trigger the cleanup
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE',  $this, 'cleanup', array());
    }

	/**
	 * start the scan
	 *
	 * scans the recent changes
	 */
    function cleanup(&$event, $param) {
        global $conf;

		if ($this->run) return;
		$this->run = true;
		echo 'clearhistory: started'.NL;
		
		$onlySmall     = $this->getConf('autoclearonlysmall');
        $onlyNoComment = $this->getConf('autoclearonlynocomment');        
		
        //$hdl = plugin_load('admin','clearhistory');
        $hdl = new admin_plugin_clearhistory();
        
		$hdl->_scanRecents(30, $onlySmall , $onlyNoComment);

		echo 'clearhistory: ' . $hdl->delcounter . ' deleted'.NL;
        touch($conf['cachedir'].'/lastclean');
    }

}



?>
