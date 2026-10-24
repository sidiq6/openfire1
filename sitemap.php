<?php
require __DIR__.'/app/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$url=absolute_url();
echo '<?xml version="1.0" encoding="UTF-8"?>';
?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc><?=e($url)?></loc><changefreq>weekly</changefreq><priority>1.0</priority></url></urlset>
