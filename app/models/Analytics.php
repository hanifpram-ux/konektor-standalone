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

    /**
     * Normalize date range. Returns [$dateFrom, $dateTo] strings 'Y-m-d'.
     * If $dateFrom/$dateTo provided, use them; otherwise fall back to $days.
     */
    public static function resolveDateRange($days = 30, $dateFrom = null, $dateTo = null)
    {
        $to   = ($dateTo   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   ? $dateTo   : date('Y-m-d');
        $from = ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) ? $dateFrom : date('Y-m-d', strtotime("-{$days} days", strtotime($to)));
        if ($from > $to) [$from, $to] = [$to, $from];
        return [$from, $to];
    }

    public static function getCampaignStats($campaignId, $days = 30, $dateFrom = null, $dateTo = null)
    {
        [$from, $to] = self::resolveDateRange($days, $dateFrom, $dateTo);

        $events = DB::rows(
            "SELECT event_type, COUNT(*) AS cnt FROM " . DB::t('events') . "
             WHERE campaign_id = ? AND DATE(created_at) BETWEEN ? AND ? GROUP BY event_type",
            [(int)$campaignId, $from, $to]
        );

        $stat = ['page_load'=>0,'form_view'=>0,'form_submit'=>0,'thanks_page'=>0,'wa_click'=>0];
        foreach ($events as $e) $stat[$e->event_type] = (int)$e->cnt;

        $leads     = Lead::count(['campaign_id' => (int)$campaignId]);
        $purchased = Lead::count(['campaign_id' => (int)$campaignId, 'status' => 'purchased']);
        $doubles   = Lead::count(['campaign_id' => (int)$campaignId, 'is_double' => 1]);

        return array_merge($stat, compact('leads','purchased','doubles'));
    }

    public static function getDailyLeads($campaignId = 0, $days = 30, $dateFrom = null, $dateTo = null)
    {
        [$from, $to] = self::resolveDateRange($days, $dateFrom, $dateTo);

        $where  = 'DATE(created_at) BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($campaignId) { $where .= ' AND campaign_id = ?'; $params[] = (int)$campaignId; }

        $rows = DB::rows(
            "SELECT DATE(created_at) AS date, COUNT(*) AS leads
             FROM " . DB::t('leads') . " WHERE {$where} GROUP BY DATE(created_at) ORDER BY date ASC",
            $params
        );

        $map = [];
        foreach ($rows as $r) $map[$r->date] = (int)$r->leads;

        // Fill all days in range
        $out  = [];
        $cur  = strtotime($from);
        $end  = strtotime($to);
        while ($cur <= $end) {
            $d     = date('Y-m-d', $cur);
            $out[] = ['date' => $d, 'leads' => isset($map[$d]) ? $map[$d] : 0];
            $cur   = strtotime('+1 day', $cur);
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
