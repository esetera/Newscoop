<?php

require_once($GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'SystemPref.php');
require_once($GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'TopicName.php');
require_once($GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'Topic.php');

require_once($GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'ArticleType.php');
require_once($GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'ArticleTypeField.php');

/**
 * NewsImport plugin specification.
 */
$info = array(
    'name' => 'newsimport',
    'version' => '0.1.0',
    'label' => 'NewsImport',
    'description' => 'This plugin provides import from news agencies.',
    'menu' => array(
        'name' => 'newsimport',
        'label' => 'NewsImport',
        'icon' => '',
        'permission' => 'plugin_manager',
        'path' => 'newsimport/admin/newsimport_prefs.php',
/*
        'sub' => array(
            array(
                'permission' => 'EditSystem-Preferences',
                'path' => 'newsimport/admin/newsimport_prefs.php',
                'label' => 'Configure',
                'icon' => '',
            ),
        ),
*/
    ),
    'userDefaultConfig' => array(
        'plugin_newsimport' => 'N',
    ),
    'permissions' => array(
    /**
     * Do not remove this comment: it is needed for the localizer
     * getGS('User may manage NewsImport');
     */
    	//'plugin_newsimport_admin' => 'User may manage NewsImport',
        'plugin_manager' => 'User may manage NewsImport',
    ),
    'template_engine' => array(
        'objecttypes' => array(),
        'listobjects' => array(),
        'init' => 'plugin_newsimport_init'
    ),
    'localizer' => array(
        'id' => 'plugin_newsimport',
        'path' => '/plugins/newsimport/*/*/*/*/*',
        'screen_name' => 'NewsImport'
    ),
    'no_menu_scripts' => array(),
    'install' => 'plugin_newsimport_install',
    'enable' => 'plugin_newsimport_enable',
    'update' => 'plugin_newsimport_update',
    'disable' => 'plugin_newsimport_disable',
    'uninstall' => 'plugin_newsimport_uninstall',
    'enabled_by_default' => false,
);

