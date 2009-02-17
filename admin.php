<?php
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'admin.php');
require_once(DOKU_INC.'inc/pageutils.php');
require_once(DOKU_INC.'inc/changelog.php');
require_once(DOKU_INC.'inc/io.php');
require_once(DOKU_INC.'inc/common.php');
/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_clearhistory extends DokuWiki_Admin_Plugin {

    /**
     * pages in a hiracical view
     *
     * "<namespace>" => array( ... )
     * "pages" => array ( pagenames )
     */
    var $pages = array();

    var $delcounter = 0;

    /**
     * return some info
     */
    function getInfo(){
      return confToHash(dirname(__FILE__).'/info.txt');
    }

    /**
     * return sort order for position in admin menu
     */
    function getMenuSort() {
      return 999;
    }

    /**
     * handle user request
     */
    function handle() {
        if (isset($_GET['clear'])) {
            if ($_GET['clear'] == 1) {
                $this->_scanRecents();
            } else if ($_GET['clear'] == 2) {
                $_GET['ns'] = cleanID($_GET['ns']);
                $this->_scan($_GET['ns']);
            }
            msg(sprintf($this->getLang('deleted'),$this->delcounter),1);
        }
    }

    /**
     * output appropriate html
     */
    function html() {
        //$this->_scanRecents();
        //$this->_scan();



        echo '<h1>'.$this->getLang('name').'</h1>';
        echo '<form action="doku.php" method="GET"><fieldset style="float:right;margin-right:10px;width:315px;margin-left:10px">';
        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="page" value="clearhistory" />';

        echo '<fieldset><input type="radio" name="clear" value="1" id="c1" checked="checked" /> <label for="c1">';
        echo $this->getLang('clean on recent changes').'</label></fieldset><br/>';

        echo '<fieldset><input type="radio" name="clear" value="2" id="c2" /> <label for="c2">';
        echo $this->getLang('clean on namespace');
        echo '</label><br /><input type="text" name="ns" class="edit" />';
        echo '</fieldset><br/>';
        echo '<input type="submit" value="'.$this->getLang('do').'" class="button" /></fieldset>';
        echo '</form>';
        echo '<p style="margin-left:30px;">'.$this->getLang('desctext').'</p>';;
    }

    /**
     * Scans namespaces for deletable revisions
     * @param string ns the namespace to search in
     */
    function _scan($ns = '') {
        $dir = preg_replace('/\.txt(\.gz)?/i','', wikiFN($ns));
        $dir = rtrim($dir,'/');
        if (!is_dir($dir)) return;
        $dh = opendir($dir);
        if (!$dh) {
            echo 'error';
            return;
        }
        $this->delcounter = 0;
        while (($file = readdir($dh)) !== false) {
            if ($file == '.' || $file == '..' ) continue;
            if (is_dir($dir.'/'.$file)) {
                $this->_scan($ns.':'.$file);
                continue;
            }
            if ($file[0] == '_') continue;
            if (substr($file,-4) == '.txt') {
                $name = substr($file,0,-4);
                $this->_parseChangesFile(metaFN($ns.':'.$name,'.changes'),$ns.':'.$name);
            }
        }
        closedir($dh);
    }

    /**
     * Scans the recent changed files for changes
     * @param int $num number of last changed files
     */
    function _scanRecents($num = 30) {
        $recents = getRecents(0,$num);
        $this->delcounter = 0;
        foreach ($recents as $recent) {
            $this->_parseChangesFile(metaFN($recent['id'],'.changes'),$recent['id']);
        }
    }

    /**
     * Parses a .changes file for deletable pages and deletes them.
     * @param string $file the path to the change file
     */
    function _parseChangesFile($file,$page) {
        if (!is_file($file)) return;
        if (checklock($page)) return;
        lock($page);
        $content = file_get_contents($file);
        // get page informations
        $max = preg_match_all('/^([0-9]+)\s+?([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\s+?(E|C|D)\s+?(\S+)\s+?(\S*)\s+?(.*)$/im',$content,$match);
        if ($max <= 1) return;
        // set mark to creation entry
        $cmptime = $match[1][$i]+9999999999;
        $cmpuser = (empty($match[5][$max-1]))?$match[2][$max-1]:$match[5][$max-1]; // user or if not logged in ip
        $newcontent = '';
        for ($i=$max-1;$i>=0;$i--) {
            $user = (empty($match[5][$i]))?$match[2][$i]:$match[5][$i]; // user or if not logged in ip
            $time = $match[1][$i];
            // Creations arnt touched
            if (!($match[3][$i] == "E" || $match[3][$i] == "e")) {
                $cmpuser = $user;
                $cmptime = $time;
                $newcontent = sprintf("%d\t%s\t%s\t%s\t%s\t%s\n",$match[1][$i],$match[2][$i],$match[3][$i],$match[4][$i],$match[5][$i],$match[6][$i]).$newcontent;
                continue;
            }
            // if not the same user -> new mark and continue
            if ($user != $cmpuser) {
                $cmpuser = $user;
                $cmptime = $time;
                $newcontent = sprintf("%d\t%s\t%s\t%s\t%s\t%s\n",$match[1][$i],$match[2][$i],$match[3][$i],$match[4][$i],$match[5][$i],$match[6][$i]).$newcontent;
                continue;
            }
            // check the time difference between the entrys
            if ( $cmptime-(60*60) < $time ) {
                @unlink(wikiFN($match[4][$i],$time));
                $this->delcounter++;
                continue;
            }
            $cmptime = $time;
            $newcontent = sprintf("%d\t%s\t%s\t%s\t%s\t%s\n",$match[1][$i],$match[2][$i],$match[3][$i],$match[4][$i],$match[5][$i],$match[6][$i]).$newcontent;
        }
        unlock($page);
        io_saveFile($file,$newcontent);
    }

    function forAdminOnly() {
        return true;
    }

    function getMenuText($lang) {
        return $this->getLang('menu');
    }

}

?>
