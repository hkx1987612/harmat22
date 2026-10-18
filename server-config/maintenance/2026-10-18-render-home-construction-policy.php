<?php
// CLI-only, synthetic native shell; no WordPress, HTTP, customer data or writes.
if (PHP_SAPI !== 'cli') exit(1);
$candidatePath = $argv[1] ?? dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-construction-updates.php';
$savedArgv = $argv;
$argv = array(__FILE__, $candidatePath);
ob_start();
try {
    // Reuse all namespaced stubs and regression checks without its exiting render modes.
    require __DIR__ . '/2026-10-18-test-construction-updates.php';
    $fixtureSummary = json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
} finally {
    ob_end_clean();
    $argv = $savedArgv;
}
if (($fixtureSummary['status'] ?? '') !== 'PASS') throw new RuntimeException('Base fixture failed');
if (!function_exists('HarmatConstructionFixture\\harmat_home_family_output')) {
    throw new RuntimeException('Real homepage controller was not loaded');
}
$state['page'] = 164;
$state['missing'] = $state['link'] = $state['empty'] = false;
$state['flags'] = array();
$harmat_home_family_units = HarmatConstructionFixture\harmat_home_family_units();
$bodyBefore = include dirname($candidatePath) . '/harmat-home-family/body.php';
$bodyTeaser = HarmatConstructionFixture\harmat_cu_home_teaser($bodyBefore);
if ($bodyTeaser === $bodyBefore) throw new RuntimeException('Actual teaser did not replace pinned journal');

// These are sentinel controls, not a copy of live quote/appointment handlers.
$nativeForms = str_replace('</form>',
    '<textarea name="message">Unsent quote draft</textarea><input type="date" name="date" value="2026-10-22">'
    . '<input type="time" name="time" value="10:30"><button type="submit">Quote</button></form>', $form)
    . '<section id="native-appointment-fixture"><form id="native-appointment-form">'
    . '<input name="name" value="Unsent appointment draft"><input type="date" name="date" value="2026-10-23">'
    . '<input type="time" name="time" value="11:30"><input type="checkbox" name="privacy" required>'
    . '<textarea name="message">Unsent appointment message</textarea><button type="submit">Appointment</button>'
    . '</form></section>';
$homeInput = str_replace(array($form, '<html>', '<body>'),
    array($nativeForms, '<html lang="hu" data-harmat-language="hu">', '<body class="home page-id-164">'), $shell);
$homeOutput = HarmatConstructionFixture\harmat_home_family_output($homeInput);
if ($homeOutput === $homeInput || strpos($homeOutput, $nativeForms) === false) {
    throw new RuntimeException('Real homepage rendering failed or changed native sentinels');
}
if (HarmatConstructionFixture\harmat_home_family_output($homeOutput) !== $homeOutput) {
    throw new RuntimeException('Homepage rendering is not idempotent');
}
echo json_encode(array(
    'fixture' => $fixtureSummary,
    'home' => $homeOutput,
    'homeInput' => $homeInput,
    'bodyBefore' => $bodyBefore,
    'bodyTeaser' => $bodyTeaser,
    'journal' => $output,
    'nativeForms' => $nativeForms,
), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
