<?php
/**
 * WebPanel - Settings Manager
 */
class Settings {
    private $cache = [];
    private $loaded = false;

    private function loadAll() {
        if ($this->loaded) return;
        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll("SELECT setting_key, setting_value FROM wp_settings");
            foreach ($rows as $row) {
                $this->cache[$row['setting_key']] = $row['setting_value'];
            }
            $this->loaded = true;
        } catch (Exception $e) {
            $this->loaded = true;
        }
    }

    public function get($key, $default = '') {
        $this->loadAll();
        return $this->cache[$key] ?? $default;
    }

    public function set($key, $value) {
        $db = Database::getInstance();
        $exists = $db->fetchOne("SELECT id FROM wp_settings WHERE setting_key = ?", [$key]);
        if ($exists) {
            $db->update('wp_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            $db->insert('wp_settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
        $this->cache[$key] = $value;
    }

    public function getMultiple($keys) {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    public function setMultiple($data) {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }
}
