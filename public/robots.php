<?php
require_once __DIR__ . '/../src/bootstrap.php';

header_remove('X-Robots-Tag');
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
foreach (['index.php', 'meeting.php', 'timer.php', 'report.php', 'cards.php', 'login.php', 'register.php', 'logout.php', 'api/'] as $path) {
    echo "Disallow: /$path\n";
}
echo "\nSitemap: " . site_base_url() . "/sitemap.xml\n";
