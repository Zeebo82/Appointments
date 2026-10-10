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
assert($vars['name'] === 'Anna Mustermann');
// Legacy templates must keep working, without guessing a first name.
assert($vars['firstName'] === 'Anna Mustermann');
assert($vars['date'] === '2026-10-15');
assert($vars['time'] === '10:30');
assert($vars['endTime'] === '11:15');
assert($vars['durationMinutes'] === '45');
assert($vars['location'] === 'Gemeindehaus');
assert($vars['attendeePhone'] === '0170 1234567');

$html = HtmlTemplate::render('<p>Hallo {{name}}, Dein Termin am {{date}} um {{time}} in {{location}}.</p>', $vars);
assertHas('Hallo Anna Mustermann', $html);
$oldHtml = HtmlTemplate::render('<p>Hallo {{firstName}},</p>', $vars);
assertHas('Hallo Anna Mustermann', $oldHtml);
assertHas('am 2026-10-15 um 10:30 in Gemeindehaus', $html);

$vars['name'] = '<script>alert(1)</script> {{email}}';
$html = HtmlTemplate::render('<p>{{name}}</p>', $vars);
assertHas('&lt;script&gt;', $html);
assertHas('{{email}}', $html);
if (str_contains($html, '<script>')) throw new RuntimeException('unsafe HTML');
// Original buttons are a distinct rendering action, not user-supplied HTML.
$parts = HtmlTemplate::splitOnConfirmationButtons(
    '<p>Hallo {{name}},</p>{{confirmationButtons}}<p>Bitte bestätigen.</p>'
);
assert($parts === ['<p>Hallo {{name}},</p>', '<p>Bitte bestätigen.</p>']);
assert(HtmlTemplate::splitOnConfirmationButtons('<p>Keine Aktionen</p>') === null);

// Multiple markers may only insert the native action group once.
$parts = HtmlTemplate::splitOnConfirmationButtons(
    'Vorher {{confirmationButtons}} Nachher {{confirmationButtons}}'
);
assert($parts === ['Vorher ', ' Nachher ']);

// A user-controlled placeholder VALUE must not turn into actionable markup.
$vars['name'] = '{{confirmationButtons}}';
$raw = '<p>{{name}}</p>';
assert(HtmlTemplate::splitOnConfirmationButtons($raw) === null);
assertHas('{{confirmationButtons}}', HtmlTemplate::render($raw, $vars));

echo "mail variable and action button smoke tests passed\n";
