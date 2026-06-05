<?php

class Operator
{
    public static function all($args = [])
    {
        $where  = '1=1';
        $params = [];
        if (!empty($args['status'])) { $where .= ' AND status = ?'; $params[] = $args['status']; }
        return DB::rows("SELECT * FROM " . DB::t('operators') . " WHERE {$where} ORDER BY name ASC", $params);
    }

    public static function find($id)
    {
        return DB::row("SELECT * FROM " . DB::t('operators') . " WHERE id = ?", [(int)$id]);
    }

    public static function findByTelegramChatId($chatId)
    {
        $chatId = trim((string)$chatId);
        if ($chatId === '') {
            return null;
        }
        return DB::row("SELECT * FROM " . DB::t('operators') . " WHERE telegram_chat_id = ? LIMIT 1", [$chatId]);
    }

    public static function save($data, $id = 0)
    {
        $id   = (int)$id;
        $type = isset($data['type']) ? $data['type'] : 'whatsapp';
        $st   = isset($data['status']) ? $data['status'] : 'on';

        $row = [
            'name'               => substr(strip_tags(isset($data['name'])   ? $data['name']   : ''), 0, 255),
            'type'               => in_array($type, ['whatsapp','email','telegram','line']) ? $type : 'whatsapp',
            'value'              => strip_tags(isset($data['value'])          ? $data['value']          : ''),
            'status'             => in_array($st, ['on','off']) ? $st : 'on',
            'work_hours_enabled' => !empty($data['work_hours_enabled']) ? 1 : 0,
            'work_hours'         => !empty($data['work_hours']) ? json_encode($data['work_hours']) : null,
            'telegram_chat_id'   => strip_tags(isset($data['telegram_chat_id']) ? $data['telegram_chat_id'] : ''),
            'notes'              => strip_tags(isset($data['notes'])             ? $data['notes']             : ''),
        ];

        if ($id > 0) {
            DB::update('operators', $row, ['id' => $id]);
            return $id;
        }
        return DB::insert('operators', $row);
    }

    public static function delete($id)
    {
        $id = (int)$id;
        DB::delete('operators', ['id' => $id]);
        DB::query("DELETE FROM " . DB::t('campaign_operators') . " WHERE operator_id = ?", [$id]);
        DB::query("DELETE FROM " . DB::t('operator_tokens')    . " WHERE operator_id = ?", [$id]);
    }

    public static function isOnDuty($operator)
    {
        if (!$operator->work_hours_enabled) return true;

        $schedule = $operator->work_hours ? json_decode($operator->work_hours, true) : [];
        if (empty($schedule)) return true;

        $now     = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $dayMap  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        $dayName = $dayMap[(int)$now->format('w')];
        $curTime = $now->format('H:i');

        foreach ($schedule as $slot) {
            if ((isset($slot['day']) ? $slot['day'] : '') === $dayName && isset($slot['start'], $slot['end'])) {
                if ($curTime >= $slot['start'] && $curTime <= $slot['end']) return true;
            }
        }
        return false;
    }

    public static function getToken($operatorId)
    {
        $existing = DB::val(
            "SELECT token FROM " . DB::t('operator_tokens') . " WHERE operator_id = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1",
            [(int)$operatorId]
        );
        if ($existing) return (string)$existing;
        return Auth::generateOperatorToken((int)$operatorId);
    }

    public static function getLeads($operatorId, $limit = 50, $offset = 0)
    {
        return DB::rows(
            "SELECT l.*, c.name AS campaign_name, c.product_name FROM " . DB::t('leads') . " l
             JOIN " . DB::t('campaigns') . " c ON c.id = l.campaign_id
             WHERE l.operator_id = ? AND c.type = 'form' ORDER BY l.created_at DESC LIMIT ? OFFSET ?",
            [(int)$operatorId, (int)$limit, (int)$offset]
        );
    }
}
