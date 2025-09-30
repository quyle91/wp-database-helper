<?php

namespace WpDatabaseHelperV2\Services;

class Renderer {
    public static function view(string $path, array $data = []) {
        $file = __DIR__ . '/../../views/' . $path . '.php';
        if (!file_exists($file)) {
            echo "<pre>View not found: {$file}</pre>";
            return;
        }
        extract($data);
        include $file;
    }
}
