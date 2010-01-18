<?php
/**
 * Cleanup Action Plugin:   Clean up the history once per day
 *
 * @author     Dominik Eckelmann <eckelmann@cosmocode.de>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_DATA')) define('DOKU_DATA',DOKU_INC.'data/');


require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_PLUGIN.'clearhistory/admin.php');

class action_plugin_clearhistory extends DokuWiki_Action_Plugin {

    var $run = false;

    function action_plugin_clearhistory() {
        global $conf;
        if ($this->getConf('autoclearenabled') == 0) $this->run = true;
        if (!is_file($conf['cachedir'].'/lastclean')) return;
        $get = fileatime($conf['cachedir'].'/lastclean');
        $get = intval($get);
        if ($get+(60*60*24) > time()) $this->run = true;
    }

    /**
     * return some info
     */
    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    /**
     * Register its handlers with the dokuwiki's event controller
     */
    function register(&$controller) {
        $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE',  $this, 'cleanup', array());
    }

    function cleanup(&$event, $param) {
        global $conf;

        $onlySmall     = $this->getConf('autoclearonlysmall');
        $onlyNoComment = $this->getConf('autoclearonlynocomment');
        if ($this->run) return;
        $this->run = true;
        $hdl = plugin_load('admin','clearhistory');
        $hdl = new admin_plugin_clearhistory();
        $hdl->_scanRecents($onlySmall , $onlyNoComment);
        touch($conf['cachedir'].'/lastclean');
    }


}



?>
