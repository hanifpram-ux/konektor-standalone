<?php

class Blocker
{
    public static function isBlocked($campaignId, $ip, $fingerprint, $cookieId)
    {
        $sql = "SELECT 1 FROM " . DB::t('blocked') . "
                WHERE (campaign_id IS NULL OR campaign_id = ?) AND (
                  (block_type = 'ip' AND (ip_address = ? OR fingerprint = ?)) OR
                  (block_type = 'cookie' AND cookie_id = ?) OR
                  (block_type = 'both' AND (ip_address = ? OR fingerprint = ? OR cookie_id = ?))
                ) LIMIT 1";
        $params = [$campaignId ?: null, $ip, $fingerprint, $cookieId, $ip, $fingerprint, $cookieId];
        return (bool)DB::val($sql, $params);
    }

    public static function block($data)
    {
        $blockBy = in_array($data['block_by'] ?? 'both', ['ip','cookie','both']) ? ($data['block_by'] ?? 'both') : 'both';
        $ip      = isset($data['ip_address'])  ? $data['ip_address']  : null;
        $cookie  = isset($data['cookie_id'])   ? $data['cookie_id']   : null;

        $saveIp     = in_array($blockBy, ['ip', 'both'], true)     ? $ip     : null;
        $saveCookie = in_array($blockBy, ['cookie', 'both'], true) ? $cookie : null;
        $saveFp     = in_array($blockBy, ['ip', 'both'], true)     ? (isset($data['fingerprint']) ? $data['fingerprint'] : null) : null;

        return DB::insert('blocked', [
            'campaign_id'   => isset($data['campaign_id'])   ? $data['campaign_id']   : null,
            'ip_address'    => $saveIp,
            'fingerprint'   => $saveFp,
            'cookie_id'     => $saveCookie,
            'phone'         => isset($data['phone'])         ? $data['phone']         : null,
            'email'         => isset($data['email'])         ? $data['email']         : null,
            'reason'        => strip_tags(isset($data['reason'])        ? $data['reason']        : ''),
            'block_type'    => $blockBy,
            'blocked_by'    => isset($data['blocked_by'])    ? $data['blocked_by']    : null,
            'operator_name' => strip_tags(isset($data['operator_name']) ? $data['operator_name'] : ''),
            'blocked_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public static function unblock($id)
    {
        $blocker = DB::row("SELECT * FROM " . DB::t('blocked') . " WHERE id = ?", [(int)$id]);
        if (!$blocker) return false;

        // Delete the blocker entry
        DB::delete('blocked', ['id' => (int)$id]);

        // Find leads matching the blocker criteria and update their status to 'new'
        $whereParts = [];
        $params = [];
        if ($blocker->ip_address) {
            $whereParts[] = "ip_address = ?";
            $params[] = $blocker->ip_address;
        }
        if ($blocker->cookie_id) {
            $whereParts[] = "cookie_id = ?";
            $params[] = $blocker->cookie_id;
        }
        if (!empty($whereParts)) {
            $where = implode(' OR ', $whereParts);
            $leads = DB::rows("SELECT id FROM " . DB::t('leads') . " WHERE ({$where}) AND status = 'blocked'", $params);
            foreach ($leads as $lead) {
                Lead::updateStatus($lead->id, 'new');
            }
        }

        return true;
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
