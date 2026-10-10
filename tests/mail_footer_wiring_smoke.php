<?php
// Static wiring checks complement the PHP smoke tests and Vue build.
$root = dirname(__DIR__);
$listener = file_get_contents($root . '/lib/Backend/DavListener.php');
$controller = file_get_contents($root . '/lib/Controller/PageController.php');
$backend = file_get_contents($root . '/lib/Backend/BackendUtils.php');
$state = file_get_contents($root . '/lib/Controller/StateController.php');
$store = file_get_contents($root . '/src/stores/settings.js');
$ui = file_get_contents($root . '/src/components/settings-v2/SectionEmail.vue');
$tests = [
    'footer in attendee and reminder paths' => substr_count($listener, 'finalizeEmailText($tmpl, $cnl_lnk_url, $settings, $mailVars)') === 2,
    'footer in organizer path' => str_contains($listener, 'appendSharedMailFooter($tmpl, $settings, $orgVars)'),
    'footer default' => str_contains($backend, 'self::MAILFOOTERHTML => ""'),
    'footer sanitized on save' => str_contains($state, 'BackendUtils::MAILFOOTERHTML'),
    'footer in the store' => str_contains($store, "mailFooterHtml: ''"),
    'footer in settings UI' => str_contains($ui, 'prop-name="mailFooterHtml"'),
    'no-name marker set by controller' => str_contains($controller, "\$post['_name_missing'] = \$post['name'] === ''"),
    'no-name marker persisted' => str_contains($backend, "attendeeNameMissing = !empty(\$info['_name_missing'])")
];
foreach ($tests as $reason => $passed) {
    if (!$passed) {
        throw new RuntimeException('Wiring missing: ' . $reason);
    }
}
echo "Mail footer wiring smoke tests passed\n";
