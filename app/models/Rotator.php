<?php

class Rotator
{
    public static function pick($campaignId)
    {
        $rows = DB::rows(
            "SELECT o.*, co.weight FROM " . DB::t('operators') . " o
             JOIN " . DB::t('campaign_operators') . " co ON co.operator_id = o.id
             WHERE co.campaign_id = ? AND o.status = 'on' ORDER BY o.id ASC",
            [$campaignId]
        );

        if (empty($rows)) return null;

        $available = array_values(array_filter($rows, function($o) {
            return Operator::isOnDuty($o);
        }));
        if (empty($available)) $available = $rows;

        $pool = [];
        foreach ($available as $op) {
            $w = max(1, min(10, (int)$op->weight));
            for ($i = 0; $i < $w; $i++) $pool[] = $op;
        }

        return $pool[array_rand($pool)];
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
