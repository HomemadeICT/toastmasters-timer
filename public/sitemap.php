<?php
require_once __DIR__ . '/../src/bootstrap.php';

header_remove('X-Robots-Tag');
header('Content-Type: application/xml; charset=utf-8');

$base = site_base_url();
$urls = [['blog.php', null], ['about.php', null], ['contact.php', null]];
foreach (blog_posts() as $slug => $post) {
    $urls[] = ['blog.php?post=' . urlencode($slug), $post['date']];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$path, $lastmod]) {
    echo '  <url><loc>' . h($base . '/' . $path) . '</loc>' . ($lastmod ? '<lastmod>' . h($lastmod) . '</lastmod>' : '') . "</url>\n";
}
echo "</urlset>\n";
