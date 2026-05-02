<?php

namespace wg\membership\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

class MediaElement {
    public static function register() {
        add_action('wp_enqueue_scripts', [static::class, 'enqueue_mejs_speed_assets'], 30);
        add_filter('mejs_settings', [static::class, 'enable_speed_feature']);
        add_action('wp_enqueue_scripts', [static::class, 'speed_firefox_timing_patch'], 31);
    }

    public static function should_load_assets() {
        $forced = apply_filters('wg_membership_load_mediaelement_speed_assets', null);
        if ($forced !== null) {
            return (bool) $forced;
        }

        if (is_admin()) {
            return false;
        }

        if (is_singular()) {
            $post = get_post();
            if ($post && static::content_has_media_context((string) $post->post_content)) {
                return true;
            }
        }

        return false;
    }

    public static function content_has_media_context($content) {
        if ($content === '') {
            return false;
        }

        $patterns = [
            '[audio',
            '[video',
            'wp:audio',
            'wp:video',
            'wp-block-audio',
            'wp-block-video',
        ];

        foreach ($patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function enqueue_mejs_speed_assets() {
        if (!static::should_load_assets()) {
            return;
        }
        wp_enqueue_script('wp-mediaelement');
        wp_enqueue_style('wp-mediaelement');
        $base = WG_MEMBERSHIP_SUITE_URL . 'assets/mejs-plugins/speed';
        wp_enqueue_style('mejs-speed', $base . '/speed.min.css', ['wp-mediaelement'], null);
        wp_enqueue_script('mejs-speed', $base . '/speed.min.js', ['wp-mediaelement'], null, true);
    }

    public static function enable_speed_feature($settings) {
        if (!static::should_load_assets()) {
            return $settings;
        }

        $features = isset($settings['features']) && is_array($settings['features']) ? $settings['features'] : ['playpause', 'current', 'progress', 'duration', 'tracks', 'volume', 'fullscreen'];
        if (!in_array('speed', $features, true)) {
            $features[] = 'speed';
        }
        $settings['features'] = $features;
        $settings['speeds'] = ['2.00', '1.50', '1.25', '1.00', '0.75'];
        $settings['defaultSpeed'] = '1.00';
        $settings['speedChar'] = 'x';
        return $settings;
    }

    public static function speed_firefox_timing_patch() {
        if (!static::should_load_assets()) {
            return;
        }
        wp_add_inline_script('mejs-speed', "(function(){function a(){var n=document.querySelectorAll('video.wp-video-shortcode,audio.wp-audio-shortcode');for(var i=0;i<n.length;i++){var p=n[i].player;if(!p||typeof p.buildspeed!=='function'||!p.controls){continue}if(p.controls.querySelector('.mejs__speed-button, .mejs-speed-button')){continue}var m=p.media||n[i];if(!m||typeof m.playbackRate!=='number'){continue}var r=m.rendererName||null;if(!r){m.rendererName='html5'}p.buildspeed(p,p.controls,p.layers,m);if(!r){m.rendererName=r}}}if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',function(){setTimeout(a,0);});}else{setTimeout(a,0)}setTimeout(a,400);}());", 'after');
    }
}
