<?php
/* Author: Implex1v <Implex1v@gmail.com>
 * Licence: Attribution-ShareAlike 4.0 International (CC BY-SA 4.0): https://creativecommons.org/licenses/by-sa/4.0/ */

$plugins->add_hook("pre_output_page", ["ImageHider", "clear_images"]);

function image_hider_info() {
    return [
        'name'           => 'Image Hider',
        'description'    => 'Hides images whose URL is not whitelisted',
        'website'        => 'https://github.com/Implex1v/MyBBImageHider',
        'author'         => 'Implex1v',
        'authorsite'     => 'https://implex1v.de/',
        'version'        => '1.0.5',
        'codename'       => 'image_hider',
        'compatibility'  => '18*',
    ];
}

function image_hider_install() {
    global $db;

    $setting_gid = $db->insert_query('settinggroups', [
      'name'        => 'image_hider',
      'title'       => 'Image Hider',
      'description' => 'Hides images whose URL is not whitelisted.',
    ]);

    $settings =
        [[
            'name'        => 'image_hider_activated',
            'title'       => 'Activated',
            'description' => 'Indicates if hiding images is active or not.',
            'optionscode' => 'onoff',
            'value'       => '1'
        ],[
            'name'        => 'image_hider_whitelist',
            'title'       => 'Semicolon sperated list of whitelisted URLs',
            'description' => 'A list of semicolon separated URLs which are whitelisted for displaying the image. Please do not use any whitespaces. Remember to whitelist your own website.',
            'optionscode' => 'text',
            'value'       => $_SERVER['HTTP_HOST']
        ],[
            'name'        => 'image_hider_replacement',
            'title'       => 'The text the matched images will be replaced with',
            'description' => 'Defines what the found images will be replaced with. You can use HTML but short code is recommended. Use <tt>{src}</tt> for the original URL to the image.',
            'optionscode' => 'text',
            'value'       => '<span>[Image: <a href="{src}">{src}</a>]</span>'
        ],[
            'name'        => 'image_hider_replacement_image',
            'title'       => 'The replacement image which will be displayed if linking is not possible.',
            'description' => 'Defines a URL to the replacement image if the linking of the image is not possible e.g. in CSS. Be sure to whitelist the url of the replacement image.',
            'optionscode' => 'text',
            'value'       => ''
        ],[
            'name'        => 'image_hider_exclude_files',
            'title'       => 'A list of files which will be excluded.',
            'description' => 'Defines a semicolon separated list of files which will be ignored by this plugin.',
            'optionscode' => 'text',
            'value'       => ''
        ],[
            'name'        => 'image_hider_check_http',
            'title'       => 'Block http image urls',
            'description' => 'Indicates if images served over http will be hidden.',
            'optionscode' => 'onoff',
            'value'       => '1'
        ]];

    $i = 1;
    foreach ($settings as &$row) {
        $row['gid']       = $setting_gid;
        $row['disporder'] = $i++;
        $db->insert_query("settings", $row);
    }

    rebuild_settings();
}

function image_hider_uninstall() {
    global $db;

    $settingGroupId = $db->fetch_field(
        $db->simple_select('settinggroups', 'gid', "name='image_hider'"),
        'gid'
    );

    $db->delete_query('settinggroups', 'gid=' . $settingGroupId);
     rebuild_settings();
}

function image_hider_is_installed() {
    global $db;

    $query = $db->simple_select('settinggroups', 'gid', "name='image_hider'");
    return (bool)$db->num_rows($query);
}

function image_hider_activate() {
    global $db;

    $db->update_query("settings", ["value" => "1"], "name = 'image_hider_activated'");
}

function image_hider_deactivate() {
    global $db;

    $db->update_query("settings", ["value" => "0"], "name = 'image_hider_activated'");
}

class ImageHider {
    /**
     * Called by 'pre_output_page' hook. Removes all not whitelisted images from a website. The setting 'image_hider_activated' is used
     * to check if the plugin is activated. The setting 'image_hider_whitelist' is used to get all whitelisted image hosts.
     * @param $page string The whole page to output
     */
    static function clear_images(&$page) {
        global $db;

        $activated = $db->fetch_array($db->simple_select("settings", "value", "name = 'image_hider_activated'"))['value'];
        if($activated) {
            if(ImageHider::check_if_excluded()) {
                return;
            }


            $urlDB = $db->fetch_array($db->simple_select("settings", "value", "name = 'image_hider_whitelist'"))['value'];
            $urls = explode(";", $urlDB);
            if($urls[sizeof($urls)-1] == "") {
                unset($urls[sizeof($urls)-1]);
            }

            $cache = array();
            ImageHider::clear_img($page, $urls, $cache);
            ImageHider::clear_others($page, $urls, $cache);
        }
    }

