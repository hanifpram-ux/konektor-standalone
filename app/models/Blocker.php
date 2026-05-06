<?php

class Blocker
{
    public static function isBlocked($campaignId, $ip, $fingerprint, $cookieId)
    {
        // Base: global block (campaign_id IS NULL)
        $params = [$ip, $fingerprint, $cookieId];
        $campaignCond = '';

        if ($campaignId) {
            // Campaign-specific block appended after
            $campaignCond = "OR (campaign_id = ? AND (ip_address = ? OR fingerprint = ? OR cookie_id = ?))";
            $params = array_merge($params, [(int)$campaignId, $ip, $fingerprint, $cookieId]);
        }

        $sql = "SELECT 1 FROM " . DB::t('blocked') . "
                WHERE (campaign_id IS NULL AND (ip_address = ? OR fingerprint = ? OR cookie_id = ?))
                {$campaignCond} LIMIT 1";

        return (bool)DB::val($sql, $params);
    }

    public static function block($data)
    {
        return DB::insert('blocked', [
            'campaign_id'   => isset($data['campaign_id'])   ? $data['campaign_id']   : null,
            'ip_address'    => isset($data['ip_address'])    ? $data['ip_address']    : null,
            'fingerprint'   => isset($data['fingerprint'])   ? $data['fingerprint']   : null,
            'cookie_id'     => isset($data['cookie_id'])     ? $data['cookie_id']     : null,
            'phone'         => isset($data['phone'])         ? $data['phone']         : null,
            'email'         => isset($data['email'])         ? $data['email']         : null,
            'reason'        => strip_tags(isset($data['reason'])        ? $data['reason']        : ''),
            'blocked_by'    => isset($data['blocked_by'])    ? $data['blocked_by']    : null,
            'operator_name' => strip_tags(isset($data['operator_name']) ? $data['operator_name'] : ''),
            'blocked_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public static function unblock($id)
    {
        DB::delete('blocked', ['id' => (int)$id]);
    }

    public static function all($campaignId = null, $limit = 50, $offset = 0)
    {
        $where  = '1=1';
        $params = [];
        if ($campaignId !== null) { $where .= ' AND campaign_id = ?'; $params[] = (int)$campaignId; }
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        return DB::rows(
            "SELECT * FROM " . DB::t('blocked') . " WHERE {$where} ORDER BY blocked_at DESC LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function count($campaignId = null)
    {
        $where  = '1=1';
        $params = [];
        if ($campaignId !== null) { $where .= ' AND campaign_id = ?'; $params[] = (int)$campaignId; }
        return DB::count('blocked', $where, $params);
    }
}
