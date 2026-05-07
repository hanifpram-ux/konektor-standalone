<?php

class CsController
{
    public static function panel($params)
    {
        $token    = isset($params['token']) ? $params['token'] : '';
        $operator = Auth::resolveOperatorToken($token);
        if (!$operator) { http_response_code(403); echo 'Invalid or expired token.'; return; }

        $page   = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $leads  = Operator::getLeads($operator->operator_id, 30, ($page - 1) * 30);
        $total  = Lead::count(['operator_id' => $operator->operator_id, 'camp_type' => 'form']);

        $leads = array_map(function($l) { return Lead::decrypt($l); }, $leads);

        include KONEKTOR_ROOT . '/public/cs-panel.php';
    }

    public static function updateLead($params)
    {
        header('Content-Type: application/json; charset=utf-8');
        $token    = isset($params['token']) ? $params['token'] : '';
        $operator = Auth::resolveOperatorToken($token);
        if (!$operator) { http_response_code(403); echo json_encode(['success'=>false]); return; }

        $raw    = file_get_contents('php://input');
        $data   = $raw ? (json_decode($raw, true) ?: []) : [];
        $leadId = (int)(isset($data['lead_id']) ? $data['lead_id'] : 0);
        $status = isset($data['status']) ? $data['status'] : '';
        $note   = strip_tags(isset($data['note']) ? $data['note'] : '');

        $ok = Lead::updateStatus($leadId, $status, $note, $operator->operator_id);
        if ($ok) {
            Helper::fireStatusEvent($leadId, $status);
        }
        echo json_encode(['success' => $ok]);
    }

    public static function blockLead($params)
    {
        header('Content-Type: application/json; charset=utf-8');
        $token    = isset($params['token']) ? $params['token'] : '';
        $operator = Auth::resolveOperatorToken($token);
        if (!$operator) { http_response_code(403); echo json_encode(['success'=>false]); return; }

        $raw    = file_get_contents('php://input');
        $data   = $raw ? (json_decode($raw, true) ?: []) : [];
        $leadId = (int)(isset($data['lead_id']) ? $data['lead_id'] : 0);
        $blockBy = in_array($data['block_by']??'both', ['ip','cookie','both']) ? ($data['block_by']??'both') : 'both';
        $reason  = strip_tags($data['reason'] ?? 'Blocked by CS');

        $lead = Lead::find($leadId);
        if (!$lead || $lead->operator_id != $operator->operator_id) {
            echo json_encode(['success'=>false, 'message'=>'Lead tidak ditemukan.']); return;
        }
        $decr = Lead::decrypt(clone $lead);
        Blocker::block([
            'campaign_id'   => $lead->campaign_id,
            'ip_address'    => $blockBy !== 'cookie' ? $lead->ip_address : null,
            'fingerprint'   => $blockBy !== 'cookie' ? $lead->fingerprint : null,
            'cookie_id'     => $blockBy !== 'ip'     ? $lead->cookie_id   : null,
            'phone'         => $decr->phone ?? '',
            'email'         => $decr->email ?? '',
            'reason'        => $reason,
            'blocked_by'    => null,
            'operator_name' => $operator->name ?? 'CS',
            'block_by'      => $blockBy,
        ]);
        Lead::updateStatus($leadId, 'blocked');
        echo json_encode(['success' => true]);
    }

    public static function unblockLead($params)
    {
        header('Content-Type: application/json; charset=utf-8');
        $token    = isset($params['token']) ? $params['token'] : '';
        $operator = Auth::resolveOperatorToken($token);
        if (!$operator) { http_response_code(403); echo json_encode(['success'=>false]); return; }

        $raw    = file_get_contents('php://input');
        $data   = $raw ? (json_decode($raw, true) ?: []) : [];
        $leadId = (int)(isset($data['lead_id']) ? $data['lead_id'] : 0);

        $lead = Lead::find($leadId);
        if (!$lead || $lead->operator_id != $operator->operator_id) {
            echo json_encode(['success'=>false, 'message'=>'Lead tidak ditemukan.']); return;
        }
        if ($lead->ip_address) {
            DB::query("DELETE FROM " . DB::t('blocked') . " WHERE ip_address = ? AND (campaign_id = ? OR campaign_id IS NULL)", [$lead->ip_address, $lead->campaign_id]);
        }
        if ($lead->cookie_id) {
            DB::query("DELETE FROM " . DB::t('blocked') . " WHERE cookie_id = ? AND (campaign_id = ? OR campaign_id IS NULL)", [$lead->cookie_id, $lead->campaign_id]);
        }
        Lead::updateStatus($leadId, 'new');
        echo json_encode(['success' => true]);
    }

    public static function followUp($params)
    {
        header('Content-Type: application/json; charset=utf-8');
        $token    = isset($params['token']) ? $params['token'] : '';
        $operator = Auth::resolveOperatorToken($token);
        if (!$operator) { http_response_code(403); echo json_encode(['success'=>false]); return; }

        $raw    = file_get_contents('php://input');
        $data   = $raw ? (json_decode($raw, true) ?: []) : [];
        $leadId = (int)(isset($data['lead_id']) ? $data['lead_id'] : 0);

        // Get lead and campaign for WA URL
        $lead = Lead::find($leadId);
        if (!$lead || $lead->operator_id != $operator->operator_id) {
            echo json_encode(['success'=>false, 'message'=>'Lead tidak ditemukan.']);
            return;
        }

        $decrypted = Lead::decrypt(clone $lead);
        $campaign  = Campaign::find($lead->campaign_id);
        $opObj     = Operator::find($lead->operator_id);

        // Build follow-up URL to customer (WA or Email depending on available contact)
        $followUrl = '';
        $followType = '';
        $template = $campaign ? $campaign->followup_message : '';
        if (!$template) {
            $thanksCfg = Campaign::getThanksConfig($campaign);
            $template = isset($thanksCfg['custom_message']) ? $thanksCfg['custom_message'] : '';
        }
        if (!$template && $opObj && $opObj->type === 'whatsapp') {
            $template = 'Halo [name], terima kasih sudah tertarik dengan [product]. Ada yang bisa kami bantu?';
        }
        $vars = array_merge((array)$decrypted, ['product_name' => $campaign ? $campaign->product_name : '', 'operator_name' => $opObj ? $opObj->name : '']);
        $msg  = Helper::parseShortcodes($template, $vars);

        if ($decrypted->phone) {
            $followUrl = Helper::waUrl($decrypted->phone, $msg);
            $followType = 'wa';
        } elseif ($decrypted->email) {
            $subject = urlencode('Follow-up: ' . ($campaign ? $campaign->product_name : ''));
            $followUrl = 'mailto:' . urlencode($decrypted->email) . '?subject=' . $subject . '&body=' . urlencode($msg);
            $followType = 'email';
        }

        Lead::markFollowedUp($leadId);
        echo json_encode(['success' => true, 'url' => $followUrl, 'type' => $followType]);
    }
}
