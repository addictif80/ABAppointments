<?php
/**
 * ABAppointments - Settings Manager
 */
class Settings {
    private static array $cache = [];
    private static bool $loaded = false;

    public static function loadAll(): void {
        if (self::$loaded) return;
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT setting_key, setting_value FROM ab_settings");
        foreach ($rows as $row) {
            self::$cache[$row['setting_key']] = $row['setting_value'];
        }
        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string {
        self::loadAll();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void {
        $db = Database::getInstance();
        $existing = $db->fetchOne("SELECT id FROM ab_settings WHERE setting_key = ?", [$key]);
        if ($existing) {
            $db->update('ab_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            $db->insert('ab_settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
        self::$cache[$key] = $value;
    }

    public static function getMultiple(array $keys): array {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::get($key);
        }
        return $result;
    }

    public static function setMultiple(array $data): void {
        foreach ($data as $key => $value) {
            self::set($key, $value);
        }
    }
}