if (!defined('PLUGIN_NEWSIMPORT_FUNCTIONS')) {
    define('PLUGIN_NEWSIMPORT_FUNCTIONS', TRUE);

	/**
     * create && fill data dirs while not fs access is set up
     *
	 * @return void
	 */
    function plugin_newsimport_make_dirs() {
        $newsimport_demo_dir = $GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'newsimport'.DIRECTORY_SEPARATOR.'demo_data';
        $newsimport_data_dir = $GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.'newsimport';

        $data_dirs = array(
            $newsimport_data_dir.DIRECTORY_SEPARATOR.'events',
            $newsimport_data_dir.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.'current',
            $newsimport_data_dir.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.'input',
            $newsimport_data_dir.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.'processed',
            $newsimport_data_dir.DIRECTORY_SEPARATOR.'movies',
            $newsimport_data_dir.DIRECTORY_SEPARATOR.'movies'.DIRECTORY_SEPARATOR.'current',
            $newsimport_data_dir.DIRECTORY_SEPARATOR.'movies'.DIRECTORY_SEPARATOR.'input',
            $newsimport_data_dir.DIRECTORY_SEPARATOR.'movies'.DIRECTORY_SEPARATOR.'processed',
        );

        foreach ($data_dirs as $one_data_dir) {
            if (!is_dir($one_data_dir)) {
                try {
                    mkdir($one_data_dir, 0755, true);
                }
                catch (Exception $ecx) {}
            }
        }

        foreach (array('events', 'movies') as $one_demo_part) {
            if (is_dir($newsimport_demo_dir.DIRECTORY_SEPARATOR.$one_demo_part)) {
                $one_demo_set = glob($newsimport_demo_dir.DIRECTORY_SEPARATOR.$one_demo_part.DIRECTORY_SEPARATOR.'*');
                if (false === $one_demo_set) {
                    continue;
                }
                foreach ($one_demo_set as $event_demo_path) {
                    if (!is_file($event_demo_path)) {
                        continue;
                    }
                    $one_dest_path = $newsimport_data_dir.DIRECTORY_SEPARATOR.$one_demo_part.DIRECTORY_SEPARATOR.'input'.DIRECTORY_SEPARATOR.basename($event_demo_path);
                    if (!is_file($one_dest_path)) {
                        try {
                            copy($event_demo_path, $one_dest_path);
                        }
                        catch (Exception $ecx) {}
                    }
                }
            }
        }
    } // fn plugin_newsimport_make_dirs

	/**
     * puts into sys-prefs info on newscoop url
     *
	 * @return void
	 */
    function plugin_newsimport_copy_conf() {
        $conf_dir = $GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'newsimport'.DIRECTORY_SEPARATOR.'include';
        $feed_conf_path_src = $conf_dir.DIR_SEP.'news_feeds_conf_inst.php';

        $feed_conf_path_dst_dir = $GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'conf'.DIRECTORY_SEPARATOR.'newsimport';
        $feed_conf_path_dst = $feed_conf_path_dst_dir.DIRECTORY_SEPARATOR.'news_feeds_conf.php';

        if (!is_dir($feed_conf_path_dst_dir)) {
            mkdir($feed_conf_path_dst_dir);
        }
        if (!is_file($feed_conf_path_dst)) {
            copy($feed_conf_path_src, $feed_conf_path_dst);
        }

    }

	/**
     * puts into sys-prefs info on newscoop url
     *
	 * @return void
	 */
    function plugin_newsimport_set_url() {

        global $Campsite;
        if (isset($Campsite['system_preferences'])) {
            unset($Campsite['system_preferences']['NewsImportBaseUrl']);
        }
        SystemPref::Set('NewsImportBaseUrl', $Campsite['WEBSITE_URL']);
        SystemPref::Set('NewsImportBaseUrl', $Campsite['WEBSITE_URL']);

/*
        $plugin_inst_name = dirname(__FILE__).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'news_feeds_intall.php';

        global $Campsite;
        $campsite_inst_dir = $Campsite['WEBSITE_URL'];

        $campsite_inst_php = '<?php' . "\n\n" . '$newsipmort_install = \'' . $campsite_inst_dir . '\';' . "\n\n";

        try {
            $plugin_inst_file = fopen($plugin_inst_name, 'w');
            fwrite($plugin_inst_file, $campsite_inst_php);
            fclose($plugin_inst_file);
        }
        catch (Exception $exc) {
            // may be some logging
        }
*/
    } // fn plugin_newsimport_set_url

	/**
     * sets cron job for event data import
     *
	 * @return bool
	 */
    function plugin_newsimport_set_cron($p_state) {
        exec('crontab -l', $cron_output, $cron_result);
        if (0 != $cron_result) {
            return false;
        }

        $request_file = dirname(__FILE__).DIRECTORY_SEPARATOR.'admin-files'.DIRECTORY_SEPARATOR.'newsimport'.DIRECTORY_SEPARATOR.'cron'.DIRECTORY_SEPARATOR.'request_import.php';

        $new_cron = array();
        foreach ($cron_output as $one_cron_line) {
            if (false !== strpos($one_cron_line, $request_file)) {
                continue;
            }
            $new_cron[] = $one_cron_line;
        }
        if ($p_state) {

            $incl_dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR;
            require($incl_dir . 'default_cron.php');

            $cron_min = $newsimport_cron['min'];
            $cron_hour = $newsimport_cron['hour'];

            $new_cron[] = $cron_min . ' ' . $cron_hour . ' * * * ' . $request_file;
        }

        $tmp_file_path = tempnam(sys_get_temp_dir(), '' . mt_rand(100, 999));
        $tmp_file = fopen($tmp_file_path, 'w');
        foreach ($new_cron as $one_cron_line) {
            fwrite($tmp_file, $one_cron_line);
            fwrite($tmp_file, "\n");
        }
        fclose($tmp_file);
        exec('crontab ' . escapeshellarg($tmp_file_path), $cron_output, $cron_result);
        unlink($tmp_file_path);
        if (0 != $cron_result) {
            return false;
        }

        return true;
    } // fn plugin_newsimport_set_cron


	/**
     * create possibly missing article type for events
     *
	 * @return void
	 */
    function plugin_newsimport_create_event_type() {
        $art_type_name = 'event';

        $art_type_obj = new ArticleType($art_type_name);
        if (!$art_type_obj->exists()) {
            $art_type_obj->create();
        }

        $art_fields = array(
            // ids - auxiliary, hidden
            'provider_id' => array('type' => 'numeric', 'params' => array('precision' => 0), 'hidden' => true), // source of the news file
            'event_id' => array('type' => 'numeric', 'params' => array('precision' => 0), 'hidden' => true), // an event at an day from a provider should have unique id
            'tour_id' => array('type' => 'numeric', 'params' => array('precision' => 0), 'hidden' => true), // for grouping of repeated events, e.g. an exhibition available for more days
            'location_id' => array('type' => 'numeric', 'params' => array('precision' => 0), 'hidden' => true), // should be unique per place/provider
            // main event info - free form
            'headline' => array('type' => 'text', 'params' => array(), 'hidden' => false), // even/tour_name (or movie name)
            'organizer' => array('type' => 'text', 'params' => array(), 'hidden' => false), // either tour_organizer (if filled) or location_name (or cinema name)
            // address - free form
            'country' => array('type' => 'text', 'params' => array(), 'hidden' => false), // ch (i.e. Swiss country code)
            'zipcode' => array('type' => 'text', 'params' => array(), 'hidden' => false),
            'town' => array('type' => 'text', 'params' => array(), 'hidden' => false),
            'street' => array('type' => 'text', 'params' => array(), 'hidden' => false), // street address, including house number
            // date/time - fixed form
            'date' => array('type' => 'date', 'params' => array(), 'hidden' => false), // text, 2010-08-31
            'date_year' => array('type' => 'numeric', 'params' => array('precision' => 0), 'hidden' => false), // number, 2010
            'date_month' => array('type' => 'numeric', 'params' => array('precision' => 0), 'hidden' => false), // number, 8
            'date_day' => array('type' => 'numeric', 'params' => array('precision' => 0), 'hidden' => false), // number, 31
            'time' => array('type' => 'text', 'params' => array(), 'hidden' => false), // event_time, like 10:30 (or a list for movie screenings at a day)
            // date/time - free form
            'date_time_text' => array('type' => 'body', 'params' => array('editor_size' => 250, 'is_content' => 1), 'hidden' => false), // comprises other textual date/time information, if available
            // contact - free form
            'web' => array('type' => 'text', 'params' => array(), 'hidden' => false), // location_url if filled, or event/tour_link if some there
            'email' => array('type' => 'text', 'params' => array(), 'hidden' => false),
            'phone' => array('type' => 'text', 'params' => array(), 'hidden' => false),
            // text parts - free form
            'description' => array('type' => 'body', 'params' => array('editor_size' => 250, 'is_content' => 1), 'hidden' => false), // a (longer) text, if some available
            'other' => array('type' => 'body', 'params' => array('editor_size' => 250, 'is_content' => 1), 'hidden' => false), // other texts, web links to audio/video, ...
            // other details - free form
            'genre' => array('type' => 'text', 'params' => array(), 'hidden' => false), // Sonderausstellung/Dauerausstellung; Jazz, Festival, ... (or movie genre)
            'languages' => array('type' => 'text', 'params' => array(), 'hidden' => false), // usually empty
            'prices' => array('type' => 'body', 'params' => array('editor_size' => 250, 'is_content' => 1), 'hidden' => false), // some textual or numerical info, if available
            'minimal_age' => array('type' => 'text', 'params' => array(), 'hidden' => false), // textual or numerical info, if any, but usually empty
            // other details - fixed form
            'canceled' => array('type' => 'switch', 'params' => array(), 'hidden' => false), // if event was canceled
            'rated' => array('type' => 'switch', 'params' => array(), 'hidden' => false), // if of some restricted (hot/explicit) kind
            'edited' => array('type' => 'switch', 'params' => array(), 'hidden' => false), // whether to disable auto-overwriting
            // category available as article topic
            // images as article images
            // geolocation as map POIs
        );

        foreach ($art_fields as $one_field_name => $one_field_params) {
            $art_type_filed_obj = new ArticleTypeField($art_type_name, $one_field_name);
            if (!$art_type_filed_obj->exists()) {
                $art_type_filed_obj->create($one_field_params['type'], $one_field_params['params']);
            }
            if (array_key_exists('hidden', $one_field_params) && $one_field_params['hidden']) {
                $art_type_filed_obj->setStatus('hide');
            }
        }
    } // fn plugin_newsimport_create_event_type

	/**
     * sets import command token
     *
	 * @return void
	 */
    function plugin_newsimport_set_preferences() {
        $incl_dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR;
        require($incl_dir . 'default_access.php');

        // shall be put into db just on explicit requests
        //$cur_nimp_auth = SystemPref::Get('NewsImportCommandToken');
        //if (empty($cur_nimp_auth)) {
        //    SystemPref::Set('NewsImportCommandToken', $newsimport_default_access);
        //}
    } // fn plugin_newsimport_set_preferences

	/**
     * create possibly missing article topic for events
     *
	 * @return array
	 */
    function plugin_newsimport_set_one_topic($p_topicCat, $p_topicNames, $p_parentIds) {
        // setting the given event topic
        $ev_this_ids = array();
        $ev_this_names = array();

        foreach ($p_topicNames as $cat_lan_id => $cat_name) {
            $cat_key_sys_pref = 'EventCat' . $cat_lan_id . ucfirst($p_topicCat);
            $cat_name_sys_pref = SystemPref::Get($cat_key_sys_pref);
            if (!empty($cat_name_sys_pref)) {
                $cat_name = $cat_name_sys_pref;
            }
            $topic_name_obj = new TopicName($cat_name, $cat_lan_id);

            if ($topic_name_obj->m_exists) {
                // found something
                $one_ev_id = $topic_name_obj->getTopicId();
                $ev_this_ids[$cat_lan_id] = $one_ev_id;
            }
            else {
                $ev_this_names[$cat_lan_id] = $cat_name;
            }
        }

        if (!empty($ev_this_names)) {
            // some topic names do not exist

            if (!empty($ev_this_ids)) {
                // we can just translate topic names (e.g. from the first topic name)
                $use_topic_id = null;
                foreach ($ev_this_ids as $cat_lan_id => $the_topic_id) {
                    $use_topic_id = $the_topic_id;
                    break;
                }

                $topic_obj = new Topic($use_topic_id);
                $the_topic_id = $topic_obj->getTopicId();
                foreach ($ev_this_names as $one_cat_lang => $one_cat_name) {
                    $topic_obj->setName($one_cat_lang, $one_cat_name);
                    $ev_this_ids[$cat_lan_id] = $the_topic_id;
                }
            }
            else {
                // we have to create new topics
                if (!empty($p_parentIds)) {
                    // create child topic(s), group by parent ids
                    $parent_id_groups = array();
                    foreach ($p_parentIds as $par_lang => $par_id) {
                        if (!array_key_exists($par_id, $parent_id_groups)) {
                            $parent_id_groups[$par_id] = array();
                        }
                        $parent_id_groups[$par_id][$par_lang] = $ev_this_names[$par_lang];
                    }

                    foreach($parent_id_groups as $par_id => $ev_cat_names) {
                        $topic_obj = new Topic();
                        $topic_obj->create(array('parent_id' => $par_id, 'names' => $ev_cat_names));
                        $the_topic_id = $topic_obj->getTopicId();
                        foreach($ev_cat_names as $cat_lan_id => $cat_name) {
                            $ev_this_ids[$cat_lan_id] = $the_topic_id;
                        }
                    }
                }
                else {
                    // create one root topic
                    $topic_obj = new Topic();
                    $topic_obj->create(array('names' => $ev_this_names));

                    $the_topic_id = $topic_obj->getTopicId();
                    foreach($ev_this_names as $cat_lan_id => $cat_name) {
                        $ev_this_ids[$cat_lan_id] = $the_topic_id;
                    }

                }
            }
        }

        return $ev_this_ids;
    } // fn plugin_newsimport_set_one_topic

	/**
     * create possibly missing article topics for events
     *
	 * @return void
	 */
    function plugin_newsimport_set_event_topics() {
        $incl_dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR;
        require($incl_dir . 'default_topics.php');

        // setting the root event topic
        $ev_root_id = null;

        $event_root_names = $newsimport_default_cat_names['event'];
        $ev_root_ids = plugin_newsimport_set_one_topic('event', $event_root_names, null);

        if (empty($ev_root_ids)) {
            // this shall not happen: either already having a root topic, or created one
            return false;
        }

        // setting the particular (non-root) event topics
        foreach ($newsimport_default_cat_names as $topic_cat_key => $topic_cat_names) {
            if ('event' == $topic_cat_key) {
                continue;
            }
            plugin_newsimport_set_one_topic($topic_cat_key, $topic_cat_names, $ev_root_ids);

        }
    } // fn plugin_newsimport_set_event_topics

	/**
     * plugin installation
     *
	 * @return void
	 */
    function plugin_newsimport_install()
    {
/*
        plugin_newsimport_copy_conf();
        plugin_newsimport_set_preferences();
        plugin_newsimport_set_event_topics();
        plugin_newsimport_create_event_type();

        global $Campsite;
        if (isset($Campsite['system_preferences'])) {
            unset($Campsite['system_preferences']['NewsImportUsage']);
        }
        SystemPref::Set('NewsImportUsage', '1');
        SystemPref::Set('NewsImportUsage', '1');

        plugin_newsimport_set_cron(true);
        plugin_newsimport_set_url();
        plugin_newsimport_make_dirs();
*/
    } // fn plugin_newsimport_install

	/**
     * plugin enabling
     *
	 * @return void
	 */
    function plugin_newsimport_enable()
    {
        // this is called wrongly during newscoop install
        if (!function_exists('getGS')) {
            return false;
        }

        set_time_limit(0);

        plugin_newsimport_copy_conf();
        plugin_newsimport_set_preferences();
        plugin_newsimport_set_event_topics();
        plugin_newsimport_create_event_type();

        global $Campsite;
        if (isset($Campsite['system_preferences'])) {
            unset($Campsite['system_preferences']['NewsImportUsage']);
        }
        SystemPref::Set('NewsImportUsage', '1');
        SystemPref::Set('NewsImportUsage', '1');

        plugin_newsimport_set_cron(true);
        plugin_newsimport_set_url();
        plugin_newsimport_make_dirs();
    } // fn plugin_newsimport_enable

	/**
     * plugin disabling
     *
	 * @return void
	 */
    function plugin_newsimport_disable()
    {

        global $Campsite;
        if (isset($Campsite['system_preferences'])) {
            unset($Campsite['system_preferences']['NewsImportUsage']);
        }
        SystemPref::Set('NewsImportUsage', '0');
        SystemPref::Set('NewsImportUsage', '0');

        plugin_newsimport_set_cron(false);
    } // fn plugin_newsimport_disable

	/**
     * plugin enabling
     *
	 * @return void
	 */
    function plugin_newsimport_uninstall()
    {

        global $Campsite;
        if (isset($Campsite['system_preferences'])) {
            unset($Campsite['system_preferences']['NewsImportUsage']);
        }
        SystemPref::Set('NewsImportUsage', '0');
        SystemPref::Set('NewsImportUsage', '0');

        plugin_newsimport_set_cron(false);
    } // fn plugin_newsimport_uninstall

	/**
     * plugin updating
     *
	 * @return void
	 */
    function plugin_newsimport_update()
    {
    } // fn plugin_newsimport_update

	/**
     * plugin template init
     *
	 * @return void
	 */
    function plugin_newsimport_init(&$p_context)
    {
    } // fn plugin_newsimport_init

	/**
     * plugin permissions
     *
	 * @return void
	 */
    function plugin_newsimport_addPermissions()
    {
        //$Admin = new UserType(1);
        //$Admin->setPermission('plugin_newsimport_admin', true);
    } // fn plugin_newsimport_addPermissions

	/**
     * test whether to do, and calls (if shall) the event import
     *
	 * @return void
	 */
    function plugin_newsimport_test()
    {
        // is this a news import request?
        $news_import_active = SystemPref::Get('NewsImportUsage');
        if (!empty($news_import_active)) {
            $news_imp_file_name = $GLOBALS['g_campsiteDir'].DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'newsimport'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'NewsImport.php';
            if (file_exists($news_imp_file_name)) {
                require_once($news_imp_file_name);
                $news_import_only = false;
                NewsImport::ProcessImport($news_import_only);
                if ($news_import_only) {
                    exit(0);
                }
            }
        }
    } // fn plugin_newsimport_test

}

// NB: this is recognizing whether the request is on events import
// this file is included during page loading, thus can be done this way
// if it would change, we would need to put it into LegacyController
plugin_newsimport_test();