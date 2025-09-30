<?php

namespace WpDatabaseHelperV2\Services;

class Assets {
    protected string $version;
    protected string $plugin_dir;
    protected string $plugin_url;

    public function __construct() {
        // absolute path (filesystem)
        $this->plugin_dir = wp_normalize_path(dirname(__DIR__, 2));

        // convert absolute path to URL
        $this->plugin_url = $this->getPluginUrl($this->plugin_dir);

        $this->version = $this->getVersion();
    }

    private function getVersion(): string {
        $composerFile = $this->plugin_dir . '/composer.json';
        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            return $composerData['version'] ?? '0.0.0';
        }
        return '0.0.0';
    }

    private function getPluginUrl(string $path): string {
        $path = wp_normalize_path($path);
        $contentDir = wp_normalize_path(WP_CONTENT_DIR);

        if (strpos($path, $contentDir) === 0) {
            return str_replace($contentDir, WP_CONTENT_URL, $path);
        }

        // fallback: nếu nằm ngoài wp-content (hiếm gặp)
        return content_url(basename($path));
    }

    // register script/style
    public function enqueue(): void {
        $js_path  = 'assets/js/repeater.js';
        $css_path = 'assets/css/repeater.css';

        wp_enqueue_script(
            'wpdh-repeater',
            $this->plugin_url . '/' . $js_path,
            ['jquery'],
            $this->version,
            true
        );

        wp_enqueue_style(
            'wpdh-repeater',
            $this->plugin_url . '/' . $css_path,
            [],
            $this->version
        );

        wp_localize_script('wpdh-repeater', 'Wpdh', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wpdh_repeater'),
            'i18n'     => [],
        ]);
    }
}
