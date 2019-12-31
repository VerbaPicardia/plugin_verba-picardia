<?php

/**
 * Plugin Name: VerbaPicardia
 * Plugin URI: /
 * Description: VerbaPicardia
 * Version: 0.0
 * Author: fz, gd
 * Author URI: keine
 * Text Domain: verba-alpina
 * License: CC BY-SA 4.0
 */

define('VA_PLUGIN_URL', plugins_url('', __FILE__));
define('VA_PLUGIN_PATH', plugin_dir_path(__FILE__));

define('VA_TEXT_DOMAIN', 'verba-alpina');

define('VA_CONTENT_MENU_NAME', 'Verba');
define('VA_CONTENT_MENU_HOOK', 'verba');
define('VA_CONTENT_MENU_SLUG', 'va_content');
define('VA_TOOLS_MENU_NAME', 'Verba Tools');
define('VA_TOOLS_MENU_SLUG', 'va_tools');
define('TYPIFICATION_MENU_SLUG', 'typification');
define('CONCEPT_TREE_MENU_SLUG', 'concept-tree');

define('IM_INITIALIZER_CLASS_NAME', "IM_Initializer");
define('TRANSCRIPTION_TOOL_CLASS_NAME', 'TranscriptionTool');

define('VA_DB_PREFIX', 'va_');

define('JSTREE_SCRIPT', 'jsTreeScript');

global $login_data;
$login_data = file(plugin_dir_path(__FILE__) . 'login', FILE_IGNORE_NEW_LINES);

global $admin;
$admin = false;

register_activation_hook(__FILE__, 'va_install');
function va_install() {
    va_register_admin_capabilities();
}

function va_register_admin_capabilities() {
    global $wp_roles;
    $administrator = $wp_roles->role_objects ['administrator'];
    $administrator->add_cap('va_tokenization');
    $administrator->add_cap('va_glossary');
    $administrator->add_cap('verba_alpina');
    $administrator->add_cap('va_typification_tool_read');
    $administrator->add_cap('va_typification_tool_write');
    $administrator->add_cap('va_concept_tree_read');
    $administrator->add_cap('va_concept_tree_write');
}

