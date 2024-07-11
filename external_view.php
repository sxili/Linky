<?php
include 'config.php';
include 'database.php';

$links = json_decode(file_get_contents('disk/links.json'), true);
$external_links = array_filter($links, function($link) {
    return $link['visibility'] == 'external';
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>External Links - <?php echo $organizationName; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <main>
            <h1>External Links</h1>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>URL</th>
                        <th>Category</th>
                        <th>Clicks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($external_links as $link): ?>
                        <tr>
                            <td><?php echo $link['name']; ?></td>
                            <td><a href="track_click.php?id=<?php echo $link['id']; ?>" target="_blank"><?php echo $link['url']; ?></a></td>
                            <td><?php echo $link['category']; ?></td>
                            <td><?php echo $link['clicks']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>
</body>
</html>
