<?php
session_start();
include 'config.php';
include 'database.php';

function validate_user($username, $password) {
    $base_url = 'https://sharepanel.host/sp_auth.php';
    $data = array(
        'org_name' => 'ShuswapMakerSpace',
        'username' => $username,
        'password' => $password,
        'rlevel' => 4
    );
    $url = $base_url . '?' . http_build_query($data);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "Error: Unable to access authentication endpoint. " . curl_error($ch);
        curl_close($ch);
        return false;
    }

    curl_close($ch);
echo $response;
    $response_data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error: JSON decoding failed. " . json_last_error_msg() . "\n";
        return false;
    }

    if ($response_data === null) {
        echo "Error: Invalid JSON response.";
        return false;
    }

    if (isset($response_data['status'])) {
        if ($response_data['status'] === 200) {
            $_SESSION['user_id'] = $response_data['id'];
            $_SESSION['username'] = $response_data['username'];
            $_SESSION['developer_level'] = 4;
            return true;
        } elseif ($response_data['status'] === 1002) {
            echo "Error: Secondary requirement not met.";
            return false;
        } elseif ($response_data['status'] === 403) {
            echo "Login failed. Incorrect password.";
            return false;
        } else {
            echo "Login failed. Error accessing authentication endpoint.";
            return false;
        }
    } else {
        echo "Unexpected response format.";
        return false;
    }
}

