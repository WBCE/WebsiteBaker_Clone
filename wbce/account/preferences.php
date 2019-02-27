<?php
/**
 * WebsiteBaker Community Edition (WBCE)
 * Way Better Content Editing.
 * Visit http://wbce.org to learn more and to join the community.
 *
 * @copyright Ryan Djurovich (2004-2009)
 * @copyright WebsiteBaker Org. e.V. (2009-2015)
 * @copyright WBCE Project (2015-)
 * @license GNU GPL2 (or any later version)
 */


require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . ' /init.php';

$wb_inst = new wb();
if ($wb_inst->is_authenticated()==false) {
	header('Location: '.WB_URL.'/account/login.php');
	exit(0);
}


    
// Required page details
$page_id = 0;
$page_description = '';
$page_keywords = '';
define('TEMPLATE', account_getConfig()['preferences_template']);
define('PAGE_ID', $page_id);
define('ROOT_PARENT', 0);
define('PARENT', 0);
define('LEVEL', 0);

define('PAGE_TITLE', $MENU['PREFERENCES']);
define('MENU_TITLE', $MENU['PREFERENCES']);
define('MODULE', '');
define('VISIBILITY', 'public');

define('PAGE_CONTENT', ACCOUNT_TOOL_PATH .'/account/preferences_form.php');

// Include the index (wrapper) file
require(WB_PATH.'/index.php');