<?php

class Rotator
{
    public static function pick($campaignId)
    {
        $campaignId = (int)$campaignId;

        $campaign = Campaign::find($campaignId);
        $formCfg  = $campaign ? Campaign::getFormConfig($campaign) : [];
        $distMode = isset($formCfg['_dist_mode']) ? $formCfg['_dist_mode'] : 'proportional';

        $rows = DB::rows(
            "SELECT o.*, co.weight
             FROM " . DB::t('operators') . " o
             JOIN " . DB::t('campaign_operators') . " co ON co.operator_id = o.id
             WHERE co.campaign_id = ? AND o.status = 'on'
             ORDER BY o.id ASC",
            [$campaignId]
        );

        if (empty($rows)) return null;

        $available = array_values(array_filter($rows, function($o) {
            return Operator::isOnDuty($o);
        }));
        if (empty($available)) $available = array_values($rows);

        return self::swrr($available, $campaignId, $distMode === 'roundrobin');
    }

    /**
     * Weighted Round-Robin berbasis lead count per operator.
     *
     * Bangun siklus dari operator yang tersedia (available) saja,
     * hitung total lead yang sudah diterima oleh operator-operator tsb,
     * lalu tentukan giliran berikutnya dari posisi siklus.
     *
     * Contoh Aeni(w=3), Lina(w=2):
     *   Siklus: [Aeni, Aeni, Aeni, Lina, Lina] (total 5 slot)
     *   Total lead Aeni+Lina = N → posisi = N mod 5 → operator di slot tsb
     *
     * Dengan cara ini: kalau operator berubah (on/off duty), hitungan
     * hanya dari operator yang aktif — tidak ada posisi yang kacau.
     */
    private static function swrr(array $ops, $campaignId, $forceEqual = false)
    {
        if (count($ops) === 1) return $ops[0];

        // Kumpulkan operator_id yang available
        $opIds = array_map(function($o) { return (int)$o->id; }, $ops);
        $placeholders = implode(',', array_fill(0, count($opIds), '?'));

        // Hitung total lead dari operator-operator yang available saja (per kampanye ini)
        $totalLeads = (int)DB::val(
            "SELECT COUNT(*) FROM " . DB::t('leads') . "
             WHERE campaign_id = ? AND operator_id IN ({$placeholders})",
            array_merge([$campaignId], $opIds)
        );

        // Bangun siklus slot sesuai bobot operator available
        // Aeni(w=3), Lina(w=2) → [Aeni, Aeni, Aeni, Lina, Lina]
        $cycle = [];
        foreach ($ops as $op) {
            $w = $forceEqual ? 1 : max(1, min(10, (int)$op->weight));
            for ($i = 0; $i < $w; $i++) {
                $cycle[] = $op;
            }
        }

        $pos = $totalLeads % count($cycle);
        return $cycle[$pos];
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
