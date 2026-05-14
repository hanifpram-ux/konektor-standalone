<?php

class Lead
{
    public static function create($data)
    {
        $encrypt = Settings::get('encrypt_lead_data', '1') === '1';

        $phone       = Helper::sanitizePhone(isset($data['phone']) ? $data['phone'] : '');
        $email       = filter_var(isset($data['email']) ? $data['email'] : '', FILTER_SANITIZE_EMAIL);
        $fingerprint = Crypto::fingerprint($phone, $email);

        $name    = strip_tags(isset($data['name'])    ? $data['name']    : '');
        $address = strip_tags(isset($data['address']) ? $data['address'] : '');

        $row = [
            'campaign_id'    => (int)(isset($data['campaign_id']) ? $data['campaign_id'] : 0),
            'operator_id'    => !empty($data['operator_id']) ? (int)$data['operator_id'] : null,
            'name'           => $encrypt ? Crypto::encrypt($name)    : $name,
            'email'          => $encrypt ? Crypto::encrypt($email)   : $email,
            'phone'          => $encrypt ? Crypto::encrypt($phone)   : $phone,
            'address'        => $encrypt ? Crypto::encrypt($address) : $address,
            'quantity'       => strip_tags(isset($data['quantity'])       ? $data['quantity']       : ''),
            'custom_message' => strip_tags(isset($data['custom_message']) ? $data['custom_message'] : ''),
            'extra_data'     => !empty($data['extra_data']) ? json_encode($data['extra_data'], JSON_UNESCAPED_UNICODE) : null,
            'ip_address'     => Helper::getClientIp(),
            'cookie_id'      => self::resolveVid(isset($data['_vid']) ? $data['_vid'] : ''),
            'user_agent'     => substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 255),
            'referrer'       => substr(isset($data['referrer']) ? $data['referrer'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''), 0, 2000),
            'fingerprint'    => $fingerprint,
            'is_double'      => 0,
            'source_url'     => substr(isset($data['source_url']) ? $data['source_url'] : '', 0, 2000),
            'status'         => 'new',
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        return DB::insert('leads', $row);
    }

    /**
     * Check if a lead is a duplicate.
     * Scope is controlled by the global setting 'double_lead_scope':
     *   'campaign' — only within this campaign (default)
     *   'domain'   — any campaign whose leads share the same source domain
     *   'page'     — any campaign whose leads share the exact same source URL/page
     *
     * Checks (in order): fingerprint (form only), cookie/VID, IP address.
     *
     * @param int    $campaignId
     * @param string $phone      Empty for wa_link
     * @param string $email      Empty for wa_link
     * @param string $vid        Cookie ID
     * @param string $ip         Auto-detected if empty
     * @param string $sourceUrl  Current page URL (for domain/page scope)
     */
    public static function checkDouble($campaignId, $phone = '', $email = '', $vid = '', $ip = '', $sourceUrl = '')
    {
        $campaignId = (int)$campaignId;
        $ip         = $ip ?: Helper::getClientIp();
        $scope      = Settings::get('double_lead_scope', 'campaign');

        // Build WHERE clause based on scope
        if ($scope === 'page' && $sourceUrl) {
            // All leads from the exact same page URL
            $scopeWhere  = 'source_url = ?';
            $scopeParams = [substr($sourceUrl, 0, 2000)];
        } elseif ($scope === 'domain' && $sourceUrl) {
            // All leads from the same domain (extract host from source_url)
            $host = parse_url($sourceUrl, PHP_URL_HOST);
            if ($host) {
                $scopeWhere  = "(source_url LIKE ? OR source_url LIKE ?)";
                $scopeParams = ['http://' . $host . '%', 'https://' . $host . '%'];
            } else {
                $scopeWhere  = 'campaign_id = ?';
                $scopeParams = [$campaignId];
            }
        } else {
            // Per campaign (default)
            $scopeWhere  = 'campaign_id = ?';
            $scopeParams = [$campaignId];
        }

        $t = DB::t('leads');

        // 1. Fingerprint (phone+email) — only meaningful for form leads
        if ($phone !== '' || $email !== '') {
            $fingerprint = Crypto::fingerprint(Helper::sanitizePhone($phone), $email);
            if ($fingerprint && DB::val(
                "SELECT id FROM {$t} WHERE {$scopeWhere} AND fingerprint = ? AND is_double = 0 LIMIT 1",
                array_merge($scopeParams, [$fingerprint])
            )) return true;
        }

        // 2. Cookie / VID
        if ($vid && DB::val(
            "SELECT id FROM {$t} WHERE {$scopeWhere} AND cookie_id = ? AND is_double = 0 LIMIT 1",
            array_merge($scopeParams, [$vid])
        )) return true;

        // 3. IP address
        if ($ip && DB::val(
            "SELECT id FROM {$t} WHERE {$scopeWhere} AND ip_address = ? AND is_double = 0 LIMIT 1",
            array_merge($scopeParams, [$ip])
        )) return true;

        return false;
    }

