<?php
/**
 * My Adult Link Url Shortener
 * 
 * PHP Version 5+
 * 
 * @category MyBB
 * @package  Myadultlink
 * @author   JoblessboiOS <info@serverslab.net>
 * @license  https://creativecommons.org/licenses/by-nc/4.0/ CC BY-NC 4.0
 * @link     http://serverslab.net
 */

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

if (defined('IN_ADMINCP')) {
    $plugins->add_hook('admin_config_plugins_deactivate_commit', 'Myadultlink_destroy');
    $plugins->add_hook('admin_config_settings_begin', 'Myadultlink_lang');
} else {
    $plugins->add_hook('parse_message', 'Myadultlink_parse');
    $plugins->add_hook('showthread_start', 'Myadultlink_thread');
}

/**
 * Return plugin info
 *
 * @return array
 */
function Myadultlink_info()
{
    global $mybb, $lang;
    
	Myadultlink_lang();
	
    $destroy = <<<EOT
<p>
    <a href="index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;destroy=1&amp;plugin=Myadultlink&amp;my_post_key={$mybb->post_code}" style="color: red; font-weight: bold">{$lang->adultlink_destroy}</a>
</p>
EOT;

    return [
        'name'          => $lang->adultlink_title,
        'description'   => $lang->adultlink_desc.$destroy,
        'website'       => $lang->adultlink_url,
        'author'        => 'JoblessboiOS',
        'authorsite'    => $lang->adultlink_JoblessboiOS,
        'version'       => '1.0',
        'compatibility' => '*',
        'codename'      => 'Myadultlink',
    ];
}

/**
 * Install the plugin
 *
 * @return void
 */
function Myadultlink_install()
{
    global $db, $lang;
    
    $collation = $db->build_create_table_collation();
    
    if (!$db->table_exists('adultlink')) {
        $db->write_query(
            "CREATE TABLE ".TABLE_PREFIX."adultlink (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `tid` int(10) unsigned NOT NULL,
                `from` varchar(255) NOT NULL default '',
                `to` varchar(255) NOT NULL default '',
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM{$collation};"
        );
    }
    
    Myadultlink_deleteSettings();
    
    $group = [
        'name'        => 'myadultlink',
        'title'       => $db->escape_string($lang->setting_group_myadultlink),
        'description' => $db->escape_string($lang->setting_group_myadultlink_desc),
    ];
    $gid = $db->insert_query('settinggroups', $group);
    
    $settings = [
        'adultlink_id'     => [
            'title'       => $db->escape_string($lang->setting_adultlink_id),
            'description' => $db->escape_string($lang->setting_adultlink_id_desc),
            'value'       => '',
            'optionscode' => 'numeric',
        ],
        'adultlink_api'    => [
            'title'       => $db->escape_string($lang->setting_adultlink_api),
            'description' => $db->escape_string($lang->setting_adultlink_api_desc),
            'value'       => '',
            'optionscode' => 'text',
        ],
        'adultlink_groups' => [
            'title'       => $db->escape_string($lang->setting_adultlink_groups),
            'description' => $db->escape_string($lang->setting_adultlink_groups_desc),
            'value'       => '',
            'optionscode' => 'groupselect',
        ]
    ];
    
    foreach ($settings as $key => $setting) {
        $setting['name'] = $key;
        $setting['gid']  = $gid;
        
        $db->insert_query('settings', $setting);
    }
    rebuild_settings();
}

/**
 * Check if plugin is installed
 *
 * @return bool
 */
function Myadultlink_is_installed()
{
    global $db, $mybb;
    
    if (isset($mybb->settings['adultlink_id'])
        && isset($mybb->settings['adultlink_api'])
        && isset($mybb->settings['adultlink_groups'])
        && $db->table_exists('adultlink')
    ) {
        return true;
    }
    
    return false;
}

/**
 * Uninstall the plugin
 *
 * @return void
 */
function Myadultlink_uninstall()
{
    global $db;
    
    Myadultlink_deleteSettings();
    rebuild_settings();
    
    if ($db->table_exists('adultlink')) {
        $db->drop_table('adultlink');
    }
}

