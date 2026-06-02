<?php

class Rotator
{
    public static function pick($campaignId)
    {
        $campaignId = (int)$campaignId;

        // Baca dist_mode dari form_config campaign
        $campaign = Campaign::find($campaignId);
        $formCfg  = $campaign ? Campaign::getFormConfig($campaign) : [];
        $distMode = isset($formCfg['_dist_mode']) ? $formCfg['_dist_mode'] : 'proportional';

        // Ambil operator aktif + bobot + jumlah lead (semua termasuk double, untuk distribusi akurat)
        $rows = DB::rows(
            "SELECT o.*, co.weight,
                    (SELECT COUNT(*) FROM " . DB::t('leads') . " l
                     WHERE l.campaign_id = ? AND l.operator_id = o.id) AS lead_count
             FROM " . DB::t('operators') . " o
             JOIN " . DB::t('campaign_operators') . " co ON co.operator_id = o.id
             WHERE co.campaign_id = ? AND o.status = 'on'
             ORDER BY o.id ASC",
            [$campaignId, $campaignId]
        );

        if (empty($rows)) return null;

        // Pisahkan operator yang sedang bertugas; fallback ke semua jika tidak ada
        $available = array_values(array_filter($rows, function($o) {
            return Operator::isOnDuty($o);
        }));
        if (empty($available)) $available = $rows;

        if ($distMode === 'roundrobin') {
            // Pilih operator dengan lead_count terkecil (giliran berurutan)
            $selected = $available[0];
            foreach ($available as $op) {
                if ((int)$op->lead_count < (int)$selected->lead_count) {
                    $selected = $op;
                }
            }
            return $selected;
        }

        // Proportional weighted: pilih operator dengan rasio lead_count/weight terkecil
        // Tie-breaking: jika ratio sama, pilih yang lead_count absolutnya lebih kecil
        // (cegah operator pertama selalu menang saat semua ratio 0)
        $selected  = null;
        $minRatio  = PHP_INT_MAX;
        $minCount  = PHP_INT_MAX;
        foreach ($available as $op) {
            $w     = max(1, min(10, (int)$op->weight));
            $count = (int)$op->lead_count;
            $ratio = $count / $w;

            if ($ratio < $minRatio || ($ratio === $minRatio && $count < $minCount)) {
                $minRatio = $ratio;
                $minCount = $count;
                $selected = $op;
            }
        }

        return $selected;
    }

    public static function getFollowupUrl($campaign, $lead, $operator = null)
    {
        if (empty($lead->phone)) return '';

        $message = Helper::parseShortcodes(isset($campaign->followup_message) ? $campaign->followup_message : '', [
            'name'           => isset($lead->name)           ? $lead->name           : '',
            'email'          => isset($lead->email)          ? $lead->email          : '',
            'phone'          => isset($lead->phone)          ? $lead->phone          : '',
            'address'        => isset($lead->address)        ? $lead->address        : '',
            'custom_message' => isset($lead->custom_message) ? $lead->custom_message : '',
            'product_name'   => isset($campaign->product_name) ? $campaign->product_name : '',
            'quantity'       => isset($lead->quantity)       ? $lead->quantity       : '',
            'operator_name'  => $operator ? $operator->name : '',
        ]);

        return Helper::waUrl($lead->phone, $message);
    }

    public static function getRedirectUrl($operator, $campaign, $leadData)
    {
        $thanksConfig = Campaign::getThanksConfig($campaign);
        $customMsg    = isset($thanksConfig['custom_message']) ? $thanksConfig['custom_message'] : '';
        $message      = Helper::parseShortcodes($customMsg, array_merge($leadData, [
            'operator_name' => isset($operator->name) ? $operator->name : '',
        ]));

        switch ($operator->type) {
            case 'whatsapp':
                return Helper::waUrl($operator->value, $message);
            case 'email':
                $productName = isset($leadData['product_name']) ? $leadData['product_name'] : 'produk Anda';
                $subject     = rawurlencode('Halo, saya tertarik dengan ' . $productName);
                return 'mailto:' . $operator->value . '?subject=' . $subject . '&body=' . rawurlencode($message);
            case 'telegram':
                return 'https://t.me/' . ltrim($operator->value, '@');
            case 'line':
                return 'https://line.me/R/ti/p/' . ltrim($operator->value, '@');
            default:
                return '';
        }
    }
}
