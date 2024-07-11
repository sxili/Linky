<?php
include 'config.php';
include 'database.php';

$link_id = $_GET['id'];

$links = json_decode(file_get_contents('disk/links.json'), true);
foreach ($links as &$link) {
    if ($link['id'] == $link_id) {
        $link['clicks']++;
        $url = $link['url'];
        break;
    }
}
save_links($links);

header("Location: $url");
?>
