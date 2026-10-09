<?php
/**
 * Plugin Name: Harmat Automatic Reminder Email Exclusions
 * Description: Pauses legal task reminders and excludes configured recipients from legal and sales task reminders only.
 * Version: 1.0.0
 * Author: Harmat22 Maintenance
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_automatic_reminder_email_exclusions($recipients) {
    $configured = get_option('harmat_automatic_reminder_email_exclusions', array());
    $configured = is_string($configured) ? array($configured) : $configured;
    $excluded = array();
    if (is_array($configured)) {
        foreach ($configured as $email) {
            if (is_string($email) && trim($email) !== '') {
                $excluded[strtolower(trim($email))] = true;
            }
        }
    }

    if (!$excluded) {
        return $recipients;
    }
    if (is_string($recipients)) {
        return isset($excluded[strtolower(trim($recipients))]) ? array() : $recipients;
    }
    if (!is_array($recipients)) {
        return $recipients;
    }

    $remaining = array_filter($recipients, function ($email) use ($excluded) {
        return !is_string($email) || !isset($excluded[strtolower(trim($email))]);
    });
    return count($remaining) === count($recipients) ? $recipients : array_values($remaining);
}

function harmat_legal_task_reminder_email_pause($recipients) {
    $enabled = get_option('harmat_legal_task_reminder_email_enabled', false);
    if (!in_array($enabled, array(true, 1, '1'), true)) {
        return array();
    }
    return harmat_automatic_reminder_email_exclusions($recipients);
}

add_filter('harmat_legal_task_reminder_recipients', 'harmat_legal_task_reminder_email_pause', PHP_INT_MAX);
add_filter('harmat_sales_task_reminder_recipients', 'harmat_automatic_reminder_email_exclusions', PHP_INT_MAX);
