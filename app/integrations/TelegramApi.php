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

        // Hanya tombol Follow-Up (status diatur via reaction emoji di Telegram)
        if (!empty($decrypted->phone)) {
            $followupUrl = Rotator::getFollowupUrl($campaign, $decrypted, $operator);
            if ($followupUrl) {
                $buttons[] = [['text' => '📲 Follow-Up Customer', 'url' => $followupUrl]];
            }
        }

        $replyMarkup = !empty($buttons) ? ['inline_keyboard' => $buttons] : null;

        $response = self::sendMessage($operator->telegram_chat_id, $lines, $replyMarkup);

        // Simpan mapping message_id → lead_id agar reaction bisa identify lead-nya
        if ($response && !empty($response['body'])) {
            $body = json_decode($response['body'], true);
            $msgId = $body['result']['message_id'] ?? null;
            if ($msgId) {
                $mapKey = 'tg_msg_' . $operator->telegram_chat_id . '_' . $msgId;
                Settings::set($mapKey, (string)$lead->id);
            }
        }
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
                $shortName = substr($name ?: "Lead {$no}", 0, 15);
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

        // Handle message_reaction: ❤️=contacted | 👍=purchased | 👎=cancelled
        if (!empty($update['message_reaction'])) {
            $reaction     = $update['message_reaction'];
            $newReactions = $reaction['new_reaction'] ?? [];

            // chat id di reaction = id chat antara user dan bot (private chat)
            // user id yang react ada di $reaction['user']['id']
            $reactUserId = $reaction['user']['id'] ?? null;
            $chatId      = $reaction['chat']['id'] ?? ($reactUserId ?? null);
            $msgId       = $reaction['message_id'] ?? null;

            if ($chatId && $msgId && !empty($newReactions)) {
                $operator = Operator::findByTelegramChatId((string)$chatId);

                // Fallback: cari via user id jika chat id berbeda (grup/channel)
                if (!$operator && $reactUserId && $reactUserId != $chatId) {
                    $operator = Operator::findByTelegramChatId((string)$reactUserId);
                }

                if ($operator) {
                    $emoji = $newReactions[0]['emoji'] ?? '';

                    // Normalisasi: hapus variation selector U+FE0F agar ❤️ == ❤
                    $emoji = preg_replace('/\x{FE0F}/u', '', $emoji);

                    $statusMap = [
                        "\u{2764}"           => 'contacted', // ❤ (tanpa VS)
                        "\u{2764}\u{FE0F}"   => 'contacted', // ❤️ (dengan VS)
                        "\u{1F44D}"          => 'purchased', // 👍
                        "\u{1F44E}"          => 'cancelled', // 👎
                    ];

                    // Cari match — coba normalized dulu, lalu raw
                    $rawEmoji  = $newReactions[0]['emoji'] ?? '';
                    $newStatus = $statusMap[$emoji] ?? ($statusMap[$rawEmoji] ?? null);

                    if ($newStatus) {
                        $mapKey = 'tg_msg_' . $chatId . '_' . $msgId;
                        $leadId = (int)Settings::get($mapKey, '0');

                        // Fallback: coba dengan user id sebagai chat id
                        if (!$leadId && $reactUserId && $reactUserId != $chatId) {
                            $mapKey = 'tg_msg_' . $reactUserId . '_' . $msgId;
                            $leadId = (int)Settings::get($mapKey, '0');
                        }

                        if ($leadId) {
                            $lead = Lead::find($leadId);
                            if ($lead && $lead->operator_id == $operator->id) {
                                if ($lead->status !== $newStatus) {
                                    Lead::updateStatus($leadId, $newStatus, 'Update via Telegram reaction', $operator->id);
                                    Helper::fireStatusEvent($leadId, $newStatus);
                                }
                            }
                        }
                    }
                }
            }
        }

        http_response_code(200);
        echo json_encode(['ok' => true]);
    }
}