/**
 * Delete all files of the plugin
 *
 * @return void
 */
function Myadultlink_destroy()
{
    global $mybb, $message, $lang;
    
    if ($mybb->input['destroy'] == 1) {
		Myadultlink_lang();
        // extra files and dirs to remove
        $extra_files = [
            'inc/languages/english/admin/Myadultlink.lang.php',
        ];
        
        foreach ($extra_files as $file) {
            if (!file_exists(MYBB_ROOT.$file) || is_dir(MYBB_ROOT.$file)) {
                continue;
            }
            
            unlink(MYBB_ROOT.$file);
        }
        unlink(__FILE__);
        
        $message = $lang->adultlink_destroyed;
    }
}

/**
 * Delete setting group and settings
 *
 * @return void
 */
function Myadultlink_deleteSettings()
{
    global $db;
    
    $db->delete_query(
        'settings',
        "name IN ('adultlink_id', 'adultlink_api', 'adultlink_groups')"
    );
    $db->delete_query('settinggroups', "name = 'myadultlink'");
}

/**
 * Load adultlink links of the current thread
 *
 * @return void
 */
function Myadultlink_thread()
{
    global $db, $tid;
    
    $query = $db->simple_select('adultlink', '*', "tid = {$tid}");
    $links = [];
    if ($db->num_rows($query) > 0) {
        while ($link = $db->fetch_array($query)) {
            $links[$link['from']] = $link['to'];
        }
    }
    
    $GLOBALS['links'] = $links;
}

/**
 * Load afly links of the current thread
 *
 * @param string $message The post message
 *
 * @return void
 */
function Myadultlink_parse(&$message)
{
	global $mybb;
    $tid = $GLOBALS['tid'];
	
    if (isset($tid) && is_member($mybb->settings['adultlink_groups'])) {
        $message = preg_replace_callback(
            "#\<a(.*?)href=\"(.*?)\"(.*?)\>#is",
            create_function('$matches', 'return Myadultlink_parseUrl($matches);'),
            $message
        );
    }
    
    return $message;
}

/**
 * Change url tags
 *
 * @param string $matches The tag url
 *
 * @return string $tag
 */
function Myadultlink_parseUrl($matches)
{
    $links = $GLOBALS['links'];
    
    if (isset($links[$matches[2]])) {
        $url = $links[$matches[2]];
    } else {
        $url = Myadultlink_getUrl($matches[2]);
    }
    
    $tag = str_replace($matches[2], $url, $matches[0]);
    return $tag;
}

/**
 * Get a new adultlink url
 *
 * @param string $url The normal url
 *
 * @return string $link The generated url
 */
function Myadultlink_getUrl($url)
{
    $mybb = $GLOBALS['mybb'];
    $api = 'http://api.adult.xyz/api.php?';
    $query = [
        'key' => $mybb->settings['adultlink_api'],
        'uid' => $mybb->settings['adultlink_id'],
        'advert_type' => 'int',
        'domain' => 'adult.xyz',
        'url' => $url,
    ];
    $api .= http_build_query($query);
	
    $link = '';
    if (!empty($query['key']) && !empty($query['uid'])) {
        if ($data = file_get_contents($api)) {
            $link = $data;

            Myadultlink_saveUrl($url, $link);
        }
    }
	
    if (empty($link) && !empty($query['uid'])) {
        $link = 'http://adult.xyz/'.$query['uid'].'/'.$url;
    } elseif (empty($link)) {
        $link = $url;
    }
	
    return $link;
}

/**
 * Save generated adultlink url in the database
 *
 * @param string $from The normal url
 * @param string $to   The generated adultlink url
 *
 * @return void
 */
function Myadultlink_saveUrl($from, $to)
{
    $db = $GLOBALS['db'];
    
    $data = [
        'tid'  => $GLOBALS['tid'],
        'from' => $from,
        'to'   => $to,
    ];
    
    $db->insert_query('adultlink', $data);
}

/**
 * Load the lang
 *
 * @return object $lang
 */
function Myadultlink_lang()
{
    global $lang;
    
    $lang->load('Myadultlink');
    
    return $lang;
}
