<?php
/**
 * Plugin Name: Harmat OpenAI FAQ Pilot
 * Description: Optional, per-question classification for the local assistant.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

const HARMAT_OPENAI_PRIVATE = '/home/harmath2/codex-backups/assistant-openai-pilot';

function harmat_openai_ready(): bool {
    $dir = HARMAT_OPENAI_PRIVATE;
    if (!is_dir($dir) || is_link($dir) || (@fileperms($dir) & 0777) !== 0700) return false;
    foreach (array('api-key', 'quota.json', 'faq-router.php') as $file) {
        $path = $dir . '/' . $file;
        if (!is_file($path) || is_link($path) || (@fileperms($path) & 0777) !== 0600 || !is_readable($path)) return false;
    }
    return is_writable($dir . '/quota.json');
}

function harmat_openai_eligible(string $question, string $lang): bool {
    return in_array($lang, array('hu', 'zh'), true) && $question !== '' && strlen($question) <= 1040
        && preg_match('/[\p{N}@\r\n]|https?:|www\.|\b(?:telefon|telefonszám|e-mail|email|nevem|lakcím|árajánlat|időpont|foglalás|árengedmény|fizetés)\b|姓名|我叫|电话|邮箱|微信|身份证|报价|预约|折扣|付款|最低价/iu', $question) === 0;
}

function harmat_openai_mac(string $question, string $lang, string $stamp): string {
    return hash_hmac('sha256', $stamp . "\n" . $lang . "\n" . $question, wp_salt('nonce'));
}

function harmat_openai_ticket(string $question, string $lang): ?string {
    if (!harmat_openai_eligible($question, $lang) || !harmat_openai_ready()) return null;
    try {
        $stamp = time() . '.' . bin2hex(random_bytes(16));
        return $stamp . '.' . harmat_openai_mac($question, $lang, $stamp);
    } catch (Throwable $ignored) {
        return null;
    }
}

function harmat_openai_valid_ticket(string $question, string $lang, string $ticket, int $now): bool {
    if (!preg_match('/\A([0-9]{10})\.([a-f0-9]{32})\.([a-f0-9]{64})\z/D', $ticket, $m)) return false;
    $age = $now - (int)$m[1];
    return $age >= 0 && $age <= 300 && harmat_openai_day($now) === harmat_openai_day((int)$m[1])
        && hash_equals(harmat_openai_mac($question, $lang, $m[1] . '.' . $m[2]), $m[3]);
}

function harmat_openai_day(int $timestamp): string {
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Europe/Budapest'))->format('Y-m-d');
}

function harmat_openai_reserve(string $path, string $ticket, string $date): bool {
    $file = @fopen($path, 'r+b');
    if (!$file) return false;
    try {
        if (!flock($file, LOCK_EX | LOCK_NB)) return false;
        $raw = stream_get_contents($file, 8193);
        if (!is_string($raw) || strlen($raw) > 8192) return false;
        $state = json_decode($raw, true);
        if (!is_array($state) || !is_string($state['date'] ?? null) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $state['date']) || $state['date'] > $date
            || !is_array($state['tickets'] ?? null)) return false;
        $used = $state['date'] === $date ? $state['tickets'] : array();
        $hash = hash('sha256', $ticket);
        if (count($used) >= 30 || in_array($hash, $used, true)) return false;
        $used[] = $hash;
        $next = json_encode(array('date' => $date, 'tickets' => $used));
        rewind($file);
        return fwrite($file, $next) === strlen($next) && ftruncate($file, strlen($next)) && fflush($file);
    } finally {
        fclose($file);
    }
}

function harmat_openai_topic(string $question, string $lang, string $ticket): ?string {
    try {
        $now = time();
        if (!harmat_openai_eligible($question, $lang) || !harmat_openai_valid_ticket($question, $lang, $ticket, $now)
            || !harmat_openai_ready()) return null;
        $key = trim((string) @file_get_contents(HARMAT_OPENAI_PRIVATE . '/api-key', false, null, 0, 1024));
        if (!preg_match('/\Ask-[a-zA-Z0-9_-]{16,512}\z/D', $key)) return null;
        require_once HARMAT_OPENAI_PRIVATE . '/faq-router.php';
        $body = \Harmat\AssistantOpenAI\request_body('gpt-5.4-mini', $question);
        if (!$body) return null;
        $body['reasoning'] = array('effort' => 'none');
        $day = harmat_openai_day($now);
        if (!harmat_openai_reserve(HARMAT_OPENAI_PRIVATE . '/quota.json', $ticket, $day)) return null;
        $response = wp_remote_post('https://api.openai.com/v1/responses', array(
            'headers' => array('Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($body), 'timeout' => 5, 'redirection' => 0, 'sslverify' => true,
            'limit_response_size' => 65536,
        ));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return null;
        $value = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($value) ? \Harmat\AssistantOpenAI\response_topic($value) : null;
    } catch (Throwable $ignored) {
        return null;
    }
}
