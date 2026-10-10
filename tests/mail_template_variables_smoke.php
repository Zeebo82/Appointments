<?php
require __DIR__ . '/../lib/Backend/HtmlTemplate.php';
require __DIR__ . '/../lib/Backend/MailTemplateVariables.php';

use OCA\Appointments\Backend\HtmlTemplate;
use OCA\Appointments\Backend\MailTemplateVariables;

class TestLocalization {
    public function l($type, $date, $opts = []) {
        $d = $date instanceof \DateTimeInterface ? $date : \DateTimeImmutable::createFromFormat('Ymd\THis', $date, new \DateTimeZone('UTC'));
        return $type === 'date' ? $d->format('Y-m-d') : $d->format('H:i');
    }
    public function t($pattern, $args = []) {
        return sprintf($pattern, ...$args);
    }
}
function assertHas($needle, $haystack) {
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException("Expected " . $needle . " in " . $haystack);
    }
}

$vars = MailTemplateVariables::build(
    'Anna Mustermann', 'Testverein', '15.10.2026, 10:30',
    'anna@example.test', 'kontakt@example.test', '0203 123',
    new DateTimeImmutable('2026-10-15 08:30:00 UTC'),
    new DateTimeImmutable('2026-10-15 09:15:00 UTC'),
    'TEurope/Berlin', new TestLocalization(),
    'Gemeindehaus', '0170 1234567'
);
assert($vars['firstName'] === 'Anna');
assert($vars['date'] === '2026-10-15');
assert($vars['time'] === '10:30');
assert($vars['endTime'] === '11:15');
assert($vars['durationMinutes'] === '45');
assert($vars['location'] === 'Gemeindehaus');
assert($vars['attendeePhone'] === '0170 1234567');

$html = HtmlTemplate::render('<p>Hallo {{firstName}}, Dein Termin am {{date}} um {{time}} in {{location}}.</p>', $vars);
assertHas('Hallo Anna', $html);
assertHas('am 2026-10-15 um 10:30 in Gemeindehaus', $html);

$vars['name'] = '<script>alert(1)</script> {{email}}';
$html = HtmlTemplate::render('<p>{{name}}</p>', $vars);
assertHas('&lt;script&gt;', $html);
assertHas('{{email}}', $html);
if (str_contains($html, '<script>')) throw new RuntimeException('unsafe HTML');
echo "mail variable smoke tests passed\n";