    public static function find($id)
    {
        return DB::row("SELECT * FROM " . DB::t('leads') . " WHERE id = ?", [(int)$id]);
    }

    public static function all($args = [])
    {
        $where  = '1=1';
        $params = [];

        if (!empty($args['campaign_id'])) { $where .= ' AND l.campaign_id = ?'; $params[] = $args['campaign_id']; }
        if (!empty($args['operator_id'])) { $where .= ' AND l.operator_id = ?'; $params[] = $args['operator_id']; }
        if (!empty($args['status']))      { $where .= ' AND l.status = ?';       $params[] = $args['status']; }
        if (isset($args['is_double']))    { $where .= ' AND l.is_double = ?';    $params[] = (int)$args['is_double']; }
        if (!empty($args['camp_type']))   { $where .= ' AND c.type = ?';         $params[] = $args['camp_type']; }
        if (!empty($args['search'])) {
            $q = '%' . $args['search'] . '%';
            $where  .= ' AND (l.name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)';
            $params  = array_merge($params, [$q, $q, $q]);
        }

        $limit  = (int)(isset($args['per_page']) ? $args['per_page'] : 50);
        $offset = ((int)(isset($args['page']) ? $args['page'] : 1) - 1) * $limit;

        $params[] = $limit;
        $params[] = $offset;

        return DB::rows(
            "SELECT l.*, c.name AS campaign_name, c.type AS campaign_type, o.name AS operator_name
             FROM " . DB::t('leads') . " l
             LEFT JOIN " . DB::t('campaigns') . " c ON c.id = l.campaign_id
             LEFT JOIN " . DB::t('operators')  . " o ON o.id = l.operator_id
             WHERE {$where} ORDER BY l.id DESC LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function count($args = [])
    {
        $where  = '1=1';
        $params = [];
        if (!empty($args['campaign_id'])) { $where .= ' AND l.campaign_id = ?'; $params[] = $args['campaign_id']; }
        if (!empty($args['operator_id'])) { $where .= ' AND l.operator_id = ?'; $params[] = $args['operator_id']; }
        if (!empty($args['status']))      { $where .= ' AND l.status = ?';       $params[] = $args['status']; }
        if (isset($args['is_double']))    { $where .= ' AND l.is_double = ?';    $params[] = (int)$args['is_double']; }

        if (!empty($args['camp_type'])) {
            // Need join for camp_type filter
            $where .= ' AND c.type = ?';
            $params[] = $args['camp_type'];
            return (int)DB::val(
                "SELECT COUNT(*) FROM " . DB::t('leads') . " l
                 LEFT JOIN " . DB::t('campaigns') . " c ON c.id = l.campaign_id
                 WHERE {$where}",
                $params
            );
        }

        // Simple count without join (replace l. prefix)
        $where = str_replace('l.', '', $where);
        return DB::count('leads', $where, $params);
    }

    public static function updateStatus($id, $status, $note = '', $operatorId = null)
    {
        $allowed = ['new','contacted','purchased','cancelled','blocked'];
        if (!in_array($status, $allowed)) return false;

        $data  = ['status' => $status, 'status_note' => $note, 'updated_at' => date('Y-m-d H:i:s')];
        $where = ['id' => (int)$id];
        if ($operatorId) $where['operator_id'] = (int)$operatorId;

        return DB::update('leads', $data, $where) > 0;
    }

    public static function markDouble($id)
    {
        DB::update('leads', ['is_double' => 1], ['id' => (int)$id]);
    }

    public static function markFollowedUp($id)
    {
        DB::update('leads', ['followed_up_at' => date('Y-m-d H:i:s')], ['id' => (int)$id]);
    }

    public static function delete($id)
    {
        DB::delete('leads', ['id' => (int)$id]);
    }

    public static function decrypt($lead)
    {
        if (Settings::get('encrypt_lead_data', '1') !== '1') return $lead;
        $lead->name    = Crypto::decrypt(isset($lead->name)    ? $lead->name    : '');
        $lead->email   = Crypto::decrypt(isset($lead->email)   ? $lead->email   : '');
        $lead->phone   = Crypto::decrypt(isset($lead->phone)   ? $lead->phone   : '');
        $lead->address = Crypto::decrypt(isset($lead->address) ? $lead->address : '');
        return $lead;
    }

    public static function exportCsv($args = [])
    {
        $args['per_page'] = 99999;
        $leads = self::all($args);

        $isLink = !empty($args['camp_type']) && $args['camp_type'] === 'wa_link';

        if ($isLink) {
            $rows = [['ID','Kampanye','Operator','IP Address','Perangkat','Browser','Cookie ID','Source URL','Referrer','Status','Waktu Klik']];
            foreach ($leads as $lead) {
                $ua = isset($lead->user_agent) ? $lead->user_agent : '';
                $dev = preg_match('/Mobile|Android|iPhone|iPad/i', $ua) ? 'Mobile' : 'Desktop';
                $br  = 'Browser';
                if (preg_match('/Chrome\/(\d+)/i', $ua, $m))      $br = 'Chrome '.$m[1];
                elseif (preg_match('/Firefox\/(\d+)/i', $ua, $m)) $br = 'Firefox '.$m[1];
                elseif (preg_match('/Edg\/(\d+)/i', $ua, $m))     $br = 'Edge '.$m[1];
                elseif (preg_match('/Safari\//i', $ua))            $br = 'Safari';
                $rows[] = [
                    $lead->id,
                    isset($lead->campaign_name) ? $lead->campaign_name : '',
                    isset($lead->operator_name) ? $lead->operator_name : '',
                    $lead->ip_address,
                    $dev,
                    $br,
                    isset($lead->cookie_id) ? $lead->cookie_id : '',
                    isset($lead->source_url) ? $lead->source_url : '',
                    isset($lead->referrer) ? $lead->referrer : '',
                    $lead->status,
                    $lead->created_at,
                ];
            }
        } else {
            $rows = [['ID','Kampanye','Operator','Nama','Email','No HP','Alamat','Jumlah','Pesan','Status','Duplikat','IP','User Agent','Tanggal']];
            foreach ($leads as $lead) {
                $l = self::decrypt($lead);
                $rows[] = [
                    $l->id,
                    isset($l->campaign_name) ? $l->campaign_name : '',
                    isset($l->operator_name) ? $l->operator_name : '',
                    $l->name, $l->email, $l->phone, $l->address,
                    $l->quantity, $l->custom_message,
                    $l->status,
                    $l->is_double ? 'Ya' : 'Tidak',
                    $l->ip_address,
                    isset($l->user_agent) ? $l->user_agent : '',
                    $l->created_at,
                ];
            }
        }
        return $rows;
    }

    private static function resolveVid($vidFromBody = '')
    {
        $vid = isset($_COOKIE['konektor_vid']) ? $_COOKIE['konektor_vid'] : '';
        if (!$vid && $vidFromBody) $vid = $vidFromBody;
        return substr(strip_tags($vid), 0, 128);
    }
}
