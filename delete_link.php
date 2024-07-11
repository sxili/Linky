<?php
session_start();
include 'config.php';
include 'database.php';

if (isset($_GET['id'])) {
    $link_id = $_GET['id'];
    $links = json_decode(file_get_contents('disk/links.json'), true);
    $updated_links = array_filter($links, function($link) use ($link_id) {
        return $link['id'] != $link_id;
    });
    file_put_contents('disk/links.json', json_encode($updated_links, JSON_PRETTY_PRINT));
}

header('Location: index.php');
exit();
?>
