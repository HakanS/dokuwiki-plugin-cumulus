<?php
/**
 * DokuWiki Plugin Cumulus (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Håkan Sandell <sandell.hakan@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_cumulus extends DokuWiki_Syntax_Plugin {

    function getInfo() {
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 98; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~\w*?CUMULUS.*?~~',$mode,'plugin_cumulus');
    }

    function handle($match, $state, $pos, &$handler) {
        $flags = explode('&', substr($match, 2, -2));
        unset($flags[0]);
        foreach ($flags as $flag) {
            list($name, $value) = explode('=', $flag);
            $data[strtolower(trim($name))] = trim($value);
        }
        return $data;
    }

    function render($mode, &$renderer, $data) {
        if($mode != 'xhtml') return false;

        // prevent caching to ensure the included pages are always fresh
        $renderer->info['cache'] = false;

        // flash movie options, input filtering
        $options['width']   = (int)(is_numeric($data['width']) ? $data['width'] : $this->getConf('width'));
        $options['height']  = (int)(is_numeric($data['height']) ? $data['height'] : $this->getConf('height'));
        $options['tcolor']  = hsc(isset($data['tcolor']) ? $data['tcolor'] : $this->getConf('tcolor'));
        $options['tcolor2'] = hsc(isset($data['tcolor2']) ? $data['tcolor2'] : $this->getConf('tcolor2'));
        $options['hicolor'] = hsc(isset($data['hicolor']) ? $data['hicolor'] : $this->getConf('hicolor'));
        $options['bgcolor'] = hsc(isset($data['bgcolor']) ? $data['bgcolor'] : $this->getConf('bgcolor'));
        $options['speed']   = (int)(is_numeric($data['speed']) ? $data['speed'] : $this->getConf('speed'));
        $options['distr']   = hsc(isset($data['distr']) ? $data['distr'] : $this->getConf('distr'));
        $options['max']     = (int)(is_numeric($data['max']) ? $data['max'] : $this->getConf('max'));
        $options['show']    = $data['show'];
        if ($options['show'] == 'tag') $options['show'] = 'tags';
        if ($options['show'] == 'namespace') $options['show'] = 'namespaces';

        // get the tag cloud...
        $tagcloud = $this->_getTagXml($options);

        // add random seeds to so name and movie url to avoid collisions and force reloading (needed for IE)
        $movie = 'cumulus/tagcloud.swf';
        if (!file_exists(DOKU_PLUGIN.$movie)) {
            $renderer->doc .= $this->getLang('filenotfound');
            return true;
        }
        $movie = '/lib/plugins/'.$movie.'?r='.rand(0,9999999);
        
        // write flash tag
        $params = array(
                'allowScriptAccess'  => 'always' ,
                'bgcolor'  => '#'.$options['bgcolor'] ,
                );

        if ($this->getConf('trans')) {
            $params['wmode'] = 'transparent';
        }

        $flashvars = array(
                'tcolor'  => '0x'.$options['tcolor'] ,
                'tcolor2' => '0x'.($options['tcolor2'] == "" ? $options['tcolor'] : $options['tcolor2']) ,
                'hicolor' => '0x'.($options['hicolor'] == "" ? $options['tcolor'] : $options['hicolor']) ,
                'tspeed'  => (int)$options['speed'] ,
                'distr'   => ($options['distr'] ? 'true' : 'false') ,
                'mode'    => 'tags' ,
                'tagcloud' => '<tags>'.$tagcloud.'</tags>' ,
                );

        if ($this->getConf('showtags')) {
            $alt = '<div id="cloud">'; 
        } else {
            $alt = '<div id="cloud" style="display:none;">';
        }
        $alt .= preg_replace('/style=".*?"/', '', urldecode($tagcloud));
        $alt .= '</div>'.DOKU_LF;
        $alt .= '<p>Download <a href="http://www.macromedia.com/go/getflashplayer">Flash Player</a> 9 or better for full experience.</p>'.DOKU_LF;
        
        $renderer->doc .= html_flashobject($movie, $options['width'], $options['height'], $params, $flashvars, null, $alt);
        return true;
    }

    /**
     * Returns <a></a> links with font style attribut representing number of ocurrences
     * (inspired by DokuWiki Cloud plugin by Gina Häußge, Michael Klier, Esther Brunner)
     */
    function _getTagXml($options) {
        global $conf;

        if ($options['show'] == 'tags') { // we need the tag helper plugin
            if (plugin_isdisabled('tag') || (!$tag = plugin_load('helper', 'tag'))) {
                msg('The Tag Plugin must be installed to display tag clouds.', -1);
                return '';
            }
            $cloud = $this->_getTagCloud($options['max'], $min, $max, $tag);

        } elseif ($options['show'] == 'namespaces') {
            $cloud = $this->_getNamespaceCloud($options['max'], $min, $max);

        } else {
            $cloud = $this->_getWordCloud($options['max'], $min, $max);
        }
        if (!is_array($cloud) || empty($cloud)) return '';

        $delta = ($max-$min)/16;
        if ($delta == 0) $delta = 1;
        
        foreach ($cloud as $word => $size) {
            if ($size < $min+round($delta)) $class = 'cloud1';
            elseif ($size < $min+round(2*$delta)) $class = 'cloud2';
            elseif ($size < $min+round(4*$delta)) $class = 'cloud3';
            elseif ($size < $min+round(8*$delta)) $class = 'cloud4';
            else $class = 'cloud5';

            $name = $word;
            if ($options['show'] == 'tags') {
                $id = $word;
                resolve_pageID($tag->namespace, $id, $exists);
                if($exists) {
                    $link = wl($id);
                    if($conf['useheading']) {
                        $name = p_get_first_heading($id, false);
                    }
                } else {
                    $link = wl($id, array('do'=>'showtag', 'tag'=>noNS($id)));
                }
                $title = $id;
                $class .= ($exists ? '_tag1' : '_tag2');

            } elseif ($options['show'] == 'namespaces') {
                $id ='';
                resolve_pageID($word, $id, $exists);
                $link = wl($id);
                $title = $id;
                $size = 108;
                $class = 'cloud5';

            } else {
                if($conf['userewrite'] == 2) {
                    $link = wl($word, array('do'=>'search', 'id'=>$word));
                    $title = $size;
                } else {
                    $link = wl($word, 'do=search');
                    $title = $size;
                }
            }

            $fsize = 8 + round(($size-$min)/$delta);
            $xmlCloude .= '<a href="' .DOKU_URL. $link . '" class="' . $class .'"' .' title="' . $title . '" style="font-size: '. $fsize .'pt;">' . $name . '</a>' . DOKU_LF;
        }
        return $xmlCloude;
    }

    /**
     * Returns the sorted namespace cloud array
     */
    function _getNamespaceCloud($num, &$min, &$max) {
        global $conf;

        $cloud = array();
        $namesp = array();
        $opts = array();
        search($namesp,$conf['datadir'],'search_namespaces',$opts);

        foreach ($namesp as $name) {
            if ($name['ns'] == '') $cloud[$name['id']] = 100;
        }
        return $this->_sortCloud($cloud, $num, $min, $max);
    }

    /**
     * Returns the sorted word cloud array
     * (from DokuWiki Cloud plugin by Gina Häußge, Michael Klier, Esther Brunner)
     */
    function _getWordCloud($num, &$min, &$max) {
        global $conf;

        // load stopwords
        $swfile = DOKU_INC.'inc/lang/'.$conf['lang'].'/stopwords.txt';
        if (@file_exists($swfile)) $stopwords = file($swfile);
        else $stopwords = array();

        // load extra local stopwords
        $swfile = DOKU_CONF.'stopwords.txt';
        if (@file_exists($swfile)) $stopwords = array_merge($stopwords, file($swfile));

        $cloud = array();

        if (@file_exists($conf['indexdir'].'/page.idx')) { // new word-lenght based index
            require_once(DOKU_INC.'inc/indexer.php');

            $n = 2; // minimum word length
            $lengths = idx_indexLengths($n);
            foreach ($lengths as $len) {
                $idx      = idx_getIndex('i', $len);
                $word_idx = idx_getIndex('w', $len);

                $this->_addWordsToCloud($cloud, $idx, $word_idx, $stopwords);
            }

        } else {                                          // old index
            $idx      = file($conf['cachedir'].'/index.idx');
            $word_idx = file($conf['cachedir'].'/word.idx');

            $this->_addWordsToCloud($cloud, $idx, $word_idx, $stopwords);
        }
        return $this->_sortCloud($cloud, $num, $min, $max);
    }

    /**
     * Adds all words in given index as $word => $freq to $cloud array
     * (from DokuWiki Cloud plugin by Gina Häußge, Michael Klier, Esther Brunner)
     */
    function _addWordsToCloud(&$cloud, $idx, $word_idx, &$stopwords) {
        $wcount = count($word_idx);

        // collect the frequency of the words
        for ($i = 0; $i < $wcount; $i++) {
            $key = trim($word_idx[$i]);
            if (!is_int(array_search("$key\n", $stopwords))) {
                $value = explode(':', $idx[$i]);
                if (!trim($value[0])) continue;
                $cloud[$key] = count($value);
            }
        }
    }

    /**
     * Returns the sorted tag cloud array
     * (from DokuWiki Cloud plugin by Gina Häußge, Michael Klier, Esther Brunner)
     */
    function _getTagCloud($num, &$min, &$max, &$tag) {
        $cloud = array();
        if(!is_array($tag->topic_idx)) return;
        foreach ($tag->topic_idx as $key => $value) {
            if (!is_array($value) || empty($value) || (!trim($value[0]))) {
                continue;
            } else {
                $pages = array();
                foreach($value as $page) {
                    if(auth_quickaclcheck($page) < AUTH_READ) continue;
                    array_push($pages, $page);
                }
                if(!empty($pages)) $cloud[$key] = count($pages);
            }
        }
        return $this->_sortCloud($cloud, $num, $min, $max);
    }

    /**
     * Sorts and slices the cloud
     * (from DokuWiki Cloud plugin by Gina Häußge, Michael Klier, Esther Brunner)
     */
    function _sortCloud($cloud, $num, &$min, &$max) {
        if(empty($cloud)) return;

        // sort by frequency, then alphabetically
        arsort($cloud);
        $cloud = array_chunk($cloud, $num, true);
        $max = current($cloud[0]);
        $min = end($cloud[0]);
        ksort($cloud[0]);

        return $cloud[0];
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
