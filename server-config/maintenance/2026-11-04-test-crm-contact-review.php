<?php
// Synthetic CRM-only fixtures. Base harness prohibits data writes and mail.
require __DIR__ . '/2026-11-04-test-crm-simplification.php';
$start_checks = $checks;
$notes = str_repeat('实际沟通记录 / Megbeszélés. ', 12) . '<script>alert(1)</script>';
$options['harmat_broker_leads_v1'] = array(
    301 => array('id' => 301, 'client_name' => 'DEMO Ágnes', 'phone' => '+36 30 123 4567', 'email' => 'agnes@example.test', 'assigned_sales_id' => 11, 'status' => 'contacted', 'source' => 'walkin', 'created_at' => '2026-11-04 10:00:00', 'next_followup' => '2026-11-06 10:00:00', 'note' => $notes),
    302 => array('id' => 302, 'client_name' => 'DEMO 张先生', 'phone' => '+36 30 987 6543', 'email' => 'zhang@example.test', 'assigned_sales_id' => 22, 'status' => 'visited', 'source' => 'walkin', 'created_at' => '2026-10-01 10:00:00', 'note' => 'DEMO'),
    303 => array('id' => 303, 'client_name' => 'DEMO 未指派', 'email' => 'unassigned@example.test', 'assigned_sales_id' => 0, 'source' => 'walkin', 'created_at' => '2026-10-01 10:00:00'),
);
$options['harmat_sales_deals_v1'] = array(
    201 => array('id' => 201, 'client_name' => 'DEMO Deal', 'assigned_sales_id' => 11, 'inquiry_id' => 102, 'amount' => 100000, 'payment_due_date' => '2026-11-07', 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00', 'phone' => '+36 30 555 7777'),
);
$posts = array();
foreach (array(101 => 11, 102 => 11, 103 => 22) as $id => $owner) {
    $posts[] = (object) array('ID' => $id, 'post_date' => '2026-11-01 10:00:00', 'post_content' => $notes, 'post_title' => 'DEMO inquiry ' . $id);
    $post_meta[$id] = array('_harmat_assigned_sales_id' => $owner, '_harmat_offer_phone' => '+36000000000', '_harmat_offer_email' => 'web-' . $id . '@example.test', '_harmat_offer_mail_status' => 'sent', '_harmat_offer_checked_at' => '2026-11-01 10:05:00', '_harmat_offer_posted' => array('your-name' => 'DEMO inquiry ' . $id, 'your-date' => '2026-11-05', 'your-time' => '10:00'));
}
$before = serialize(array($options, $post_meta));
foreach (array('manager', 'staff') as $mode) {
    foreach (array('zh', 'hu') as $lang) {
        $_GET = array('sales_lang' => $lang);
        $text = call_crm('sales_contact_review_text');
        $statuses = call_crm('sales_clients_page_text', $lang)['status'];
        $visible = call_crm('visible_leads', call_crm('get_leads'));
        foreach (array('agnes', 'AGNES@EXAMPLE.TEST', '36301234567', '+36 (30) 123-4567') as $query) {
            check(array_column(call_crm('filter_sales_contact_rows', $visible, array('q' => $query)), 'id') === array(301), 'name, email and formatted phone search: ' . $query);
        }
        check(array_column(call_crm('filter_sales_contact_rows', $visible, array('owner' => '11', 'status' => 'contacted')), 'id') === array(301), 'owner and status intersect');
        check(count(call_crm('filter_sales_contact_rows', $visible, array('owner' => '999'))) === 0, 'unknown owner never broadens results');
        check(count(call_crm('filter_sales_contact_rows', $visible, array('owner' => '0'))) === ($mode === 'manager' ? 1 : 0), 'unassigned obeys visibility');
        $_GET += array('client_status' => 'bogus', 'client_owner' => '-1');
        check(call_crm('sales_contact_filters', 'client', $statuses)['status'] === '', 'unknown status neutralized');
        check(call_crm('sales_contact_filters', 'client', $statuses)['owner'] === '', 'invalid owner neutralized');
        $rows = call_crm('sales_inquiry_summary_rows');
        $web = array_values(array_filter($rows, fn($r) => $r['kind'] === 'website'));
        check(count($web) === ($mode === 'manager' ? 3 : 2), 'inquiry permissions retained');
        $plain = array_values(array_filter($web, fn($r) => $r['id'] === 101))[0];
        check($plain['progress'] === 'unrecorded' && $plain['mail_status'] === 'sent', 'sent mail does not mean contacted');
        $converted = call_crm('sales_inquiry_summary_rows', '', '', $lang, array('status' => 'converted'));
        check(count($converted) === 1 && $converted[0]['id'] === 102, 'existing deal identified, not created');
        $xp = dom_of(rendered('inquiries', $lang));
        check(strpos($xp->query('//*[@id="inquiry-website-101"]/td[6]')->item(0)->textContent, $text['unrecorded']) !== false, 'progress displayed independently');
        check($xp->query('//*[@id="inquiry-website-102"]/td[9]/a[contains(@href,"edit_deal=201")]')->length === 1, 'linked inquiry opens existing deal');
        check($xp->query('//details[@class="harmat-sales-row-more"]//form//input[starts-with(@value,"delete_")]')->length > 0, 'delete remains behind More and original form');
        $clients = rendered('clients', $lang); $xp = dom_of($clients);
        check($xp->query('//form[@method="get"]//input[@name="client_q"]')->length === 1, 'client search visible');
        check($xp->query('//details[@class="harmat-sales-note-details"]')->length >= 1, 'long notes collapsed');
        check($xp->query('//details[@class="harmat-sales-note-details"]//div/script')->length === 0, 'notes escaped');
        check(strpos($xp->query('//details[@class="harmat-sales-note-details"]/div')->item(0)->textContent, '<script>alert(1)</script>') !== false, 'full original note retained as text');
        $edit = dom_of(rendered('clients', $lang, array('edit_lead' => 301)));
        $dataset = json_decode($edit->query('//*[@data-client-duplicates]')->item(0)->getAttribute('data-client-duplicates'), true);
        check(!array_filter($dataset, fn($r) => $r['kind'] === 'lead' && $r['id'] === 301), 'editing excludes self from duplicate suggestions');
        check(count(array_filter($dataset, fn($r) => $r['kind'] === 'lead' && $r['id'] === 302)) === ($mode === 'manager' ? 1 : 0), 'duplicate suggestions do not leak hidden clients');
        check(count(array_filter($dataset, fn($r) => $r['kind'] === 'inquiry' && $r['id'] === 103)) === ($mode === 'manager' ? 1 : 0), 'duplicate suggestions do not leak hidden inquiries');
        $filtered = dom_of(rendered('clients', $lang, array('client_q' => '36301234567')));
        check($filtered->query('//table[contains(@class,"harmat-sales-client-table")]/tbody/tr')->length === 1, 'client GET filter rendered correctly');
        $scheduled = call_crm('sales_tasks');
        $all = call_crm('sales_tasks', true);
        $task_text = call_crm('sales_task_text');
        check(!array_filter($scheduled, fn($r) => !empty($r['inferred'])), 'default excludes inferred reminders');
        check(count($all) > count($scheduled), 'system suggestions remain available');
        check(count(array_filter($scheduled, fn($r) => $r['type'] === $task_text['type_client'])) === 1, 'explicit follow-up retained');
        check(count(array_filter($scheduled, fn($r) => $r['type'] === $task_text['type_payment'])) >= 1, 'actual payment dates retained');
        check(count(array_filter($scheduled, fn($r) => $r['type'] === $task_text['type_viewing'])) >= 1, 'actual viewing dates retained');
        $tasks = rendered('tasks', $lang); $xp = dom_of($tasks);
        check($xp->query('//nav[@class="harmat-sales-task-scopes"]/a[@aria-current="page"]')->length === 1, 'scheduled scope selected');
        $suggested = rendered('tasks', $lang, array('task_scope' => 'automatic')); $xp = dom_of($suggested);
        check($xp->query('//table[contains(@class,"harmat-sales-task-table")]//span[contains(@class,"task-overdue")]')->length === 0, 'system suggestions are not displayed as overdue deadlines');
        if (in_array('--preview', $argv, true)) {
            foreach (array('clients', 'inquiries', 'tasks') as $view) file_put_contents("$out/$mode-$lang-$view.html", rendered($view, $lang));
            file_put_contents("$out/$mode-$lang-tasks-automatic.html", $suggested);
            file_put_contents("$out/$mode-$lang-clients-filtered.html", rendered('clients', $lang, array('client_q' => '36301234567')));
        }
    }
}
check($before === serialize(array($options, $post_meta)), 'all record data unchanged');
echo 'PASS ' . ($checks - $start_checks) . " contact-review checks; synthetic data only.\n";
