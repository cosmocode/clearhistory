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
        return array(
            'author' => 'Dominik Eckelmann',
            'email'  => 'dokuwiki@cosmocode.de',
            'date'   => '2008-09-12',
            'name'   => 'admin plugin to cleanup the history',
            'desc'   => 'This plugin deletes history entrys that are -> only from one user (not interrupted form another) about 1h.',
            'url'    => 'http://www.dokuwiki.org/plugin:clearhistory',
        );
    }

    /**
     * Register its handlers with the dokuwiki's event controller
     */
    function register(&$controller) {
        $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE',  $this, 'cleanup', array());
    }

    function cleanup(&$event, $param) {
        global $conf;
        if ($this->run) return;
        $this->run = true;
        $hdl = plugin_load('admin','clearhistory');
        $hdl = new admin_plugin_clearhistory();
        $hdl->_scanRecents();
        touch($conf['cachedir'].'/lastclean');
    }


}



?>
