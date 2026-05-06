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
        $total  = DB::count('leads', 'operator_id = ?', [$operator->operator_id]);

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
        echo json_encode(['success' => $ok]);
    }
}