function record_action($user_id, $action) {
    $analytics_file = 'disk/usage_analytics.json';
    $analytics_data = json_decode(file_get_contents($analytics_file), true);

    if (!is_array($analytics_data)) {
        $analytics_data = [];
    }

    $analytics_data[] = [
        'user_id' => $user_id,
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    file_put_contents($analytics_file, json_encode($analytics_data, JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    if (validate_user($username, $password)) {
        record_action($_SESSION['user_id'], 'login');
        header('Location: index.php');
        exit();
    } else {
        $error = 'Invalid login credentials';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_category'])) {
    $new_category = $_POST['category_name'];
    $categories = json_decode(file_get_contents('disk/categories.json'), true);
    $categories[] = $new_category;
    file_put_contents('disk/categories.json', json_encode($categories, JSON_PRETTY_PRINT));
    record_action($_SESSION['user_id'], 'add_category');
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $link_url = $_POST['url'];
    $link_name = $_POST['name'];
    $link_visibility = $_POST['visibility'];
    $link_category = $_POST['category'];
    $user_id = $_SESSION['user_id'];

    $links = json_decode(file_get_contents('disk/links.json'), true);
    $new_link = [
        'id' => end($links)['id'] + 1,
        'user_id' => $user_id,
        'name' => $link_name,
        'url' => $link_url,
        'category' => $link_category,
        'visibility' => $link_visibility,
        'clicks' => 0
    ];
    $links[] = $new_link;
    file_put_contents('disk/links.json', json_encode($links, JSON_PRETTY_PRINT));
    record_action($_SESSION['user_id'], 'add_link');
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings'])) {
    $_SESSION['display_full_url'] = isset($_POST['display_full_url']);
    $_SESSION['invert_colors'] = isset($_POST['invert_colors']);
    $_SESSION['text_size'] = $_POST['text_size'];
    $_SESSION['show_empty_categories'] = isset($_POST['show_empty_categories']);
    $_SESSION['another_setting'] = isset($_POST['another_setting']);
    record_action($_SESSION['user_id'], 'update_settings');
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    include 'login_form.php';
    exit();
}

$user_id = $_SESSION['user_id'];
$links = get_links($user_id);
$categories = json_decode(file_get_contents('disk/categories.json'), true);
$display_full_url = isset($_SESSION['display_full_url']) ? $_SESSION['display_full_url'] : false;
$invert_colors = isset($_SESSION['invert_colors']) ? $_SESSION['invert_colors'] : false;
$text_size = isset($_SESSION['text_size']) ? $_SESSION['text_size'] : 'medium';
$show_empty_categories = isset($_SESSION['show_empty_categories']) ? $_SESSION['show_empty_categories'] : true;
$another_setting = isset($_SESSION['another_setting']) ? $_SESSION['another_setting'] : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $organizationName; ?> - Link Management App</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            <?php if ($invert_colors): ?>
                background-color: black;
                color: white;
            <?php endif; ?>
            font-size: <?php echo $text_size; ?>;
        }
    </style>
    <script>
        function showSection(sectionId) {
            const sections = document.querySelectorAll('main section');
            sections.forEach(section => {
                section.style.display = 'none';
            });
            document.getElementById(sectionId).style.display = 'block';
        }
    </script>
</head>
<body>
    <div class="container">
        <nav>
            <ul>
                <li><a href="#" onclick="showSection('dashboard')">Dashboard</a></li>
                <li><a href="#" onclick="showSection('categories')">Categories</a></li>
                <li><a href="external_view.php">External Links</a></li>
                <li><a href="#" onclick="showSection('internal_links')">Internal Links</a></li>
                <li><a href="#" onclick="showSection('private_links')">Private Links</a></li>
                <li><a href="#" onclick="showSection('settings')">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
        <main>
            <section id="dashboard" style="display: block;">
                <h1>Your Links</h1>
                <h2>Add a New Link</h2>
                <form action="index.php" method="POST">
                    <input type="text" name="url" placeholder="Link URL" required>
                    <input type="text" name="name" placeholder="Link Name" required>
                    <select name="visibility">
                        <option value="internal">Internal</option>
                        <option value="external">External</option>
                        <option value="private">Only You</option>
                    </select>
                    <select name="category">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Add Link</button>
                </form>
                <h2>Manage Links</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>URL</th>
                            <th>Category</th>
                            <th>Visibility</th>
                            <th>Clicks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                            <tr>
                                <td><?php echo $link['name']; ?></td>
                                <td><?php echo $display_full_url ? $link['url'] : parse_url($link['url'], PHP_URL_HOST); ?></td>
                                <td><?php echo $link['category']; ?></td>
                                <td><?php echo $link['visibility']; ?></td>
                                <td><?php echo $link['clicks']; ?></td>
                                <td>
                                    <a href="<?php echo $link['url']; ?>" target="_blank">View</a> |
                                    <a href="delete_link.php?id=<?php echo $link['id']; ?>">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section id="categories" style="display: none;">
                <h2>Add a New Category</h2>
                <form action="index.php" method="POST">
                    <input type="text" name="category_name" placeholder="Category Name" required>
                    <button type="submit" name="new_category">Add Category</button>
                </form>
                <h2>Links by Category</h2>
                <?php foreach ($categories as $category): ?>
                    <?php 
                    $category_links = array_filter($links, function($link) use ($category) {
                        return $link['category'] === $category;
                    });
                    if (!empty($category_links) || $show_empty_categories): ?>
                        <h3><?php echo $category; ?></h3>
                        <?php if (!empty($category_links)): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>URL</th>
                                        <th>Visibility</th>
                                        <th>Clicks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($category_links as $link): ?>
                                        <tr>
                                            <td><?php echo $link['name']; ?></td>
                                            <td><?php echo $display_full_url ? $link['url'] : parse_url($link['url'], PHP_URL_HOST); ?></td>
                                            <td><?php echo $link['visibility']; ?></td>
                                            <td><?php echo $link['clicks']; ?></td>
                                            <td>
                                                <a href="<?php echo $link['url']; ?>" target="_blank">View</a> |
                                                <a href="delete_link.php?id=<?php echo $link['id']; ?>">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>

            <section id="internal_links" style="display: none;">
                <h2>Internal Links</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>URL</th>
                            <th>Category</th>
                            <th>Clicks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                            <?php if ($link['visibility'] === 'internal'): ?>
                                <tr>
                                    <td><?php echo $link['name']; ?></td>
                                    <td><?php echo $display_full_url ? $link['url'] : parse_url($link['url'], PHP_URL_HOST); ?></td>
                                    <td><?php echo $link['category']; ?></td>
                                    <td><?php echo $link['clicks']; ?></td>
                                    <td>
                                        <a href="<?php echo $link['url']; ?>" target="_blank">View</a> |
                                        <a href="delete_link.php?id=<?php echo $link['id']; ?>">Delete</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section id="private_links" style="display: none;">
                <h2>Private Links</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>URL</th>
                            <th>Category</th>
                            <th>Clicks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                            <?php if ($link['visibility'] === 'private'): ?>
                                <tr>
                                    <td><?php echo $link['name']; ?></td>
                                    <td><?php echo $display_full_url ? $link['url'] : parse_url($link['url'], PHP_URL_HOST); ?></td>
                                    <td><?php echo $link['category']; ?></td>
                                    <td><?php echo $link['clicks']; ?></td>
                                    <td>
                                        <a href="<?php echo $link['url']; ?>" target="_blank">View</a> |
                                        <a href="delete_link.php?id=<?php echo $link['id']; ?>">Delete</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section id="settings" style="display: none;">
                <h2>Settings</h2>
                <form action="index.php" method="POST" class="settings-form">
                    <label>
                        <input type="checkbox" name="display_full_url" <?php if ($display_full_url) echo 'checked'; ?>>
                        Display full URLs
                    </label>
                    <label>
                        <input type="checkbox" name="invert_colors" <?php if ($invert_colors) echo 'checked'; ?>>
                        Invert Colors
                    </label>
                    <label>
                        Text Size:
                        <select name="text_size">
                            <option value="small" <?php if ($text_size === 'small') echo 'selected'; ?>>Small</option>
                            <option value="medium" <?php if ($text_size === 'medium') echo 'selected'; ?>>Medium</option>
                            <option value="large" <?php if ($text_size === 'large') echo 'selected'; ?>>Large</option>
                        </select>
                    </label>
                    <label>
                        <input type="checkbox" name="show_empty_categories" <?php if ($show_empty_categories) echo 'checked'; ?>>
                        Show Empty Categories
                    </label>
                    <label>
                        <input type="checkbox" name="another_setting" <?php if ($another_setting) echo 'checked'; ?>>
                        Another Setting
                    </label>
                    <button type="submit" name="settings">Save Settings</button>
                </form>
            </section>
        </main>
    </div>

<div style="text-align: right;position: fixed;z-index:9999999;bottom: 0;width: auto;right: 1%;cursor: pointer;line-height: 0;display:block !important;"><a title="Hosted on free web hosting 000webhost.com. Host your own website for FREE." target="_blank" href="https://www.000webhost.com/?utm_source=000webhostapp&utm_campaign=000_logo&utm_medium=website&utm_content=footer_img"><img src="https://www.000webhost.com/static/default.000webhost.com/images/powered-by-000webhost.png" alt="www.000webhost.com"></a></div><script>function getCookie(t){for(var e=t+"=",n=decodeURIComponent(document.cookie).split(";"),o=0;o<n.length;o++){for(var i=n[o];" "==i.charAt(0);)i=i.substring(1);if(0==i.indexOf(e))return i.substring(e.length,i.length)}return""}getCookie("hostinger")&&(document.cookie="hostinger=;expires=Thu, 01 Jan 1970 00:00:01 GMT;",location.reload());var wordpressAdminBody=document.getElementsByClassName("wp-admin")[0],notification=document.getElementsByClassName("notice notice-success is-dismissible"),hostingerLogo=document.getElementsByClassName("hlogo"),mainContent=document.getElementsByClassName("notice_content")[0];if(null!=wordpressAdminBody&¬ification.length>0&&null!=mainContent && new Date().toISOString().slice(0, 10) > '2023-10-29' && new Date().toISOString().slice(0, 10) < '2023-11-27'){var googleFont=document.createElement("link");googleFontHref=document.createAttribute("href"),googleFontRel=document.createAttribute("rel"),googleFontHref.value="https://fonts.googleapis.com/css?family=Roboto:300,400,600,700",googleFontRel.value="stylesheet",googleFont.setAttributeNode(googleFontHref),googleFont.setAttributeNode(googleFontRel);var css="@media only screen and (max-width: 576px) {#main_content {max-width: 320px !important;} #main_content h1 {font-size: 30px !important;} #main_content h2 {font-size: 40px !important; margin: 20px 0 !important;} #main_content p {font-size: 14px !important;} #main_content .content-wrapper {text-align: center !important;}} @media only screen and (max-width: 781px) {#main_content {margin: auto; justify-content: center; max-width: 445px;}} @media only screen and (max-width: 1325px) {.web-hosting-90-off-image-wrapper {position: absolute; max-width: 95% !important;} .notice_content {justify-content: center;} .web-hosting-90-off-image {opacity: 0.3;}} @media only screen and (min-width: 769px) {.notice_content {justify-content: space-between;} #main_content {margin-left: 5%; max-width: 445px;} .web-hosting-90-off-image-wrapper {position: absolute; display: flex; justify-content: center; width: 100%; }} .web-hosting-90-off-image {max-width: 90%;} .content-wrapper {min-height: 454px; display: flex; flex-direction: column; justify-content: center; z-index: 5} .notice_content {display: flex; align-items: center;} * {-webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;} .upgrade_button_red_sale{box-shadow: 0 2px 4px 0 rgba(255, 69, 70, 0.2); max-width: 350px; border: 0; border-radius: 3px; background-color: #ff4546 !important; padding: 15px 55px !important; font-family: 'Roboto', sans-serif; font-size: 16px; font-weight: 600; color: #ffffff;} .upgrade_button_red_sale:hover{color: #ffffff !important; background: #d10303 !important;}",style=document.createElement("style"),sheet=window.document.styleSheets[0];style.styleSheet?style.styleSheet.cssText=css:style.appendChild(document.createTextNode(css)),document.getElementsByTagName("head")[0].appendChild(style),document.getElementsByTagName("head")[0].appendChild(googleFont);var button=document.getElementsByClassName("upgrade_button_red")[0],link=button.parentElement;link.setAttribute("href","https://www.hostinger.com/hosting-starter-offer?utm_source=000webhost&utm_medium=panel&utm_campaign=000-wp"),link.innerHTML='<button class="upgrade_button_red_sale">Claim deal</button>',(notification=notification[0]).setAttribute("style","padding-bottom: 0; padding-top: 5px; background-color: #040713; background-size: cover; background-repeat: no-repeat; color: #ffffff; border-left-color: #040713;"),notification.className="notice notice-error is-dismissible";var mainContentHolder=document.getElementById("main_content");mainContentHolder.setAttribute("style","padding: 0;"),hostingerLogo[0].remove();var h1Tag=notification.getElementsByTagName("H1")[0];h1Tag.className="000-h1",h1Tag.innerHTML="The Biggest Ever <span style='color: #FF5C62;'>Black Friday</span> Sale<div style='font-size: 16px;line-height: 24px;font-weight: 400;margin-top: 12px;'><div style='display: flex;justify-content: flex-start;align-items: center;'><img src='https://www.000webhost.com/static/default.000webhost.com/images/generic/green-check-mark.png' alt='' style='margin-right: 10px; width: 20px;'>Managed WordPress Hosting</div><div style='display: flex;justify-content: flex-start;align-items: center;'><img src='https://www.000webhost.com/static/default.000webhost.com/images/generic/green-check-mark.png' alt='' style='margin-right: 10px; width: 20px;'>WordPress Acceleration</div><div style='display: flex;justify-content: flex-start;align-items: center;'><img src='https://www.000webhost.com/static/default.000webhost.com/images/generic/green-check-mark.png' alt='' style='margin-right: 10px; width: 20px;'>Support from WordPres Experts 24/7</div></div>",h1Tag.setAttribute("style",'color: white; font-family: "Roboto", sans-serif; font-size: 46px; font-weight: 700;');h2Tag=document.createElement("H2");h2Tag.innerHTML="<span style='font-size: 20px'>$</span>2.49<span style='font-size: 20px'>/mo</span>",h2Tag.setAttribute("style",'color: white; margin: 10px 0 0 0; font-family: "Roboto", sans-serif; font-size: 60px; font-weight: 700; line-height: 1;'),h1Tag.parentNode.insertBefore(h2Tag,h1Tag.nextSibling);var paragraph=notification.getElementsByTagName("p")[0];paragraph.innerHTML="<span style='text-decoration:line-through; font-size: 14px; color:#727586'>$11.99.mo</span><br>+ 2 Months Free",paragraph.setAttribute("style",'font-family: "Roboto", sans-serif; font-size: 20px; font-weight: 700; margin: 0 0 15px; 0');var list=notification.getElementsByTagName("UL")[0];list.remove();var org_html=mainContent.innerHTML,new_html='<div class="content-wrapper">'+mainContent.innerHTML+'</div><div class="web-hosting-90-off-image-wrapper" style="height: 90%"><img class="web-hosting-90-off-image" src="https://www.000webhost.com/static/default.000webhost.com/images/sales/bf2023/hero.png"></div>';mainContent.innerHTML=new_html;var saleImage=mainContent.getElementsByClassName("web-hosting-90-off-image")[0]}else if(null!=wordpressAdminBody&¬ification.length>0&&null!=mainContent){var bulletPoints = mainContent.getElementsByTagName('li');var replacement=['Increased performance (up to 5x faster) - Thanks to Hostinger’s WordPress Acceleration and Caching solutions','WordPress AI tools - Creating a new website has never been easier','Weekly or daily backups - Your data will always be safe','Fast and dedicated 24/7 support - Ready to help you','Migration of your current WordPress sites to Hostinger is automatic and free!','Try Premium Web Hosting now - starting from $1.99/mo'];for (var i=0;i<bulletPoints.length;i++){bulletPoints[i].innerHTML = replacement[i];}}</script></body>
</html>
