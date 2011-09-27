<?php
/*
   Plugin Name: Awesome Flickr Gallery
   Plugin URI: http://www.ronakg.com/projects/awesome-flickr-gallery-wordpress-plugin/
   Description: Awesome Flickr Gallery is a simple, fast and light plugin to create a gallery of your Flickr photos on your WordPress enabled website.  This plugin aims at providing a simple yet customizable way to create stunning Flickr gallery.
   Version: 3.0.8
   Author: Ronak Gandhi
   Author URI: http://www.ronakg.com
   License: GPL2

   Copyright 2011 Ronak Gandhi (email : ronak.gandhi@ronakg.com)

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License, version 2, as
   published by the Free Software Foundation.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
require_once('afgFlickr/afgFlickr.php');
include_once('admin_settings.php');
include_once('afg_libs.php');

if ( !is_admin() ) {
    /* Short code to load Awesome Flickr Gallery plugin.  Detects the word
     * [AFG_gallery] in posts or pages and loads the gallery.
     */
    add_shortcode('AFG_gallery', 'afg_display_gallery');
    add_filter('widget_text', 'do_shortcode', SHORTCODE_PRIORITY);

    add_action('wp_print_scripts', 'enqueue_afg_scripts');
    add_action('wp_print_styles', 'enqueue_afg_styles');
}

add_action('wp_head', 'add_afg_headers');
function enqueue_afg_scripts() {
    if(!get_option('afg_disable_slideshow')) {
        if (get_option('afg_slideshow_option') == 'colorbox') {
            wp_enqueue_script('jquery');
            wp_enqueue_script('afg_colorbox_script', BASE_URL . "/colorbox/jquery.colorbox-min.js" , array('jquery'));
            wp_enqueue_script('afg_colorbox_js', BASE_URL . "/colorbox/mycolorbox.js" , array('jquery'));
        }
        else if (get_option('afg_slideshow_option') == 'highslide') {
            wp_enqueue_script('afg_highslide_js', BASE_URL . "/highslide/highslide-full.min.js");
        }
    }
}

function enqueue_afg_styles() {
    if(!get_option('afg_disable_slideshow'))
        if (get_option('afg_slideshow_option') == 'colorbox') {
            wp_enqueue_style('afg_colorbox_css', BASE_URL . "/colorbox/colorbox.css");
        }
        else if (get_option('afg_slideshow_option') == 'highslide') {
            wp_enqueue_style('afg_highslide_css', BASE_URL . "/highslide/highslide.css");
        }
    wp_enqueue_style('afg_css', BASE_URL . "/afg.css");
}

function add_afg_headers() {
    if (get_option('afg_slideshow_option') == 'highslide') {
        echo "<script type='text/javascript'>
            hs.graphicsDir = '" . BASE_URL . "/highslide/graphics/';
        hs.align = 'center';
        hs.transitions = ['expand', 'crossfade'];
        hs.fadeInOut = true;
        hs.dimmingOpacity = 0.85;
        hs.outlineType = 'rounded-white';
        hs.captionEval = 'this.thumb.alt';
        hs.marginBottom = 115; // make room for the thumbstrip and the controls
        hs.numberPosition = 'caption';
        // Add the slideshow providing the controlbar and the thumbstrip
        hs.addSlideshow({
            //slideshowGroup: 'group1',
            interval: 3500,
                repeat: false,
                useControls: true,
                overlayOptions: {
                    className: 'text-controls',
                        position: 'bottom center',
                        relativeTo: 'viewport',
                        offsetY: -60
    },
    thumbstrip: {
        position: 'bottom center',
            mode: 'horizontal',
            relativeTo: 'viewport'
    }
    });
         </script>";
    }
}

function afg_return_error_code($rsp) {
    return $rsp['message'];
}

