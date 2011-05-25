<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based photo gallery                                    |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2011 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// | Basic constants and includes                                          |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH','./');
define('IN_ADMIN', true);

include_once(PHPWG_ROOT_PATH.'include/common.inc.php');
include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'admin/include/functions_plugins.inc.php');

trigger_action('loc_begin_admin');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | Direct actions                                                        |
// +-----------------------------------------------------------------------+

// theme changer
if (isset($_GET['change_theme']))
{
  $admin_themes = array('roma', 'clear');

  $new_admin_theme = array_pop(
    array_diff(
      $admin_themes,
      array($conf['admin_theme'])
      )
    );

  conf_update_param('admin_theme', $new_admin_theme);

  redirect('admin.php');
}

// +-----------------------------------------------------------------------+
// | Synchronize user informations                                         |
// +-----------------------------------------------------------------------+

if (mt_rand(0,9)==0)
{
  sync_users();
}

// +-----------------------------------------------------------------------+
// | Variables init                                                        |
// +-----------------------------------------------------------------------+

// ?page=plugin-community-pendings is an clean alias of
// ?page=plugin&section=community/admin.php&tab=pendings
if (isset($_GET['page']) and preg_match('/^plugin-([^-]*)(?:-(.*))?$/', $_GET['page'], $matches))
{
  $_GET['page'] = 'plugin';
  $_GET['section'] = $matches[1].'/admin.php';
  if (isset($matches[2]))
  {
    $_GET['tab'] = $matches[2];
  }
}

if (isset($_GET['page'])
    and preg_match('/^[a-z_]*$/', $_GET['page'])
    and is_file(PHPWG_ROOT_PATH.'admin/'.$_GET['page'].'.php'))
{
  $page['page'] = $_GET['page'];
}
else
{
  $page['page'] = 'intro';
}

$page['errors'] = array();
$page['infos']  = array();
$page['warnings']  = array();

if (isset($_SESSION['page_infos']))
{
  $page['infos'] = array_merge($page['infos'], $_SESSION['page_infos']);
  unset($_SESSION['page_infos']);
}

$link_start = PHPWG_ROOT_PATH.'admin.php?page=';
$conf_link = $link_start.'configuration&amp;section=';

// +-----------------------------------------------------------------------+
// | Template init                                                         |
// +-----------------------------------------------------------------------+

$title = l10n('Piwigo Administration'); // for include/page_header.php
$page['page_banner'] = '<h1>'.l10n('Piwigo Administration').'</h1>';
$page['body_id'] = 'theAdminPage';

$template->set_filenames(array('admin' => 'admin.tpl'));

$template->assign(
  array(
    'USERNAME' => $user['username'],
    'ENABLE_SYNCHRONIZATION' => $conf['enable_synchronization'],
    'U_SITE_MANAGER'=> $link_start.'site_manager',
    'U_HISTORY_STAT'=> $link_start.'stats',
    'U_FAQ'=> $link_start.'help',
    'U_SITES'=> $link_start.'remote_site',
    'U_MAINTENANCE'=> $link_start.'maintenance',
    'U_NOTIFICATION_BY_MAIL'=> $link_start.'notification_by_mail',
    'U_CONFIG_GENERAL'=> $link_start.'configuration',
    'U_CONFIG_DISPLAY'=> $conf_link.'default',
    'U_CONFIG_EXTENTS'=> $link_start.'extend_for_templates',
    'U_CONFIG_MENUBAR'=> $link_start.'menubar',
    'U_CONFIG_LANGUAGES' => $link_start.'languages',
    'U_CONFIG_THEMES'=> $link_start.'themes',
    'U_CATEGORIES'=> $link_start.'cat_list',
    'U_MOVE'=> $link_start.'cat_move',
    'U_CAT_OPTIONS'=> $link_start.'cat_options',
    'U_CAT_UPDATE'=> $link_start.'site_update&amp;site=1',
    'U_RATING'=> $link_start.'rating',
    'U_RECENT_SET'=> $link_start.'batch_manager&amp;cat=recent',
    'U_BATCH'=> $link_start.'batch_manager',
    'U_TAGS'=> $link_start.'tags',
    'U_THUMBNAILS'=> $link_start.'thumbnail',
    'U_USERS'=> $link_start.'user_list',
    'U_GROUPS'=> $link_start.'group_list',
    'U_PERMALINKS'=> $link_start.'permalinks',
    'U_RETURN'=> make_index_url(),
    'U_ADMIN'=> PHPWG_ROOT_PATH.'admin.php',
    'U_LOGOUT'=> PHPWG_ROOT_PATH.'index.php?act=logout',
    'U_PLUGINS'=> $link_start.'plugins',
    'U_ADD_PHOTOS' => $link_start.'photos_add',
    'U_CHANGE_THEME' => PHPWG_ROOT_PATH.'admin.php?change_theme=1',
    'U_PENDING_COMMENTS' => $link_start.'comments',
    'U_UPDATES' => $link_start.'updates',
    )
  );

// +-----------------------------------------------------------------------+
// | Plugin menu                                                           |
// +-----------------------------------------------------------------------+

$plugin_menu_links = trigger_event('get_admin_plugin_menu_links', array() );

function UC_name_compare($a, $b)
{
  return strcmp(strtolower($a['NAME']), strtolower($b['NAME']));
}
usort($plugin_menu_links, 'UC_name_compare');
$template->assign('plugin_menu_items', $plugin_menu_links);

// +-----------------------------------------------------------------------+
// | Refresh permissions                                                   |
// +-----------------------------------------------------------------------+

// Only for pages witch change permissions
if (
    in_array($page['page'],
      array(
        'site_manager', // delete site
        'site_update',  // ?only POST
        'cat_list',     // delete cat
        'cat_modify',   // delete cat; public/private; lock/unlock
        'cat_move',     // ?only POST
        'cat_options',  // ?only POST; public/private; lock/unlock
        'cat_perm',     // ?only POST
        'picture_modify', // ?only POST; associate/dissociate
        'user_perm',
        'group_perm',
        'group_list',   // delete group
      )
    )
    or ( !empty($_POST) and in_array($page['page'],
        array(
          'batch_manager',  // associate/dissociate; delete; set level
          'user_list',    // group assoc; user level
        )
      )
    )
  )
{
  invalidate_user_cache();
}

// +-----------------------------------------------------------------------+
// | Include specific page                                                 |
// +-----------------------------------------------------------------------+

trigger_action('loc_begin_admin_page');
include(PHPWG_ROOT_PATH.'admin/'.$page['page'].'.php');

// +-----------------------------------------------------------------------+
// | Errors, Infos & Warnings                                              |
// +-----------------------------------------------------------------------+

$template->assign('ACTIVE_MENU', get_active_menu($page['page']));

if (count($page['errors']) != 0)
{
  $template->assign('errors', $page['errors']);
}

if (count($page['infos']) != 0)
{
  $template->assign('infos', $page['infos']);
}

if (count($page['warnings']) != 0)
{
  $template->assign('warnings', $page['warnings']);
}

// +-----------------------------------------------------------------------+
// | Sending html code                                                     |
// +-----------------------------------------------------------------------+

// Add the Piwigo Official menu
$template->assign( 'pwgmenu', pwg_URL() );

include(PHPWG_ROOT_PATH.'include/page_header.php');

trigger_action('loc_end_admin');

$template->pparse('admin');

include(PHPWG_ROOT_PATH.'include/page_tail.php');
?>
