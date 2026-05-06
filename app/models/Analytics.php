<?php

class Analytics
{
    public static function logEvent($campaignId, $type, $leadId = null)
    {
        $allowed = ['page_load','form_view','form_submit','thanks_page','wa_click'];
        if (!in_array($type, $allowed)) return;

        DB::insert('events', [
            'campaign_id' => (int)$campaignId,
            'event_type'  => $type,
            'lead_id'     => $leadId ? (int)$leadId : null,
            'ip_address'  => Helper::getClientIp(),
            'referrer'    => substr(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '', 0, 2000),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public static function getCampaignStats($campaignId, $days = 30)
    {
        $since = date('Y-m-d', strtotime("-{$days} days"));

        $events = DB::rows(
            "SELECT event_type, COUNT(*) AS cnt FROM " . DB::t('events') . "
             WHERE campaign_id = ? AND DATE(created_at) >= ? GROUP BY event_type",
            [(int)$campaignId, $since]
        );

        $stat = ['page_load'=>0,'form_view'=>0,'form_submit'=>0,'thanks_page'=>0,'wa_click'=>0];
        foreach ($events as $e) $stat[$e->event_type] = (int)$e->cnt;

        $leads     = Lead::count(['campaign_id' => (int)$campaignId]);
        $purchased = Lead::count(['campaign_id' => (int)$campaignId, 'status' => 'purchased']);
        $doubles   = Lead::count(['campaign_id' => (int)$campaignId, 'is_double' => 1]);

        return array_merge($stat, compact('leads','purchased','doubles'));
    }

    public static function getDailyLeads($campaignId = 0, $days = 30)
    {
        $since  = date('Y-m-d', strtotime("-{$days} days"));
        $where  = 'DATE(created_at) >= ?';
        $params = [$since];
        if ($campaignId) { $where .= ' AND campaign_id = ?'; $params[] = (int)$campaignId; }

        $rows = DB::rows(
            "SELECT DATE(created_at) AS date, COUNT(*) AS leads
             FROM " . DB::t('leads') . " WHERE {$where} GROUP BY DATE(created_at) ORDER BY date ASC",
            $params
        );

        $map = [];
        foreach ($rows as $r) $map[$r->date] = (int)$r->leads;

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d     = date('Y-m-d', strtotime("-{$i} days"));
            $out[] = ['date' => $d, 'leads' => isset($map[$d]) ? $map[$d] : 0];
        }
        return $out;
    }

    public static function getOverallStats()
    {
        $total     = Lead::count();
        $today     = (int)(DB::val("SELECT COUNT(*) FROM " . DB::t('leads') . " WHERE DATE(created_at) = CURDATE()") ?: 0);
        $purchased = Lead::count(['status' => 'purchased']);
        $campaigns = DB::count('campaigns', "status = 'active'");
        $operators = DB::count('operators', "status = 'on'");

        return compact('total','today','purchased','campaigns','operators');
    }

    public static function getOperatorStats($operatorIds = [])
    {
        if (empty($operatorIds)) {
            $ops = Operator::all();
            $operatorIds = array_column($ops, 'id');
        }
        if (empty($operatorIds)) return [];

        $phs = implode(',', array_fill(0, count($operatorIds), '?'));
        return DB::rows(
            "SELECT o.id, o.name,
                COUNT(l.id)               AS total,
                SUM(l.status='new')       AS new_leads,
                SUM(l.status='contacted') AS contacted,
                SUM(l.status='purchased') AS purchased
             FROM " . DB::t('operators') . " o
             LEFT JOIN " . DB::t('leads') . " l ON l.operator_id = o.id
             WHERE o.id IN ({$phs}) GROUP BY o.id",
            $operatorIds
        );
    }
}