/* Main function that loads the gallery. */
function afg_display_gallery($atts) {
    global $size_heading_map, $afg_text_color_map, $pf;

    if (!get_option('afg_pagination')) update_option('afg_pagination', 'on');

    extract( shortcode_atts( array(
        'id' => '0',
    ), $atts ) );

    $request_uri = $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'];
    
    if ($request_uri == '' || !$request_uri) $request_uri = $_SERVER['REQUEST_URI'];

    $cur_page = 1;
    $cur_page_url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? "https://".$_SERVER['HTTP_HOST'].$request_uri : "http://".$_SERVER['SERVER_NAME'].$request_uri;

    preg_match("/afg{$id}_page_id=(?P<page_id>\d+)/", $cur_page_url, $matches);

    if ($matches) {
        $cur_page = ($matches['page_id']);
        $match_pos = strpos($cur_page_url, "afg{$id}_page_id=$cur_page") - 1;
        $cur_page_url = substr($cur_page_url, 0, $match_pos);
        if(function_exists('qtrans_convertURL')) {
            $cur_page_url = qtrans_convertURL($cur_page_url);
        }
    }

    if (strpos($cur_page_url,'?') === false) $url_separator = '?';
    else $url_separator = '&';

    $galleries = get_option('afg_galleries');
    $gallery = $galleries[$id];

    $api_key = get_option('afg_api_key');
    $user_id = get_option('afg_user_id');
    $disable_slideshow = get_option('afg_disable_slideshow');
    $slideshow_option = get_option('afg_slideshow_option');

    $per_page = get_afg_option($gallery, 'per_page');
    $photo_size = get_afg_option($gallery, 'photo_size');
    $photo_title = get_afg_option($gallery, 'captions');
    $photo_descr = get_afg_option($gallery, 'descr');
    $bg_color = get_afg_option($gallery, 'bg_color');
    $columns = get_afg_option($gallery, 'columns');
    $credit_note = get_afg_option($gallery, 'credit_note');
    $gallery_width = get_afg_option($gallery, 'width');
    $pagination = get_afg_option($gallery, 'pagination');

    if ($photo_size == 'custom') {
        $custom_size = get_afg_option($gallery, 'custom_size');
        $custom_size_square = get_afg_option($gallery, 'custom_size_square');
        
        if ($custom_size <= 70) $photo_size = '_s';
        else if ($custom_size <= 90) $photo_size = '_t';
        else if ($custom_size <= 220) $photo_size = '_m';
        else if ($custom_size <= 500) $photo_size = 'NULL';
    }
    else {
        $custom_size = 0;
        $custom_size_square = 'false';
    }

    if (isset($gallery['photo_source']) && $gallery['photo_source'] == 'photoset') $photoset_id = $gallery['photoset_id'];
    else if (isset($gallery['photo_source']) && $gallery['photo_source'] == 'gallery') $gallery_id = $gallery['gallery_id'];
    else if (isset($gallery['photo_source']) && $gallery['photo_source'] == 'group') $group_id = $gallery['group_id'];
    
    $disp_gallery = "<!-- Awesome Flickr Gallery Start -->";
    $disp_gallery .= "<!--" .
        " - Version - " . VERSION .
        " - User ID - " . $user_id .
        " - Photoset ID - " . (isset($photoset_id)? $photoset_id: '') .
        " - Gallery ID - " . (isset($gallery_id)? $gallery_id: '') .
        " - Group ID - " . (isset($group_id)? $group_id: '') .
        " - Per Page - " . $per_page .
        " - Photo Size - " . $photo_size .
        " - Custom Size - " . $custom_size .
        " - Square - " . $custom_size_square .
        " - Captions - " . $photo_title .
        " - Description - " . $photo_descr .
        " - Columns - " . $columns .
        " - Credit Note - " . $credit_note .
        " - Background Color - " . $bg_color .
        " - Width - " . $gallery_width .
        " - Pagination - " . $pagination .
        " - Slideshow - " . $slideshow_option .
        " - Disable slideshow? - " . $disable_slideshow .
        "-->";

    if (isset($photoset_id) && $photoset_id) {
        $rsp_obj = $pf->photosets_getInfo($photoset_id);
        if ($pf->error_code) return afg_error();
        $total_photos = $rsp_obj['photos'];
    }
    else if (isset($gallery_id) && $gallery_id) {
        $rsp_obj = $pf->galleries_getInfo($gallery_id);
        if ($pf->error_code) return afg_error();
        $total_photos = $rsp_obj['gallery']['count_photos']['_content'];
    }
    else if (isset($group_id) && $group_id) {
        $rsp_obj = $pf->groups_pools_getPhotos($group_id, NULL, NULL, NULL, NULL, 1, 1);
        if ($pf->error_code) return afg_error();
        $total_photos = $rsp_obj['photos']['total'];
        if ($total_photos > 500) $total_photos = 500;
        }
    else {
        $rsp_obj = $pf->people_getInfo($user_id);
        if ($pf->error_code) return afg_error();
        $total_photos = $rsp_obj['photos']['count']['_content'];
    }

    $photos = get_transient('afg_id_' . $id);
    $extras = 'url_l, description';

    if ($photos == false || $total_photos != count($photos)) {
        $photos = array();
        for($i=1; $i<($total_photos/500)+1; $i++) {
            if ($photoset_id) {
                $flickr_api = 'photoset';
                $rsp_obj_total = $pf->photosets_getPhotos($photoset_id, $extras, NULL, 500, $i);
                if ($pf->error_code) return afg_error();
            }
            else if ($gallery_id) {
                $flickr_api = 'photos';
                $rsp_obj_total = $pf->galleries_getPhotos($gallery_id, $extras, 500, $i);
                if ($pf->error_code) return afg_error();
            }
            else if ($group_id) {
                $flickr_api = 'photos';
                $rsp_obj_total = $pf->groups_pools_getPhotos($group_id, NULL, NULL, NULL, $extras, 500, $i);
                if ($pf->error_code) return afg_error();
            }
            else {
                $flickr_api = 'photos';
                if (get_option('afg_flickr_token')) $rsp_obj_total = $pf->people_getPhotos($user_id, array('extras' => $extras, 'per_page' => 500, 'page' => $i));
                else $rsp_obj_total = $pf->people_getPublicPhotos($user_id, NULL, $extras, 500, $i);
                if ($pf->error_code) return afg_error();
            }
            $photos = array_merge($photos, $rsp_obj_total[$flickr_api]['photo']);
        }
        set_transient('afg_id_' . $id, $photos, 60 * 60 * 24 * 3);
    }

    if (($total_photos % $per_page) == 0) $total_pages = (int)($total_photos / $per_page);
    else $total_pages = (int)($total_photos / $per_page) + 1;

    if ($slideshow_option == 'highslide')
        $disp_gallery .= "<div class='highslide-gallery'>";

    if ($gallery_width == 'auto') $gallery_width = 100;
    $disp_gallery .= "<div class='afg-table' style='background-color:{$bg_color}; width:$gallery_width%'>";

    $photo_count = 1;
    $cur_col = 0;
    $column_width = (int)($gallery_width/$columns);

    foreach($photos as $pid => $photo) {
        if (isset($photo['url_l'])? $photo['url_l']: '') {
            $photo_page_url = $photo['url_l'];
        }
        else {
            $photo_page_url = afg_get_photo_url($photo['farm'], $photo['server'],
                $photo['id'], $photo['secret'], '_z');
        }
        $photo_url = afg_get_photo_url($photo['farm'], $photo['server'],
            $photo['id'], $photo['secret'], $photo_size);
        if ( ($photo_count <= $per_page * $cur_page) && ($photo_count > $per_page * ($cur_page - 1)) ) {
            $text_color = isset($afg_text_color_map[$bg_color])? $afg_text_color_map[$bg_color]: '';

            if ($cur_col % $columns == 0) $disp_gallery .= "<div class='afg-row'>";
            $disp_gallery .= "<div class='afg-cell' style='width:${column_width}%;" .
                " color:{$text_color}; border-color:{$bg_color};'>";

            $pid_len = strlen($photo['id']);

            /* If photo descriptions are ON and size is not Square and Thumbnail,
             * get photo descriptions
             */
            if ($disable_slideshow) {
                $class = '';
                $rel = '';
                $click_event = '';
            }
            else {
                if ($slideshow_option == 'colorbox') {
                    $class = "class='afgcolorbox'";
                    $rel = "rel='example4{$id}'";
                    $click_event = '';
                }
                else if ($slideshow_option == 'highslide') {
                    $class = "class='highslide'";
                    $rel = "";
                    $click_event = "onclick='return hs.expand(this, {slideshowGroup: $id })'";
                }
            }

            if ($photo_size == '_s') {
                $photo_width = "width='75'";
                $photo_height = "height='75'";
            }
            else {
                $photo_width = '';
                $photo_height = '';
            }

            if ($custom_size) {
                $timthumb_script = BASE_URL . "/timthumb.php?src=";
                $timthumb_params = "&q=100&w=$custom_size";
                if ($custom_size_square == 'true')
                    $timthumb_params .= "&h=$custom_size";
            }
            else {
                $timthumb_script = "";
                $timthumb_params = "";
            }

            $disp_gallery .= "<a $class $rel $click_event href='$photo_page_url' " .
                "title='{$photo['title']}'>" .
                "<img class='afg-img' src='{$timthumb_script}{$photo_url}{$timthumb_params}' " .
                "alt='{$photo['title']}' " .
                "onmouseover='this.style.opacity=0.6;this.filters.alpha.opacity=60' " .
                "onmouseout='this.style.opacity=1;this.filters.alpha.opacity=100' " .
                "/></a>";
            if($size_heading_map[$photo_size] && $photo_title == 'on') {
                if ($group_id) $owner_title = "- by <a href='http://www.flickr.com/photos/{$photo['owner']}/' target='_blank'>{$photo['ownername']}</a>";
                else $owner_title = '';
                $disp_gallery .= "<div class='afg-title' style='" .
                   " font-size:{$size_heading_map[$photo_size]}'>{$photo['title']} $owner_title</div>";
            }

            if($photo_descr == 'on' && $photo_size != '_s' && $photo_size != '_t') {
                $disp_gallery .= "<div class='afg-description'>" .
                    $photo['description']['_content'] . "</div>";
            }

            $cur_col += 1;
            $disp_gallery .= '</div>';
            if ($cur_col % $columns == 0) $disp_gallery .= '</div>';
        }
        else {
            if ($pagination == 'on') {
                $photo_url = afg_get_photo_url($photo['farm'], $photo['server'],
                    $photo['id'], $photo['secret'], '_s');
                $disp_gallery .= "<a style='display:none' $class $rel $click_event href='$photo_page_url'" .
                    " title='{$photo['title']}'>" .
                    " <img class='afg-img' alt='{$photo['title']}' src='$photo_url' width='75' height='75'" .
                    "onmouseover='this.style.opacity=0.6;this.filters.alpha.opacity=60' " .
                    "onmouseout='this.style.opacity=1;this.filters.alpha.opacity=100'></a> ";
            }
        }
        $photo_count += 1;
    }

    if ($cur_col % $columns != 0) $disp_gallery .= '</div>';
    $disp_gallery .= '</div>';
    if ($slideshow_option == 'highslide') $disp_gallery .= "</div>";

    // Pagination
    if ($pagination == 'on' && $total_pages > 1) {
        $disp_gallery .= "<br /><br />";
        $disp_gallery .= "<div class='afg-pagination' style='background-color:{$bg_color}; width:$gallery_width%; color:{$text_color}; border-color:{$bg_color}'>";
        if ($cur_page == 1) {
            $disp_gallery .="<font class='afg-page'>&nbsp;&#171; prev&nbsp;</font>&nbsp;&nbsp;&nbsp;&nbsp;";
            $disp_gallery .="<font class='afg-cur-page'> 1 </font>&nbsp;";
        }
        else {
            $prev_page = $cur_page - 1;
            $disp_gallery .= "<a class='afg-page' href='{$cur_page_url}{$url_separator}afg{$id}_page_id=$prev_page' title='Prev Page'>&nbsp;&#171; prev </a>&nbsp;&nbsp;&nbsp;&nbsp;";
            $disp_gallery .= "<a class='afg-page' href='{$cur_page_url}{$url_separator}afg{$id}_page_id=1' title='Page 1'> 1 </a>&nbsp;";
        }
        if ($cur_page - 2 > 2) {
            $start_page = $cur_page - 2;
            $end_page = $cur_page + 2;
            $disp_gallery .= " ... ";
        }
        else {
            $start_page = 2;
            $end_page = 6;
        }
        for ($count = $start_page; $count <= $end_page; $count += 1) {
            if ($count > $total_pages) break;
            if ($cur_page == $count)
                $disp_gallery .= "<font class='afg-cur-page'>&nbsp;{$count}&nbsp;</font>&nbsp;";
            else
                $disp_gallery .= "<a class='afg-page' href='{$cur_page_url}{$url_separator}afg{$id}_page_id={$count}' title='Page {$count}'>&nbsp;{$count} </a>&nbsp;";
        }

        if ($count < $total_pages) $disp_gallery .= " ... ";
        if ($count <= $total_pages)
            $disp_gallery .= "<a class='afg-page' href='{$cur_page_url}{$url_separator}afg{$id}_page_id={$total_pages}' title='Page {$total_pages}'>&nbsp;{$total_pages} </a>&nbsp;";
        if ($cur_page == $total_pages) $disp_gallery .= "&nbsp;&nbsp;&nbsp;<font class='afg-page'>&nbsp;next &#187;&nbsp;</font>";
        else {
            $next_page = $cur_page + 1;
            $disp_gallery .= "&nbsp;&nbsp;&nbsp;<a class='afg-page' href='{$cur_page_url}{$url_separator}afg{$id}_page_id=$next_page' title='Next Page'> next &#187; </a>&nbsp;";
        }
        $disp_gallery .= "<br />({$total_photos} Photos)";
        $disp_gallery .= "</div>";
    }
    if ($credit_note == 'on') {
        $disp_gallery .= "<br />";
        $disp_gallery .= "<div class='afg-credit' style='color:{$text_color}; border-color:{$bg_color};'>Powered by " .
            "<a href='http://www.ronakg.com/projects/awesome-flickr-gallery-wordpress-plugin'" .
            "title='Awesome Flickr Gallery by Ronak Gandhi'/>AFG</a>";
        $disp_gallery .= "</div>";
    }
    $disp_gallery .= "<!-- Awesome Flickr Gallery End -->";
    return $disp_gallery;
}
?>
