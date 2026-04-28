<?php
/* AXENDELL — Dynamic XML Sitemap (Faz 3.A) */
header('Content-Type: application/xml; charset=utf-8');
require_once __DIR__ . '/db.php';

$base = 'https://www.axendell.com';
$today = date('Y-m-d');

$urls = [];

// Anasayfa
$urls[] = ['loc' => $base . '/', 'lastmod' => $today, 'changefreq' => 'daily', 'priority' => '1.0'];

// PDP — tüm hesaplar
$ids = [];
$stmt = $conn->prepare("SELECT id FROM accounts ORDER BY id DESC");
if ($stmt) {
    $stmt->execute();
    $stmt->store_result();
    $aid = 0;
    $stmt->bind_result($aid);
    while ($stmt->fetch()) { $ids[] = (int)$aid; }
    $stmt->close();
}
foreach ($ids as $aid) {
    $urls[] = [
        'loc' => $base . '/ilan.php?id=' . $aid,
        'lastmod' => $today,
        'changefreq' => 'weekly',
        'priority' => '0.8',
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
    echo "    <lastmod>" . $u['lastmod'] . "</lastmod>\n";
    echo "    <changefreq>" . $u['changefreq'] . "</changefreq>\n";
    echo "    <priority>" . $u['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
