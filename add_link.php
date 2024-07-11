<?php
session_start();
include 'config.php';
include 'database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$url = $_POST['url'];
$name = $_POST['name'];
$visibility = $_POST['visibility'];
$category = $_POST['category'];

$links = json_decode(file_get_contents('links.json'), true);
$link_id = count($links) + 1;

$new_link = [
    'id' => $link_id,
    'user_id' => $user_id,
    'url' => $url,
    'name' => $name,
    'visibility' => $visibility,
    'category' => $category,
    'clicks' => 0
];

$links[] = $new_link;
save_links($links);

header('Location: index.php');
?>
