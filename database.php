<?php
function get_links($user_id) {
    $links = json_decode(file_get_contents('disk/links.json'), true);
    return array_filter($links, function($link) use ($user_id) {
        return $link['user_id'] == $user_id || $link['visibility'] != 'private';
    });
}

function save_links($links) {
    file_put_contents('disk/links.json', json_encode($links, JSON_PRETTY_PRINT));
}




?>