    /**
     * Replaces all not whitelisted images in HTML-Code. Found images will be replaced with the value of the 'image_hider_replacement' setting.
     * @param $page string The whole page to output
     * @param $urls array The list of whitelisted URLs
     * @param $cache array A cache of already replaced links. Used to add found URLs
     */
    static function clear_img(&$page, &$urls, &$cache) {
        global $db;

        $preg = "/(<img\s)[a-zA-Z0-9:;-=\"'\s\.\{\[\]\}\&\?\$\/\_\\\\]+(\/)?(>)/im";
        preg_match_all($preg, $page, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $value) {
            $src = ImageHider::extract_url($value[0]);

            if($src === FALSE) {
                $src = ImageHider::extract_url_idiots($value[0]);
                if($src === FALSE) {
                    continue;
                }
            }

            $matched = false;
            foreach($urls as $url) {
                if(strpos($src, $url) !== FALSE) {
                    $matched = true;
                }
            }

            // check if local images are included
            if(ImageHider::starts_with($src, "images/") OR ImageHider::starts_with($src, "/") OR ImageHider::starts_with($src, "./")) {
                $matched = true;
            } else if(ImageHider::block_http($src)) {
                $matched = false;
            }

            if(!$matched) {
                $cache[] = $src;

                $replacement = $db->fetch_array($db->simple_select("settings", "value", "name = 'image_hider_replacement'"))['value'];
                $replaced = str_replace("{src}", $src, $replacement);
                $page = str_replace($value, $replaced, $page);
            }
        }
    }

    /**
     * Replaces all other found images with a defined replacement image. The replacement image can be set in the ACP settings (key=image_hider_replacement_image).
     * @param $page string The whole page to output
     * @param $urls array The list of whitelisted URLs
     * @param $cache array A cache of already replaced links. Used to check if url is already replaced
     */
    static function clear_others(&$page, &$urls, &$cache) {
        global $db;

        $replacement = $db->fetch_array($db->simple_select("settings", "value", "name = 'image_hider_replacement_image'"))['value'];
        $reg = "/[a-zA-Z0-9\.\/\?\&\:\;\_\-]*\/[a-zA-Z0-9\.\/\?\&\:\;\_\-]*(\.gif|\.jpg|\.png|\.jpeg|\.bmp|\.svg|\.tif)/im";
        preg_match_all($reg, $page, $matches);

        foreach($matches[0] as $value) {
            $src = $value;
            $matched = false;
            foreach($urls as $url) {
                if(strpos($src, $url) !== FALSE) {
                    $matched = true;
                }
            }

            // check if local images are included
            if(ImageHider::starts_with($src, "images/") OR ImageHider::starts_with($src, "/") OR ImageHider::starts_with($src, "./")) {
                $matched = true;
            } else if(ImageHider::block_http($src)) {
                $matched = false;
            }

            if(!$matched && !in_array($src, $cache)) {
                $page = str_replace($value, $replacement, $page);
            }
        }
    }

    /**
     * Extracts a url from a src tag when the author did not use quotes ....
     * @param $img_tag string The whole img-tag
     * @return bool|string The link if matched otherwise <code>FALSE</code>
     */
    static function extract_url_idiots($img_tag) {
        $preg = "/(src)(\s)*=(\s)*[a-zA-Z0-9\?\&\#:\/\\\\;:\s\-\_\.\%]*(>)/im";
        preg_match($preg, $img_tag, $match);

        if(sizeof($match) > 0) {
            $src = $match[0];
            $equal = strpos($src, "=");

            $linkBegin = $equal;
            for(; $linkBegin < sizeof($src); $linkBegin++) {
                if(! preg_match("/\s/", substr($src, $equal, 1))) {
                    break;
                }
            }

            return substr($src, $linkBegin+1, sizeof($src)-2);
        } else {
            return FALSE;
        }
    }

    /**
     * Extracts a url from a src tag
     * @param $img_tag string The whole img-tag
     * @return bool|string The link if matched otherwise <code>FALSE</code>
     */
    static function extract_url($img_tag) {
        $preg = "/(src)(\s)*=(\s)*(\"|\')[a-zA-Z0-9\?\&\#:\/\\\\;:\s\-\_\.\%]*(\"|\')/im";
        preg_match($preg, $img_tag, $match);

        if(sizeof($match) > 0) {
            $src = $match[0];
            $lastQuote = substr($src, -1);
            $firstQuote = strpos($src, $lastQuote);

            if($firstQuote) {
                return substr($src, $firstQuote+1, sizeof($src)-2);
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Returns if the current file is excluded from checking.
     * @return bool <code>TRUE</code> if the current file is excluded otherwise <code>false</code>
     */
    static function check_if_excluded() {
        global $db;

        $exclude = $db->fetch_array($db->simple_select("settings", "value", "name = 'image_hider_exclude_files'"))['value'];
        $excludes = explode(";", $exclude);
        if($excludes[sizeof($excludes)-1] == "") {
            unset($excludes[sizeof($excludes)-1]);
        }

        foreach($excludes as $file) {
            if(THIS_SCRIPT AND $file === THIS_SCRIPT) {
                return TRUE;
            }
        }

        return FALSE;
    }

    static function block_http($src) {
        global $db;

        $exclude = $db->fetch_array($db->simple_select("settings", "value", "name = 'image_hider_check_http'"))['value'];
        if($exclude) {
            return preg_match("/(http:)/im", $src) > 0;
        } else {
            return false;
        }
    }

    /**
     * Checks if <code>$haystack</code> starts with <code>$needle</code>
     * @param $haystack
     * @param $needle
     * @return bool <code>TRUE</code> if <code>$haystack</code> starts with <code>$needle</code> otherwise <code>FALSE</code>
     */
    static function starts_with($haystack, $needle) {
         $length = strlen($needle);
         return (substr($haystack, 0, $length) === $needle);
    }
}