if($login_data !== false) {

    add_action('init', 'va_init', 11);
    function va_init() {
        global $lang;
        global $Ue;
        global $va_xxx;
        global $vadb;

        va_setup_db_access();

        if (class_exists(IM_INITIALIZER_CLASS_NAME)) {
            if (isset($_POST ['action']) && $_POST ['action'] == 'im_a'
                    && isset($_POST ['namespace'])
                    && ($_POST ['namespace'] == 'save_syn_map'
                            || $_POST ['namespace'] == 'load_syn_map')) {
                // Always get the synoptic map outline data from the xxx version:
                IM_Initializer::$instance->database = $va_xxx;
            } else {
                IM_Initializer::$instance->database = $vadb;
            }
        }

        load_plugin_textdomain(VA_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
        $lang = va_get_language();
        $Ue = va_get_translations($lang);

        _include_libraries();
        _include_features();
    }

    function _include_libraries() {
        require 'lib/html5-dom-document/autoload.php';
        include_once ('util/tools.php');
        include_once ('util/ajax/va_ajax.php');
        include_once ('util/parseGlossarSyntax.php');
        include_once ('util/va_beta_parser.php');
    }

    function _include_features() {
        if (class_exists(TRANSCRIPTION_TOOL_CLASS_NAME)) {
            va_init_transcription_tool();
        }
        include_once ('backend/concept_tree.php');
        include_once ('backend/typification/main.php');
        include_once ('backend/ipa.php');
        include_once ('backend/tokenization.php');
        include_once ('backend/fix_tokens.php');
        include_once ('backend/build_tables.php');
        include_once ('backend/overview.php');
    }

    add_action('admin_menu', 'va_menus');
    function va_menus() {
        add_menu_page(VA_CONTENT_MENU_NAME, VA_CONTENT_MENU_NAME, 'va_glossary', VA_CONTENT_MENU_SLUG, function() {});
        if (class_exists(TRANSCRIPTION_TOOL_CLASS_NAME)) {
            TranscriptionTool::create_menu(VA_CONTENT_MENU_SLUG);
        }
        add_submenu_page(VA_CONTENT_MENU_SLUG, "Concept Tree", "Concept Tree", 'va_concept_tree_read', CONCEPT_TREE_MENU_SLUG, 'konzeptbaum');
        add_submenu_page(VA_CONTENT_MENU_SLUG, __('Typification', VA_TEXT_DOMAIN), __('Typification', VA_TEXT_DOMAIN), 'va_typification_tool_read', TYPIFICATION_MENU_SLUG, 'lex_typification');
        add_submenu_page(VA_CONTENT_MENU_SLUG, 'Base Types', 'Base Types', 'va_typification_tool_read', 'base_types', 'va_edit_base_type_page');

        add_menu_page(VA_TOOLS_MENU_NAME, VA_TOOLS_MENU_NAME, 'verba_alpina', VA_TOOLS_MENU_SLUG);
        add_submenu_page(VA_TOOLS_MENU_SLUG, 'Overview', 'Overview', 'verba_alpina', 'va_tools', 'overview_page');
        add_submenu_page(VA_TOOLS_MENU_SLUG, 'Tokenization', 'Tokenization', 'va_tokenization', 'va_tools_tok', 'va_create_tokenizer_page');
        add_submenu_page(VA_TOOLS_MENU_SLUG, 'Beta -> Original', 'Beta -> Original', 'verba_alpina', 'fix_tokens', 'fix_tokens');
        add_submenu_page(VA_TOOLS_MENU_SLUG, 'Beta -> IPA', 'Beta -> IPA', 'verba_alpina', 'ipa', 'ipa_page');
        add_submenu_page(VA_TOOLS_MENU_SLUG, 'Build Tables', 'Build Tables', 'verba_alpina', 'build_tables', 'frontend_build_tables');
    }

    add_action('set_current_user', 'va_set_admin_va_mitarbeiter');
    function va_set_admin_va_mitarbeiter() {
        global $admin;
        global $va_mitarbeiter;

        $current_user = wp_get_current_user();
        $roles = $current_user->roles;
        $admin = in_array("administrator", $roles);
        $va_mitarbeiter = in_array('projektmitarbeiter', $roles);
    }

    if (class_exists(IM_INITIALIZER_CLASS_NAME)) {
        add_action('im_plugin_files_ready', 'va_load_im_config_files');
        function va_load_im_config_files() {
            IM_Initializer::$instance->map_function = 'create_va_map';
            IM_Initializer::$instance->load_function = 'load_va_data';
            IM_Initializer::$instance->edit_function = 'edit_va_data';
            IM_Initializer::$instance->info_window_function = 'va_load_info_window';
            IM_Initializer::$instance->search_location_function = 'search_va_locations';
            IM_Initializer::$instance->get_location_function = 'get_va_location';
            IM_Initializer::$instance->global_search_function = 'va_ling_search';
        }

        add_filter('im_default_map_type', function ($type) {
            return 'pixi';
        });

        add_action('im_define_main_file_constants', 'va_map_plugin_version');
        function va_map_plugin_version() {
            define('IM_MAIN_PHP_FILE', dirname(__FILE__) . '/im_config/live/im_live.phar');
            define('IM_MAIN_JS_FILE', plugin_dir_url(__FILE__) . 'im_config/live/im_live.js');
            define('IM_MAIN_CSS_FILE', plugin_dir_url(__FILE__) . 'im_config/live/im_live.css');
        }
    }

    add_filter('show_admin_bar', 'va_show_admin_bar');
    function va_show_admin_bar() {
        return false;
    }

    add_filter('page_link', 'va_enforce_page_link_db_query_arg', 10, 2);
    function va_enforce_page_link_db_query_arg($url, $post) {
        if ($post) {
            if (isset($_GET ['db'])) {
                $url = add_query_arg('db', $_GET ['db'], $url);
            } else {
                global $va_current_db_name;
                $url = add_query_arg('db', substr($va_current_db_name, 3), $url);
            }
        }
        return $url;
    }

    add_filter('logout_url', 'va_redirect_to_latest_stable');
    function va_redirect_to_latest_stable($url) {
        if (isset($_GET ['db']) && $_GET ['db'] == 'xxx') {
            global $va_xxx;
            $rurl = urlencode(_url_protocol() . _url_host() . add_query_arg('db', $va_xxx->get_var('SELECT MAX(Nummer) FROM Versionen'), _uri_request()));
        } else {
            $rurl = urlencode(_url_protocol() . _url_host() . _uri_request());
        }

        return add_query_arg('redirect_to', $rurl, $url);
    }

    function _url_protocol() {
        return is_ssl() ? 'https://' : 'http://';
    }

    function _url_host() {
        return $_SERVER ['HTTP_HOST'];
    }

    function _uri_request() {
        return $_SERVER ['REQUEST_URI'];
    }

    add_filter('login_url', 'va_redirect_to_current');
    function va_redirect_to_current($url) {
        $rurl = urlencode(_url_protocol() . _url_host() . add_query_arg('db', 'xxx', $_SERVER ['REQUEST_URI']));
        return add_query_arg('redirect_to', $rurl, $url);
    }

    add_action('wp_enqueue_scripts', 'va_scripts_fe');
    function va_scripts_fe() {
        wp_enqueue_script('cookieConsent', VA_PLUGIN_URL . '/lib/cookieconsent.min.js');
        _enqueue_tools(false);

        wp_enqueue_style('va_map_style', plugins_url('/im_config/va_map.css', __FILE__));
        _enqueue_jstree();

        if (class_exists(IM_INITIALIZER_CLASS_NAME)) {
            IM_Initializer::$instance->enqueue_font_awesome();
            IM_Initializer::$instance->enqueue_qtips();
            IM_Initializer::$instance->enqueue_select2_library();
        }
    }

    function _enqueue_tools($for_backend) {
        $script_name = 'toolsSkript';
        wp_enqueue_script($script_name, plugins_url('/util/tools.js', __FILE__));
        if($for_backend) {
            $ajax_object = _build_backend_ajax_object();
        } else {
            $ajax_object = _build_frontend_ajax_object();
        }
        wp_localize_script($script_name, 'ajax_object', $ajax_object);
    }

    function _build_backend_ajax_object() {
        return array ('ajaxurl' => admin_url('admin-ajax.php'));
    }

    function _build_frontend_ajax_object() {
        global $post;
        global $admin;
        global $va_mitarbeiter;
        global $va_current_db_name;
        global $va_next_db_name;

        $ajax_url = admin_url('admin-ajax.php');
        if (isset($_GET ['dev'])) {
            $ajax_url = add_query_arg('dev', 'true', $ajax_url);
        }

        $ajax_url = add_query_arg('db', substr($va_current_db_name, 3), $ajax_url);

        $ajax_object = array (
                'ajaxurl' => $ajax_url,
                'site_url' => get_site_url(1),
                'plugin_url' => VA_PLUGIN_URL,
                'db' => substr($va_current_db_name, 3),
                'va_staff' => $admin || $va_mitarbeiter,
                'dev' => '0',
                'user' => wp_get_current_user()->user_login,
                'next_version' => $va_next_db_name
        );

        if (isset($post)) {
            $ajax_object ['page_title'] = $post->post_title;
        }

        return $ajax_object;
    }

    function _enqueue_jstree() {
        wp_enqueue_style('jsTreeStyle', VA_PLUGIN_URL . '/lib/jstree/dist/themes/default/style.min.css');
        wp_enqueue_script(JSTREE_SCRIPT, VA_PLUGIN_URL . '/lib/jstree/dist/jstree.min.js', array ('jquery'));
    }

    add_action('admin_enqueue_scripts', 'va_scripts_be');
    function va_scripts_be($hook) {
        wp_enqueue_style('va_style', plugins_url('/css/styles.css', __FILE__));

        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('history.js', VA_PLUGIN_URL . '/lib/history.js/scripts/bundled/html5/jquery.history.js');
        wp_enqueue_script('grammarScript', VA_PLUGIN_URL . '/lib/peg-0.10.0.min.js');
        wp_enqueue_script('tablesorter.js', VA_PLUGIN_URL . '/lib/jquery.tablesorter.min.js');
        _enqueue_jstree();

        if (class_exists(IM_INITIALIZER_CLASS_NAME)) {
            IM_Initializer::$instance->enqueue_qtips();
            IM_Initializer::$instance->enqueue_chosen_library();
            IM_Initializer::$instance->enqueue_select2_library();
            IM_Initializer::$instance->enqueue_gui_elements();
        }

        _enqueue_tools(true);
        wp_enqueue_script('typifiy_script', plugins_url('/backend/typification/util.js', __FILE__));

        if ($hook === _va_page_hook(TYPIFICATION_MENU_SLUG)) {
            wp_enqueue_script('lex_typifiy_script', plugins_url('/backend/typification/lex.js', __FILE__));
        }

        if ($hook === _va_page_hook(CONCEPT_TREE_MENU_SLUG)) {
            global $va_xxx;
            $parents = $va_xxx->get_results('SELECT Id_Kategorie, Id_Ueberkategorie FROM Konzepte_Kategorien WHERE Id_Ueberkategorie IS NOT NULL', ARRAY_N);
            wp_localize_script(JSTREE_SCRIPT, 'PARENTS', va_two_dim_to_assoc($parents));
        }
    }

    function _va_page_hook($menu_slug) {
        return VA_CONTENT_MENU_HOOK . '_page_' . $menu_slug;
    }

    function va_get_language() {
        switch (get_locale()) {
            case 'fr_FR' :
                return 'F';
            case 'it_IT' :
                return 'I';
            case 'de_DE' :
                return 'D';
            case 'sl_SI' :
                return 'S';
            case 'rg_CH' :
                return 'R';
            case 'en_UK' :
            case 'en_US' :
                return 'E';
            default :
                return 'D';
        }
    }

    function va_get_translations($lang) {
        global $va_xxx;

        $transl = 'Begriff_' . $lang;

        $res = $va_xxx->get_results("SELECT Schluessel, IF($transl = '', CONCAT(Begriff_D, '(!!!)'), $transl) FROM Uebersetzungen", ARRAY_N);

        $Ue = array ();
        foreach ( $res as $r ) {
            $Ue [$r [0]] = $r [1];
        }
        return $Ue;
    }
}

function va_setup_db_access() {
    global $login_data;
    $dbuser = $login_data [0];
    $dbpassw = $login_data [1];
    $dbhost = $login_data [2];

    global $va_work_db_name;
    $va_work_db_name = _va_work_db_name();

    global $va_xxx;
    // Va_xxx data base, used for all queries that have to be placed in the current working version
    $va_xxx = new wpdb($dbuser, $dbpassw, $va_work_db_name, $dbhost);
    $va_xxx->show_errors();

    global $va_current_db_name;
    global $va_next_db_name;

    $max_version = $va_xxx->get_var('SELECT MAX(Nummer) FROM Versionen');
    $va_next_db_name = va_increase_version($max_version);

    if (is_user_logged_in()) {
        $va_current_db_name = $va_work_db_name;
    } else {
        $va_current_db_name = _va_stable_db_name($max_version);
    }
    if (isset($_GET ['db'])) {
        $va_current_db_name = _va_stable_db_name($_GET ['db']);
    }

    if (isset($_GET ['page_id']) && ! isset($_GET ['db'])) {
        $version_number = _va_version_number_from_db_name($va_current_db_name);
        header('Location: ' . add_query_arg('db', $version_number, get_permalink()));
        exit();
    }

    global $vadb;
    // Data base for general frontend query (in general readonly except it is va_xxx)
    $vadb = new wpdb($dbuser, $dbpassw, $va_current_db_name, $dbhost);
    $vadb->show_errors();
}

function va_increase_version($old) {
    if (substr($old, - 1) == '2') {
        return (intval(substr($old, 0, - 1)) + 1) . '1';
    } else {
        return substr($old, 0, - 1) . '2';
    }
}

function _va_work_db_name() {
    return VA_DB_PREFIX . 'xxx';
}

function _va_stable_db_name($version_number) {
    return VA_DB_PREFIX . $version_number;
}

function _va_version_number_from_db_name($db_name) {
    return substr($db_name, strlen(VA_DB_PREFIX));
}

function va_init_transcription_tool() {
    global $va_xxx;

    $mappings = [ ];

    $mappings ['codepage_original'] = new TableMapping('tcodepage_original');
    $mappings ['transcription_rules'] = new TableMapping('trules');
    $mappings ['stimuli'] = new TableMapping('tstimuli');
    $mappings ['informants'] = new TableMapping('tinformants');
    $mappings ['locks'] = new TableMapping('locks', [
            'Context' => 'Tabelle',
            'Value' => 'Wert',
            'Locked_By' => 'Gesperrt_Von',
            'Time' => 'Zeit'
    ]);
    $mappings ['c_attestation_concept'] = new TableMapping('VTBL_Aeusserung_Konzept', [
            'Id_Concept' => 'Id_Konzept',
            'Id_Attestation' => 'Id_Aeusserung'
    ]);
    $mappings ['attestations'] = new TableMapping('Aeusserungen', [
            'Id_Attestation' => 'Id_Aeusserung',
            'Attestation' => 'Aeusserung',
            'Transcribed_By' => 'Erfasst_Von',
            'Created' => 'Erfasst_Am',
            'Classification' => 'Klassifizierung',
            'Tokenized' => 'Tokenisiert'
    ], [
            'Classification' => [
                    'A' => 'B'
            ]
    ]);

    TranscriptionTool::add_special_val_button('vacat', 'vacat', __('Adds a marker to the data base that there are no attestations for this informant.', VA_TEXT_DOMAIN));
    TranscriptionTool::init('/dokumente/scans/', $va_xxx, $va_xxx->get_col('SELECT DISTINCT Erhebung FROM Stimuli JOIN Bibliographie ON Abkuerzung = Erhebung WHERE VA_Beta'), $va_xxx->get_results("SELECT Id_Konzept AS id, IF(Name_D != '', Name_D, Beschreibung_D) as text FROM Konzepte ORDER BY Text ASC", ARRAY_A), $mappings);
}

function va_produce_external_map_link($atlas, $map, $num, $informant) {
    $attributes = ' style="text-decoration: underline;" target="_BLANK" ';

    if ($atlas == 'AIS') {
        if ($num == '1') {
            $link = 'http://www3.pd.istc.cnr.it/navigais-web/?map=' . $map . '&point=' . $informant;
        } else {
            $link = 'http://www3.pd.istc.cnr.it/navigais-web/?map=' . $map;
        }
        return 'G. Tisato - NavigAIS - <a' . $attributes . 'href="' . $link . '">' . $link . '</a>';
    }

    if ($atlas == 'ALF') {
        if (is_numeric($map)) {
            $number = str_pad($map, 4, '0', STR_PAD_LEFT);
        } else if (in_array(substr($map, - 1), [
                'A',
                'B'
        ])) {
            $number = str_pad(substr($map, 0, - 1), 4, '0', STR_PAD_LEFT) . substr($map, - 1);
        } else {
            return null; // No maps for supplements
        }
        $link = 'http://lig-tdcge.imag.fr/cartodialect3/visualiseur?numCarte=' . $number;

        return '<a' . $attributes . 'href="' . $link . '">Link</a>';
    }

    return null;
}
?>