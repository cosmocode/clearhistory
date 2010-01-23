<?php
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'admin.php');
require_once(DOKU_INC.'inc/pageutils.php');
require_once(DOKU_INC.'inc/changelog.php');
require_once(DOKU_INC.'inc/io.php');
require_once(DOKU_INC.'inc/common.php');

/**
 * This plugin is used to cleanup the history
 *
 * @see    http://dokuwiki.org/plugin:clearhistory
 * @author Dominik Eckelmann <deckelmann@gmail.com>
 */
class admin_plugin_clearhistory extends DokuWiki_Admin_Plugin {

    /**
     * pages in a hiracical view
     *
     * "<namespace>" => array( ... )
     * "pages" => array ( pagenames )
     */
    var $pages = array();

	/**
	 * counts deleted pages for a run
	 */
    var $delcounter = 0;

    /**
     * return some information about the plugin
	 *
	 * @return array
     */
    function getInfo(){
      return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    /**
     * return sort order for position in admin menu
	 *
	 * @return integer
     */
    function getMenuSort() {
      return 999;
    }

    /**
     * handle the request befor html output
	 *
	 * @see html()
     */
    function handle() {
        $onlySmall = false;
        $onlsNoComment = false;
        if (isset($_REQUEST['onlysmall']) && $_REQUEST['onlysmall'] == 'on' ) $onlySmall = true;
        if (isset($_REQUEST['onlynocomment']) && $_REQUEST['onlynocomment'] == 'on' ) $onlyNoComment = true;

        if (isset($_GET['clear'])) {
            if ($_GET['clear'] == 1) {
                $this->_scanRecents( 30 , $onlySmall , $onlyNoComment );
            } else if ($_GET['clear'] == 2) {
                $_GET['ns'] = cleanID($_GET['ns']);
                $this->_scanNamespace($_GET['ns'] , $onlySmall , $onlyNoComment );
            }
            msg(sprintf($this->getLang('deleted'),$this->delcounter),1);
        }
    }

    /**
     * output html for the admin page
     */
    function html() {
        echo '<h1>'.$this->getLang('name').'</h1>';
        echo '<form action="doku.php" method="GET"><fieldset class="clearhistory">';
        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="page" value="clearhistory" />';

        echo '<fieldset><input type="radio" name="clear" value="1" id="c1" checked="checked" /> <label for="c1">';
        echo $this->getLang('clean on recent changes').'</label></fieldset><br/>';

        echo '<fieldset><input type="radio" name="clear" value="2" id="c2" /> <label for="c2">';
        echo $this->getLang('clean on namespace');
        echo '</label><br /><input type="text" name="ns" class="edit" />';
        echo '</fieldset><br/>';
		echo '<fieldset>';
		echo '<input type="checkbox" name="onlysmall" id="c3" '. ($this->getConf('autoclearonlysmall')?'checked="checked"':'') .'  /> <label for="c3">'.$this->getLang('onlysmall').'</label><br />';
		echo '<input type="checkbox" name="onlynocomment" id="c4" '. ($this->getConf('autoclearonlynocomment')?'checked="checked"':'') .' /> <label for="c4">'.$this->getLang('onlynocomment').'</label><br />';
		echo '</fieldset>';

        echo '<input type="submit" value="'.$this->getLang('do').'" class="button" /></fieldset>';
        echo '</form>';
        echo '<p class="clearhistory">'.$this->getLang('desctext').'</p>';
    }

	/**
	 * Scans throu a namespace and count the deleted pages in $this->delcounter
	 *
     * @param string	$ns 			the namespace to search in
	 * @param boolean	$onlySmall 		only delete small changes on true
	 * @param boolean	$onlyNoComment	don't delete changes with a comment on true
	 */
	function _scanNamespace($ns, $onlySmall = false, $onlyNoComment = false) {
		$this->delcounter = 0;
		$this->_scan($ns, $onlySmall, $onlyNoComment);
	}


    /**
     * Scans namespaces for deletable revisions
	 *
     * @param string	$ns				the namespace to search in
	 * @param boolean	$onlySmall 		only delete small changes on true
	 * @param boolean	$onlyNoComment	don't delete changes with a comment on true
     */
    function _scan($ns = '', $onlySmall = false, $onlyNoComment = false) {
        $dir = preg_replace('/\.txt(\.gz)?/i','', wikiFN($ns));
        $dir = rtrim($dir,'/');
        if (!is_dir($dir)) return;
        $dh = opendir($dir);
        if (!$dh) {
            echo 'error';
            return;
        }
        while (($file = readdir($dh)) !== false) {
            if ($file == '.' || $file == '..' ) continue;
            if (is_dir($dir.'/'.$file)) {
                $this->_scan($ns.':'.$file);
                continue;
            }
            if ($file[0] == '_') continue;
            if (substr($file,-4) == '.txt') {
                $name = substr($file,0,-4);
                $this->_parseChangesFile(metaFN($ns.':'.$name,'.changes'),$ns.':'.$name, $onlySmall, $onlyNoComment);
            }
        }
        closedir($dh);
    }

    /**
     * Scans the recent changed files for changes
	 *
     * @param int 		$num 			number of last changed files to scan
	 * @param boolean	$onlySmall 		only delete small changes on true
	 * @param boolean	$onlyNoComment	don't delete changes with a comment on true
     */
    function _scanRecents( $num = 30, $onlySmall = false , $onlyNoComment = false ) {
        $recents = getRecents(0,$num);

        $this->delcounter = 0;
        foreach ($recents as $recent) {
            $this->_parseChangesFile(metaFN($recent['id'],'.changes'),$recent['id'], $onlySmall, $onlyNoComment);
        }
    }

    /**
     * Parses a .changes file for deletable pages and deletes them.
	 *
     * @param string	$file			the path to the change file
	 * @param string	$page			wiki pagename
	 * @param boolean	$onlySmall		deletes only small changes
	 * @param boolean	$onlyNoComment	deletes only entrys without a comment
     */
    function _parseChangesFile( $file , $page , $onlySmall = false , $onlyNoComment = false ) {
        if (!is_file($file)) return;
        if (checklock($page)) return;
        lock($page);
        $content = file_get_contents($file);
        // get page informations
        $max = preg_match_all('/^([0-9]+)\s+?([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\s+?(E|C|D|R)\s+?(\S+)\s+?(\S*)\s+?(.*)$/im',$content,$match);
        if ($max <= 1) return;
        // set mark to creation entry
        $cmptime = $match[1][$i]+9999999999;
        $cmpuser = (empty($match[5][$max-1]))?$match[2][$max-1]:$match[5][$max-1]; // user or if not logged in ip
        $newcontent = '';
        for ($i=$max-1;$i>=0;$i--) {
            $user = (empty($match[5][$i]))?$match[2][$i]:$match[5][$i]; // user or if not logged in ip
            $time = $match[1][$i];
            // Creations arnt touched
            if ($match[3][$i] != "E" && $match[3][$i] != "e") {
                $cmpuser = $user;
                $cmptime = $time;
                $newcontent = $this->_addLine($match,$i) . $newcontent;
                continue;
            }
            // if not the same user -> new mark and continue
            if ($user != $cmpuser) {
                $cmpuser = $user;
                $cmptime = $time;
                $newcontent = $this->_addLine($match,$i) . $newcontent;
                continue;
            }

			// if onlySmall is set we just want to handle the small matches
			if ($onlySmall && $match[3][$i] == 'E') {
				$cmpuser = $user;
				$cmptime = $time;
				$newcontent = $this->_addLine($match,$i) . $newcontent;
				continue;
			}

			// if onlyNoComment is set we pass all lines with a comment
			if ($onlyNoComment && trim($match[6][$i]) != '') {
				$cmpuser = $user;
				$cmptime = $time;
				$newcontent = $this->_addLine($match,$i) . $newcontent;
				continue;
			}

            // check the time difference between the entrys
            if ( $cmptime-(60*60) < $time ) {
                @unlink(wikiFN($match[4][$i],$time));
                $this->delcounter++;
                continue;
            }
            $cmptime = $time;
            $newcontent = $this->_addLine($match,$i) . $newcontent;
        }
        unlock($page);
        io_saveFile($file,$newcontent);
    }

	function _addLine($match,$i) {
		return $match[0][$i]."\n";
	}

	/**
	 * shows that this function is accessible only by admins
	 *
	 * @return true
	 */
    function forAdminOnly() {
        return true;
    }

	/**
	 * returns the name in the menu
	 *
	 * @return Menu name for the plugin
	 */
    function getMenuText($lang) {
        return $this->getLang('menu');
    }

}

?>
