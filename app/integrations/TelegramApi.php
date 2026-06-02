<?php

class TelegramApi
{
    public static function sendMessage($chatId, $text, $replyMarkup = null)
    {
        $token = Settings::get('telegram_bot_token');
        if (!$token || !$chatId) return null;

        $payload  = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }
        $endpoint = "https://api.telegram.org/bot{$token}/sendMessage";
        $response = Helper::httpPost($endpoint, $payload);
        return $response;
    }

    public static function answerCallback($callbackQueryId, $text = '')
    {
        $token = Settings::get('telegram_bot_token');
        if (!$token) return null;
        $payload = ['callback_query_id' => $callbackQueryId, 'text' => $text];
        Helper::httpPost("https://api.telegram.org/bot{$token}/answerCallbackQuery", $payload);
    }

    public static function notifyLead($campaign, $lead, $operator)
    {
        if (empty($operator->telegram_chat_id)) return;

        $decrypted = Lead::decrypt(clone $lead);
        $camName   = isset($campaign->name) ? $campaign->name : '';

        // Build field lines based on active form fields only
        $formCfg    = Campaign::getFormConfig($campaign);
        $allFields  = isset($formCfg['fields']) ? $formCfg['fields'] : [];
        $extraFields = isset($formCfg['extra_fields']) ? $formCfg['extra_fields'] : [];

        $fieldMap = [
            'name'           => isset($decrypted->name)           ? $decrypted->name           : '',
            'phone'          => isset($decrypted->phone)          ? $decrypted->phone          : '',
            'email'          => isset($decrypted->email)          ? $decrypted->email          : '',
            'address'        => isset($decrypted->address)        ? $decrypted->address        : '',
            'quantity'       => isset($lead->quantity)            ? $lead->quantity            : '',
            'custom_message' => isset($lead->custom_message)      ? $lead->custom_message      : '',
        ];

        $lines = "<b>Lead Baru - {$camName}</b>\n";

        foreach ($allFields as $field) {
            if (empty($field['enabled'])) continue;
            $key   = isset($field['name'])  ? $field['name']  : '';
            $label = isset($field['label']) ? $field['label'] : $key;
            $val   = isset($fieldMap[$key]) ? trim($fieldMap[$key]) : '';
            if ($val === '') continue;
            $lines .= "{$label}: {$val}\n";
        }

        // Extra / custom fields from extra_data
        if (!empty($extraFields)) {
            $extraData = [];
            if (!empty($lead->extra_data)) {
                $decoded = json_decode($lead->extra_data, true);
                if (is_array($decoded)) $extraData = $decoded;
            }
            foreach ($extraFields as $ef) {
                if (empty($ef['enabled'])) continue;
                $key   = isset($ef['name'])  ? $ef['name']  : '';
                $label = isset($ef['label']) ? $ef['label'] : $key;
                $val   = isset($extraData[$key]) ? trim((string)$extraData[$key]) : '';
                if ($val === '') continue;
                $lines .= "{$label}: {$val}\n";
            }
        }

        $lines .= 'Waktu: ' . date('d/m/Y H:i', strtotime($lead->created_at));

        $buttons = [];

        // Follow-up button + tombol Dihubungi
        if (!empty($decrypted->phone)) {
            $followupUrl = Rotator::getFollowupUrl($campaign, $decrypted, $operator);
            $row = [];
            if ($followupUrl) {
                $row[] = ['text' => '📲 Follow-Up Customer', 'url' => $followupUrl];
            }
            $row[] = ['text' => '📞 Dihubungi', 'callback_data' => 'status:contacted:' . $lead->id];
            $buttons[] = $row;
        }

        $replyMarkup = !empty($buttons) ? ['inline_keyboard' => $buttons] : null;

        self::sendMessage($operator->telegram_chat_id, $lines, $replyMarkup);
    }

    /**
     * Kirim rekap lead kemarin ke semua operator yang punya telegram_chat_id.
     * Dipanggil oleh cron harian jam 14.00 WIB via /api/cron/daily-recap.
     */
    public static function sendDailyRecap()
    {
        $yesterday  = date('Y-m-d', strtotime('-1 day'));
        $dateFrom   = $yesterday . ' 00:00:00';
        $dateTo     = $yesterday . ' 23:59:59';

        $operators  = Operator::all(['status' => 'on']);
        $sent       = 0;

        foreach ($operators as $op) {
            if (empty($op->telegram_chat_id)) continue;

            $leads = DB::rows(
                "SELECT l.*, c.name AS campaign_name FROM " . DB::t('leads') . " l
                 LEFT JOIN " . DB::t('campaigns') . " c ON c.id = l.campaign_id
                 WHERE l.operator_id = ? AND l.created_at >= ? AND l.created_at <= ?
                 ORDER BY l.id ASC",
                [(int)$op->id, $dateFrom, $dateTo]
            );

            if (empty($leads)) continue;

            $dayLabel = date('d/m/Y', strtotime($yesterday));
            $text  = "<b>Rekap Lead Kemarin - {$dayLabel}</b>\n";
            $text .= "Total: " . count($leads) . " lead\n\n";

            $buttons = [];
            foreach ($leads as $idx => $lead) {
                $dec   = Lead::decrypt(clone $lead);
                $no    = $idx + 1;
                $name  = trim($dec->name  ?? '');
                $phone = trim($dec->phone ?? '');
                $camp  = trim($lead->campaign_name ?? '');
                $stat  = $lead->status ?? 'new';

                $statLabel = ['new' => '🆕', 'contacted' => '📞', 'purchased' => '✅', 'cancelled' => '❌'][$stat] ?? '❔';

                $text .= "{$no}. {$statLabel} <b>{$name}</b>";
                if ($phone) $text .= " | {$phone}";
                if ($camp)  $text .= "\n    📋 {$camp}";
                $text .= "\n";

                // Satu baris tombol per lead: Beli + Batal dengan nama
                $shortName = mb_substr($name ?: "Lead {$no}", 0, 15);
                $buttons[] = [
                    ['text' => "✅ {$shortName} - Beli",  'callback_data' => 'status:purchased:'  . $lead->id],
                    ['text' => "❌ {$shortName} - Batal", 'callback_data' => 'status:cancelled:' . $lead->id],
                ];
            }

            $replyMarkup = ['inline_keyboard' => $buttons];
            self::sendMessage($op->telegram_chat_id, $text, $replyMarkup);
            $sent++;
        }

        return $sent;
    }

    /**
     * Handle incoming Telegram webhook update.
     * Called by Router from /telegram/callback endpoint.
     */
    public static function handleWebhook($params = [])
    {
        $raw    = file_get_contents('php://input');
        $update = $raw ? json_decode($raw, true) : null;
        if (!$update) { http_response_code(200); return; }

        // Handle callback_query (inline button press)
        if (!empty($update['callback_query'])) {
            $cb     = $update['callback_query'];
            $cbId   = $cb['id'] ?? '';
            $cbData = $cb['data'] ?? '';
            $chatId = $cb['from']['id'] ?? ($cb['message']['chat']['id'] ?? null);

            $operator = Operator::findByTelegramChatId($chatId);
            if (!$operator) {
                self::answerCallback($cbId, '❌ Operator Telegram tidak terdaftar.');
                http_response_code(200);
                echo json_encode(['ok' => false]);
                return;
            }

            if (strpos($cbData, 'followup:') === 0) {
                $leadId = (int)substr($cbData, 9);
                $lead   = Lead::find($leadId);
                if ($lead && $lead->operator_id == $operator->id) {
                    Lead::markFollowedUp($leadId);
                    if ($lead->status === 'new') {
                        Lead::updateStatus($leadId, 'contacted', 'Follow-up via Telegram', $operator->id);
                        Helper::fireStatusEvent($leadId, 'contacted');
                    }
                    self::answerCallback($cbId, '✅ Status diperbarui — Telah Di-Follow Up!');
                } else {
                    self::answerCallback($cbId, '❌ Lead tidak ditemukan atau bukan milik Anda.');
                }
            } elseif (strpos($cbData, 'status:') === 0) {
                $parts = explode(':', $cbData, 3);
                $status = $parts[1] ?? '';
                $leadId = isset($parts[2]) ? (int)$parts[2] : 0;
                $allowed = ['contacted','purchased','cancelled'];
                if (!in_array($status, $allowed, true)) {
                    self::answerCallback($cbId, '❌ Status tidak valid.');
                } else {
                    $lead = Lead::find($leadId);
                    if ($lead && $lead->operator_id == $operator->id) {
                        if ($lead->status === $status) {
                            self::answerCallback($cbId, 'ℹ️ Status sudah ' . $status . '.');
                        } else {
                            $ok = Lead::updateStatus($leadId, $status, 'Update status via Telegram', $operator->id);
                            if ($ok) {
                                Helper::fireStatusEvent($leadId, $status);
                                self::answerCallback($cbId, '✅ Status berhasil diubah menjadi ' . ucfirst($status) . '.');
                            } else {
                                self::answerCallback($cbId, '❌ Gagal memperbarui status lead.');
                            }
                        }
                    } else {
                        self::answerCallback($cbId, '❌ Lead tidak ditemukan atau bukan milik Anda.');
                    }
                }
            } else {
                self::answerCallback($cbId);
            }
        }

        http_response_code(200);
        echo json_encode(['ok' => true]);
    }
}
