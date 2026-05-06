<?php

class DB
{
    /** @var PDO */
    private static $pdo;
    /** @var string */
    private static $prefix = '';

    public static function init($host, $port, $name, $user, $pass, $prefix = 'knk_')
    {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        self::$prefix = $prefix;
    }

    public static function pdo()
    {
        return self::$pdo;
    }

    public static function prefix()
    {
        return self::$prefix;
    }

    public static function t($table)
    {
        return '`' . self::$prefix . $table . '`';
    }

    public static function query($sql, $params = [])
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function row($sql, $params = [])
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function rows($sql, $params = [])
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function val($sql, $params = [])
    {
        $stmt = self::query($sql, $params);
        $stmt->setFetchMode(PDO::FETCH_NUM);
        $row = $stmt->fetch();
        return $row ? $row[0] : null;
    }

    public static function insert($table, $data)
    {
        $keys = array_keys($data);
        $cols = implode(', ', array_map(function($k) { return "`{$k}`"; }, $keys));
        $phs  = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO " . self::t($table) . " ({$cols}) VALUES ({$phs})", array_values($data));
        return (int)self::$pdo->lastInsertId();
    }

    public static function insertIgnore($table, $data)
    {
        $keys = array_keys($data);
        $cols = implode(', ', array_map(function($k) { return "`{$k}`"; }, $keys));
        $phs  = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT IGNORE INTO " . self::t($table) . " ({$cols}) VALUES ({$phs})", array_values($data));
        return (int)self::$pdo->lastInsertId();
    }

    public static function update($table, $data, $where)
    {
        $set   = implode(', ', array_map(function($k) { return "`{$k}` = ?"; }, array_keys($data)));
        $conds = implode(' AND ', array_map(function($k) { return "`{$k}` = ?"; }, array_keys($where)));
        $stmt  = self::query(
            "UPDATE " . self::t($table) . " SET {$set} WHERE {$conds}",
            array_merge(array_values($data), array_values($where))
        );
        return $stmt->rowCount();
    }

    public static function delete($table, $where)
    {
        $conds = implode(' AND ', array_map(function($k) { return "`{$k}` = ?"; }, array_keys($where)));
        $stmt  = self::query("DELETE FROM " . self::t($table) . " WHERE {$conds}", array_values($where));
        return $stmt->rowCount();
    }

    public static function count($table, $where = '1=1', $params = [])
    {
        return (int)self::val("SELECT COUNT(*) FROM " . self::t($table) . " WHERE {$where}", $params);
    }
}
