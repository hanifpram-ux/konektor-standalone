<?php

class Settings
{
    private static $cache = [];

    public static function get($key, $default = '')
    {
        if (!isset(self::$cache[$key])) {
            $row = DB::row("SELECT setting_value FROM " . DB::t('settings') . " WHERE setting_key = ?", [$key]);
            self::$cache[$key] = $row ? (string)$row->setting_value : null;
        }
        return isset(self::$cache[$key]) ? self::$cache[$key] : $default;
    }

    public static function set($key, $value)
    {
        self::$cache[$key] = $value;
        $exists = DB::val("SELECT 1 FROM " . DB::t('settings') . " WHERE setting_key = ?", [$key]);
        if ($exists) {
            DB::update('settings', ['setting_value' => $value], ['setting_key' => $key]);
        } else {
            DB::insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }

    public static function all()
    {
        $rows = DB::rows("SELECT setting_key, setting_value FROM " . DB::t('settings'));
        $out  = [];
        foreach ($rows as $r) {
            $out[$r->setting_key] = $r->setting_value;
        }
        return $out;
    }

    public static function setMany($data)
    {
        foreach ($data as $k => $v) {
            self::set((string)$k, (string)$v);
        }
    }
}
