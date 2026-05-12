<?php

class Campaign
{
    // ─── CRUD ───────────────────────────────────────────────────────────────

    public static function all($args = [])
    {
        $where  = '1=1';
        $params = [];

        if (!empty($args['type']))   { $where .= ' AND type = ?';   $params[] = $args['type']; }
        if (!empty($args['status'])) { $where .= ' AND status = ?'; $params[] = $args['status']; }

        return DB::rows("SELECT * FROM " . DB::t('campaigns') . " WHERE {$where} ORDER BY id DESC", $params);
    }

    public static function find($id)
    {
        return DB::row("SELECT * FROM " . DB::t('campaigns') . " WHERE id = ?", [(int)$id]);
    }

    public static function findBySlug($slug)
    {
        return DB::row(
            "SELECT * FROM " . DB::t('campaigns') . " WHERE slug = ? AND status = 'active'",
            [Helper::slug($slug)]
        );
    }

    public static function save($data, $id = 0)
    {
        $id   = (int)$id;
        $name = isset($data['name']) ? $data['name'] : '';
        $slug = !empty($data['slug']) ? Helper::slug($data['slug']) : self::uniqueSlug($name, $id);
        if (empty($slug)) $slug = self::uniqueSlug($name ?: 'campaign', $id);

        $type   = isset($data['type']) ? $data['type'] : 'form';
        $status = isset($data['status']) ? $data['status'] : 'active';

        $row = [
            'name'                => substr(strip_tags($name), 0, 255),
            'slug'                => $slug,
            'type'                => in_array($type, ['form','wa_link']) ? $type : 'form',
            'store_name'          => substr(strip_tags(isset($data['store_name'])   ? $data['store_name']   : ''), 0, 255),
            'product_name'        => substr(strip_tags(isset($data['product_name']) ? $data['product_name'] : ''), 0, 255),
            'form_config'         => self::encodeJsonField($data['form_config']        ?? null),
            'thanks_page_config'  => self::encodeJsonField($data['thanks_page_config'] ?? null),
            'pixel_config'        => self::encodeJsonField($data['pixel_config']       ?? null),
            'double_lead_enabled' => !empty($data['double_lead_enabled']) ? 1 : 0,
            'double_lead_message' => strip_tags(isset($data['double_lead_message']) ? $data['double_lead_message'] : ''),
            'followup_message'    => strip_tags(isset($data['followup_message'])    ? $data['followup_message']    : ''),
            'allowed_domains'     => self::encodeJsonField($data['allowed_domains'] ?? null) ?: '[]',
            'block_enabled'       => !empty($data['block_enabled']) ? 1 : 0,
            'block_message'       => strip_tags(isset($data['block_message']) ? $data['block_message'] : ''),
            'status'              => in_array($status, ['active','inactive']) ? $status : 'active',
        ];

        if ($id > 0) {
            $row['updated_at'] = date('Y-m-d H:i:s');
            DB::update('campaigns', $row, ['id' => $id]);
        } else {
            $id = DB::insert('campaigns', $row);
        }

        // operators may come as {mode,operators:[]} or plain array
        $opList  = null;
        $distMode = 'proportional';
        if (isset($data['operators'])) {
            $raw = $data['operators'];
            if (is_array($raw) && isset($raw['operators'])) {
                // new format: {mode, operators:[]}
                $distMode = isset($raw['mode']) ? $raw['mode'] : 'proportional';
                $opList   = is_array($raw['operators']) ? $raw['operators'] : [];
            } elseif (is_array($raw)) {
                $opList = $raw;
            }
        }
        if ($opList !== null) {
            DB::query("DELETE FROM " . DB::t('campaign_operators') . " WHERE campaign_id = ?", [$id]);
            foreach ($opList as $op) {
                // In roundrobin mode all weights equal 1; proportional uses actual weight
                $w = ($distMode === 'roundrobin') ? 1 : max(1, min(10, (int)(isset($op['weight']) ? $op['weight'] : 1)));
                DB::insert('campaign_operators', [
                    'campaign_id' => $id,
                    'operator_id' => (int)$op['id'],
                    'weight'      => $w,
                ]);
            }
        }

        return $id;
    }

    public static function delete($id)
    {
        DB::delete('campaigns', ['id' => (int)$id]);
        DB::query("DELETE FROM " . DB::t('campaign_operators') . " WHERE campaign_id = ?", [(int)$id]);
    }

    public static function duplicate($id)
    {
        $orig = self::find($id);
        if (!$orig) return 0;

        $data = (array) $orig;
        unset($data['id'], $data['created_at'], $data['updated_at'], $data['slug']);
        $data['name'] = $data['name'] . ' (Copy)';

        $newId = self::save($data);

        // Copy operators (getOperators JOINs operators table so use o.id, not operator_id)
        $ops = self::getOperators($id);
        foreach ($ops as $op) {
            $opId = (int)$op->id;
            if ($opId <= 0) continue;
            DB::insert('campaign_operators', [
                'campaign_id' => $newId,
                'operator_id' => $opId,
                'weight'      => (int)$op->weight,
            ]);
        }

        return $newId;
    }

