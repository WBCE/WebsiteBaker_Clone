<?php
/**
 *
 * @category        admintools
 * @package         wbstats
 * @author          Ruud Eisinga - Dev4me
 * @link			https://dev4me.com/
 * @license         http://www.gnu.org/licenses/gpl.html
 * @platform        WebsiteBaker 2.8.x / WBCE 1.4
 * @requirements    PHP 5.6 and higher
 * @version         0.2.2
 * @lastmodified    December 9, 2020
 *
 */

defined('WB_PATH') OR die(header('Location: ../index.php'));

$table_day   = TABLE_PREFIX .'mod_wbstats_day';
$table_ips   = TABLE_PREFIX .'mod_wbstats_ips';
$table_pages = TABLE_PREFIX .'mod_wbstats_pages';
$table_ref   = TABLE_PREFIX .'mod_wbstats_ref';
$table_key   = TABLE_PREFIX .'mod_wbstats_keywords';
$table_lang  = TABLE_PREFIX .'mod_wbstats_lang';

class counter {

	private $ip;
	private $referer;
	private $host;
	private $referer_host;
	private $referer_spam= 0;
	private $page;
	private $keywords;
	private $language;
	
	private $time;
	private $day;
	private $month;
	private $old_data;
	private $old_date;
	private $reload;
	private $online;
	
	function __construct() {
		$this->init();
		$this->count();
	}

	function init() {
		global $database, $table_day, $table_ips, $table_pages, $table_ref, $table_key, $table_lang;

		$time = time();
		$this->time = $time;
		$this->day   = date("Ymd",$time);
		$this->month = date("Ym",$time);
		//$this->old_data = mktime(0, 0, 0, date("n"), date("j"), date("Y")) - 48*60*60 ; // 48 hours
		//$this->old_date = date("Ymd", mktime(0, 0, 0, date("n"), date("j")-7, date("Y"))); // 7 days
		$this->old_data = strtotime(date("Ymd", mktime(0, 0, 0, date("n"), date("j") - 90, date("Y")))); // 90 days
		$this->old_date = date("Ymd", mktime(0, 0, 0, date("n"), date("j") - 90, date("Y"))); // 90 days
		$this->reload = 3 * 60 * 60 ;
		$this->online = $time - 3 * 60;

		$database->query("DELETE FROM ".$table_ips." WHERE `time` < '".$this->old_data."'");
		$database->query("DELETE FROM ".$table_pages." WHERE `day` < '".$this->old_date."'");
		$database->query("DELETE FROM ".$table_ref.  " WHERE `day` < '".$this->old_date."'");
		$database->query("DELETE FROM ".$table_key.  " WHERE `day` < '".$this->old_date."'");
		$database->query("DELETE FROM ".$table_lang. " WHERE `day` < '".$this->old_date."'");
		$id = $database->get_one("SELECT `id` FROM ".$table_day." WHERE `day` = '".$this->day."'");
		if (!$id) $database->query("INSERT INTO ".$table_day." (day, user, view) values ('".$this->day."', '0', '0')");
	}
	
	function count() {
		global $database, $table_day, $table_ips, $table_pages, $table_ref, $table_key, $table_lang;
		$this->getHosts();
		$this->getKeywords();
		$this->getSearch ();
		if ($this->newUser()) {
			if($this->referer_host && stristr($this->host, $this->referer_host) === false) {
				if(!$id = $database->get_one("SELECT `id` from ".$table_ref." WHERE `referer`='".$this->referer_host."' AND day='".$this->day."'") ) {
					$database->query("INSERT INTO ".$table_ref." (`day`, `referer`, `view`, `spam`) VALUES ('".$this->day."', '".$this->referer_host."', '1','".$this->referer_spam."' )");
				} else { 
					$database->query("UPDATE ".$table_ref." SET `view`=`view`+1 where `id`='$id'");
				}
			}
			if($this->language) {
				if (!$id = $database->get_one("SELECT `id` from ".$table_lang." WHERE `language`='".$this->language."' AND `day`='".$this->day."'")) {
					$database->query("INSERT INTO ".$table_lang." (`day`, `language`, `view`) VALUES ('".$this->day."', '".$this->language."', '1')");
				} else { 
					$database->query("UPDATE ".$table_lang." SET `view`=`view`+1 where `id`='$id'");
				}
			}
		} 
		
		if($this->keywords) {
			if (!$id = $database->get_one("SELECT `id` from ".$table_key." WHERE `keyword`='".$this->keywords."' AND `day`='".$this->day."'")) {
				$database->query("INSERT INTO ".$table_key." (`day`, `keyword`, `view`) VALUES ('".$this->day."', '".$this->keywords."', '1')");
			} else { 
				$database->query("UPDATE ".$table_key." SET `view`=`view`+1 where `id`='$id'");
			}
		}
		if($this->page <> "") {
			if (!$id = $database->get_one("SELECT `id` from ".$table_pages." WHERE `page`='".$this->page."' AND `day`='".$this->day."'")) {
				$database->query("INSERT INTO ".$table_pages." (`day`, `page`, `view`) VALUES ('".$this->day."', '".$this->page."', '1')");
			} else { 
				$database->query("UPDATE ".$table_pages." SET `view`=`view`+1 WHERE id='$id'");
			}
		}
	}
	function getHosts() {
		global $referer;
		$fp = $_SERVER['REMOTE_ADDR'] . session_id(); 
		if(isset($_SERVER['HTTP_USER_AGENT'])) $fp .= $_SERVER['HTTP_USER_AGENT'];
		if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $fp .= $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		$this->ip = md5($fp); 
        if (defined( 'ORG_REFERER' )) {
            $this->referer = ORG_REFERER;
		} elseif (isset($referer)) {
			$this->referer = $referer;
		} else {
			$this->referer = $_SERVER['HTTP_REFERER'];
		} 	
		$this->page = $_SERVER['REQUEST_URI']; 
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			$this->language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'],0,2);
		}
		$this->host=$_SERVER["HTTP_HOST"]; 
		if (substr($this->host,0,4) == "www.") $this->host=substr($this->host,4);
		$this->referer_host = parse_url($this->referer, PHP_URL_HOST); // Referrer Host
		if (substr($this->referer_host,0,4) == "www.") $this->referer_host=substr($this->referer_host,4);

