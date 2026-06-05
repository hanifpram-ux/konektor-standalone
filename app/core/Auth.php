<?php

class Auth
{
    private static $SESSION_KEY = 'knk_admin_user';

    public static function attempt($username, $password)
    {
        $user = DB::row(
            "SELECT * FROM " . DB::t('users') . " WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1",
            [$username, $username]
        );
        if (!$user || !password_verify($password, $user->password_hash)) return false;

        $_SESSION[self::$SESSION_KEY] = [
            'id'       => $user->id,
            'username' => $user->username,
            'email'    => isset($user->email) ? $user->email : '',
            'role'     => $user->role,
        ];

        DB::update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user->id]);
        return true;
    }

    public static function check()
    {
        return !empty($_SESSION[self::$SESSION_KEY]);
    }

    public static function user()
    {
        return isset($_SESSION[self::$SESSION_KEY]) ? $_SESSION[self::$SESSION_KEY] : null;
    }

    public static function id()
    {
        return isset($_SESSION[self::$SESSION_KEY]['id']) ? (int)$_SESSION[self::$SESSION_KEY]['id'] : null;
    }

    public static function requireAdmin()
    {
        if (!self::check()) {
            Helper::redirect(KONEKTOR_SITE_URL . '/admin/login.php');
        }
    }

    public static function logout()
    {
        unset($_SESSION[self::$SESSION_KEY]);
        session_destroy();
    }

    public static function resolveOperatorToken($token)
    {
        if (empty($token)) return null;
        $row = DB::row(
            "SELECT ot.*, o.name, o.type, o.value, o.status
             FROM " . DB::t('operator_tokens') . " ot
             JOIN " . DB::t('operators') . " o ON o.id = ot.operator_id
             WHERE ot.token = ? AND (ot.expires_at IS NULL OR ot.expires_at > NOW())
             AND o.status = 'on' LIMIT 1",
            [$token]
        );
        return $row ?: null;
    }

    public static function generateOperatorToken($operatorId)
    {
        $token = Crypto::randomToken(48);
        DB::insert('operator_tokens', [
            'operator_id' => $operatorId,
            'token'       => $token,
            'expires_at'  => null,
        ]);
        return $token;
    }

    public static function revokeOperatorTokens($operatorId)
    {
        DB::query("DELETE FROM " . DB::t('operator_tokens') . " WHERE operator_id = ?", [$operatorId]);
    }

    public static function changePassword($userId, $newPassword)
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        return DB::update('users', ['password_hash' => $hash], ['id' => $userId]) > 0;
    }
}