    public static function getOperators($campaignId)
    {
        return DB::rows(
            "SELECT o.*, co.weight FROM " . DB::t('operators') . " o
             JOIN " . DB::t('campaign_operators') . " co ON co.operator_id = o.id
             WHERE co.campaign_id = ? ORDER BY o.id ASC",
            [(int)$campaignId]
        );
    }

    // ─── Internal helpers ────────────────────────────────────────────────────

    /**
     * Safely JSON-encode a field that may already be a JSON string (e.g. from a DB row cast to array).
     * Prevents double-encoding when duplicating campaigns.
     */
    private static function encodeJsonField($value)
    {
        if (empty($value)) return null;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Decode a JSON field, handling double-encoded strings (from old duplicate bug).
     * Always returns an array.
     */
    public static function decodeJsonField($value)
    {
        if (empty($value)) return [];
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) return [];
        // Handle double-encoded: result is still a string
        if (is_string($decoded)) {
            $decoded2 = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded2)) return $decoded2;
            return [];
        }
        return is_array($decoded) ? $decoded : [];
    }

    // ─── Config helpers ─────────────────────────────────────────────────────

    public static function getPixelConfig($campaign)
    {
        return self::decodeJsonField($campaign->pixel_config ?? null);
    }

    public static function getFormConfig($campaign)
    {
        $cfg = self::decodeJsonField($campaign->form_config ?? null);
        $def = self::defaultFormConfig();
        if (empty($cfg['fields']))       $cfg['fields']       = $def['fields'];
        if (empty($cfg['template']))     $cfg['template']     = $def['template'];
        if (empty($cfg['submit_label'])) $cfg['submit_label'] = $def['submit_label'];
        return $cfg;
    }

    public static function getThanksConfig($campaign)
    {
        $cfg = self::decodeJsonField($campaign->thanks_page_config ?? null);
        $def = self::defaultThanksConfig();
        foreach ($def as $k => $v) {
            if (!isset($cfg[$k])) $cfg[$k] = $v;
        }
        return $cfg;
    }

    public static function defaultFormConfig()
    {
        return [
            'template'     => 'modern',
            'submit_label' => 'Kirim Sekarang',
            'size'         => 'default',
            'fields'       => [
                ['name'=>'name',           'label'=>'Nama',        'type'=>'text',     'required'=>true,  'enabled'=>true,  'placeholder'=>'Nama lengkap Anda'],
                ['name'=>'phone',          'label'=>'No HP/WA',    'type'=>'tel',      'required'=>true,  'enabled'=>true,  'placeholder'=>'08xxx'],
                ['name'=>'email',          'label'=>'Email',       'type'=>'email',    'required'=>false, 'enabled'=>false, 'placeholder'=>'email@domain.com'],
                ['name'=>'address',        'label'=>'Alamat',      'type'=>'textarea', 'required'=>false, 'enabled'=>false, 'placeholder'=>'Alamat lengkap'],
                ['name'=>'quantity',       'label'=>'Jumlah',      'type'=>'number',   'required'=>false, 'enabled'=>false, 'placeholder'=>'1'],
                ['name'=>'custom_message', 'label'=>'Pesan',       'type'=>'textarea', 'required'=>false, 'enabled'=>false, 'placeholder'=>'Pesan Anda...'],
            ],
            'custom_style' => [],
            'extra_fields' => [],
        ];
    }

    public static function defaultThanksConfig()
    {
        return [
            'template'         => 'modern',
            'description'      => 'Terima kasih! Pesanan Anda sedang kami proses.',
            'redirect_type'    => 'cs',
            'redirect_url'     => '',
            'redirect_cs_type' => 'whatsapp',
            'custom_message'   => 'Halo [oname], saya [cname] ingin memesan [product] sebanyak [quantity].',
            'delay_redirect'   => 3,
            'show_countdown'   => true,
            'theme'            => [
                'bg_color'      => '#f0fdf4',
                'card_color'    => '#ffffff',
                'accent_color'  => '#16a34a',
                'text_color'    => '#0f172a',
                'icon'          => '',
                'heading'       => 'Pesanan Berhasil!',
            ],
        ];
    }

    // ─── Slug generation ────────────────────────────────────────────────────

    private static function uniqueSlug($name, $excludeId = 0)
    {
        $base = Helper::slug($name) ?: 'campaign';
        $slug = $base;
        $i    = 1;
        while (true) {
            $exists = DB::val(
                "SELECT id FROM " . DB::t('campaigns') . " WHERE slug = ? AND id != ?",
                [$slug, (int)$excludeId]
            );
            if (!$exists) break;
            $slug = $base . '-' . ($i++);
        }
        return $slug;
    }
}
