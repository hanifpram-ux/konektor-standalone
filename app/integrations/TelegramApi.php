<?php

class TelegramApi
{
    public static function sendMessage($chatId, $text)
    {
        $token = Settings::get('telegram_bot_token');
        if (!$token || !$chatId) return null;

        $payload  = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
        $endpoint = "https://api.telegram.org/bot{$token}/sendMessage";
        $response = Helper::httpPost($endpoint, $payload);
        return $response;
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

        self::sendMessage($operator->telegram_chat_id, $text);
    }
}