		$this->referer = $this->escapeString($this->referer);
		$this->page = $this->escapeString($this->page);
		$this->language = $this->escapeString($this->language);
		$this->referer_host = $this->escapeString($this->referer_host);

	}
	
	function getKeywords () {
		$ref = parse_url($this->referer, PHP_URL_QUERY);
		parse_str( $ref, $parms );
		if(isset($parms['q']) && $parms['q']!="") $this->keywords = urldecode($parms['q']); 
		//elseif(isset($parms['q'])) 		$this->keywords = 'Searchkey not provided'; 
		elseif(isset($parms['p'])) 		$this->keywords = urldecode($parms['p']); 
		elseif(isset($parms['query'])) 	$this->keywords = urldecode($parms['query']); 
		$this->keywords = $this->escapeString($this->keywords);
	}

	function getSearch () {
		$ref = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
		parse_str( $ref, $parms );
		if(isset($parms['string']))  {
			$this->keywords = "Local search: ".urldecode($parms['string']); 
			$this->keywords = $this->escapeString($this->keywords);
		}
	}

	function newUser() {
		global $database, $table_day, $table_ips;
		$session = ""; //session_id();
		$timeout = time() - $this->reload;
		$loggedin = isset($_SESSION['USER_ID']) ? ", `loggedin`='1'":"";
		if($this->isBot()) {
			$database->query("UPDATE ".$table_day." SET `bots`=`bots`+1 WHERE `day`='".$this->day."'");
			$this->page = ''; //prevent pagecounting
			return false;
		} elseif($this->isRefererSpam()) {
			$database->query("UPDATE ".$table_day." SET `refspam`=`refspam`+1 WHERE `day`='".$this->day."'");
			$this->page = ''; //prevent pagecounting
			$this->keywords = ''; //prevent pagecounting
			$this->language = ''; //prevent pagecounting
			return true;
		} elseif(!$id = $database->get_one("SELECT `id` FROM ".$table_ips." WHERE `ip`='".$this->ip."' AND `time` > '$timeout' ORDER BY `id` DESC LIMIT 1")) {
			$database->query("INSERT INTO ".$table_ips." (ip, time, online,page,last_page,pages) VALUES ('".$this->ip."', '".$this->time."', '".$this->time."', '".$this->page."', '".$this->page."','1')");
			$database->query("UPDATE ".$table_day." SET `user`=`user`+1, `view`=`view`+1 WHERE `day`='".$this->day."'");
			return true;
		} else {
			$database->query("UPDATE ".$table_ips." SET `online`='".$this->time."', `last_page`='".$this->page."', `pages`=`pages`+1 $loggedin WHERE `id`='$id'");
			$database->query("UPDATE ".$table_day." SET `view`=`view`+1 WHERE `day`='".$this->day."'");
			return false;
		}
	}
	
	function isBot() { 
		if(!isset($_SERVER['HTTP_USER_AGENT'])) return true;
		require('botlist.php');
		$userAgent = strtolower($_SERVER['HTTP_USER_AGENT']); 
		if(empty($userAgent)) return true; //Empty useraget is mostly a bot
		foreach($botUserAgents as $botUserAgent) { 
			if(stripos($userAgent, $botUserAgent) !== false) { 
				return true; 
			} 
		} 
		return false; 
	}
	
	function isRefererSpam() {
		if(!$this->referer_host) return false;
		require('referers.php');
		foreach($spamReferers as $spammer) { 
			if(stripos($this->referer_host, $spammer) !== false) { 
				$this->referer_spam = '1';
				return true; 
			} 
		} 
		return false; 
		
	}
	function escapeString($string) {	
		global $database;
		if(is_object($database->DbHandle)) { 
			$rval = $database->escapeString($string);
		} else {
			$rval = mysql_real_escape_string ($string);
		}
		return $rval;
	}
	
} // end class

