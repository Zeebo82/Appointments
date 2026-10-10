<?php
require __DIR__ . '/../lib/Backend/HtmlTemplate.php';
use OCA\Appointments\Backend\HtmlTemplate;

function checkImage(string $raw, string $expected): void {
    $safe = HtmlTemplate::sanitize($raw);
    if (!str_contains($safe, $expected)) {
        throw new \RuntimeException("Image missing: " . $safe);
    }
}

checkImage('<img src="https://img.example.org/logo.png?x=1&amp;y=2" alt="Logo" width="400" height="200">', 'src="https://img.example.org/logo.png?x=1&amp;y=2"');
checkImage('<p><img src="/apps/appointments/img/logo.png" alt="Logo"></p>', 'src="/apps/appointments/img/logo.png"');

$safe = HtmlTemplate::sanitize('<img src="https://img.example.org/photo.jpg" onerror="alert(1)" srcset="https://evil.org/pixel.jpg 2x" style="background:url(https://evil.org/a)" width="99999">');
checkImage($safe, 'src="https://img.example.org/photo.jpg"');
foreach (['onerror', 'srcset', 'style=', 'width="99999"'] as $forbidden) {
    if (str_contains($safe, $forbidden)) {
        throw new \RuntimeException('Unsafe attribute kept: ' . $forbidden);
    }
}

foreach (['javascript:alert(1)', 'data:image/svg+xml,PHN2Zz4=', 'http://img.example.org/a.png', '//img.example.org/a.png', '/\\evil.org/a.png', 'https://user:pass@example.org/a.png'] as $src) {
    $clean = HtmlTemplate::sanitize('<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="unsafe">');
    if (str_contains($clean, '<img')) {
        throw new \RuntimeException("Unsafe image survived: " . $src . ' ' . $clean);
    }
}

$origins = HtmlTemplate::imageOrigins('<img src="https://img.example.org/a.png"><img src="https://img.example.org/b.png"><img src="https://cdn.example.net:8443/a.png"><img src="/local.png">');
sort($origins);
if ($origins !== ['https://cdn.example.net:8443', 'https://img.example.org']) {
    throw new \RuntimeException('Incorrect CSP origins: ' . json_encode($origins));
}

$rendered = HtmlTemplate::render(
    '<p>Hallo {{name}}</p><img src="https://img.example.org/logo.png" alt="{{organization}}">',
    ['name' => 'Alex <script>x</script>', 'organization' => 'Mein & Verein']
);
if (str_contains($rendered, '<script>') || !str_contains($rendered, 'src="https://img.example.org/logo.png"') || !str_contains($rendered, 'Mein &amp; Verein')) {
    throw new \RuntimeException('Rendering regression: ' . $rendered);
}

echo "HTML image smoke tests passed\n";
