<?php

class Logger
{
    public static function log($source, $eventName, $endpoint, $payload, $response)
    {
        try {
            $code    = isset($response['code']) ? $response['code'] : 0;
            $body    = isset($response['body']) ? $response['body'] : '';
            $success = ($code >= 200 && $code < 300) ? 1 : 0;

            DB::insert('api_logs', [
                'source'      => substr($source, 0, 100),
                'event_name'  => substr($eventName, 0, 100),
                'endpoint'    => $endpoint,
                'payload'     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'response'    => is_string($body) ? substr($body, 0, 65535) : json_encode($body),
                'status_code' => (int)$code,
                'success'     => $success,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            // Never let logging break the main flow
        }
    }

    public static function getLogs($limit = 100, $offset = 0)
    {
        return DB::rows(
            "SELECT * FROM " . DB::t('api_logs') . " ORDER BY id DESC LIMIT ? OFFSET ?",
            [(int)$limit, (int)$offset]
        );
    }

    public static function countLogs()
    {
        return DB::count('api_logs');
    }

    public static function clearOld($days = 30)
    {
        DB::query(
            "DELETE FROM " . DB::t('api_logs') . " WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [(int)$days]
        );
    }
}
