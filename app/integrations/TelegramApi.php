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
        $name      = isset($decrypted->name)         ? $decrypted->name         : '';
        $phone     = isset($decrypted->phone)        ? $decrypted->phone        : '';
        $email     = isset($decrypted->email)        ? $decrypted->email        : '';
        $product   = isset($campaign->product_name)  ? $campaign->product_name  : '';
        $camName   = isset($campaign->name)          ? $campaign->name          : '';

        $text = "<b>Lead Baru - {$camName}</b>\n"
              . "Nama: {$name}\n"
              . "HP: {$phone}\n"
              . "Email: {$email}\n"
              . "Produk: {$product}\n"
              . date('d/m/Y H:i');

        $buttons = [];

        // Status update buttons mirror CS panel actions.
        $buttons[] = [
            ['text' => '✅ Dihubungi', 'callback_data' => 'status:contacted:' . $lead->id],
            ['text' => '🛒 Beli', 'callback_data' => 'status:purchased:' . $lead->id],
            ['text' => '❌ Batal', 'callback_data' => 'status:cancelled:' . $lead->id],
        ];

        // Follow-up button (URL) — opens WA/email
        if (!empty($decrypted->phone)) {
            $followupUrl = Rotator::getFollowupUrl($campaign, $decrypted, $operator);
            if ($followupUrl) {
                $buttons[] = [['text' => '📲 Follow-Up Customer', 'url' => $followupUrl]];
            }
        }

        $replyMarkup = [
            'inline_keyboard' => $buttons,
        ];

        self::sendMessage($operator->telegram_chat_id, $text, $replyMarkup);
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
