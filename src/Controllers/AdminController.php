<?php

namespace WpDatabaseHelperV2\Controllers;
use WpDatabaseHelperV2\Models\OptionModel;
use WpDatabaseHelperV2\Services\Renderer;

class AdminController {
    public function optionsPage() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('wpdh_options_save')) {
            $data = $_POST['wpdh_options'] ?? [];
            OptionModel::save('wpdh_builder', $data);
            echo '<div class="updated notice"><p>Saved</p></div>';
        }

        $config = OptionModel::get('wpdh_builder', []);
        Renderer::view('admin/options-page', ['config' => $config]);
    }
}
