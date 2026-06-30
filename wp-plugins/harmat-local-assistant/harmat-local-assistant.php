<?php
/**
 * Plugin Name: Harmat Local Assistant
 * Plugin URI: https://harmat22.hu
 * Description: Local knowledge-base assistant for Harmat Lakópark apartment questions, prices, FAQ, and sales handoff.
 * Version: 0.3.3
 * Author: Harmat22 Maintenance
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null) {
        $chars = preg_split('//u', (string) $string, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) {
            return $length === null ? substr((string) $string, $start) : substr((string) $string, $start, $length);
        }
        $slice = $length === null ? array_slice($chars, $start) : array_slice($chars, $start, $length);
        return implode('', $slice);
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null) {
        $string = strtr((string) $string, array(
            'Á' => 'á',
            'É' => 'é',
            'Í' => 'í',
            'Ó' => 'ó',
            'Ö' => 'ö',
            'Ő' => 'ő',
            'Ú' => 'ú',
            'Ü' => 'ü',
            'Ű' => 'ű',
        ));
        return strtolower($string);
    }
}

if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = null) {
        return strpos((string) $haystack, (string) $needle, (int) $offset);
    }
}

final class Harmat_Local_Assistant {
    const VERSION = '0.3.3';
    const REST_NAMESPACE = 'harmat-local-assistant/v1';
    const CONTACT_EMAIL = 'ertekesites@harmat22.hu';
    const CONTACT_PHONE = '+36300733375';

    private static $apartments = null;

    public function __construct() {
        remove_action('wp_footer', 'harmat_perf_ai_customer_assistant', 130);
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_footer', array($this, 'render_widget'), 120);
        add_shortcode('harmat_local_assistant', array($this, 'render_shortcode'));
    }

    public function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/ask', array(
            'methods' => 'POST',
            'callback' => array($this, 'answer_request'),
            'permission_callback' => '__return_true',
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));

        register_rest_route(self::REST_NAMESPACE, '/handoff', array(
            'methods' => 'POST',
            'callback' => array($this, 'handoff_request'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::REST_NAMESPACE, '/event', array(
            'methods' => 'POST',
            'callback' => array($this, 'event_request'),
            'permission_callback' => '__return_true',
        ));
    }

    public function answer_request(WP_REST_Request $request) {
        $nonce_error = $this->validate_rest_nonce($request);
        if ($nonce_error) {
            return $nonce_error;
        }

        if (!$this->check_rate_limit('ask', 40, 5 * MINUTE_IN_SECONDS)) {
            return new WP_Error('harmat_ai_rate_limited', 'Túl sok kérés érkezett. Kérjük, próbálja újra néhány perc múlva.', array('status' => 429));
        }

        $message = trim((string) $request->get_param('message'));
        $message = wp_strip_all_tags($message);
        $message = mb_substr($message, 0, 260, 'UTF-8');
        $requested_lang = sanitize_key((string) $request->get_param('lang'));
        if (!in_array($requested_lang, array('hu', 'zh', 'en'), true)) {
            $requested_lang = '';
        }

        if ($message === '') {
            $lang = $requested_lang ?: 'hu';
            return rest_ensure_response(array(
                'ok' => false,
                'answer' => $this->text('empty', $lang),
                'cards' => array(),
                'actions' => array(),
                'suggestions' => $this->default_suggestions($lang),
            ));
        }

        $lang = $requested_lang ?: $this->detect_language($message);
        $this->track_event('assistant_question', array('lang' => $lang, 'message_len' => strlen($message)));
        $result = $this->answer_message($message, $lang);
        return rest_ensure_response($result);
    }

    public function handoff_request(WP_REST_Request $request) {
        $nonce_error = $this->validate_rest_nonce($request);
        if ($nonce_error) {
            return $nonce_error;
        }

        if ((string) $request->get_param('company_url') !== '') {
            return rest_ensure_response(array('success' => true, 'ignored' => true));
        }

        $lang = sanitize_key((string) $request->get_param('lang'));
        if (!in_array($lang, array('hu', 'zh', 'en'), true)) {
            $lang = 'hu';
        }
        $name = sanitize_text_field((string) $request->get_param('name'));
        $phone = sanitize_text_field((string) $request->get_param('phone'));
        $email = sanitize_email((string) $request->get_param('email'));
        $message = $this->limit_text(sanitize_textarea_field((string) $request->get_param('message')), 200);
        $context = $this->limit_text(sanitize_textarea_field((string) $request->get_param('context')), 2000);
        $page = esc_url_raw((string) $request->get_param('page'));
        $intent = sanitize_key((string) $request->get_param('intent'));
        $interested_unit = sanitize_text_field((string) $request->get_param('interested_unit'));
        $apartment_type = sanitize_text_field((string) $request->get_param('apartment_type'));
        $budget_range = sanitize_text_field((string) $request->get_param('budget_range'));
        $preferred_contact_time = sanitize_text_field((string) $request->get_param('preferred_contact_time'));
        $preferred_room_count = sanitize_text_field((string) $request->get_param('preferred_room_count'));
        $conversation_summary = $this->limit_text(sanitize_textarea_field((string) $request->get_param('conversation_summary')), 5000);
        $utm = $this->sanitize_utm($request->get_param('utm'));

        if ($name === '' || ($phone === '' && $email === '')) {
            return new WP_Error('harmat_ai_handoff_required', $this->by_lang($lang, 'Kerjuk, adja meg nevet es legalabb egy elerhetoseget.', '请填写姓名，并至少留下电话或邮箱。', 'Please enter your name and at least one contact detail.'), array('status' => 400));
        }
        if ((string) $request->get_param('email') !== '' && !is_email($email)) {
            return new WP_Error('harmat_ai_handoff_email', $this->by_lang($lang, 'Az e-mail cim nem megfelelo.', '邮箱格式不正确。', 'The email address is not valid.'), array('status' => 400));
        }

        $rate_key = 'harmat_ai_handoff_' . md5($this->visitor_key() . '|' . strtolower($email) . '|' . preg_replace('/\D+/', '', $phone));
        if (get_transient($rate_key)) {
            return new WP_Error('harmat_ai_handoff_rate', $this->by_lang($lang, 'Koszonjuk, az adatok mar rogzitesre kerultek. Kerjuk, varjon nehany percet uj kuldes elott.', '谢谢，系统已经收到。请稍等几分钟后再重复提交。', 'Thank you, we have received your details. Please wait a few minutes before submitting again.'), array('status' => 429));
        }
        set_transient($rate_key, 1, 10 * MINUTE_IN_SECONDS);

        $customer_message = trim($message);
        if ($customer_message === '') {
            $customer_message = $this->by_lang($lang, 'AI asszisztensbol emberi visszahivast kert.', '客户通过 AI 客服请求人工跟进。', 'Customer requested human follow-up from the AI assistant.');
        }

        $ai_lead_details = array();
        if ($interested_unit !== '' || $apartment_type !== '' || $budget_range !== '' || $preferred_room_count !== '' || $preferred_contact_time !== '') {
            if ($interested_unit !== '') {
                $ai_lead_details['interested_unit'] = $interested_unit;
            }
            if ($apartment_type !== '') {
                $ai_lead_details['apartment_type'] = $apartment_type;
            }
            if ($budget_range !== '') {
                $ai_lead_details['budget_range'] = $budget_range;
            }
            if ($preferred_room_count !== '') {
                $ai_lead_details['preferred_room_count'] = $preferred_room_count;
            }
            if ($preferred_contact_time !== '') {
                $ai_lead_details['preferred_contact_time'] = $preferred_contact_time;
            }
        }

        $posted = array(
            'your-name' => $name,
            'your-email' => $email,
            'your-phone' => $phone,
            'your-date' => '',
            'your-time' => '',
            'your-message' => $customer_message,
            'selected-building' => '',
            'selected-floor' => '',
            'selected-apartment' => $interested_unit,
            'selected-area' => '',
            'selected-rooms' => '',
            'selected-price' => '',
            'selected-url' => $page,
            'privacy-acceptance' => '1',
            'marketing-consent' => '',
            'source' => $page,
            'lead_source' => 'Harmat asszisztens',
            'intent' => $intent,
            'interested_unit' => $interested_unit,
            'apartment_type' => $apartment_type,
            'budget_range' => $budget_range,
            'preferred_contact_time' => $preferred_contact_time,
            'preferred_room_count' => $preferred_room_count,
            'conversation_summary' => $conversation_summary,
        );

        $post_id = wp_insert_post(array(
            'post_type' => 'harmat_offer_lead',
            'post_status' => 'private',
            'post_title' => 'AI: ' . $name . ' - ' . current_time('ymd-His'),
            'post_content' => $customer_message,
        ), true);

        if (is_wp_error($post_id) || !$post_id) {
            return new WP_Error('harmat_ai_handoff_save', $this->by_lang($lang, 'A rogzitest most nem sikerult elvegezni.', '暂时无法保存，请稍后再试。', 'We could not save the request right now.'), array('status' => 500));
        }

        $crm = 'AI-' . current_time('Ymd') . '-' . str_pad((string) $post_id, 5, '0', STR_PAD_LEFT);
        update_post_meta($post_id, '_harmat_offer_posted', $posted);
        update_post_meta($post_id, '_harmat_offer_email', $email);
        update_post_meta($post_id, '_harmat_offer_phone', $phone);
        update_post_meta($post_id, '_harmat_offer_apartment', $interested_unit);
        update_post_meta($post_id, '_harmat_offer_date', '');
        update_post_meta($post_id, '_harmat_offer_time', $preferred_contact_time);
        update_post_meta($post_id, '_harmat_offer_crm_code', $crm);
        update_post_meta($post_id, '_harmat_offer_mail_status', 'queued');
        update_post_meta($post_id, '_harmat_offer_mail_checked_at', current_time('mysql'));
        update_post_meta($post_id, '_harmat_offer_tracking', array(
            'lead_source' => 'Harmat asszisztens',
            'landing_page' => $page,
            'source_page' => $page,
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
            'intent' => $intent,
            'utm' => $utm,
            'conversation_summary' => $conversation_summary,
            'interested_unit' => $interested_unit,
            'preferred_room_count' => $preferred_room_count,
        ));
        update_post_meta($post_id, '_harmat_offer_source_page', $page);
        update_post_meta($post_id, '_harmat_offer_utm', $utm);
        update_post_meta($post_id, '_harmat_offer_ai_context', $context);
        update_post_meta($post_id, '_harmat_offer_ai_lead_details', $ai_lead_details);
        update_post_meta($post_id, '_harmat_offer_conversation_summary', $conversation_summary);
        update_post_meta($post_id, '_harmat_offer_crm_summary', $this->build_crm_summary(array(
            'lang' => $lang,
            'intent' => $intent,
            'message' => $customer_message,
            'context' => $context,
            'conversation_summary' => $conversation_summary,
            'interested_unit' => $interested_unit,
            'apartment_type' => $apartment_type,
            'budget_range' => $budget_range,
            'preferred_room_count' => $preferred_room_count,
            'preferred_contact_time' => $preferred_contact_time,
        )));
        update_post_meta($post_id, '_harmat_offer_preferred_room_count', $preferred_room_count);
        update_post_meta($post_id, '_harmat_offer_preferred_contact_time', $preferred_contact_time);

        if (has_action('harmat_sales_public_offer_mail')) {
            wp_schedule_single_event(time() + 1, 'harmat_sales_public_offer_mail', array((int) $post_id));
            spawn_cron(time());
        } else {
            wp_mail(self::CONTACT_EMAIL, 'Harmat AI - ugyfel visszahivast ker', $customer_message . "\n\nNev: " . $name . "\nTelefon: " . $phone . "\nE-mail: " . $email, array('Content-Type: text/plain; charset=UTF-8'));
        }

        $this->track_event($intent === 'appointment' ? 'appointment_submitted' : 'offer_request_submitted', array('lang' => $lang, 'lead_id' => (int) $post_id));

        $message_key = $intent === 'appointment' ? 'appointment_saved' : 'handoff_saved';
        return rest_ensure_response(array(
            'success' => true,
            'crm' => $crm,
            'message' => $this->text($message_key, $lang),
        ));
    }

    public function event_request(WP_REST_Request $request) {
        $nonce_error = $this->validate_rest_nonce($request);
        if ($nonce_error) {
            return $nonce_error;
        }

        $event = sanitize_key((string) $request->get_param('event'));
        $allowed = array(
            'assistant_open',
            'quick_button_click',
            'apartment_recommendation',
            'offer_request_started',
            'offer_request_submitted',
            'appointment_started',
            'appointment_submitted',
            'human_handoff',
            'unknown_question',
        );
        if (!in_array($event, $allowed, true)) {
            return rest_ensure_response(array('success' => false));
        }

        $meta = $request->get_param('meta');
        $this->track_event($event, is_array($meta) ? $meta : array());
        return rest_ensure_response(array('success' => true));
    }

    public function render_shortcode() {
        ob_start();
        $this->render_widget(true);
        return ob_get_clean();
    }

    public function render_widget($shortcode = false) {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!$shortcode && $this->is_private_portal()) {
            return;
        }

        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        $endpoint = esc_url_raw(rest_url(self::REST_NAMESPACE . '/ask'));
        $handoff_endpoint = esc_url_raw(rest_url(self::REST_NAMESPACE . '/handoff'));
        $event_endpoint = esc_url_raw(rest_url(self::REST_NAMESPACE . '/event'));
        $nonce = wp_create_nonce('wp_rest');
        $page_lang = substr((string) get_locale(), 0, 2);
        if (!in_array($page_lang, array('hu', 'zh', 'en'), true)) {
            $page_lang = 'hu';
        }
        ?>
        <style id="harmat-local-assistant-style">
          .harmat-local-ai-launch {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 99990;
            min-height: 48px;
            padding: 5px 17px 5px 6px;
            border: 0;
            border-radius: 999px;
            background: #9a6a2a;
            color: #fff;
            box-shadow: 0 16px 38px rgba(35, 33, 28, .26);
            font: 800 14px/1.2 Montserrat, Arial, sans-serif;
            letter-spacing: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
          }
          .harmat-local-ai-avatar {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff7e9;
            color: #9a6a2a;
            box-shadow: inset 0 0 0 1px rgba(154, 106, 42, .2);
            flex: 0 0 38px;
          }
          .harmat-local-ai-avatar svg {
            width: 24px;
            height: 24px;
            display: block;
          }
          .harmat-local-ai-panel {
            position: fixed;
            right: 18px;
            bottom: 78px;
            z-index: 99991;
            width: min(390px, calc(100vw - 24px));
            max-height: min(690px, calc(100vh - 108px));
            display: none;
            grid-template-rows: auto minmax(0, 1fr) auto auto auto;
            overflow: hidden;
            border: 1px solid rgba(154, 106, 42, .28);
            border-radius: 8px;
            background: #fffaf2;
            color: #273136;
            box-shadow: 0 22px 58px rgba(34, 30, 22, .22);
          }
          .harmat-local-ai-panel.is-open { display: grid; }
          .harmat-local-ai-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            background: #283137;
            color: #fff;
          }
          .harmat-local-ai-head strong { display: block; font-size: 15px; line-height: 1.2; }
          .harmat-local-ai-head small { display: block; margin-top: 3px; color: rgba(255,255,255,.72); font-size: 11px; }
          .harmat-local-ai-head-tools { display: flex; align-items: center; gap: 8px; }
          .harmat-local-ai-lang {
            display: inline-flex;
            gap: 3px;
            padding: 3px;
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 999px;
            background: rgba(255,255,255,.08);
          }
          .harmat-local-ai-lang button {
            min-width: 34px;
            min-height: 26px;
            padding: 0 7px;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: rgba(255,255,255,.78);
            font: 800 11px/1 Montserrat, Arial, sans-serif;
            cursor: pointer;
          }
          .harmat-local-ai-lang button.is-active { background: #fff7e9; color: #8a5a18; }
          .harmat-local-ai-close {
            width: 32px;
            height: 32px;
            border: 1px solid rgba(255,255,255,.28);
            border-radius: 999px;
            background: transparent;
            color: #fff;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
          }
          .harmat-local-ai-body {
            min-height: 0;
            overflow: auto;
            padding: 14px;
            display: grid;
            align-content: start;
            gap: 10px;
            background: #fffaf2;
          }
          .harmat-local-ai-msg {
            max-width: 94%;
            padding: 12px 13px;
            border-radius: 8px;
            font: 500 14px/1.58 Montserrat, Arial, sans-serif;
            white-space: pre-wrap;
            overflow-wrap: break-word;
            word-break: normal;
            hyphens: auto;
            text-align: left;
          }
          .harmat-local-ai-msg.bot { justify-self: start; background: #fff; border: 1px solid #eadcc7; }
          .harmat-local-ai-msg.user { justify-self: end; background: #9a6a2a; color: #fff; }
          .harmat-local-ai-cards { display: grid; gap: 8px; margin-top: 8px; }
          .harmat-local-ai-card {
            display: grid;
            gap: 8px;
            padding: 10px;
            border: 1px solid #eadcc7;
            border-radius: 7px;
            background: #fff;
            color: #273136;
            text-decoration: none;
          }
          .harmat-local-ai-card b { display: block; margin-bottom: 5px; color: #9a6a2a; }
          .harmat-local-ai-card span { display: block; color: #5d6468; font-size: 12px; line-height: 1.45; }
          .harmat-local-ai-card-actions,
          .harmat-local-ai-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 3px;
          }
          .harmat-local-ai-card-actions a,
          .harmat-local-ai-actions a,
          .harmat-local-ai-actions button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0 10px;
            border: 1px solid #d8bd8d;
            border-radius: 6px;
            background: #fffaf2;
            color: #6c4a1d;
            font: 800 12px/1.1 Montserrat, Arial, sans-serif;
            text-decoration: none;
            cursor: pointer;
          }
          .harmat-local-ai-card-actions a.is-primary,
          .harmat-local-ai-actions a.is-primary,
          .harmat-local-ai-actions button.is-primary { background: #9a6a2a; color: #fff; border-color: #9a6a2a; }
          .harmat-local-ai-handoff {
            display: grid;
            gap: 8px;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #d8bd8d;
            border-radius: 7px;
            background: #fffaf2;
          }
          .harmat-local-ai-handoff strong { color: #283137; font-size: 13px; }
          .harmat-local-ai-handoff span { color: #6b5a44; font-size: 12px; line-height: 1.45; }
          .harmat-local-ai-handoff input,
          .harmat-local-ai-handoff textarea {
            width: 100%;
            min-height: 36px;
            padding: 8px 9px;
            border: 1px solid #d8bd8d;
            border-radius: 6px;
            background: #fff;
            color: #273136;
            font: 500 12px/1.35 Montserrat, Arial, sans-serif;
          }
          .harmat-local-ai-handoff textarea { min-height: 58px; resize: vertical; }
          .harmat-local-ai-handoff-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
          .harmat-local-ai-handoff label {
            display: flex;
            gap: 7px;
            align-items: flex-start;
            color: #6b5a44;
            font-size: 11px;
            line-height: 1.35;
          }
          .harmat-local-ai-handoff label input { width: 14px; min-height: 14px; margin-top: 2px; padding: 0; }
          .harmat-local-ai-handoff button {
            min-height: 36px;
            border: 0;
            border-radius: 6px;
            background: #9a6a2a;
            color: #fff;
            font: 800 12px/1 Montserrat, Arial, sans-serif;
            cursor: pointer;
          }
          .harmat-local-ai-handoff small { color: #1f7a4d; font-size: 11px; line-height: 1.35; }
          .harmat-local-ai-suggestions { display: flex; flex-wrap: wrap; gap: 7px; max-height: 82px; overflow: auto; padding: 0 14px 12px; background: #fffaf2; border-top: 1px solid rgba(234, 220, 199, .55); }
          .harmat-local-ai-suggestions button {
            min-height: 31px;
            padding: 0 10px;
            border: 1px solid #d8bd8d;
            border-radius: 999px;
            background: #fff;
            color: #6c4a1d;
            font: 700 12px/1 Montserrat, Arial, sans-serif;
            cursor: pointer;
          }
          .harmat-local-ai-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
            padding: 12px 14px 14px;
            border-top: 1px solid #eadcc7;
            background: #fff;
          }
          .harmat-local-ai-input {
            min-height: 42px;
            border: 1px solid #d8bd8d;
            border-radius: 7px;
            padding: 0 12px;
            color: #273136;
            background: #fff;
            font: 500 14px/1 Montserrat, Arial, sans-serif;
            outline: none;
          }
          .harmat-local-ai-send {
            min-height: 42px;
            padding: 0 13px;
            border: 0;
            border-radius: 7px;
            background: #283137;
            color: #fff;
            font: 800 13px/1 Montserrat, Arial, sans-serif;
            cursor: pointer;
          }
          .harmat-local-ai-footnote {
            padding: 0 14px 10px;
            background: #fff;
            color: #8a7a64;
            font: 500 11px/1.4 Montserrat, Arial, sans-serif;
          }
          @media (max-width: 520px) {
            .harmat-local-ai-launch { right: 12px; bottom: 12px; padding-right: 14px; }
            .harmat-local-ai-panel { right: 12px; bottom: 68px; max-height: calc(100vh - 86px); grid-template-rows: auto minmax(0, 1fr) auto auto auto; }
            .harmat-local-ai-suggestions { max-height: 68px; }
            .harmat-local-ai-handoff-grid { grid-template-columns: 1fr; }
          }
        </style>
        <button class="harmat-local-ai-launch" type="button" aria-controls="harmat-local-ai-panel" aria-expanded="false">
          <span class="harmat-local-ai-avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path fill="currentColor" d="M12 12.4a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Zm0 2.1c-3.5 0-6.6 1.8-7.9 4.5-.3.7.2 1.5 1 1.5h13.8c.8 0 1.3-.8 1-1.5-1.3-2.7-4.4-4.5-7.9-4.5Z"/>
            </svg>
          </span>
          <span>Kérdezzen</span>
        </button>
        <section class="harmat-local-ai-panel" id="harmat-local-ai-panel" aria-label="Harmat Lakópark asszisztens">
          <div class="harmat-local-ai-head">
            <div>
              <strong>Harmat asszisztens</strong>
              <small>Lakás, ár, alaprajz és időpont</small>
            </div>
            <div class="harmat-local-ai-head-tools">
              <div class="harmat-local-ai-lang" aria-label="Nyelv">
                <button type="button" data-harmat-ai-lang="hu">Magyar</button>
                <button type="button" data-harmat-ai-lang="zh">中文</button>
                <button type="button" data-harmat-ai-lang="en">EN</button>
              </div>
              <button class="harmat-local-ai-close" type="button" aria-label="Bezárás">×</button>
            </div>
          </div>
          <div class="harmat-local-ai-body" data-harmat-local-ai-body></div>
          <div class="harmat-local-ai-suggestions" data-harmat-local-ai-suggestions></div>
          <form class="harmat-local-ai-form" data-harmat-local-ai-form>
            <input class="harmat-local-ai-input" name="message" autocomplete="off" maxlength="260" placeholder="Írja be kérdését..." aria-label="Kérdés">
            <button class="harmat-local-ai-send" type="submit">Küldés</button>
          </form>
          <div class="harmat-local-ai-footnote">Automatizált, tájékoztató válaszok. Nem hivatalos ajánlat, jogi vagy pénzügyi tanácsadás; a végleges adatokat az értékesítés és a szerződés erősíti meg.</div>
        </section>
        <script id="harmat-local-assistant-script">
        (function () {
          if (window.__harmatLocalAssistantReady) return;
          window.__harmatLocalAssistantReady = true;

          var endpoint = <?php echo wp_json_encode($endpoint); ?>;
          var handoffEndpoint = <?php echo wp_json_encode($handoff_endpoint); ?>;
          var eventEndpoint = <?php echo wp_json_encode($event_endpoint); ?>;
          var nonce = <?php echo wp_json_encode($nonce); ?>;
          var currentLang = <?php echo wp_json_encode($page_lang); ?>;
          var panel = null;
          var launch = null;
          var close = null;
          var body = null;
          var form = null;
          var input = null;
          var suggestions = null;
          var conversation = [];
            var defaultQuickButtons = {
            hu: ['2 szobás lakást keresek', 'Kertes lakást keresek', 'Nagy teraszos lakást keresek', 'Közlekedés és buszok', 'Közeli iskolák', 'Fizetési ütemezés', 'Árajánlatot kérek', 'Időpontot foglalok', 'Hol található a bemutatóiroda?', 'Finanszírozás / CSOK érdekel'],
            zh: ['我要找两房', '我要带花园的房源', '我要大露台户型', '周边公交线路', '附近学校', '付款节点', '我要报价', '预约看房', '销售办公室在哪里？', '贷款 / CSOK 咨询'],
            en: ['I am looking for a 2-room apartment', 'I am looking for a garden apartment', 'I am looking for a large terrace apartment', 'Nearby bus routes', 'Nearby schools', 'Payment schedule', 'Request an offer', 'Book a viewing', 'Where is the sales office?', 'Financing / CSOK']
          };
          var introText = {
            hu: 'Üdvözlöm! Automatizált Harmat asszisztensként segítek lakást keresni, ajánlatot kérni vagy időpontot indítani. Kérdezhet árakról, szobaszámról, alapterületről, vásárlási folyamatról vagy konkrét lakásról, például A1-F-L1. A válaszok tájékoztató jellegűek, a végleges adatokat az értékesítés erősíti meg.',
            zh: '您好，我是 Harmat 自动客服，可以帮您找房、查参考价格、预约看房或提交报价需求。您可以问房间数、预算、面积、交付、周边，或直接输入房号，例如 A1-F-L1。回答为参考信息，最终以销售团队确认和正式文件为准。',
            en: 'Hello, I am the automated Harmat assistant. I can help with apartment search, indicative prices, offer requests and viewing requests. You can ask by room count, budget, area, handover, surroundings, or a unit code such as A1-F-L1. Answers are informative; final data is confirmed by sales.'
          };

          function refreshElements() {
            panel = document.getElementById('harmat-local-ai-panel');
            launch = document.querySelector('.harmat-local-ai-launch');
            close = panel ? panel.querySelector('.harmat-local-ai-close') : null;
            body = panel ? panel.querySelector('[data-harmat-local-ai-body]') : null;
            form = panel ? panel.querySelector('[data-harmat-local-ai-form]') : null;
            input = form ? form.querySelector('input[name="message"]') : null;
            suggestions = panel ? panel.querySelector('[data-harmat-local-ai-suggestions]') : null;
            return !!(panel && launch && close && body && form && input && suggestions);
          }

          function trackingMeta(extra) {
            var params = new URLSearchParams(window.location.search || '');
            var meta = {
              lang: currentLang,
              path: window.location.pathname || '',
              source_page: window.location.href
            };
            ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'].forEach(function(key) {
              if (params.get(key)) meta[key] = params.get(key);
            });
            if (extra) {
              Object.keys(extra).forEach(function(key) { meta[key] = extra[key]; });
            }
            return meta;
          }

          function trackEvent(name, meta) {
            try {
              fetch(eventEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-WP-Nonce': nonce},
                credentials: 'same-origin',
                body: JSON.stringify({event: name, meta: trackingMeta(meta || {})})
              }).catch(function(){});
            } catch (e) {}
          }

          function setLanguage(lang) {
            if (!defaultQuickButtons[lang]) lang = 'hu';
            currentLang = lang;
            if (panel) {
              panel.querySelectorAll('[data-harmat-ai-lang]').forEach(function(button) {
                button.classList.toggle('is-active', button.getAttribute('data-harmat-ai-lang') === currentLang);
              });
            }
            if (input) {
              input.placeholder = lang === 'zh' ? '请输入您的问题...' : (lang === 'en' ? 'Type your question...' : 'Írja be kérdését...');
            }
            setSuggestions(defaultQuickButtons[currentLang]);
          }

          function esc(text) {
            return String(text || '').replace(/[&<>"']/g, function (c) {
              return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
          }

          function addMessage(kind, text, cards, handoff, actions) {
            if (!body && !refreshElements()) return;
            var msg = document.createElement('div');
            msg.className = 'harmat-local-ai-msg ' + kind;
            msg.innerHTML = esc(text);
            if (cards && cards.length) {
              var list = document.createElement('div');
              list.className = 'harmat-local-ai-cards';
              cards.forEach(function (card) {
                var item = document.createElement('div');
                item.className = 'harmat-local-ai-card';
                var viewUrl = card.url || '#';
                var offerUrl = card.offer_url || viewUrl;
                item.innerHTML =
                  '<b>' + esc(card.title) + '</b>' +
                  '<span>' + esc(card.meta) + '</span>' +
                  '<div class="harmat-local-ai-card-actions">' +
                    '<a href="' + esc(viewUrl) + '">' + esc(card.view_label || 'Megnézem') + '</a>' +
                    '<a class="is-primary" href="' + esc(offerUrl) + '">' + esc(card.offer_label || 'Árajánlatot kérek') + '</a>' +
                  '</div>';
                list.appendChild(item);
              });
              msg.appendChild(list);
            }
            if (kind === 'bot' && actions && actions.length) {
              appendActions(msg, actions);
            }
            if (kind === 'bot' && handoff) {
              appendHandoffForm(msg, handoff, text);
            }
            body.appendChild(msg);
            conversation.push({kind: kind, text: String(text || '').slice(0, 500)});
            if (conversation.length > 12) conversation = conversation.slice(-12);
            if (kind === 'bot') {
              window.setTimeout(function () {
                body.scrollTop = Math.max(0, msg.offsetTop - body.offsetTop - 8);
              }, 0);
            } else {
              body.scrollTop = body.scrollHeight;
            }
          }

          function appendActions(container, actions) {
            var wrap = document.createElement('div');
            wrap.className = 'harmat-local-ai-actions';
            actions.forEach(function(action) {
              if (action.message) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = action.primary ? 'is-primary' : '';
                button.textContent = action.label || '';
                button.addEventListener('click', function() {
                  trackEvent('quick_button_click', {label: action.label || '', action: 'message'});
                  input.value = action.message;
                  form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                });
                wrap.appendChild(button);
                return;
              }
              var link = document.createElement('a');
              link.className = action.primary ? 'is-primary' : '';
              link.href = action.url || '#';
              if (action.external) {
                link.target = '_blank';
                link.rel = 'noopener';
              }
              link.textContent = action.label || '';
              link.addEventListener('click', function() { trackEvent(action.event || 'quick_button_click', {label: action.label || ''}); });
              wrap.appendChild(link);
            });
            container.appendChild(wrap);
          }

          function appendHandoffForm(container, handoff, contextText) {
            var formNode = document.createElement('form');
            formNode.className = 'harmat-local-ai-handoff';
            formNode.setAttribute('data-harmat-local-ai-handoff', '1');
            formNode.dataset.context = contextText || '';
            formNode.dataset.intent = handoff.intent || '';
            formNode.dataset.lang = handoff.lang || 'hu';
            formNode.innerHTML =
              '<strong>' + esc(handoff.title || 'Kapcsolatfelvetel') + '</strong>' +
              '<span>' + esc(handoff.text || '') + '</span>' +
              '<input name="name" autocomplete="name" required placeholder="' + esc(handoff.name_placeholder || 'Nev') + '">' +
              '<input name="phone" autocomplete="tel" placeholder="' + esc(handoff.phone_placeholder || 'Telefon') + '">' +
              '<input name="email" type="email" autocomplete="email" placeholder="' + esc(handoff.email_placeholder || 'E-mail') + '">' +
              '<div class="harmat-local-ai-handoff-grid">' +
                '<input name="interested_unit" autocomplete="off" placeholder="' + esc(handoff.unit_placeholder || 'Lakás vagy típus') + '">' +
                '<input name="preferred_room_count" autocomplete="off" placeholder="' + esc(handoff.rooms_placeholder || 'Szobaszám') + '">' +
                '<input name="budget_range" autocomplete="off" placeholder="' + esc(handoff.budget_placeholder || 'Árkeret') + '">' +
                '<input name="preferred_contact_time" autocomplete="off" placeholder="' + esc(handoff.time_placeholder || 'Mikor hívjuk?') + '">' +
              '</div>' +
              '<textarea name="message" maxlength="200" placeholder="' + esc(handoff.message_placeholder || 'Uzenet') + '"></textarea>' +
              '<input name="company_url" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px" aria-hidden="true">' +
              '<label><input type="checkbox" name="privacy" required> <span>' + esc(handoff.privacy || '') + '</span></label>' +
              '<button type="submit">' + esc(handoff.button || 'Kuldes') + '</button>' +
              '<small data-harmat-ai-handoff-status></small>';
            container.appendChild(formNode);
          }

          function setSuggestions(items) {
            if (!suggestions && !refreshElements()) return;
            suggestions.innerHTML = '';
            (items || defaultQuickButtons[currentLang] || []).slice(0, 10).forEach(function (text) {
              var btn = document.createElement('button');
              btn.type = 'button';
              btn.textContent = text;
              btn.onclick = function () {
                trackEvent('quick_button_click', {label: text});
                input.value = text;
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
              };
              suggestions.appendChild(btn);
            });
          }

          function openPanel() {
            if (!refreshElements()) return;
            panel.classList.add('is-open');
            launch.setAttribute('aria-expanded', 'true');
            setLanguage(currentLang);
            trackEvent('assistant_open');
            if (!body.dataset.started) {
              body.dataset.started = '1';
              addMessage('bot', introText[currentLang] || introText.hu);
              setSuggestions(defaultQuickButtons[currentLang]);
            }
            setTimeout(function () { input && input.focus(); }, 80);
          }

          function closePanel() {
            if (!refreshElements()) return;
            panel.classList.remove('is-open');
            launch.setAttribute('aria-expanded', 'false');
          }

          async function ask(text) {
            if (!refreshElements()) return;
            addMessage('user', text);
            var pending = document.createElement('div');
            pending.className = 'harmat-local-ai-msg bot';
            pending.textContent = 'Válasz készül...';
            body.appendChild(pending);
            body.scrollTop = body.scrollHeight;
            try {
              var response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-WP-Nonce': nonce
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message: text, lang: currentLang })
              });
              var data = await response.json();
              pending.remove();
              if (data.event) trackEvent(data.event, {intent: data.intent || ''});
              addMessage('bot', data.answer || 'Nem sikerült választ adni.', data.cards || [], data.handoff || null, data.actions || []);
              setSuggestions(data.suggestions || []);
            } catch (err) {
              pending.remove();
              addMessage('bot', 'Most nem sikerült elérni az asszisztenst. Kérem, próbálja újra később, vagy írjon az ertekesites@harmat22.hu címre.');
            }
          }

          async function handleHandoffSubmit(event) {
            event.preventDefault();
            var handoffForm = event.target;
            var status = handoffForm.querySelector('[data-harmat-ai-handoff-status]');
            var button = handoffForm.querySelector('button[type="submit"]');
            var name = (handoffForm.querySelector('[name="name"]') || {}).value || '';
            var phone = (handoffForm.querySelector('[name="phone"]') || {}).value || '';
            var email = (handoffForm.querySelector('[name="email"]') || {}).value || '';
            var message = ((handoffForm.querySelector('[name="message"]') || {}).value || '').slice(0, 200);
            var company = (handoffForm.querySelector('[name="company_url"]') || {}).value || '';
            var interestedUnit = (handoffForm.querySelector('[name="interested_unit"]') || {}).value || '';
            var roomCount = (handoffForm.querySelector('[name="preferred_room_count"]') || {}).value || '';
            var budgetRange = (handoffForm.querySelector('[name="budget_range"]') || {}).value || '';
            var contactTime = (handoffForm.querySelector('[name="preferred_contact_time"]') || {}).value || '';
            if (!name.trim() || (!phone.trim() && !email.trim())) {
              if (status) status.textContent = 'Kérjük, adjon meg nevet és telefonszámot vagy e-mailt.';
              return;
            }
            if (button) button.disabled = true;
            if (status) status.textContent = 'Küldés folyamatban...';
            try {
              var response = await fetch(handoffEndpoint, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-WP-Nonce': nonce
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                  name: name,
                  phone: phone,
                  email: email,
                  message: message,
                  company_url: company,
                  context: handoffForm.dataset.context || '',
                  conversation_summary: conversation.map(function(item){ return item.kind + ': ' + item.text; }).join('\\n'),
                  intent: handoffForm.dataset.intent || '',
                  lang: handoffForm.dataset.lang || 'hu',
                  page: window.location.href,
                  source_page: window.location.href,
                  interested_unit: interestedUnit,
                  apartment_type: interestedUnit,
                  preferred_room_count: roomCount,
                  budget_range: budgetRange,
                  preferred_contact_time: contactTime,
                  utm: trackingMeta({})
                })
              });
              var data = await response.json();
              if (!response.ok || data.code) {
                throw new Error(data.message || 'Küldés sikertelen.');
              }
              if (status) status.textContent = data.message || 'Köszönjük, hamarosan jelentkezünk.';
              trackEvent(handoffForm.dataset.intent === 'appointment' ? 'appointment_submitted' : 'offer_request_submitted', {crm: data.crm || ''});
              handoffForm.classList.add('is-sent');
              Array.prototype.forEach.call(handoffForm.querySelectorAll('input,textarea,button'), function(control) {
                control.disabled = true;
              });
            } catch (error) {
              if (button) button.disabled = false;
              if (status) status.textContent = error.message || 'Most nem sikerült elküldeni. Kérjük, próbálja újra.';
            }
          }

          function handleSubmit(event) {
            event.preventDefault();
            if (!refreshElements()) return;
            var text = input.value.trim();
            if (!text) return;
            input.value = '';
            ask(text);
          }

          refreshElements();
          document.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.closest) return;
            if (target.closest('.harmat-local-ai-launch')) {
              event.preventDefault();
              openPanel();
              return;
            }
            if (target.closest('.harmat-local-ai-close')) {
              event.preventDefault();
              closePanel();
            }
            var langButton = target.closest('[data-harmat-ai-lang]');
            if (langButton) {
              event.preventDefault();
              setLanguage(langButton.getAttribute('data-harmat-ai-lang'));
              trackEvent('quick_button_click', {label: 'language_' + currentLang});
            }
          }, true);
          document.addEventListener('submit', function (event) {
            if (event.target && event.target.matches && event.target.matches('[data-harmat-local-ai-handoff]')) {
              handleHandoffSubmit(event);
              return;
            }
            if (event.target && event.target.matches && event.target.matches('[data-harmat-local-ai-form]')) {
              handleSubmit(event);
            }
          }, true);
          window.addEventListener('load', refreshElements, { once: true });
        })();
        </script>
        <?php
    }

    private function is_private_portal() {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');
        return in_array($path, array('sales', 'agent', 'client', 'customer', 'ugyfel', 'sales-admin'), true);
    }

    private function limit_text($text, $max_chars) {
        $text = trim((string) $text);
        $max_chars = max(1, (int) $max_chars);
        return mb_substr($text, 0, $max_chars, 'UTF-8');
    }

    private function answer_message($message, $lang) {
        $normalized = $this->normalize($message);
        $apartments = $this->apartments();
        $profile = $this->extract_buyer_profile($message, $normalized);
        $filters = $this->extract_filters($message, $normalized);
        $filters['profile'] = $profile;

        $code = $this->extract_apartment_code($message);
        $intent = $this->classify_intent($message, $normalized, $filters, $profile, $code);
        if ($this->is_technical_document_question($normalized)) {
            return $this->response(
                $this->technical_document_answer($lang),
                array(),
                $lang,
                null,
                $this->technical_document_actions($lang),
                'technical_document_opened',
                'technical_document'
            );
        }
        if ($code) {
            $apartment = $this->find_apartment($code, $apartments);
            if ($apartment) {
                return $this->response($this->apartment_answer($apartment, $lang, $profile), array($this->card($apartment, $profile)), $lang, null, $this->apartment_actions($apartment, $lang), '', $intent);
            }
            return $this->response($this->unknown_apartment_answer($code, $lang), array(), $lang);
        }

        if ($this->is_offer_request($normalized)) {
            $this->track_event('offer_request_started', array('lang' => $lang));
            return $this->response(
                $this->offer_request_answer($lang),
                array(),
                $lang,
                $this->handoff_payload('offer', $lang, $filters, $profile),
                $this->sales_contact_actions($lang),
                'offer_request_started',
                'offer'
            );
        }

        if ($this->is_appointment_request($normalized)) {
            $this->track_event('appointment_started', array('lang' => $lang));
            return $this->response(
                $this->sales_office_visit_answer($lang),
                array(),
                $lang,
                $this->handoff_payload('appointment', $lang, $filters, $profile),
                $this->sales_office_actions($lang),
                'appointment_started',
                'appointment'
            );
        }

        if ($this->is_sales_office_request($normalized)) {
            return $this->response(
                $this->sales_office_visit_answer($lang),
                array(),
                $lang,
                null,
                $this->sales_office_actions($lang),
                '',
                'location'
            );
        }

        if ($this->is_available_list_request($normalized)) {
            $matches = $this->default_available_apartments($apartments, $filters, $profile);
            if ($matches) {
                return $this->response(
                    $this->recommendation_answer($matches, $filters, $lang),
                    $this->cards_for_matches($matches, $profile, $lang),
                    $lang,
                    null,
                    $this->recommendation_actions($lang),
                    'apartment_recommendation',
                    'recommendation'
                );
            }
        }

        $profile_driven_search = !$filters['has_search'] && $this->profile_requests_recommendation($profile, $normalized);
        if ($profile_driven_search) {
            $filters['has_search'] = true;
        }
        $ground_floor_search = $filters['ground_floor'] && $this->has_any($normalized, array('ajanl', 'keres', 'lakas', 'lakast', 'recommend', 'available', 'apartment', 'flat', 'melyik', '预算', '推荐', '房源', '有哪些'));

        if ($this->should_answer_faq_before_search($intent, $filters, $profile_driven_search, $ground_floor_search)) {
            $faq = $this->faq_answer($normalized, $lang, $intent);
            if ($faq !== null) {
                return $this->response($faq, array(), $lang);
            }
        }

        if ($filters['has_search'] && ($filters['rooms'] || $filters['budget'] || $filters['area'] || $filters['area_min'] || $filters['area_max'] || $filters['building'] || $filters['floor'] || $filters['garden'] || $filters['terrace'] || $filters['cheap'] || $ground_floor_search || $profile_driven_search)) {
            $matches = $this->search_apartments($apartments, $filters);
            if ($matches) {
                return $this->response($this->recommendation_answer($matches, $filters, $lang), $this->cards_for_matches($matches, $profile, $lang), $lang, null, $this->recommendation_actions($lang), 'apartment_recommendation', 'recommendation');
            }
            $near_matches = $this->near_match_apartments($apartments, $filters);
            if ($near_matches) {
                return $this->response($this->near_match_answer($near_matches, $filters, $lang), $this->cards_for_matches($near_matches, $profile, $lang), $lang, null, $this->recommendation_actions($lang), 'apartment_recommendation', 'recommendation');
            }
            return $this->response($this->text('no_match', $lang), array(), $lang);
        }

        $faq = $this->faq_answer($normalized, $lang, $intent);
        if ($faq !== null) {
            return $this->response($faq, array(), $lang);
        }

        if ($filters['has_search']) {
            return $this->response($this->selection_guidance_answer($lang), array(), $lang);
        }

        if ($this->has_price_intent($normalized)) {
            return $this->response($this->price_overview_answer($apartments, $lang), array(), $lang);
        }

        $this->track_event('unknown_question', array('lang' => $lang, 'intent' => $intent));
        return $this->response($this->text('fallback', $lang), array(), $lang, $this->handoff_payload('handoff', $lang, $filters, $profile), $this->sales_contact_actions($lang), 'unknown_question', $intent);
    }

    private function response($answer, $cards, $lang, $handoff = null, $actions = array(), $event = '', $intent = '') {
        return array(
            'ok' => true,
            'answer' => $answer,
            'cards' => $cards,
            'handoff' => $handoff,
            'actions' => $actions,
            'event' => $event,
            'intent' => $intent,
            'suggestions' => $this->default_suggestions($lang),
        );
    }

    private function apartments() {
        if (self::$apartments !== null) {
            return self::$apartments;
        }

        $path = plugin_dir_path(__FILE__) . 'data/harmat_apartments_kb.json';
        if (!is_readable($path)) {
            self::$apartments = array();
            return self::$apartments;
        }

        $data = json_decode((string) file_get_contents($path), true);
        self::$apartments = is_array($data) ? $data : array();
        return self::$apartments;
    }

    private function detect_language($message) {
        if (preg_match('/\p{Han}/u', $message)) {
            return 'zh';
        }

        if (preg_match('/\b(price|prices|amount|cost|payment|pay|installment|quote|offer|address|apartment|flat|handover|floor|room|rooms|investment|investor|rental|rent|family|child|children|floorplan|layout|pdf|reserve|reservation|status|available|sold|budget|parking|storage|loan|mortgage|when|where|have|nearby|opening|hours|weekend|dog|pet|park|school|shopping|transport|contact|appointment|viewing|visit|heating|cooling|green|process|purchase|choose|advantage|benefit|discount|expensive|cheaper|launch|garden|ground)\b/i', $message)) {
            return 'en';
        }

        return 'hu';
    }

    private function normalize($text) {
        $text = mb_strtolower($text, 'UTF-8');
        $from = array('á','é','í','ó','ö','ő','ú','ü','ű');
        $to = array('a','e','i','o','o','o','u','u','u');
        $text = str_replace(
            array('á','à','ä','â','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ő','ô','ú','ù','ü','ű','û'),
            array('a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','u'),
            $text
        );
        return str_replace($from, $to, $text);
    }

    private function extract_apartment_code($message) {
        if (!preg_match('/\b(A\d{1,2})[\s_-]*(F|FSZ|\d{1,2})[\s_-]*(L\d{1,2})\b/iu', $message, $match)) {
            return null;
        }

        $floor = strtoupper($match[2]);
        if ($floor === 'FSZ') {
            $floor = 'F';
        }

        return strtoupper($match[1]) . '-' . $floor . '-' . strtoupper($match[3]);
    }

    private function find_apartment($code, $apartments) {
        $needle = mb_strtolower($code, 'UTF-8');
        foreach ($apartments as $apartment) {
            if (mb_strtolower((string) ($apartment['apartment'] ?? ''), 'UTF-8') === $needle) {
                return $apartment;
            }
        }
        return null;
    }

    private function unknown_apartment_answer($code, $lang) {
        return $this->by_lang($lang,
            sprintf('A megadott lakáskódot nem találtam az aktuális adatbázisban: %s. Kérjük, ellenőrizze a kódot, például A1-F-L1 formában. Ha bizonytalan, írja meg a kívánt szobaszámot, árkeretet vagy emeletet, és ajánlok elérhető lakásokat.', $code),
            sprintf('我在当前房源库里没有找到这个房号：%s。请确认房号格式，例如 A1-F-L1。如果你不确定房号，可以告诉我房间数、预算或楼层偏好，我帮你筛选。', $code),
            sprintf('I could not find this apartment code in the current database: %s. Please check the code format, for example A1-F-L1. If you are not sure, share room count, budget or floor preference and I can suggest available units.', $code)
        );
    }

    private function classify_intent($message, $normalized, $filters, $profile, $code) {
        $scores = array(
            'apartment_code' => $code ? 20 : 0,
            'recommendation' => 0,
            'apartment_search' => 0,
            'price' => 0,
            'availability' => 0,
            'floorplan' => 0,
            'appointment' => 0,
            'payment' => 0,
            'loan' => 0,
            'subsidy' => 0,
            'discount' => 0,
            'legal' => 0,
            'surroundings' => 0,
            'pet' => 0,
            'garden' => 0,
            'opening' => 0,
            'handover' => 0,
            'project_count' => 0,
            'parking' => 0,
            'technical' => 0,
            'developer' => 0,
            'process' => 0,
            'help' => 0,
            'location' => 0,
        );

        if (!empty($filters['rooms']) || !empty($filters['budget']) || !empty($filters['area']) || !empty($filters['area_min']) || !empty($filters['area_max']) || !empty($filters['building']) || !empty($filters['floor']) || !empty($filters['garden']) || !empty($filters['terrace']) || !empty($filters['cheap']) || !empty($filters['ground_floor'])) {
            $scores['apartment_search'] += 4;
        }
        if (!empty($filters['has_search'])) {
            $scores['apartment_search'] += 2;
        }
        if (!empty(array_filter($profile)) && $this->profile_requests_recommendation($profile, $normalized)) {
            $scores['recommendation'] += 5;
        }

        $this->add_intent_score($scores, 'recommendation', $normalized, array('ajanl', 'melyik', 'melyiket', 'valasszam', 'shortlist', 'recommend', 'which', 'suitable', 'good for', '推荐', '哪套', '哪些', '适合', '帮我选', '怎么选'), 6);
        $this->add_intent_score($scores, 'price', $normalized, array('ar', 'ara', 'arak', 'mennyibe', 'price', 'prices', 'amount', 'cost', 'huf', 'ft', '多少钱', '价格', '价钱', '金额', '总价', '单价', '预算'), 5);
        $this->add_intent_score($scores, 'availability', $normalized, array('elerheto', 'elerhetoseg', 'foglalhato', 'szabad', 'available', 'availability', 'reserve', 'reservation', 'status', 'sold', '可售', '还有吗', '能预订', '预订', '保留', '状态', '卖掉', '已售'), 5);
        $this->add_intent_score($scores, 'floorplan', $normalized, array('alaprajz', 'floorplan', 'floor plan', 'layout', 'pdf', 'virtualis', 'lakasvalaszto', '户型图', '平面图', '虚拟选房', '房源详情'), 5);
        $this->add_intent_score($scores, 'appointment', $normalized, array('idopont', 'megtekintes', 'ajanlatkeres', 'ajanlat', 'contact', 'appointment', 'visit', 'viewing', 'quote', 'offer', '预约', '看房', '联系', '报价', '询价'), 6);
        $this->add_intent_score($scores, 'payment', $normalized, array('fizetes', 'fizetesi', 'utemezes', 'reszlet', 'teljes fizetes', 'fizetesi merfoldko', 'merfoldko', 'payment', 'pay', 'installment', 'schedule', 'milestone', '付款', '付款方式', '付款节点', '资金节点', '工程节点', '怎么付款', '分期', '全款', '首付', '50-50'), 5);
        $this->add_intent_score($scores, 'loan', $normalized, array('finanszirozas', 'finanszírozás', 'hitel', 'bank', 'loan', 'mortgage', 'financing', '贷款', '按揭', '银行贷款', '融资'), 7);
        $this->add_intent_score($scores, 'subsidy', $normalized, array('csok', 'tamogatas', 'subsidy', '补贴', '政府补贴', '家庭补贴'), 7);
        $this->add_intent_score($scores, 'discount', $normalized, array('engedmeny', 'kedvezmeny', 'akcio', 'alku', 'discount', 'promotion', 'negotiate', '优惠', '折扣', '砍价', '讲价', '便宜点', '特价'), 8);
        $this->add_intent_score($scores, 'legal', $normalized, array('szerzodes', 'ugyved', 'ado', 'illetek', 'foldhivatal', 'contract', 'lawyer', 'legal', 'tax', 'duty', 'permit', 'residence permit', 'vat', '合同', '律师', '法律', '税费', '过户', '许可', '居留', '印花税'), 8);
        $this->add_intent_score($scores, 'surroundings', $normalized, array('kornyek', 'kozelben', 'ohegy', 'iskola', 'egyetem', 'bevasarlas', 'kozlekedes', 'onkormanyzat', 'nearby', 'surrounding', 'school', 'university', 'mall', 'shopping', 'transport', 'district office', '周边', '附近', '学校', '小学', '中学', '大学', '商场', '购物', '交通', '区政府'), 5);
        $this->add_intent_score($scores, 'pet', $normalized, array('kutyafuttato', 'kutya', 'kisallat', 'allatbarat', 'dog park', 'pet park', 'pets', 'dog', '宠物公园', '宠物', '狗公园', '遛狗'), 6);
        $this->add_intent_score($scores, 'garden', $normalized, array('foldszinti kert', 'kert ajandek', 'garden', 'ground floor garden', 'gift garden', 'included garden', '底楼花园', '底层花园', '花园赠送', '赠送花园', '送花园'), 5);
        $this->add_intent_score($scores, 'opening', $normalized, array('ertekesites indul', 'nyito', 'nyitas', 'sales launch', 'launch date', 'opening date', '开盘', '开售', '发售'), 5);
        $this->add_intent_score($scores, 'handover', $normalized, array('atadas', 'hatarido', 'handover', 'delivery', '交付', '交房'), 5);
        $this->add_intent_score($scores, 'project_count', $normalized, array('hany lakas', 'osszesen', 'darab', 'how many', '多少套', '几套', '总共'), 5);
        $this->add_intent_score($scores, 'parking', $normalized, array('parkolo', 'garazs', 'tarolo', 'parking', 'storage', '车位', '停车', '储藏'), 5);
        $this->add_intent_score($scores, 'technical', $normalized, array('hoszivattyu', 'futes', 'hutes', 'energia', 'zoldfelulet', 'uj epites', 'heating', 'cooling', 'heat pump', 'energy', 'green ratio', 'new build', '采暖', '制冷', '热泵', '新房', '绿化', '绿地', '配置'), 5);
        $this->add_intent_score($scores, 'developer', $normalized, array('fejleszto', 'beruhazo', 'investor company', 'developer', 'company', '开发商', '投资方', '公司'), 5);
        $this->add_intent_score($scores, 'process', $normalized, array('vasarlas menete', 'vasarlasi folyamat', 'hogyan tudok vasarolni', 'buying process', 'purchase process', 'how to buy', 'next step', '买房流程', '购买流程', '怎么买', '下一步', '购买步骤', '流程'), 5);
        $this->add_intent_score($scores, 'help', $normalized, array('mit tudsz', 'miben segitesz', 'segitseg', 'help', 'what can you do', 'assistant', '你能做什么', '你会什么', '怎么使用', '客服'), 5);
        $this->add_intent_score($scores, 'location', $normalized, array('hol', 'talalhato', 'cim', 'address', 'where', '位置', '地址'), 5);

        if ($this->is_payment_schedule_question($normalized)) {
            $scores['payment'] += 12;
        }
        if ($this->is_tax_vat_question($normalized)) {
            $scores['legal'] += 12;
        }

        arsort($scores);
        $intent = (string) key($scores);
        return ((int) current($scores)) > 0 ? $intent : 'unknown';
    }

    private function add_intent_score(&$scores, $intent, $text, $needles, $points) {
        if ($this->has_any($text, $needles)) {
            $scores[$intent] += (int) $points;
        }
    }

    private function should_answer_faq_before_search($intent, $filters, $profile_driven_search, $ground_floor_search) {
        if (in_array($intent, array('recommendation', 'apartment_search', 'price', 'availability', 'floorplan'), true)) {
            return false;
        }
        if ($profile_driven_search || $ground_floor_search) {
            return false;
        }
        if (!empty($filters['rooms']) || !empty($filters['budget']) || !empty($filters['area']) || !empty($filters['area_min']) || !empty($filters['area_max']) || !empty($filters['building']) || !empty($filters['floor']) || !empty($filters['garden']) || !empty($filters['terrace']) || !empty($filters['cheap'])) {
            return false;
        }
        return in_array($intent, array('appointment', 'payment', 'loan', 'subsidy', 'discount', 'legal', 'surroundings', 'pet', 'garden', 'opening', 'handover', 'project_count', 'parking', 'technical', 'developer', 'process', 'help', 'location'), true);
    }

    private function is_transport_question($text) {
        return $this->has_any($text, array('kozlekedes', 'busz', 'autobusz', 'jarat', 'megallo', 'bkk', 'budapestgo', 'transport', 'bus', 'public transport', 'bus route', 'bus stop', '交通', '公交', '公交车', '巴士', '线路', '几路', '车站'));
    }

    private function is_education_question($text) {
        return $this->has_any($text, array('iskola', 'altalanos iskola', 'gimnazium', 'technikum', 'ovoda', 'bolcsode', 'education', 'school', 'primary school', 'secondary school', 'kindergarten', '学校', '小学', '中学', '高中', '幼儿园', '教育'));
    }

    private function transport_answer($lang) {
        return $this->by_lang($lang,
            'A Harmat utca 22. kb. 1000 m-es körzetében a nyilvános térképadatok alapján több buszjárat érhető el. A környéken megjelenő járatok közé tartozik: 85, 85E, 95, 161, 161A, 161E, 162, 168E, 169E, 185, 195, 217, 261E, 262, valamint éjszakai/távolsági jellegű vonalak is előfordulhatnak. Közeli megállók például: Kápolna tér, Óhegy park, Szent László Gimnázium, Kada utca / Harmat utca, Kőér utca. Menetrendhez és aktuális tereléshez mindig a BudapestGO/BKK adatait érdemes ellenőrizni.',
            'Harmat utca 22 周边约 1000 米范围内，公开地图数据显示有多条公交线路可用。周边常见线路包括：85、85E、95、161、161A、161E、162、168E、169E、185、195、217、261E、262，也可能有夜间/郊区线路。附近站点包括：Kápolna tér、Óhegy park、Szent László Gimnázium、Kada utca / Harmat utca、Kőér utca。具体到站时间和临时改线建议以 BudapestGO/BKK 为准。',
            'Within roughly 1000 m of Harmat utca 22, public map data shows several bus routes nearby, including 85, 85E, 95, 161, 161A, 161E, 162, 168E, 169E, 185, 195, 217, 261E and 262, with some night/suburban services also appearing nearby. Nearby stops include Kápolna tér, Óhegy park, Szent László Gimnázium, Kada utca / Harmat utca and Kőér utca. Please check BudapestGO/BKK for live timetables and diversions.'
        );
    }

    private function education_answer($lang) {
        return $this->by_lang($lang,
            'A környéken több oktatási intézmény található. Kb. 1-1,2 km-en belül nyilvános térképadatok alapján például: Janikovszky Éva Általános Iskola, Harmat Általános Iskola, Pannonhalmi Béla Baptista Általános Iskola, BGSZC Giorgio Perlasca Vendéglátóipari Technikum és Szakképző Iskola, Bercsényi Miklós Élelmiszeripari Szakképző Iskola, valamint Kőbányai Szent László Gimnázium kicsit távolabb. Óvoda/bölcsőde oldalról például Kiskakas Óvoda, Apró csodák Bölcsőde és Kőbányai Kincskeresők Óvoda szerepel a környéken. Beiratkozási körzetet és aktuális férőhelyet mindig az adott intézmény vagy az önkormányzat erősíti meg.',
            '周边有多个教育配套。公开地图数据显示，约 1-1.2 公里内可关注：Janikovszky Éva Általános Iskola、Harmat Általános Iskola、Pannonhalmi Béla Baptista Általános Iskola、BGSZC Giorgio Perlasca 技术/职业学校、Bercsényi Miklós 职业学校，以及距离稍远的 Kőbányai Szent László Gimnázium。幼儿园/托育方面有 Kiskakas Óvoda、Apró csodák Bölcsőde、Kőbányai Kincskeresők Óvoda 等。入学片区、学位和当年政策需以学校或市政部门最新确认为准。',
            'There are several education facilities nearby. Public map data shows, within roughly 1-1.2 km, Janikovszky Éva Primary School, Harmat Primary School, Pannonhalmi Béla Baptist Primary School, BGSZC Giorgio Perlasca vocational/technical school, Bercsényi Miklós vocational school, and Kőbányai Szent László Gimnázium slightly farther away. For kindergarten/nursery, examples include Kiskakas Óvoda, Apró csodák Bölcsőde and Kőbányai Kincskeresők Óvoda. Catchment area and availability should be confirmed with the institution or municipality.'
        );
    }

    private function faq_answer_by_intent($intent, $lang) {
        switch ($intent) {
            case 'discount':
                return $this->by_lang($lang,
                    'Kedvezményt, alkut vagy akciós árat az asszisztens nem ígérhet. Ha egy konkrét lakás érdekli, meg tudom adni a tájékoztató árat és a státuszt, majd az értékesítés tudja megerősíteni, hogy az adott lakásnál van-e aktuális egyedi feltétel.',
                    '优惠、折扣或议价不能由 AI 承诺。如果你有具体房号，我可以先查当前参考价和状态；是否有个别优惠或特别条件，需要销售团队按具体房源确认。',
                    'The assistant cannot promise discounts, negotiation terms or promotional prices. If you share a specific apartment code, I can show the indicative price and status; sales must confirm any individual condition for that unit.'
                );
            case 'legal':
                return $this->by_lang($lang,
                    'Szerződéses, adózási, illeték-, földhivatali vagy jogi kérdésben csak általános tájékoztatást adhatok. Pontos választ az értékesítés és az ügyvéd tud adni a kiválasztott lakás, vevői helyzet és szerződéses dokumentumok alapján.',
                    '合同、税费、过户、律师、许可或法律问题，我只能做一般说明，不能给正式法律意见。准确答案需要销售团队和律师根据具体房号、买方身份和合同文件确认。',
                    'For contract, tax, duty, land-registry, permit or legal questions, I can only provide general guidance. Sales and the lawyer must confirm the exact answer based on the selected unit, buyer situation and contract documents.'
                );
            case 'loan':
                return $this->financing_answer($lang);
            case 'subsidy':
                return $this->financing_answer($lang);
            case 'payment':
                return $this->payment_schedule_answer($lang);
            case 'appointment':
                return $this->by_lang($lang,
                    'Szívesen segítünk ajánlatot vagy időpontot kérni. A gyors egyeztetéshez érdemes megadni: név, telefon vagy e-mail, kiválasztott lakáskód, szobaszám, árkeret és kívánt időpont. Elérhetőség: ertekesites@harmat22.hu, +36300733375.',
                    '可以预约看房或索取报价。为了销售更快回复，建议留下：姓名、电话或邮箱、目标房号、房间数、预算和方便的时间。联系方式：ertekesites@harmat22.hu，+36300733375。',
                    'We can help start an offer request or viewing appointment. For a faster reply, please provide: name, phone or email, target apartment code, room count, budget and preferred time. Contact: ertekesites@harmat22.hu, +36300733375.'
                );
        }

        return null;
    }

    private function faq_answer($text, $lang, $intent = null) {
        if ($this->is_transport_question($text)) {
            return $this->transport_answer($lang);
        }
        if ($this->is_education_question($text)) {
            return $this->education_answer($lang);
        }
        if ($this->is_payment_schedule_question($text)) {
            return $this->payment_schedule_answer($lang);
        }
        if ($this->is_tax_vat_question($text)) {
            return $this->tax_vat_answer($lang);
        }
        $priority = $this->faq_answer_by_intent($intent, $lang);
        if ($priority !== null) {
            return $priority;
        }

        if ($this->has_any($text, array('nyitvatart', 'nyitva', 'hetfo', 'pentek', 'szombat', 'vasarnap', 'hetveg', 'opening hour', 'opening hours', 'weekend', 'open on', 'hours', '营业', '营业时间', '开门', '周末', '上班时间'))) {
            return $this->by_lang($lang,
                'Az értékesítési iroda hétfőtől péntekig 09:00-17:00 között érhető el. Szombaton és vasárnap zárva tart. Időpont egyeztetéshez írjon az ertekesites@harmat22.hu címre vagy hívja a +36300733375 számot.',
                '销售办公室营业时间：星期一至星期五 09:00-17:00，星期六和星期天不营业。如需预约看房，可以联系 ertekesites@harmat22.hu 或 +36300733375。',
                'The sales office is available Monday to Friday, 09:00-17:00. It is closed on Saturday and Sunday. For a viewing appointment, contact ertekesites@harmat22.hu or +36300733375.'
            );
        }

        if ($this->has_any($text, array('ertekesites indul', 'ertekesitesi indul', 'nyito', 'nyitas', 'mikor indul', 'start', 'sales launch', 'launch date', 'opening date', 'launch', '开盘', '开售', '发售', '什么时候卖', '什么时候开'))) {
            return $this->opening_construction_answer($lang);
        }

        if ($this->has_any($text, array('foldszinti kert', 'foldszint kert', 'kert ajandek', 'ajandek kert', 'foldszint', 'garden', 'ground floor garden', 'ground-floor garden', 'gift garden', 'included garden', '底楼花园', '底层花园', '花园赠送', '赠送花园', '送花园', '底楼', '底层'))) {
            return $this->by_lang($lang,
                'A jelenlegi értékesítési információ szerint a földszinti lakások kertje ajándékként jár, külön kertár nélkül. A kert méretét, használati részleteit és szerződéses rögzítését mindig az adott lakásnál az értékesítés erősíti meg.',
                '目前销售信息：底楼房源的花园为赠送，不单独计算花园价格。具体花园面积、使用规则和合同写法，需要按具体房号由销售团队确认。',
                'According to current sales information, gardens for ground-floor apartments are included as a gift, with no separate garden price. Garden size, use details and contractual wording should be confirmed by sales for the specific apartment.'
            );
        }

        if ($this->has_any($text, array('kutyafuttato', 'kutya', 'kisallat', 'allatbarat', 'dog park', 'pet park', 'pets', 'dog', '宠物公园', '宠物', '狗公园', '遛狗', '狗'))) {
            return $this->by_lang($lang,
                'Igen, a Harmat utca 22. közelében, körülbelül 200 méterre található kutyafuttató. Ez erős plusz azoknak, akik kisállattal költöznének; emellett az Óhegy park is kb. 600 méterre van.',
                '有的。Harmat utca 22. 附近约 200 米有宠物/遛狗公园，适合养宠家庭日常散步。Óhegy park 也在约 600 米范围内，周边绿地资源比较好。',
                'Yes. There is a nearby dog park about 200 m from Harmat utca 22, useful for residents with pets. Óhegy park is also about 600 m away.'
            );
        }

        if ($this->has_any($text, array('kornyek', 'kozelben', 'ohegy', 'iskola', 'egyetem', 'bevasarlas', 'kozlekedes', 'busz', 'villamos', 'onkormanyzat', 'polgarmesteri', 'nearby', 'surrounding', 'school', 'university', 'mall', 'shopping', 'transport', 'district office', '周边', '附近', '学校', '小学', '中学', '大学', '商场', '购物', '交通', '区政府', '政府'))) {
            return $this->by_lang($lang,
                'A környék főbb pontjai: kutyafuttató kb. 200 m, Óhegy park kb. 600 m, Kőbányai Szent László Gimnázium kb. 700 m, Kőbányai Harmat Általános Iskola kb. 1,2 km, Szent László tér / kerületi központ kb. 800 m, ÁRKÁD Budapest kb. 1,9 km, Sugár Üzletközpont kb. 2,1 km, KÖKI Terminál kb. 2,6 km. A távolságok tájékoztató jellegűek.',
                '周边重点配套：宠物公园约 200 米，Óhegy park 约 600 米，Kőbányai Szent László Gimnázium 约 700 米，Kőbányai Harmat Általános Iskola 约 1.2 公里，区中心/Szent László tér 约 800 米，ÁRKÁD Budapest 约 1.9 公里，Sugár 商场约 2.1 公里，KÖKI Terminál 约 2.6 公里。距离为参考值。',
                'Nearby highlights: dog park about 200 m, Óhegy park about 600 m, Kőbányai Szent László Gimnázium about 700 m, Kőbányai Harmat Általános Iskola about 1.2 km, Szent László tér / district centre about 800 m, ÁRKÁD Budapest about 1.9 km, Sugár shopping centre about 2.1 km, and KÖKI Terminál about 2.6 km. Distances are indicative.'
            );
        }

        if ($this->has_any($text, array('alaprajz', 'floorplan', 'floor plan', 'pdf', 'layout', 'virtualis', 'lakasvalaszto', '户型图', '平面图', 'pdf', '虚拟选房', '房号怎么看', '怎么看房号', '房源详情'))) {
            return $this->by_lang($lang,
                'A lakáskód felépítése például A1-F-L1: A1 épület, Fsz vagy emelet, L1 lakás. A részletes adatlap és alaprajz a lakás oldalán érhető el, a böngészéshez használható a Lakáskereső és a Virtuális lakásválasztó is.',
                '房号示例 A1-F-L1：A1 是楼栋，F/FSZ 表示底层，数字表示楼层，L1 是房号。每套房源详情页里可以看面积、参考价格和户型图/PDF；也可以通过“Lakáskereső”或“虚拟选房”按楼栋、楼层筛选。',
                'Apartment codes work like A1-F-L1: A1 is the building, F/FSZ or a number is the floor, and L1 is the unit. The apartment detail page contains area, indicative price and floor plan/PDF where available; you can also browse via the apartment search or virtual selector.'
            );
        }

        if ($this->has_any($text, array('elerheto', 'elerhetoseg', 'foglalhato', 'foglalas', 'statusz', 'szabad', 'available', 'availability', 'reserve', 'reservation', 'status', 'sold', 'hold', '可售', '还有吗', '能预订', '预订', '保留', '状态', '卖掉', '已售'))) {
            return $this->by_lang($lang,
                'Az elérhetőség gyorsan változhat. Ha megad egy konkrét lakáskódot, például A1-F-L1, meg tudom mutatni a jelenlegi adatbázis szerinti státuszt és irányárat; a foglalást és végleges elérhetőséget az értékesítés erősíti meg.',
                '可售状态会变化。如果你给我具体房号，例如 A1-F-L1，我可以按当前房源库显示状态、面积和参考金额；是否能预订、保留条件和最终可售状态需要销售确认。',
                'Availability can change quickly. If you provide a specific apartment code, for example A1-F-L1, I can show the current database status, area and indicative price; reservation and final availability must be confirmed by sales.'
            );
        }

        if ($this->has_any($text, array('befektetes', 'kiadas', 'befekteto', 'investment', 'investor', 'rental', 'rent out', 'yield', '投资', '出租', '租金', '回报', '收益'))) {
            return $this->by_lang($lang,
                'Befektetési szempontból általában a jó ár-értékű 1-2 szobás lakások, a közlekedési kapcsolatok és a könnyen érthető környék számítanak erősnek. Hozamot vagy bérleti díjat nem ígérek; ha megad árkeretet és szobaszámot, konkrét lakásokat tudok ajánlani az aktuális árlistából.',
                '投资角度通常更关注总价、面积效率、1-2 房、交通和周边配套。AI 不承诺租金或收益率；如果你告诉我预算和房间数，我可以按当前价格表推荐具体房号和金额。',
                'For investment, buyers often compare total price, efficient layout, 1-2 room flats, transport and easy-to-understand surroundings. I cannot promise rent or yield; if you share budget and room count, I can recommend specific units from the current price list.'
            );
        }

        if ($this->has_any($text, array('csalad', 'gyerek', 'gyerekes', 'sajat lakhat', 'family', 'children', 'kids', 'own use', 'live in', '自住', '家庭', '孩子', '小孩', '老人', '宠物家庭'))) {
            return $this->by_lang($lang,
                'Saját lakhatásra és családoknak a zöld környezet, az Óhegy park kb. 600 m-re, a közeli kutyafuttató kb. 200 m-re, valamint a környékbeli iskolák lehetnek fontos szempontok. Ilyenkor gyakran 2-4 szobás, kényelmesebb alapterületű lakásokat érdemes nézni.',
                '自住和家庭客户可以重点看：绿地环境、约 600 米的 Óhegy park、约 200 米的宠物公园，以及周边学校。通常 2-4 房、面积更舒适的户型更适合家庭，我可以按预算继续筛选。',
                'For own use and families, useful points include the green setting, Óhegy park about 600 m away, the dog park about 200 m away, and nearby schools. Families often compare 2-4 room flats with more comfortable area; I can filter by budget.'
            );
        }

        if ($this->has_any($text, array('hoszivattyu', 'futes', 'hutes', 'energia', 'zoldfelulet', 'uj epites', 'heating', 'cooling', 'heat pump', 'energy', 'green ratio', 'new build', '采暖', '制冷', '热泵', '新房', '绿化', '绿地', '配置'))) {
            return $this->by_lang($lang,
                'A projekt új építésű lakópark, a jelenlegi ismert fő pontok: 75% zöldfelület, hőszivattyús fűtés-hűtés, teremgarázs és tárolók. A műszaki tartalom részleteit és szerződéses vállalásait az értékesítési dokumentáció rögzíti.',
                '项目是新建住宅，当前已知重点包括：约 75% 绿化/绿地比例、热泵采暖制冷、地下车位和储藏室。具体技术配置、交付标准和合同承诺需以销售资料和正式文件为准。',
                'The project is a new-build residential development. Known highlights include about 75% green area, heat-pump heating/cooling, underground parking and storage units. Technical specifications and contractual commitments should be checked in the official sales documents.'
            );
        }

        if ($this->has_any($text, array('fejleszto', 'beruhazo', 'investor company', 'developer', 'company', '开发商', '投资方', '公司'))) {
            return $this->by_lang($lang,
                'A projekthez a weboldalon megjelenített fejlesztő / beruházó: Cooperation Power Kft. Szerződéses vagy cégjogi részletekben az értékesítés tud pontos tájékoztatást adni.',
                '网站显示的项目开发/投资方为 Cooperation Power Kft.。如涉及合同主体或公司法律信息，需要销售团队提供正式确认。',
                'The developer/investor shown on the website is Cooperation Power Kft. For contractual or corporate legal details, please ask the sales team for official confirmation.'
            );
        }

        if ($this->has_any($text, array('mit tudsz', 'miben segitesz', 'segitseg', 'help', 'what can you do', 'how can you help', 'assistant', '你能做什么', '你会什么', '怎么使用', '客服', 'ai功能', 'ai 功能'))) {
            return $this->by_lang($lang,
                'Lakáskeresésben, ár- és státuszellenőrzésben, alaprajzokban, környék-információban, fizetési irányokban és időpontkérésben tudok segíteni. A leggyorsabb út: írjon szobaszámot, árkeretet, alapterületet vagy konkrét lakáskódot, például A1-F-L1.',
                '我可以帮你做几件事：按预算/房间数/面积筛房源，查具体房号价格和状态，解释户型图、周边、付款方向，并引导预约或报价。最快的问法是告诉我：预算、房间数、面积，或直接给房号，例如 A1-F-L1。',
                'I can help with apartment search, indicative price/status checks, floor plans, surroundings, payment directions and viewing or offer requests. The fastest way is to share room count, budget, size, or a specific code such as A1-F-L1.'
            );
        }

        if ($this->has_any($text, array('vasarlas menete', 'vasarlasi folyamat', 'hogyan tudok vasarolni', 'kovetkezo lepes', 'buying process', 'purchase process', 'how to buy', 'next step', '买房流程', '购买流程', '怎么购买', '怎么买', '下一步', '购买步骤', '流程'))) {
            return $this->by_lang($lang,
                'A javasolt folyamat: 1. cél és árkeret tisztázása, 2. lakáskeresés szobaszám, méret és ár alapján, 3. konkrét lakás státuszának és tájékoztató árának ellenőrzése, 4. ajánlat- vagy időpontkérés, 5. foglalási és fizetési feltételek egyeztetése az értékesítéssel, 6. szerződéses dokumentáció. A végleges feltételeket mindig az értékesítés és a szerződés rögzíti.',
                '建议流程：1. 明确自住/投资、预算和房间数；2. 按面积、楼层、房号筛选；3. 确认目标房源的状态和参考金额；4. 预约看房或索取报价；5. 和销售确认保留、付款节奏和所需资料；6. 进入正式合同流程。最终条件以销售和正式文件为准。',
                'Suggested process: 1. clarify own-use/investment, budget and room count, 2. shortlist by size, floor and apartment code, 3. check status and indicative amount, 4. request an offer or viewing, 5. confirm reservation/payment details with sales, 6. proceed with contractual documents. Final terms are always set by sales and the contract.'
            );
        }

        if ($this->has_any($text, array('nem tudom melyik', 'melyiket valasszam', 'valasztasi logika', 'help me choose', 'not sure which', 'which one', 'choose', '怎么选', '不知道选', '帮我选', '选择逻辑', '推荐逻辑'))) {
            return $this->selection_guidance_answer($lang);
        }

        if ($this->has_any($text, array('elony', 'miert jo', 'miert erdemes', 'miert vegyek', 'osszehasonlit', 'advantage', 'benefit', 'why buy', 'why harmat', 'worth', 'compare', '优点', '优势', '为什么买', '值得买吗', '卖点', '对比'))) {
            return $this->by_lang($lang,
                'A Harmat Lakópark fő érvei: új építésű projekt Budapest X. kerületében, Harmat utca 22. cím, kb. 75% zöldfelület, hőszivattyús fűtés-hűtés, 1-5 szobás választék, teremgarázs és tároló lehetőség, Óhegy park kb. 600 m, kutyafuttató kb. 200 m, valamint több iskola és bevásárlási pont a közelben. Ha megad célt és árkeretet, konkrét lakásokat is össze tudok hasonlítani.',
                '项目主要优势：Budapest X 区 Harmat utca 22 的新建住宅项目，约 75% 绿化/绿地比例，热泵采暖制冷，1-5 房可选，地下车位和储藏室配套，Óhegy park 约 600 米，宠物公园约 200 米，周边有学校、商场和区中心。你告诉我用途和预算后，我可以进一步对比具体房源。',
                'Key strengths: a new-build project at Harmat utca 22 in Budapest District X, about 75% green area, heat-pump heating/cooling, 1-5 room options, garage/storage possibilities, Óhegy park about 600 m away, a dog park about 200 m away, plus nearby schools, shopping and district services. Share your goal and budget and I can compare specific units.'
            );
        }

        if ($this->has_any($text, array('tul draga', 'draga', 'olcsobbat', 'engedmeny', 'kedvezmeny', 'akcio', 'alku', 'too expensive', 'expensive', 'cheaper', 'discount', 'negotiate', '太贵', '贵了', '便宜点', '折扣', '优惠', '优惠价', '砍价', '讲价'))) {
            return $this->by_lang($lang,
                'Ha az árkeret szűk, érdemes először szobaszámot, minimális alapterületet és maximum keretet megadni. Így a jelenlegi listából a kerethez legközelebbi lakásokat tudom ajánlani. Kedvezményt vagy alkut nem ígérhetek; ezt konkrét lakásra az értékesítés erősíti meg.',
                '如果觉得价格偏高，建议先明确：最高预算、最少房间数、最低接受面积，以及是否必须要车位/储藏室。这样我可以按当前房源表找最接近预算的房号。AI 不承诺折扣或议价，具体优惠只能由销售按房号确认。',
                'If the budget feels tight, share your maximum budget, minimum room count, minimum size and whether parking/storage is needed. I can then suggest the closest matches from the current list. I cannot promise discounts or negotiation terms; sales must confirm them for a specific unit.'
            );
        }
        if ($this->has_any($text, array('hol', 'talalhato', 'cim', 'address', 'where', '位置', '地址'))) {
            return $this->by_lang($lang,
                'A Harmat Lakópark Budapest X. kerületében, a Harmat utca 22. szám alatt található. Ha szeretné, segítek lakást keresni szobaszám, méret vagy árkeret alapján.',
                'Harmat Lakópark 位于 Budapest X 区，地址是 Harmat utca 22。你可以告诉我预算、面积或房间数，我可以推荐合适房源。',
                'Harmat Lakópark is located at Harmat utca 22, Budapest District X. Tell me your budget, size or room preference and I can suggest apartments.'
            );
        }

        if ($this->has_any($text, array('atadas', 'hatarido', 'handover', 'delivery', '交付', '交房', '什么时候'))) {
            return $this->opening_construction_answer($lang);
        }

        if ($this->has_any($text, array('fizetes', 'fizetesi', 'utemezes', 'reszlet', 'teljes fizetes', 'fizetesi merfoldko', 'merfoldko', 'payment', 'pay', 'installment', 'schedule', 'milestone', '付款', '付款方式', '付款节点', '资金节点', '工程节点', '怎么付款', '分期', '全款', '首付', '50-50'))) {
            return $this->payment_schedule_answer($lang);

        }

        if ($this->has_any($text, array('hany lakas', 'osszesen', 'darab', 'how many', '多少套', '几套'))) {
            return $this->by_lang($lang,
                'A teljes projekt tervezetten 398 lakásból áll, az első ütemben 124 lakás szerepel. Az első ütemhez 124 teremgarázs-beálló és 92 tároló tartozik.',
                '项目总规划 398 套，第一期 124 套；第一期规划地下车位 124 个、储藏室 92 个。',
                'The full project is planned with 398 apartments, with 124 apartments in the first phase. The first phase includes 124 garage spaces and 92 storage units.'
            );
        }

        if ($this->has_any($text, array('parkolo', 'garazs', 'tarolo', 'parking', 'storage', '车位', '停车', '储藏'))) {
            return $this->by_lang($lang,
                'Az első ütemhez 124 teremgarázs-beálló és 92 tároló tartozik a jelenlegi projektadatok szerint. A parkoló és tároló aktuális elérhetőségét, árát és foglalási feltételeit az értékesítés erősíti meg; ha megad egy lakáskódot, az ajánlatkérésben együtt lehet egyeztetni.',
                '目前项目资料显示，第一期有 124 个地下车位和 92 个储藏室。车位/储藏室的实时可选情况、金额和预订条件需要销售确认；如果你已有目标房号，可以和房源报价一起咨询。',
                'The first phase currently includes 124 garage spaces and 92 storage units. Current availability, price and reservation terms for parking/storage should be confirmed by sales; if you have a target apartment code, they can be discussed together with the offer.'
            );
        }

        if ($this->has_any($text, array('finanszirozas', 'finanszírozás', 'hitel', 'bank', 'loan', 'mortgage', 'financing', '贷款', '按揭', '融资'))) {
            return $this->financing_answer($lang);
        }

        if ($this->has_any($text, array('csok', 'tamogatas', 'subsidy', '补贴'))) {
            return $this->financing_answer($lang);
        }

        if ($this->has_any($text, array('idopont', 'megtekintes', 'ajanlat', 'ajanlatkeres', 'contact', 'appointment', 'visit', 'viewing', 'quote', 'offer', '预约', '看房', '联系', '报价', '询价'))) {
            return $this->by_lang($lang,
                'Szívesen segítünk ajánlatot vagy időpontot kérni. A gyors egyeztetéshez érdemes megadni: név, telefon vagy e-mail, kiválasztott lakáskód, szobaszám, árkeret és kívánt időpont. Elérhetőség: ertekesites@harmat22.hu, +36300733375.',
                '可以预约看房或索取报价。为了销售更快回复，建议留下：姓名、电话或邮箱、目标房号、房间数、预算和方便的时间。联系方式：ertekesites@harmat22.hu，+36300733375。',
                'We can help start an offer request or viewing appointment. For a faster reply, please provide: name, phone or email, target apartment code, room count, budget and preferred time. Contact: ertekesites@harmat22.hu, +36300733375.'
            );
        }

        if ($this->has_any($text, array('kulfol', 'foreign', '中国人', '外国人', '海外'))) {
            return $this->by_lang($lang,
                'Külföldi vásárlók esetén a vásárlás feltételei állampolgárságtól és jogi körülményektől függhetnek. Jogilag pontos választ ügyvéd vagy az értékesítés tud adni.',
                '外国买家购买条件可能取决于国籍和法律情况，准确答案需要律师或销售团队确认。',
                'For foreign buyers, purchase conditions can depend on nationality and legal details. Please confirm with a lawyer or the sales team.'
            );
        }

        return null;
    }

    private function technical_document_url() {
        return home_url('/wp-content/uploads/2026/06/harmat-lakopark-muszaki-leiras.pdf');
    }

    private function is_technical_document_question($normalized) {
        if ($this->has_any($normalized, array('muszaki leiras', 'muszaki tartalom', 'muszaki dokumentum', 'technikai leiras', 'energetikai besorolas', 'energiaosztaly', 'energia osztaly', 'energiabesorolas', 'hoszivattyu', 'futes', 'hutes', 'nyilaszarok', 'burkolat', 'padlofutes', 'mennyezeti futes', 'gaz bevezetve', 'lift', 'felvono', 'szemelyfelvono', 'technical specification', 'technical document', 'building specification', 'energy class', 'heating', 'cooling', 'heat pump', 'elevator', 'technical content', $this->u('\u7535\u68af')))) {
            return true;
        }

        return $this->has_any($normalized, array('muszaki', 'technical')) && $this->has_any($normalized, array('pdf', 'dokumentum', 'document', 'leiras', 'specification'));
    }

    private function technical_document_summary_answer($lang) {
        $url = $this->technical_document_url();
        $hu = $this->u('A Harmat Lak\u00f3park m\u0171szaki tartalm\u00e1nak f\u0151 pontjai: 1105 Budapest, Harmat utca 22.; tervezett energetikai besorol\u00e1s: A; korszer\u0171 VRF leveg\u0151-v\u00edz h\u0151szivatty\u00fas f\u0171t\u00e9s-h\u0171t\u00e9s; a nappalikban, h\u00e1l\u00f3szob\u00e1kban \u00e9s \u00e9tkez\u0151kben mennyezeti fel\u00fcletf\u0171t\u00e9s-fel\u00fcleth\u0171t\u00e9s; a f\u00fcrd\u0151kben, k\u00f6zleked\u0151kben \u00e9s konyh\u00e1kban padl\u00f3f\u0171t\u00e9s; a lak\u00e1sokba nincs g\u00e1z bevezetve; h\u00e1romr\u00e9teg\u0171 h\u0151szigetel\u0151 \u00fcvegez\u00e9s\u0171 Rehau SYNEGO 80 ny\u00edl\u00e1sz\u00e1r\u00f3k; DELTA SPECIAL70 biztons\u00e1gi bej\u00e1rati ajt\u00f3; lak\u00f3szob\u00e1kban lamin\u00e1lt parketta, vizes helyis\u00e9gekben ker\u00e1mia burkolat; k\u00f6zponti haszn\u00e1lati melegv\u00edz \u00e9s egyedi fogyaszt\u00e1sm\u00e9r\u00e9s; kaputelefon, RJ45 el\u0151k\u00e9sz\u00edt\u00e9s; l\u00e9pcs\u0151h\u00e1zank\u00e9nt 1 db g\u00e9ph\u00e1z n\u00e9lk\u00fcli, ellens\u00falyos, 630 kg teherb\u00edr\u00e1s\u00fa szem\u00e9lyfelvon\u00f3; teremgar\u00e1zs- \u00e9s t\u00e1rol\u00f3lehet\u0151s\u00e9g. A teljes m\u0171szaki le\u00edr\u00e1s PDF-ben: ') . $url . $this->u(' A dokumentum t\u00e1j\u00e9koztat\u00f3 jelleg\u0171; a kiv\u00e1lasztott lak\u00e1s v\u00e9gleges m\u0171szaki \u00e9s szerz\u0151d\u00e9ses felt\u00e9teleit az \u00e9rt\u00e9kes\u00edt\u00e9s \u00e9s a szerz\u0151d\u00e9s r\u00f6gz\u00edti.');
        $en = 'Main technical points for Harmat Lakopark: 1105 Budapest, Harmat utca 22.; planned energy class: A; modern VRF air-to-water heat-pump heating and cooling; ceiling surface heating/cooling in living rooms, bedrooms and dining areas; underfloor heating in bathrooms, corridors and kitchens; no gas connection inside the apartments; Rehau SYNEGO 80 windows with triple insulating glazing; DELTA SPECIAL70 security entrance door; laminate flooring in living rooms and ceramic tiles in wet rooms; central domestic hot water with individual metering; intercom and RJ45 preparation; one machine-room-less, counterweighted 630 kg passenger elevator per staircase; and garage/storage options. Full Hungarian technical PDF: ' . $url . ' The document is informative; final technical and contractual terms are confirmed by sales and the contract for the selected apartment.';

        return $this->by_lang($lang, $hu, $hu, $en);
    }

    private function technical_document_answer($lang) {
        return $this->technical_document_summary_answer($lang);
    }

    private function technical_document_actions($lang) {
        return array(
            array(
                'label' => $this->u('M\u0171szaki le\u00edr\u00e1s'),
                'url' => $this->technical_document_url(),
                'primary' => true,
                'event' => 'technical_document_opened',
            ),
        );
    }

    private function is_offer_request($normalized) {
        return $this->has_any($normalized, array('arajanlatot kerek', 'arajanlat', 'ajanlatot kerek', 'ajanlatkeres', 'quote', 'offer request', 'request an offer', '我要报价', '索取报价', '报价', '询价'));
    }

    private function is_appointment_request($normalized) {
        return $this->has_any($normalized, array('idopontot foglalok', 'idopont foglalas', 'idopontot kerek', 'megtekintes', 'bemutatoiroda idopont', 'book a viewing', 'appointment', 'viewing', '预约看房', '预约', '看房'));
    }

    private function is_sales_office_request($normalized) {
        return $this->has_any($normalized, array('bemutatoiroda', 'ertekesitesi iroda', 'hol talalhato', 'utvonal', 'google terkep', 'sales office', 'showroom', 'where is the sales office', '销售办公室', '售楼处', '销售中心', '在哪里', '路线'));
    }

    private function is_available_list_request($normalized) {
        return $this->has_any($normalized, array('milyen lakasok erhetok el', 'elerheto lakasok', 'milyen lakasok', 'what apartments are available', 'available apartments', '有哪些房源', '可选房源', '可售房源', '有什么房源'));
    }

    private function offer_request_answer($lang) {
        return $this->by_lang($lang,
            'Rendben, elindíthatjuk az ajánlatkérést. Kérem, adja meg a nevét, telefonszámát vagy e-mail címét, az érdeklődött lakást vagy lakástípust, árkeretet és a preferált kapcsolatfelvételi időt. Az üzenet CRM-be kerül Harmat asszisztens forrásként.',
            '可以，我帮您发起报价需求。请填写姓名、电话或邮箱、意向房号/房型、预算范围和方便联系的时间。线索会以 Harmat asszisztens 来源进入销售 CRM。',
            'Sure, we can start an offer request. Please enter your name, phone or email, interested unit or apartment type, budget range and preferred contact time. The lead will be saved to the CRM with Harmat assistant as source.'
        );
    }

    private function sales_office_visit_answer($lang) {
        return $this->by_lang($lang,
            'A bemutatóiroda címe: 1105 Budapest, Harmat utca 22. A helyszínen projektmakett és értékesítési konzultáció érhető el. Időpontot lehet kérni, de az időpont nem automatikus visszaigazolás: az értékesítési csapat hamarosan megerősíti.',
            '销售办公室地址：1105 Budapest, Harmat utca 22. 现场可查看项目模型/沙盘，并与销售团队沟通。可以提交预约，但系统不会自动确认时间，销售团队会尽快回确认。',
            'The sales office address is 1105 Budapest, Harmat utca 22. A project model and sales consultation are available. You can request an appointment, but it is not automatically confirmed; the sales team will confirm it soon.'
        );
    }

    private function payment_schedule_answer($lang) {
        return $this->by_lang($lang,
            $this->u('T\u00e1j\u00e9koztat\u00f3 fizet\u00e9si \u00fctemez\u00e9s a jelenlegi v\u00e9teli aj\u00e1nlat minta alapj\u00e1n:\n1. V\u00e9teli biztos\u00edt\u00e9k: 1.000.000 Ft, az aj\u00e1nlat al\u00e1\u00edr\u00e1sakor vagy 3 napon bel\u00fcl.\n2. El\u0151szerz\u0151d\u00e9s al\u00e1\u00edr\u00e1s\u00e1t\u00f3l 3 napon bel\u00fcl: a v\u00e9tel\u00e1r 25%-a.\n3. 2026.12.31-ig: 25%.\n4. 2027.03.31-ig: 25%.\n5. 2027.06.30-ig: 20%.\n6. Kulcsrak\u00e9sz \u00e1llapotn\u00e1l: 5%.\n\nProjektid\u0151pontok: szerz\u0151d\u00e9sk\u00f6t\u00e9s 2026. j\u00fanius, szerkezetk\u00e9sz / tet\u0151szint 2027. m\u00e1jus, bels\u0151 munk\u00e1k 2027. szeptember, m\u0171szaki \u00e1tad\u00e1s / ellen\u0151rz\u00e9s 2028. m\u00e1rcius, v\u00e1rhat\u00f3 \u00e1tad\u00e1s 2028. j\u00fanius. A v\u00e9gleges ar\u00e1nyokat \u00e9s hat\u00e1rid\u0151ket mindig az adott lak\u00e1s aj\u00e1nlata \u00e9s a szerz\u0151d\u00e9s r\u00f6gz\u00edti.'),
            $this->u('\u5f53\u524d\u53ef\u6309\u5f8b\u5e08\u6a21\u677f\u5411\u5ba2\u6237\u8bf4\u660e\u4ed8\u6b3e\u8282\u70b9\uff1a\n1. \u8d2d\u623f\u610f\u5411\u4fdd\u8bc1\u91d1\uff1a1,000,000 Ft\uff0c\u7b7e\u7f72\u610f\u5411\u4e66\u65f6\u6216 3 \u5929\u5185\u652f\u4ed8\u3002\n2. \u7b7e\u8ba2\u9884\u552e\u5408\u540c\u540e 3 \u5929\u5185\uff1a\u623f\u4ef7 25%\u3002\n3. 2026-12-31 \u524d\uff1a25%\u3002\n4. 2027-03-31 \u524d\uff1a25%\u3002\n5. 2027-06-30 \u524d\uff1a20%\u3002\n6. \u8fbe\u5230\u7cbe\u88c5 / \u4ea4\u94a5\u5319\u72b6\u6001\u65f6\uff1a5%\u3002\n\n\u5de5\u7a0b\u65f6\u95f4\u53c2\u8003\uff1a\u7b7e\u8ba2\u5408\u540c 2026 \u5e74 6 \u6708\uff0c\u5c01\u9876 2027 \u5e74 5 \u6708\uff0c\u5ba4\u5185\u5de5\u7a0b 2027 \u5e74 9 \u6708\uff0c\u6280\u672f\u9a8c\u623f 2028 \u5e74 3 \u6708\uff0c\u9884\u8ba1\u4ea4\u4ed8 2028 \u5e74 6 \u6708\u3002\u6700\u7ec8\u6bd4\u4f8b\u548c\u65e5\u671f\u4ee5\u5177\u4f53\u623f\u6e90\u62a5\u4ef7\u53ca\u6b63\u5f0f\u5408\u540c\u4e3a\u51c6\u3002'),
            $this->u('Indicative payment schedule based on the current purchase-offer template:\n1. Purchase-offer security deposit: 1,000,000 Ft, payable at signing or within 3 days.\n2. Within 3 days after signing the preliminary contract: 25% of the purchase price.\n3. By 2026-12-31: 25%.\n4. By 2027-03-31: 25%.\n5. By 2027-06-30: 20%.\n6. At turnkey condition: 5%.\n\nProject dates for orientation: contract signing June 2026, topping-out / structural stage May 2027, interior works September 2027, technical inspection March 2028, expected handover June 2028. Final percentages and deadlines are confirmed in the selected apartment offer and contract.')
        );
    }

    private function tax_vat_answer($lang) {
        return $this->by_lang($lang,
            $this->u('\u00c1FA \u00e9s illet\u00e9k t\u00e1j\u00e9koztat\u00f3: a v\u00e9teli aj\u00e1nlat minta szerint a lak\u00e1s \u00e9s a hozz\u00e1 tartoz\u00f3 terasz / erk\u00e9ly \u00c1FA-kulcsa 5%, a t\u00e1rol\u00f3 27%, a g\u00e9pkocsibe\u00e1ll\u00f3 27%. A vagyonszerz\u00e9si illet\u00e9k \u00e1ltal\u00e1nosan az ingatlan(ok) brutt\u00f3 v\u00e9tel\u00e1r\u00e1nak 4%-a lehet; ez nem kedvezm\u00e9ny \u00e9s nem azonos az \u00c1FA-val. A v\u00e9gleges ad\u00f3z\u00e1si, illet\u00e9k- \u00e9s jogi v\u00e1laszt az \u00fcgyv\u00e9d vagy hivatalos tan\u00e1csad\u00f3 er\u0151s\u00edti meg.'),
            $this->u('\u7a0e\u7387\u8bf4\u660e\uff1a\u6309\u5f8b\u5e08\u6a21\u677f\uff0c\u4f4f\u5b85\u53ca\u5bf9\u5e94\u9732\u53f0 / \u9633\u53f0\u4e3a 5% VAT\uff1b\u50a8\u85cf\u5ba4\u4e3a 27% VAT\uff1b\u8f66\u4f4d\u4e3a 27% VAT\u3002\u6587\u4ef6\u4e2d\u7684 4% \u662f\u8d2d\u7f6e\u7a0e / \u8d22\u4ea7\u53d6\u5f97\u7a0e\u7684\u5e38\u89c1\u53e3\u5f84\uff0c\u4e0d\u662f\u6298\u6263\uff0c\u4e5f\u4e0d\u662f VAT\u3002\u6700\u7ec8\u7a0e\u52a1\u3001\u8d2d\u7f6e\u7a0e\u548c\u6cd5\u5f8b\u89e3\u91ca\u5fc5\u987b\u7531\u5f8b\u5e08\u6216\u6b63\u5f0f\u987e\u95ee\u786e\u8ba4\u3002'),
            $this->u('Tax and VAT guidance: based on the purchase-offer template, the apartment and its related terrace/balcony use 5% VAT; storage uses 27% VAT; parking space uses 27% VAT. The 4% item in the document is the usual property acquisition duty basis, not a discount and not VAT. Final tax, duty and legal interpretation must be confirmed by the lawyer or an official adviser.')
        );
    }

    private function is_payment_schedule_question($text) {
        return $this->has_any($text, array(
            'fizetes', 'fizetesi', $this->u('fizet\u00e9s'), $this->u('fizet\u00e9si'), $this->u('\u00fctemez\u00e9s'), 'utemezes', 'reszlet', $this->u('r\u00e9szlet'), 'payment', 'installment', 'schedule', 'milestone',
            $this->u('\u4ed8\u6b3e'), $this->u('\u4ed8\u6b3e\u8282\u70b9'), $this->u('\u4ed8\u6b3e\u65f6\u95f4'), $this->u('\u600e\u4e48\u4ed8\u6b3e'), $this->u('\u5206\u671f'), $this->u('\u9996\u4ed8'), $this->u('\u7b7e\u8ba2\u5408\u540c'), $this->u('\u8d44\u91d1\u8282\u70b9')
        ));
    }

    private function is_tax_vat_question($text) {
        return $this->has_any($text, array(
            'afa', $this->u('\u00e1fa'), 'vat', 'ado', $this->u('ad\u00f3'), 'illetek', $this->u('illet\u00e9k'), 'tax', 'duty', '5%', '27%', '4%',
            $this->u('\u7a0e\u7387'), $this->u('\u7a0e\u8d39'), $this->u('\u7a0e'), $this->u('\u589e\u503c\u7a0e'), $this->u('\u8d2d\u7f6e\u7a0e'), $this->u('\u8d22\u4ea7\u53d6\u5f97\u7a0e'), $this->u('\u5370\u82b1\u7a0e'), $this->u('\u8f66\u4f4d\u7a0e'), $this->u('\u50a8\u85cf\u5ba4\u7a0e')
        ));
    }
    private function financing_answer($lang) {
        return $this->by_lang($lang,
            'Finanszírozás, banki hitel vagy CSOK lehetőség iránti érdeklődés esetén az értékesítési csapat segít elindítani az egyeztetést. A finanszírozási információk tájékoztató jellegűek, nem minősülnek pénzügyi tanácsadásnak.',
            '如果您关注贷款、银行融资或 CSOK，可以由销售团队协助启动进一步确认。融资信息仅供参考，不构成财务建议。',
            'If you are interested in financing, bank loan or CSOK options, the sales team can help start the discussion. Financing information is informative only and does not constitute financial advice.'
        );
    }

    private function opening_construction_answer($lang) {
        return $this->by_lang($lang,
            'Fontos projektidőpontok: 2026. június 12. alapkőletétel és hivatalos értékesítési nyitás, bemutatóiroda megnyitása és projektmakett bemutatása. Az I. ütem várható átadása jelenlegi információ szerint 2028 Q2.',
            '重要项目节点：2026年6月12日奠基仪式和正式开盘，同时开放销售办公室并展示项目模型/沙盘。第一期预计交付时间为 2028 年第二季度。',
            'Key project milestones: June 12, 2026 foundation-stone ceremony and official sales opening, sales office opening and project model presentation. Expected phase I handover is currently 2028 Q2.'
        );
    }

    private function handoff_payload($type, $lang, $filters = array(), $profile = array()) {
        $is_appointment = $type === 'appointment';
        $title = $is_appointment
            ? $this->by_lang($lang, 'Időpontkérés', '预约看房', 'Viewing request')
            : $this->by_lang($lang, 'Kapcsolat az értékesítéssel', '联系销售', 'Contact sales');
        $text = $is_appointment
            ? $this->by_lang($lang, 'Köszönjük! Az értékesítési csapat hamarosan visszaigazolja az időpontot.', '谢谢！销售团队会尽快确认预约时间。', 'Thank you. The sales team will confirm the appointment soon.')
            : $this->by_lang($lang, 'Ezt az értékesítési csapat tudja pontosan megerősíteni. Kérhet visszahívást vagy ajánlatot.', '这个问题需要销售团队准确确认。您可以申请回电或报价。', 'The sales team can confirm this precisely. You can request a callback or offer.');

        return array(
            'intent' => $is_appointment ? 'appointment' : ($type === 'offer' ? 'offer' : 'handoff'),
            'lang' => $lang,
            'title' => $title,
            'text' => $text,
            'name_placeholder' => $this->by_lang($lang, 'Név', '姓名', 'Name'),
            'phone_placeholder' => $this->by_lang($lang, 'Telefon', '电话', 'Phone'),
            'email_placeholder' => 'E-mail',
            'unit_placeholder' => $this->by_lang($lang, 'Lakás vagy típus', '意向房号或房型', 'Unit or type'),
            'rooms_placeholder' => $this->by_lang($lang, 'Szobaszám', '房间数', 'Room count'),
            'budget_placeholder' => $this->by_lang($lang, 'Árkeret', '预算范围', 'Budget range'),
            'time_placeholder' => $this->by_lang($lang, 'Preferált kapcsolatfelvételi idő', '方便联系时间', 'Preferred contact time'),
            'message_placeholder' => $this->by_lang($lang, 'Üzenet, kérdés vagy megjegyzés', '留言或补充需求', 'Message or note'),
            'privacy' => $this->by_lang($lang, 'Elfogadom az adatkezelési tájékoztatót.', '我同意隐私政策和数据处理说明。', 'I accept the privacy notice.'),
            'button' => $is_appointment
                ? $this->by_lang($lang, 'Időpontkérés küldése', '提交预约', 'Send viewing request')
                : $this->by_lang($lang, 'Küldés az értékesítésnek', '发送给销售', 'Send to sales'),
        );
    }

    private function sales_office_actions($lang) {
        return array(
            array(
                'label' => $this->by_lang($lang, 'Időpontot foglalok', '预约看房', 'Book a viewing'),
                'message' => $this->by_lang($lang, 'Időpontot foglalok', '预约看房', 'Book a viewing'),
                'primary' => true,
                'event' => 'appointment_started',
            ),
            array(
                'label' => $this->by_lang($lang, 'Útvonal Google Térképen', 'Google 地图路线', 'Google Maps route'),
                'url' => 'https://www.google.com/maps/search/?api=1&query=1105%20Budapest%2C%20Harmat%20utca%2022',
                'external' => true,
            ),
            array(
                'label' => $this->by_lang($lang, 'Telefonos kapcsolat', '电话联系', 'Call sales'),
                'url' => 'tel:+36300733375',
                'event' => 'human_handoff',
            ),
        );
    }

    private function sales_contact_actions($lang) {
        return array(
            array(
                'label' => $this->by_lang($lang, 'Visszahívást kérek', '申请回电', 'Request a callback'),
                'message' => $this->by_lang($lang, 'Kapcsolat az értékesítéssel', '联系销售', 'Contact sales'),
                'primary' => true,
                'event' => 'human_handoff',
            ),
            array(
                'label' => '+36300733375',
                'url' => 'tel:+36300733375',
                'event' => 'human_handoff',
            ),
            array(
                'label' => 'ertekesites@harmat22.hu',
                'url' => 'mailto:ertekesites@harmat22.hu',
                'event' => 'human_handoff',
            ),
        );
    }

    private function recommendation_actions($lang) {
        return array(
            array(
                'label' => $this->by_lang($lang, 'Árajánlatot kérek', '我要报价', 'Request an offer'),
                'message' => $this->by_lang($lang, 'Árajánlatot kérek', '我要报价', 'Request an offer'),
                'primary' => true,
                'event' => 'offer_request_started',
            ),
            array(
                'label' => $this->by_lang($lang, 'Időpontot foglalok', '预约看房', 'Book a viewing'),
                'message' => $this->by_lang($lang, 'Időpontot foglalok', '预约看房', 'Book a viewing'),
                'event' => 'appointment_started',
            ),
        );
    }

    private function apartment_actions($apartment, $lang) {
        $url = (string) ($apartment['property_url'] ?? home_url('/lakaskereso/'));
        return array(
            array(
                'label' => $this->by_lang($lang, 'Árajánlatot kérek', '我要报价', 'Request an offer'),
                'message' => $this->by_lang($lang, 'Árajánlatot kérek ' . ($apartment['apartment'] ?? ''), '我要报价 ' . ($apartment['apartment'] ?? ''), 'Request an offer for ' . ($apartment['apartment'] ?? '')),
                'primary' => true,
                'event' => 'offer_request_started',
            ),
            array(
                'label' => $this->by_lang($lang, 'Adatlap megnyitása', '打开房源页', 'Open detail page'),
                'url' => $url,
            ),
        );
    }

    private function extract_filters($message, $normalized) {
        $filters = array(
            'rooms' => null,
            'budget' => null,
            'area' => null,
            'area_min' => null,
            'area_max' => null,
            'building' => null,
            'floor' => null,
            'garden' => false,
            'terrace' => false,
            'cheap' => false,
            'ground_floor' => false,
            'profile' => array(),
            'has_search' => false,
        );

        if ($this->has_any($normalized, array('ket szob', '2 szob', '2-room', 'two room', 'two-room')) || preg_match('/(?:两|二|2)\s*(?:房|室|居|个房间)/u', $message)) {
            $filters['rooms'] = 2;
        } elseif ($this->has_any($normalized, array('egy szob', '1 szob', '1-room', 'one room', 'one-room')) || preg_match('/(?:一|1)\s*(?:房|室|居|个房间)/u', $message)) {
            $filters['rooms'] = 1;
        } elseif ($this->has_any($normalized, array('harom szob', '3 szob', '3-room', 'three room', 'three-room')) || preg_match('/(?:三|3)\s*(?:房|室|居|个房间)/u', $message)) {
            $filters['rooms'] = 3;
        } elseif ($this->has_any($normalized, array('negy szob', '4 szob', '4-room', 'four room', 'four-room')) || preg_match('/(?:四|4)\s*(?:房|室|居|个房间)/u', $message)) {
            $filters['rooms'] = 4;
        } elseif ($this->has_any($normalized, array('ot szob', '5 szob', '5-room', 'five room', 'five-room')) || preg_match('/(?:五|5)\s*(?:房|室|居|个房间)/u', $message)) {
            $filters['rooms'] = 5;
        } elseif (preg_match('/([1-5])\s*-?\s*(szob|room|rooms|房|居)/iu', $message, $match)) {
            $filters['rooms'] = (int) $match[1];
        } elseif ($this->has_any($normalized, array('egy szob', '1 szob'))) {
            $filters['rooms'] = 1;
        } elseif ($this->has_any($normalized, array('ket szob', '2 szob'))) {
            $filters['rooms'] = 2;
        } elseif ($this->has_any($normalized, array('harom szob', '3 szob'))) {
            $filters['rooms'] = 3;
        } elseif ($this->has_any($normalized, array('negy szob', '4 szob'))) {
            $filters['rooms'] = 4;
        } elseif ($this->has_any($normalized, array('ot szob', '5 szob'))) {
            $filters['rooms'] = 5;
        }

        if (preg_match('/(\d{2,3}(?:[,.]\d+)?)\s*(?:m2|m²|nm|négyzet|平方米|平米|平)\s*(?:felett|folott|fölött|nagyobb|legalabb|legalább|min(?:imum)?|from|above|over|以上|起|至少|不低于)/iu', $message, $match) ||
            preg_match('/(?:felett|folott|fölött|nagyobb|legalabb|legalább|min(?:imum)?|from|above|over|以上|起|至少|不低于)\s*(\d{2,3}(?:[,.]\d+)?)\s*(?:m2|m²|nm|négyzet|平方米|平米|平)/iu', $message, $match)) {
            $filters['area_min'] = (float) str_replace(',', '.', $match[1]);
        } elseif (preg_match('/(\d{2,3}(?:[,.]\d+)?)\s*(?:m2|m²|nm|négyzet|平方米|平米|平)\s*(?:alatt|kisebb|legfeljebb|max(?:imum)?|under|below|less than|以下|以内|不超过)/iu', $message, $match) ||
            preg_match('/(?:alatt|kisebb|legfeljebb|max(?:imum)?|under|below|less than|以下|以内|不超过)\s*(\d{2,3}(?:[,.]\d+)?)\s*(?:m2|m²|nm|négyzet|平方米|平米|平)/iu', $message, $match)) {
            $filters['area_max'] = (float) str_replace(',', '.', $match[1]);
        } elseif (preg_match('/(\d{2,3}(?:[,.]\d+)?)\s*(?:m2|m²|nm|négyzet|平方米|平米|平)/iu', $message, $match)) {
            $filters['area'] = (float) str_replace(',', '.', $match[1]);
        }

        if (preg_match('/(\d{2,3}(?:[,.]\d+)?)\s*(?:millio|millió|million|m\b)/iu', $normalized, $match)) {
            $filters['budget'] = (int) round(((float) str_replace(',', '.', $match[1])) * 1000000);
        } elseif (preg_match('/(\d{3,5})\s*万/u', $message, $match)) {
            $filters['budget'] = ((int) $match[1]) * 10000;
        } elseif (preg_match('/(\d[\d\s]{6,})\s*(?:ft|huf)?/iu', $message, $match)) {
            $filters['budget'] = (int) preg_replace('/\D/', '', $match[1]);
        }

        if (preg_match('/\b(A[1-4])\b/iu', $message, $match)) {
            $filters['building'] = strtoupper($match[1]);
        }

        if ($this->has_any($normalized, array('foldszint', 'fsz', 'ground floor', 'ground-floor', '底楼', '底层'))) {
            $filters['floor'] = 'Fsz';
        } elseif (preg_match('/(?:^|\D)([1-9])\s*(?:emelet|floor|楼层|楼)/iu', $message, $match)) {
            $filters['floor'] = (string) (int) $match[1];
        }

        $filters['cheap'] = $this->has_any($normalized, array('olcso', 'legolcsobb', 'cheap', 'cheapest', '便宜', '最低', '低价'));
        $filters['garden'] = $this->has_any($normalized, array('kert', 'kertes', 'garden', 'gift garden', 'included garden', '底楼花园', '底层花园', '花园', '赠送花园', '送花园'));
        $filters['ground_floor'] = $this->has_any($normalized, array('foldszint', 'fsz', 'ground floor', 'ground-floor', '底楼', '底层')) || $filters['garden'];
        $filters['terrace'] = $this->has_any($normalized, array('terasz', 'erkely', 'erkély', 'nagy terasz', 'large terrace', 'balcony', '露台', '大露台', '阳台'));

        $filters['has_search'] = $filters['rooms'] || $filters['budget'] || $filters['area'] || $filters['area_min'] || $filters['area_max'] || $filters['building'] || $filters['floor'] || $filters['cheap'] || $filters['ground_floor'] || $filters['garden'] || $filters['terrace'] ||
            $this->has_any($normalized, array('ajanl', 'keres', 'lakast', 'lakas', 'recommend', 'available', 'looking for', 'budget', 'buy', '预算', '推荐', '买房', '房源', 'lakások érhetők el', 'lakasok erhetok el'));

        return $filters;
    }

    private function extract_buyer_profile($message, $normalized) {
        return array(
            'investment' => $this->has_any($normalized, array('befektetes', 'kiadas', 'befekteto', 'investment', 'investor', 'rental', 'rent out', 'yield', '投资', '出租', '租金', '回报', '收益')),
            'family' => $this->has_any($normalized, array('csalad', 'gyerek', 'gyerekes', 'family', 'children', 'kids', 'child', '家庭', '孩子', '小孩', '老人', '宠物家庭')),
            'own_use' => $this->has_any($normalized, array('sajat lakhat', 'sajat cel', 'own use', 'live in', 'for myself', '自住', '自己住')),
            'pet' => $this->has_any($normalized, array('kutyafuttato', 'kutya', 'kisallat', 'allatbarat', 'dog', 'pet', 'pets', '宠物', '狗', '遛狗')),
            'garden' => $this->has_any($normalized, array('kert', 'garden', 'ground floor', 'ground-floor', '底楼', '底层', '花园', '赠送花园')),
            'price_sensitive' => $this->has_any($normalized, array('olcso', 'legolcsobb', 'tul draga', 'olcsobbat', 'cheap', 'cheapest', 'expensive', 'cheaper', 'budget', '低价', '便宜', '太贵', '预算', '低总价')),
        );
    }

    private function profile_requests_recommendation($profile, $normalized) {
        if (empty(array_filter($profile))) {
            return false;
        }

        return $this->has_any($normalized, array(
            'ajanl',
            'melyik',
            'melyiket',
            'keres',
            'lakast',
            'lakas',
            'opcio',
            'befektetes',
            'csalad',
            'sajat',
            'recommend',
            'which',
            'option',
            'good for',
            'suitable',
            'investment',
            'family',
            'own use',
            'garden',
            'ground floor',
            'ground-floor',
            'kert',
            'foldszint',
            '推荐',
            '哪套',
            '哪些',
            '适合',
            '投资',
            '自住',
            '家庭',
            '宠物',
            '花园',
        ));
    }

    private function search_apartments($apartments, $filters) {
        $matches = array();
        foreach ($apartments as $apartment) {
            if (!$this->is_available_apartment($apartment)) {
                continue;
            }
            if ($filters['rooms'] && (int) ($apartment['rooms'] ?? 0) !== (int) $filters['rooms']) {
                continue;
            }
            if ($filters['budget'] && (int) ($apartment['price_huf'] ?? 0) > (int) $filters['budget']) {
                continue;
            }
            if (!empty($filters['area_min']) && (float) ($apartment['sales_area_m2'] ?? 0) < (float) $filters['area_min']) {
                continue;
            }
            if (!empty($filters['area_max']) && (float) ($apartment['sales_area_m2'] ?? 0) > (float) $filters['area_max']) {
                continue;
            }
            if (!empty($filters['ground_floor']) && (string) ($apartment['floor'] ?? '') !== 'Fsz') {
                continue;
            }
            if (!empty($filters['building']) && strtoupper((string) ($apartment['building'] ?? '')) !== strtoupper((string) $filters['building'])) {
                continue;
            }
            if (!empty($filters['floor']) && (string) ($apartment['floor'] ?? '') !== (string) $filters['floor']) {
                continue;
            }
            if (!empty($filters['terrace']) && (string) ($apartment['floor'] ?? '') === 'Fsz' && empty($filters['garden'])) {
                continue;
            }
            if ((!empty($filters['terrace']) || !empty($filters['garden'])) && !$this->apartment_has_outdoor_data($apartment)) {
                continue;
            }
            $matches[] = $apartment;
        }

        if (!$matches) {
            return array();
        }

        usort($matches, function ($a, $b) use ($filters) {
            $sa = $this->buyer_match_score($a, $filters['profile'] ?? array());
            $sb = $this->buyer_match_score($b, $filters['profile'] ?? array());
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            if (!empty($filters['terrace']) || !empty($filters['garden']) || !empty($filters['ground_floor'])) {
                $oa = $this->outdoor_area_value($a);
                $ob = $this->outdoor_area_value($b);
                if ($oa !== $ob) {
                    return $ob <=> $oa;
                }
            }

            if ($filters['area']) {
                $da = abs((float) $a['sales_area_m2'] - (float) $filters['area']);
                $db = abs((float) $b['sales_area_m2'] - (float) $filters['area']);
                if ($da !== $db) {
                    return $da <=> $db;
                }
            }
            if (!empty($filters['area_min'])) {
                $da = abs((float) $a['sales_area_m2'] - (float) $filters['area_min']);
                $db = abs((float) $b['sales_area_m2'] - (float) $filters['area_min']);
                if ($da !== $db) {
                    return $da <=> $db;
                }
            }

            if ($filters['budget']) {
                $da = abs((int) $filters['budget'] - (int) ($a['price_huf'] ?? 0));
                $db = abs((int) $filters['budget'] - (int) ($b['price_huf'] ?? 0));
                if ($da !== $db) {
                    return $da <=> $db;
                }
            }

            return (int) $a['price_huf'] <=> (int) $b['price_huf'];
        });

        return array_slice($matches, 0, 8);
    }

    private function default_available_apartments($apartments, $filters, $profile) {
        $filters['profile'] = $profile;
        $matches = $this->search_apartments($apartments, $filters);
        if (!$matches) {
            $filters['rooms'] = null;
            $filters['budget'] = null;
            $filters['area'] = null;
            $filters['area_min'] = null;
            $filters['area_max'] = null;
            $matches = $this->search_apartments($apartments, $filters);
        }
        return array_slice($matches, 0, 5);
    }

    private function is_available_apartment($apartment) {
        $status = $this->normalize((string) ($apartment['status'] ?? ''));
        if ($status === '') {
            return true;
        }
        return !($this->has_any($status, array('elad', 'sold', 'foglalt', 'reserved')));
    }

    private function apartment_has_outdoor_data($apartment) {
        foreach (array('outdoor_area_m2', 'terrace_m2', 'terrace', 'outdoor_m2', 'garden_m2', 'balcony_m2') as $key) {
            if (isset($apartment[$key]) && (float) $apartment[$key] > 0) {
                return true;
            }
        }
        return false;
    }

    private function outdoor_area_value($apartment) {
        foreach (array('outdoor_area_m2', 'garden_m2', 'terrace_m2', 'balcony_m2', 'terrace', 'outdoor_m2') as $key) {
            if (!isset($apartment[$key])) {
                continue;
            }
            $value = (float) str_replace(',', '.', (string) $apartment[$key]);
            if ($value > 0) {
                return $value;
            }
        }
        return 0.0;
    }

    private function outdoor_label($apartment, $lang) {
        $type = (string) ($apartment['outdoor_type'] ?? '');
        $floor = (string) ($apartment['floor'] ?? '');
        if ($type === 'garden' || $floor === 'Fsz') {
            return $this->by_lang($lang, 'Kert / terasz', '花园 / 露台', 'Garden / terrace');
        }
        return $this->by_lang($lang, 'Terasz / erkély', '露台 / 阳台', 'Terrace / balcony');
    }

    private function outdoor_summary($apartment, $lang) {
        $area = $this->outdoor_area_value($apartment);
        if ($area <= 0) {
            return '';
        }
        return $this->outdoor_label($apartment, $lang) . ' ' . $this->format_area($area) . ' m²';
    }

    private function near_match_apartments($apartments, $filters) {
        $relaxed = $filters;
        $relaxed['budget'] = null;

        $matches = $this->search_apartments($apartments, $relaxed);
        if (!$matches && (!empty($relaxed['area_min']) || !empty($relaxed['area_max']))) {
            $relaxed['area_min'] = null;
            $relaxed['area_max'] = null;
            $matches = $this->search_apartments($apartments, $relaxed);
        }
        if (!$matches && !empty($relaxed['ground_floor'])) {
            $relaxed['ground_floor'] = false;
            $relaxed['garden'] = false;
            $matches = $this->search_apartments($apartments, $relaxed);
        }
        if (!$matches && !empty($relaxed['terrace'])) {
            $relaxed['terrace'] = false;
            $matches = $this->search_apartments($apartments, $relaxed);
        }

        return array_slice($matches, 0, 4);
    }

    private function near_match_answer($matches, $filters, $lang) {
        $top = array_slice($matches, 0, 5);
        $lines = array();
        foreach ($top as $item) {
            $lines[] = $this->recommendation_line($item, $lang, $filters['profile'] ?? array());
        }

        $intro = $this->by_lang($lang,
            'A megadott feltételekre pontos találatot most nem találtam, de ezek állnak a legközelebb a kereséshez:',
            '按你给的条件暂时没有完全匹配的房源，但下面这几套最接近你的需求：',
            'I could not find an exact match for the conditions, but these units are the closest options:'
        );

        $hint = $this->near_match_hint($filters, $lang);
        $next = $this->by_lang($lang,
            'Ha megad egy kicsit tágabb árkeretet, emeletet vagy minimum alapterületet, tovább tudom szűkíteni a listát.',
            '如果你可以补充预算上限、楼层偏好或最低面积，我可以继续帮你缩小范围。',
            'If you share a wider budget range, floor preference or minimum size, I can narrow the list further.'
        );

        return $intro . ($hint ? "\n" . $hint : '') . "\n\n" . implode("\n", $lines) . "\n\n" . $next;
    }

    private function near_match_hint($filters, $lang) {
        $hints = array();
        if (!empty($filters['budget'])) {
            $hints[] = $this->by_lang($lang,
                'A költségkeret valószínűleg túl szűk az adott szobaszámhoz vagy mérethez.',
                '你的预算可能对这个房间数或面积要求来说偏紧。',
                'The budget may be tight for the requested room count or size.'
            );
        }
        if (!empty($filters['ground_floor'])) {
            $hints[] = $this->by_lang($lang,
                'A földszinti vagy kertes lakásokból kevesebb van, ezért érdemes gyorsan egyeztetni.',
                '底楼或带花园的房源数量比较少，适合尽快确认。',
                'Ground-floor or garden units are more limited, so it is worth checking quickly.'
            );
        }
        if (!empty($filters['area_min']) || !empty($filters['area_max'])) {
            $hints[] = $this->by_lang($lang,
                'Az alapterületre megadott határ is szűkíti a találatokat.',
                '面积上下限也会明显缩小可选范围。',
                'The area limit also narrows the available matches.'
            );
        }
        if (!empty($filters['rooms'])) {
            $hints[] = $this->by_lang($lang,
                sprintf('A keresésben megtartottam a %d szobás igényt.', (int) $filters['rooms']),
                sprintf('我保留了你要 %d 房的条件。', (int) $filters['rooms']),
                sprintf('I kept the %d-room requirement in the search.', (int) $filters['rooms'])
            );
        }

        return implode("\n", array_slice($hints, 0, 2));
    }

    private function buyer_match_score($item, $profile) {
        $score = 0;
        $rooms = (int) ($item['rooms'] ?? 0);
        $area = (float) ($item['sales_area_m2'] ?? 0);
        $price = (int) ($item['price_huf'] ?? 0);
        $sqm = (int) ($item['sqm_price_huf'] ?? 0);
        $floor = (string) ($item['floor'] ?? '');
        $outdoor = $this->outdoor_area_value($item);

        if (!empty($profile['investment'])) {
            $score += ($rooms <= 2) ? 6 : 0;
            $score += ($area > 0 && $area <= 55) ? 3 : 0;
            $score += ($price > 0 && $price <= 70000000) ? 3 : 0;
            $score += ($sqm > 0 && $sqm <= 1400000) ? 2 : 0;
        }
        if (!empty($profile['family'])) {
            $score += ($rooms >= 3) ? 6 : 0;
            $score += ($area >= 70) ? 3 : 0;
            $score += ($rooms >= 4) ? 2 : 0;
        }
        if (!empty($profile['own_use'])) {
            $score += ($rooms >= 2 && $rooms <= 4) ? 3 : 0;
            $score += ($area >= 55) ? 2 : 0;
        }
        if (!empty($profile['pet'])) {
            $score += ($floor === 'Fsz') ? 2 : 0;
        }
        if (!empty($profile['garden'])) {
            $score += ($floor === 'Fsz') ? 7 : 0;
            $score += min(5, (int) floor($outdoor / 35));
        }
        if (!empty($profile['price_sensitive'])) {
            $score += ($price > 0 && $price <= 65000000) ? 5 : 0;
            $score += ($sqm > 0 && $sqm <= 1400000) ? 2 : 0;
            $score += ($rooms <= 2) ? 1 : 0;
        }

        return $score;
    }

    private function selection_guidance_answer($lang) {
        return $this->by_lang($lang,
            "Szívesen segítek szűkíteni a választékot. A legjobb ajánláshoz elég 3-4 adat:
1. saját lakhatás vagy befektetés
2. hány szoba
3. körülbelüli árkeret
4. fontos-e földszint/kert, emelet, parkoló vagy tároló

Példa: \"2 szobás lakás 70 millió Ft körül\" vagy \"3 szobás saját lakhatásra, parkolóval\".",
            "可以，我先像销售顾问一样帮你缩小范围。为了推荐得准，请告诉我 3-4 个条件：
1. 自住还是投资
2. 几房
3. 大概预算
4. 是否需要底楼花园、指定楼层、车位或储藏室

例如可以问：\"7000万福林左右两房推荐\" 或 \"自住三房，最好带车位\"。",
            "I can help narrow the options like a sales advisor. For a useful recommendation, please share 3-4 points:
1. own use or investment
2. room count
3. approximate budget
4. whether ground floor/garden, floor, parking or storage matters

Example: \"2-room around 70 million Ft\" or \"3-room for own use with parking\"."
        );
    }

    private function recommendation_answer($matches, $filters, $lang) {
        $top = array_slice($matches, 0, 5);
        $lines = array();
        foreach ($top as $item) {
            $lines[] = $this->recommendation_line($item, $lang, $filters['profile'] ?? array());
        }

        $prefix = $this->by_lang($lang,
            'A jelenlegi árlista alapján ezeket ajánlom:',
            '根据当前价格表，我先推荐这些：',
            'Based on the current price list, I would suggest these options:'
        );
        $profile_note = $this->profile_note($filters['profile'] ?? array(), $lang);

        $suffix = $this->by_lang($lang,
            'Az árak tájékoztató jellegűek; végleges elérhetőséget és árat az értékesítés erősít meg.',
            '以上价格为当前资料参考价，最终可售状态和价格需要销售团队确认。',
            'Prices are indicative; final availability and price should be confirmed by sales.'
        );
        if (!empty($filters['terrace'])) {
            $suffix .= "\n" . $this->by_lang($lang,
                'A terasz vagy erkély pontos méretét a kiválasztott lakás adatlapja és az értékesítési csapat erősíti meg.',
                '露台或阳台的准确面积，请以具体房源页和销售团队确认为准。',
                'Exact terrace or balcony size should be confirmed on the selected apartment page and by the sales team.'
            );
        }

        return $prefix . ($profile_note ? "\n" . $profile_note : '') . "\n\n" . implode("\n", $lines) . "\n\n" . $suffix;
    }

    private function recommendation_line($item, $lang, $profile = array()) {
        $sqm = $this->format_money($item['sqm_price_huf'] ?? 0) . ' / m²';
        $status = (string) ($item['status'] ?? '');
        $outdoor = $this->outdoor_summary($item, $lang);
        $tags = $this->apartment_tags($item, $profile, $lang);
        $tag_text = $tags ? $this->tag_prefix($lang) . implode($this->tag_separator($lang), $tags) . $this->sentence_end($lang) : '';

        if ($lang === 'zh') {
            return sprintf(
                '%s：%s，%s，%s，销售面积 %s m²%s，参考总价 %s，参考单价 %s，状态：%s。%s',
                $item['apartment'],
                $item['building'],
                $this->floor_label($item['floor'], 'zh'),
                $this->room_label($item, 'zh'),
                $this->format_area($item['sales_area_m2']),
                $outdoor ? '，' . $outdoor : '',
                $this->format_money($item['price_huf']),
                $sqm,
                $status,
                $tag_text
            );
        }

        if ($lang === 'en') {
            return sprintf(
                '%s: building %s, %s, %s, %s m² sales area%s, indicative total price %s, indicative sqm price %s, status: %s. %s',
                $item['apartment'],
                $item['building'],
                $this->floor_label($item['floor'], 'en'),
                $this->room_label($item, 'en'),
                $this->format_area($item['sales_area_m2']),
                $outdoor ? ', ' . $outdoor : '',
                $this->format_money($item['price_huf']),
                $sqm,
                $status,
                $tag_text
            );
        }

        return sprintf(
            '%s: %s épület, %s, %s, %s m² eladási terület%s, tájékoztató teljes ár %s, négyzetméterár %s, státusz: %s. %s',
            $item['apartment'],
            $item['building'],
            $this->floor_label($item['floor'], 'hu'),
            $this->room_label($item, 'hu'),
            $this->format_area($item['sales_area_m2']),
            $outdoor ? ', ' . $outdoor : '',
            $this->format_money($item['price_huf']),
            $sqm,
            $status,
            $tag_text
        );
    }

    private function profile_note($profile, $lang) {
        if (empty(array_filter($profile))) {
            return '';
        }
        if (!empty($profile['investment'])) {
            return $this->by_lang($lang,
                'Befektetési szándéknál előrébb sorolom a kompaktabb, alacsonyabb teljes árú és kedvezőbb négyzetméterárú lakásokat.',
                '你提到投资，我会优先看总价、面积效率、1-2 房和相对友好的单价。',
                'For investment intent, I prioritize compact units with lower total price and more favorable sqm price.'
            );
        }
        if (!empty($profile['pet'])) {
            return $this->by_lang($lang,
                'Kisállatos szempontnál fontos, hogy a közelben kb. 200 m-re kutyafuttató található; lakásszinten csak a valós emeletadat alapján súlyozok.',
                '你提到宠物，我会结合项目附近约 200 米宠物公园这一点；单套房源只按真实楼层信息做轻微排序，不会编花园或朝向。',
                'For pet-related searches, the nearby dog park about 200 m away is useful; at apartment level I only weigh real floor data and do not invent garden or orientation details.'
            );
        }
        if (!empty($profile['family'])) {
            return $this->by_lang($lang,
                'Családi használatnál előrébb sorolom a több szobás és kényelmesebb alapterületű lakásokat.',
                '你提到家庭/自住，我会优先看房间数更够用、面积更舒适的户型。',
                'For family use, I prioritize more rooms and more comfortable floor area.'
            );
        }
        if (!empty($profile['own_use'])) {
            return $this->by_lang($lang,
                'Saját lakhatásnál az alapterület, a szobaszám és a hosszabb távú használhatóság kap nagyobb súlyt.',
                '你提到自住，我会更重视面积、房间数和长期使用舒适度。',
                'For own use, I give more weight to size, room count and long-term usability.'
            );
        }
        if (!empty($profile['garden'])) {
            return $this->by_lang($lang,
                'Kertes vagy földszinti keresésnél csak a valós földszinti lakásokat sorolom előre; a földszinti kert a jelenlegi információ szerint ajándék.',
                '你提到底楼/花园，我会优先显示真实底楼房源；目前信息为底楼花园赠送，不单独计算花园价格。',
                'For garden or ground-floor searches, I prioritize real ground-floor units; current information says the ground-floor garden is included as a gift.'
            );
        }
        if (!empty($profile['price_sensitive'])) {
            return $this->by_lang($lang,
                'Árérzékeny keresésnél az alacsonyabb teljes ár és a kedvezőbb négyzetméterár kerül előre.',
                '你提到预算/价格敏感，我会优先看低总价和相对友好的单价。',
                'For budget-sensitive searches, I prioritize lower total price and more favorable sqm price.'
            );
        }
        return '';
    }

    private function apartment_answer($item, $lang, $profile = array()) {
        $floor_hu = $this->floor_label($item['floor'], 'hu');
        $floor_zh = $this->floor_label($item['floor'], 'zh');
        $floor_en = $this->floor_label($item['floor'], 'en');
        $detail_url = (string) ($item['property_url'] ?? '');
        $floorplan_url = (string) ($item['floorplan_pdf'] ?? '');
        $tags_hu = $this->tags_sentence($item, $profile, 'hu');
        $tags_zh = $this->tags_sentence($item, $profile, 'zh');
        $tags_en = $this->tags_sentence($item, $profile, 'en');
        $outdoor_hu = $this->outdoor_summary($item, 'hu');
        $outdoor_zh = $this->outdoor_summary($item, 'zh');
        $outdoor_en = $this->outdoor_summary($item, 'en');
        $hu = sprintf(
            '%s jelenlegi adatai: %s épület, %s, %s, %s m² eladási terület%s. Tájékoztató teljes ár: %s. Négyzetméterár: %s. Státusz: %s.%s Adatlap: %s. Alaprajz/PDF: %s. A végleges árat és elérhetőséget az értékesítés erősíti meg.',
            $item['apartment'],
            $item['building'],
            $floor_hu,
            $this->room_label($item, 'hu'),
            $this->format_area($item['sales_area_m2']),
            $outdoor_hu ? ', ' . $outdoor_hu : '',
            $this->format_money($item['price_huf']),
            $this->format_money($item['sqm_price_huf']) . ' / m²',
            (string) ($item['status'] ?? ''),
            $tags_hu ? ' ' . $tags_hu : '',
            $detail_url,
            $floorplan_url
        );
        $zh = sprintf(
            '%s 当前资料：%s 楼，%s，%s，销售面积 %s m²%s。参考总价：%s；参考单价：%s / m²。状态：%s。%s详情页：%s。户型图/PDF：%s。最终价格和可售状态请以销售团队确认为准。',
            $item['apartment'],
            $item['building'],
            $floor_zh,
            $this->room_label($item, 'zh'),
            $this->format_area($item['sales_area_m2']),
            $outdoor_zh ? '，' . $outdoor_zh : '',
            $this->format_money($item['price_huf']),
            $this->format_money($item['sqm_price_huf']),
            (string) ($item['status'] ?? ''),
            $tags_zh ? $tags_zh . ' ' : '',
            $detail_url,
            $floorplan_url
        );
        $en = sprintf(
            '%s current data: building %s, %s, %s, %s m² sales area%s. Indicative total price: %s. Indicative sqm price: %s / m². Status: %s.%s Detail page: %s. Floor plan/PDF: %s. Final availability and price should be confirmed by sales.',
            $item['apartment'],
            $item['building'],
            $floor_en,
            $this->room_label($item, 'en'),
            $this->format_area($item['sales_area_m2']),
            $outdoor_en ? ', ' . $outdoor_en : '',
            $this->format_money($item['price_huf']),
            $this->format_money($item['sqm_price_huf']),
            (string) ($item['status'] ?? ''),
            $tags_en ? ' ' . $tags_en : '',
            $detail_url,
            $floorplan_url
        );

        return $this->by_lang($lang, $hu, $zh, $en);
    }

    private function cards_for_matches($matches, $profile, $lang = 'hu') {
        $cards = array();
        foreach (array_slice($matches, 0, 5) as $item) {
            $cards[] = $this->card($item, $profile, $lang);
        }
        return $cards;
    }

    private function card($item, $profile = array(), $lang = 'hu') {
        $tags = $this->apartment_tags($item, $profile, $lang);
        $tag_text = $tags ? ' · ' . implode(', ', array_slice($tags, 0, 2)) : '';
        $url = (string) ($item['property_url'] ?? home_url('/lakaskereso/'));
        $apartment = (string) ($item['apartment'] ?? '');
        return array(
            'title' => $apartment,
            'url' => $url,
            'offer_url' => add_query_arg(array('assistant_offer' => rawurlencode($apartment)), $url),
            'view_label' => $this->by_lang($lang, 'Megnézem', '查看房源', 'View'),
            'offer_label' => $this->by_lang($lang, 'Árajánlatot kérek', '我要报价', 'Request offer'),
            'meta' => sprintf('%s · %s · %s · %s m²%s · %s · %s / m²%s', $item['building'], $this->floor_label($item['floor'] ?? '', $lang), $this->room_label($item, $lang), $this->format_area($item['sales_area_m2']), $this->outdoor_summary($item, $lang) ? ' · ' . $this->outdoor_summary($item, $lang) : '', $this->format_money($item['price_huf']), $this->format_money($item['sqm_price_huf']), $tag_text),
        );
    }

    private function tags_sentence($item, $profile, $lang) {
        $tags = $this->apartment_tags($item, $profile, $lang);
        if (!$tags) {
            return '';
        }
        return $this->tag_prefix($lang) . implode($this->tag_separator($lang), $tags) . $this->sentence_end($lang);
    }

    private function apartment_tags($item, $profile, $lang) {
        $tags = array();
        $rooms = (int) ($item['rooms'] ?? 0);
        $area = (float) ($item['sales_area_m2'] ?? 0);
        $price = (int) ($item['price_huf'] ?? 0);
        $sqm = (int) ($item['sqm_price_huf'] ?? 0);
        $floor = (string) ($item['floor'] ?? '');
        $outdoor = $this->outdoor_area_value($item);

        if ($floor === 'Fsz') {
            $tags[] = $this->by_lang($lang, 'ajándék kert', '底楼赠送花园', 'gift garden');
        }
        if ($floor === 'Fsz' && $outdoor >= 80) {
            $tags[] = $this->by_lang($lang, 'nagyobb kert', '较大花园', 'larger garden');
        }
        if ($floor !== 'Fsz' && $outdoor >= 20) {
            $tags[] = $this->by_lang($lang, 'nagy terasz', '大露台', 'large terrace');
        }
        if (!empty($profile['investment']) && $rooms <= 2) {
            $tags[] = $this->by_lang($lang, 'befektetési shortlist', '投资优先', 'investment shortlist');
        }
        if (!empty($profile['family']) && $rooms >= 3) {
            $tags[] = $this->by_lang($lang, 'családi jelölt', '家庭候选', 'family candidate');
        }
        if (!empty($profile['own_use']) && $rooms >= 2 && $area >= 55) {
            $tags[] = $this->by_lang($lang, 'saját lakhatásra is kényelmes', '自住舒适', 'comfortable for own use');
        }
        if (!empty($profile['pet']) && $floor === 'Fsz') {
            $tags[] = $this->by_lang($lang, 'földszinti elérés', '底层出入方便', 'ground-floor access');
        }
        if ($price > 0 && $price <= 65000000) {
            $tags[] = $this->by_lang($lang, 'alacsonyabb teljes ár', '低总价', 'lower total price');
        }
        if ($rooms <= 2 && $area > 0 && $area <= 55) {
            $tags[] = $this->by_lang($lang, 'kompakt alapterület', '紧凑户型', 'compact layout');
        }
        if ($rooms >= 3 && $area >= 60) {
            $tags[] = $this->by_lang($lang, 'több szobás használat', '多房间实用', 'multi-room usability');
        }
        if ($rooms >= 4 || $area >= 79) {
            $tags[] = $this->by_lang($lang, 'nagyobb családi méret', '大户型', 'larger family size');
        }
        if ($sqm > 0 && $sqm <= 1400000) {
            $tags[] = $this->by_lang($lang, 'kedvezőbb m² ár', '单价较友好', 'more favorable sqm price');
        }
        return array_slice(array_values(array_unique($tags)), 0, 3);
    }

    private function tag_prefix($lang) {
        return $this->by_lang($lang, 'AI címkék: ', 'AI 标签：', 'AI tags: ');
    }

    private function tag_separator($lang) {
        return $lang === 'zh' ? '、' : ', ';
    }

    private function sentence_end($lang) {
        return $lang === 'zh' ? '。' : '.';
    }

    private function floor_label($floor, $lang) {
        $floor = (string) $floor;
        if ($floor === 'Fsz') {
            return $this->by_lang($lang, 'Fsz', '底层', 'ground floor');
        }
        if ($lang === 'zh') {
            return $floor . ' 楼';
        }
        if ($lang === 'en') {
            return 'floor ' . $floor;
        }
        return $floor . '. emelet';
    }

    private function room_label($item, $lang) {
        $rooms = (int) ($item['rooms'] ?? 0);
        if ($lang === 'zh') {
            return $rooms . ' 房';
        }
        if ($lang === 'en') {
            return $rooms . ' room' . ($rooms === 1 ? '' : 's');
        }
        return $rooms . ' szobás';
    }

    private function format_money($value) {
        return number_format((float) $value, 0, ',', ' ') . ' Ft';
    }

    private function format_area($value) {
        return number_format((float) $value, 2, ',', ' ');
    }

    private function has_price_intent($text) {
        return $this->has_any($text, array('ar', 'ara', 'arak', 'mennyibe', 'price', 'prices', 'amount', 'cost', 'huf', 'ft', '多少钱', '价格', '价钱', '金额', '总价', '单价', '预算'));
    }

    private function price_overview_answer($apartments, $lang) {
        $ranges = $this->price_ranges_by_room($apartments);
        if (!$ranges) {
            return $this->by_lang($lang,
                'Az árak elérhetőségét az értékesítés tudja pontosan megerősíteni.',
                '价格需要销售团队根据实时房源确认。',
                'Prices should be confirmed by the sales team based on current availability.'
            );
        }

        $lines = array();
        foreach ($ranges as $rooms => $range) {
            if ($lang === 'zh') {
                $lines[] = sprintf(
                    '%d 房：%d 套，面积 %s-%s m²，参考总价 %s-%s，参考单价 %s-%s / m²。',
                    $rooms,
                    $range['count'],
                    $this->format_area($range['min_area']),
                    $this->format_area($range['max_area']),
                    $this->format_money($range['min_price']),
                    $this->format_money($range['max_price']),
                    $this->format_money($range['min_sqm']),
                    $this->format_money($range['max_sqm'])
                );
                continue;
            }

            if ($lang === 'en') {
                $lines[] = sprintf(
                    '%d-room: %d flats, %s-%s m², indicative total price %s-%s, sqm price %s-%s / m².',
                    $rooms,
                    $range['count'],
                    $this->format_area($range['min_area']),
                    $this->format_area($range['max_area']),
                    $this->format_money($range['min_price']),
                    $this->format_money($range['max_price']),
                    $this->format_money($range['min_sqm']),
                    $this->format_money($range['max_sqm'])
                );
                continue;
            }

            $lines[] = sprintf(
                '%d szobás: %d lakás, %s-%s m², tájékoztató teljes ár %s-%s, négyzetméterár %s-%s / m².',
                $rooms,
                $range['count'],
                $this->format_area($range['min_area']),
                $this->format_area($range['max_area']),
                $this->format_money($range['min_price']),
                $this->format_money($range['max_price']),
                $this->format_money($range['min_sqm']),
                $this->format_money($range['max_sqm'])
            );
        }

        $prefix = $this->by_lang($lang,
            'A jelenlegi árlistában ezek a fő sávok látszanak:',
            '当前价格表里的主要区间如下：',
            'The current price list shows these main ranges:'
        );
        $suffix = $this->by_lang($lang,
            'Az összegek tájékoztató jellegűek; a végleges árat és elérhetőséget az értékesítés erősíti meg.',
            '以上金额为当前资料参考，最终价格和可售状态需要销售团队确认。',
            'Amounts are indicative; final price and availability should be confirmed by sales.'
        );

        return $prefix . "\n\n" . implode("\n", $lines) . "\n\n" . $suffix;
    }

    private function price_ranges_by_room($apartments) {
        $ranges = array();
        foreach ($apartments as $item) {
            $rooms = (int) ($item['rooms'] ?? 0);
            $price = (int) ($item['price_huf'] ?? 0);
            if ($rooms < 1 || $price <= 0) {
                continue;
            }

            if (!isset($ranges[$rooms])) {
                $ranges[$rooms] = array(
                    'count' => 0,
                    'min_price' => $price,
                    'max_price' => $price,
                    'min_area' => (float) ($item['sales_area_m2'] ?? 0),
                    'max_area' => (float) ($item['sales_area_m2'] ?? 0),
                    'min_sqm' => (int) ($item['sqm_price_huf'] ?? 0),
                    'max_sqm' => (int) ($item['sqm_price_huf'] ?? 0),
                );
            }

            $ranges[$rooms]['count'] += 1;
            $ranges[$rooms]['min_price'] = min($ranges[$rooms]['min_price'], $price);
            $ranges[$rooms]['max_price'] = max($ranges[$rooms]['max_price'], $price);
            $ranges[$rooms]['min_area'] = min($ranges[$rooms]['min_area'], (float) ($item['sales_area_m2'] ?? 0));
            $ranges[$rooms]['max_area'] = max($ranges[$rooms]['max_area'], (float) ($item['sales_area_m2'] ?? 0));
            $ranges[$rooms]['min_sqm'] = min($ranges[$rooms]['min_sqm'], (int) ($item['sqm_price_huf'] ?? 0));
            $ranges[$rooms]['max_sqm'] = max($ranges[$rooms]['max_sqm'], (int) ($item['sqm_price_huf'] ?? 0));
        }

        ksort($ranges);
        return $ranges;
    }

    private function has_any($text, $needles) {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($text, $needle, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }

    private function u($escaped) {
        $decoded = json_decode('"' . (string) $escaped . '"');
        return is_string($decoded) ? $decoded : (string) $escaped;
    }

    private function by_lang($lang, $hu, $zh, $en) {
        if ($lang === 'zh') {
            return $zh;
        }
        if ($lang === 'en') {
            return $en;
        }
        return $hu;
    }

    private function visitor_key() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) wp_unslash($_SERVER['HTTP_USER_AGENT']), 0, 160) : '';
        return hash_hmac('sha256', $ip . '|' . $ua, wp_salt('auth'));
    }

    private function validate_rest_nonce(WP_REST_Request $request) {
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce !== '' && wp_verify_nonce($nonce, 'wp_rest')) {
            return null;
        }

        return new WP_Error(
            'harmat_ai_nonce',
            'A biztonsági ellenőrzés lejárt. Kérjük, frissítse az oldalt, majd próbálja újra.',
            array('status' => 403)
        );
    }

    private function check_rate_limit($scope, $limit, $ttl) {
        $scope = sanitize_key((string) $scope);
        $limit = max(1, (int) $limit);
        $ttl = max(60, (int) $ttl);
        $key = 'harmat_ai_rate_' . md5($scope . '|' . $this->visitor_key());
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, $ttl);
        return true;
    }

    private function build_crm_summary($data) {
        $data = is_array($data) ? $data : array();
        $intent = sanitize_key((string) ($data['intent'] ?? ''));
        $intent_labels = array(
            'appointment' => '预约看房 / Időpontkérés',
            'offer' => '报价需求 / Árajánlatkérés',
            'payment' => '付款咨询 / Fizetési kérdés',
            'loan' => '贷款/融资咨询 / Finanszírozás',
            'recommendation' => '选房推荐 / Lakásajánlás',
            'apartment_search' => '找房需求 / Lakáskeresés',
        );
        $lines = array(
            '来源：Harmat AI 客服',
            '意向类型：' . ($intent_labels[$intent] ?? ($intent !== '' ? $intent : '未明确')),
        );
        $unit = trim((string) ($data['interested_unit'] ?? ''));
        $type = trim((string) ($data['apartment_type'] ?? ''));
        $rooms = trim((string) ($data['preferred_room_count'] ?? ''));
        $budget = trim((string) ($data['budget_range'] ?? ''));
        $time = trim((string) ($data['preferred_contact_time'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $context = trim((string) ($data['context'] ?? ''));
        $conversation = trim((string) ($data['conversation_summary'] ?? ''));
        $all_text = mb_strtolower($message . "\n" . $context . "\n" . $conversation, 'UTF-8');

        if ($unit !== '') {
            $lines[] = '意向房源：' . $unit;
        }
        if ($type !== '') {
            $lines[] = '户型/类型：' . $type;
        }
        if ($rooms !== '') {
            $lines[] = '房间数偏好：' . $rooms;
        }
        if ($budget !== '') {
            $lines[] = '预算范围：' . $budget;
        }
        if ($time !== '') {
            $lines[] = '偏好联系时间：' . $time;
        }

        $concerns = array();
        $checks = array(
            '价格/付款' => array('价格', '价钱', '预算', '付款', '分期', 'fizet', 'ár', 'payment', 'price'),
            '贷款/CSOK' => array('贷款', '融资', 'csok', 'hitel', 'bank', 'loan'),
            '看房预约' => array('预约', '看房', 'időpont', 'megtekint', 'appointment', 'viewing'),
            '花园/露台' => array('花园', '露台', 'kert', 'teras', 'garden', 'terrace'),
            '交通/周边' => array('交通', '公交', '学校', '周边', 'közleked', 'iskola', 'nearby', 'transport'),
            '交付/工期' => array('交付', '封顶', '验房', 'átadás', 'handover', 'delivery'),
        );
        foreach ($checks as $label => $needles) {
            foreach ($needles as $needle) {
                if ($needle !== '' && mb_strpos($all_text, mb_strtolower($needle, 'UTF-8')) !== false) {
                    $concerns[] = $label;
                    break;
                }
            }
        }
        if ($concerns) {
            $lines[] = '客户关注点：' . implode('、', array_unique($concerns));
        }

        if ($conversation !== '') {
            $lines[] = '聊天摘要：' . mb_substr($conversation, 0, 260, 'UTF-8');
        } elseif ($message !== '') {
            $lines[] = '客户留言：' . mb_substr($message, 0, 220, 'UTF-8');
        }

        $next = '建议销售首次联系后补齐预算、房间数、用途和可联系时间。';
        if ($unit !== '') {
            $next = '建议核对 ' . $unit . ' 的状态、价格和可约看时间，并尽快回访。';
        } elseif ($rooms !== '' || $budget !== '') {
            $next = '建议按房间数/预算筛选 2-4 套可售房源，发送报价并约看房。';
        } elseif (in_array('贷款/CSOK', $concerns, true)) {
            $next = '建议先确认客户自有资金、贷款需求和 CSOK 资格方向，再安排销售/银行顾问跟进。';
        }
        $lines[] = '下一步建议：' . $next;

        return $this->limit_text(implode("\n", array_filter($lines)), 1200);
    }

    private function sanitize_utm($utm) {
        if (!is_array($utm)) {
            return array();
        }

        $allowed = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'landing_page', 'source_page', 'referrer', 'lead_source', 'path');
        $clean = array();
        foreach ($allowed as $key) {
            if (!isset($utm[$key])) {
                continue;
            }
            $value = is_scalar($utm[$key]) ? (string) $utm[$key] : '';
            if ($value === '') {
                continue;
            }
            $clean[$key] = in_array($key, array('landing_page', 'source_page', 'referrer'), true)
                ? esc_url_raw($value)
                : sanitize_text_field($value);
        }
        return $clean;
    }

    private function track_event($event, $meta = array()) {
        $event = sanitize_key((string) $event);
        $allowed = array(
            'assistant_open',
            'assistant_question',
            'quick_button_click',
            'apartment_recommendation',
            'offer_request_started',
            'offer_request_submitted',
            'appointment_started',
            'appointment_submitted',
            'human_handoff',
            'unknown_question',
        );
        if (!in_array($event, $allowed, true)) {
            return;
        }

        $safe_meta = array();
        if (is_array($meta)) {
            foreach ($meta as $key => $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $safe_meta[sanitize_key((string) $key)] = sanitize_text_field(mb_substr((string) $value, 0, 180, 'UTF-8'));
            }
        }

        $option = 'harmat_ai_events_' . current_time('Ymd');
        $payload = get_option($option, array());
        if (!is_array($payload)) {
            $payload = array();
        }
        if (empty($payload['counts']) || !is_array($payload['counts'])) {
            $payload['counts'] = array();
        }
        if (empty($payload['recent']) || !is_array($payload['recent'])) {
            $payload['recent'] = array();
        }

        $payload['counts'][$event] = (int) ($payload['counts'][$event] ?? 0) + 1;
        $payload['recent'][] = array(
            'event' => $event,
            'time' => current_time('mysql'),
            'meta' => $safe_meta,
        );
        $payload['recent'] = array_slice($payload['recent'], -80);

        update_option($option, $payload, false);
        do_action('harmat_ai_assistant_event', $event, $safe_meta);
    }

    private function text($key, $lang) {
        $texts = array(
            'empty' => array(
                'hu' => 'Kérem, írja be kérdését.',
                'zh' => '请输入你的问题。',
                'en' => 'Please enter your question.',
            ),
            'no_match' => array(
                'hu' => 'Erre a szűrésre most nem találtam megfelelő lakást. Próbáljon más árkeretet, szobaszámot vagy alapterületet megadni.',
                'zh' => '按这个条件暂时没有找到合适房源。可以换一个预算、房间数或面积范围。',
                'en' => 'I could not find a matching apartment for these filters. Try another budget, room count or size.',
            ),
            'fallback' => array(
                'hu' => "Pontosan szívesen segítek, csak egy kicsit több irány kell. Kérdezhet például így:
- \"A1-F-L1 ára és alaprajza\"
- \"2 szobás lakás 70 millió Ft körül\"
- \"Melyik jó befektetésre?\"
- \"Hogyan tudok időpontot kérni?\"

Ha lakást keres, írja meg a szobaszámot, árkeretet és hogy saját használatra vagy befektetésre nézi.",
                'zh' => "我可以帮你查，但需要更具体一点。你可以这样问：
- \"A1-F-L1 的价格和户型图\"
- \"7000万福林左右两房推荐\"
- \"哪几套适合投资？\"
- \"怎么预约看房？\"

如果你要选房，请告诉我房间数、预算、自住还是投资。",
                'en' => "I can help, but I need a little more direction. You can ask for example:
- \"A1-F-L1 price and floor plan\"
- \"2-room around 70 million Ft\"
- \"Which units are good for investment?\"
- \"How can I book a viewing?\"

If you are looking for a unit, share room count, budget and whether it is for own use or investment.",
            ),
            'handoff_saved' => array(
                'hu' => 'Köszönjük! Az értékesítési csapat hamarosan felveszi Önnel a kapcsolatot.',
                'zh' => '谢谢！销售团队会尽快与您联系。',
                'en' => 'Thank you. The sales team will contact you soon.',
            ),
            'appointment_saved' => array(
                'hu' => 'Köszönjük! Az értékesítési csapat hamarosan visszaigazolja az időpontot.',
                'zh' => '谢谢！销售团队会尽快确认预约时间。',
                'en' => 'Thank you. The sales team will confirm the appointment soon.',
            ),
        );

        if (!isset($texts[$key])) {
            $key = 'fallback';
        }
        return $texts[$key][$lang] ?? $texts[$key]['hu'];
    }

    private function default_suggestions($lang) {
        if ($lang === 'zh') {
            return array('我要找两房', '我要带花园的房源', '我要大露台户型', '周边公交线路', '附近学校', '付款节点', '我要报价', '预约看房', '销售办公室在哪里？', '贷款 / CSOK 咨询');
        }
        if ($lang === 'en') {
            return array('I am looking for a 2-room apartment', 'I am looking for a garden apartment', 'I am looking for a large terrace apartment', 'Nearby bus routes', 'Nearby schools', 'Payment schedule', 'Request an offer', 'Book a viewing', 'Where is the sales office?', 'Financing / CSOK');
        }
        return array('2 szobás lakást keresek', 'Kertes lakást keresek', 'Nagy teraszos lakást keresek', 'Közlekedés és buszok', 'Közeli iskolák', 'Fizetési ütemezés', 'Árajánlatot kérek', 'Időpontot foglalok', 'Hol található a bemutatóiroda?', 'Finanszírozás / CSOK érdekel');
    }
}

$GLOBALS['harmat_local_assistant'] = new Harmat_Local_Assistant();
