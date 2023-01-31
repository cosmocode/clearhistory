<?php

/**
 * Cleanup Action Plugin:   Clean up the history once per day
 *
 * @author     Dominik Eckelmann <eckelmann@cosmocode.de>
 */
class action_plugin_clearhistory extends DokuWiki_Action_Plugin
{

    /**
     * if true a cleanup process is already running
     * or done in the last 24h
     */
    protected $run = false;

    /**
     * Constructor - get some config details and check if a check runs in the last 24h
     */
    public function __construct()
    {
        global $conf;

        // check if the autocleaner is enabled
        if ($this->getConf('autoclearenabled') == 0) $this->run = true;

        // check if a runfile exists - if not -> there is no last run
        if (!is_file($conf['cachedir'] . '/lastclean')) return;

        // check last run
        $get = fileatime($conf['cachedir'] . '/lastclean');
        $get = intval($get);
        if ($get + (60 * 60 * 24) > time()) $this->run = true;
    }

    /** @inheritdoc */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE', $this, 'cleanup', array());
    }

    /**
     * start the scan
     *
     * scans the recent changes
     */
    public function cleanup($event, $param)
    {
        global $conf;

        if ($this->run) return;
        $this->run = true;
        echo 'clearhistory: started' . NL;

        $onlySmall = $this->getConf('autoclearonlysmall');
        $onlyNoComment = $this->getConf('autoclearonlynocomment');

        //$hdl = plugin_load('admin','clearhistory');
        $hdl = new admin_plugin_clearhistory();

        $hdl->_scanRecents(30, $onlySmall, $onlyNoComment);

        echo 'clearhistory: ' . $hdl->delcounter . ' deleted' . NL;
        touch($conf['cachedir'] . '/lastclean');
    }

}
