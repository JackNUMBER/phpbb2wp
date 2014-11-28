<?php
/*
 * PHPBB2WP
 * Migrate phpBB forum to WP blog
 * Version 0.2
 * By Colin Braly (@4wk_) & Antoine Cadoret (@JackNUMBER)
 *
 * HOW TO:
 * 1. Don't be a hero and backup your database ;)
 * 2. Install Wordpress on your phpBB server.
 * 3. Download and edit the file with your db login.
 * 4. Put the file into the root folder.
 * 5. Run it.
 *
 */

$mysql_host = 'localhost';  // Edit with your db adress
$mysql_db   = 'phpbb2wp';   // Edit with your db name
$mysql_user = 'root';       // Edit with your db username
$mysql_pwd  = '';           // Edit with your db user password

$wp_prefix       = 'wp_';    //Edit with your WP table name prefix
$phpbb_prefix= 'phpbb_';     //Edit with your phpBB table name prefix

?>

<h1>Migrate phpBB2 to Wordpress</h1>
<style>
    .alert{background:#ff9;}
    .warning{background:#f99;}
    .success{background:#9f9;}
</style>

<?php

/* Database connection */
if (($db_connect = @mysql_connect($mysql_host, $mysql_user, $mysql_pwd)) && (mysql_select_db($mysql_db))) {
    echo '<p class="success">Database connection successful</p>';
} else {
    die('<p class="msg warning">Database connection failed: ' . mysql_error() . '</p>');
}
mysql_query("SET NAMES 'utf8'", $db_connect);


/* ==================== Global Functions ==================== */

function testTableExists($table, $db) {
    $query = 'SHOW TABLES FROM ' . $db . ' LIKE \'' . $table . '\'';
    $exec = mysql_query($query);
    return mysql_num_rows($exec);
}

function testTables() {
    global $mysql_db, $phpbb_prefix, $wp_prefix;

    if (!testTableExists($phpbb_prefix . 'posts', $mysql_db)) {
        echo '<p class="warning">The phpBB database seems not available (' . $phpbb_prefix . 'posts)';
        exit;
    }

    if (!testTableExists($wp_prefix . 'posts', $mysql_db)) {
        echo '<p class="warning">The Wordpress database seems not available (' . $wp_prefix . 'posts)';
        exit;
    }
}

function createCategoriesCrossReference($type, $phpbb_id, $label) {

    global $categories_cross_reference;

    // wp_set_post_terms($label);







    /*

    @TODO créer les catégories dans le système de Wordpress
            wp_create_category($label) ne marche pas
            http://codex.wordpress.org/Category:Actions
            http://codex.wordpress.org/Plugin_API/Action_Reference


    tester wp_newCategory()

    */






    $categories_cross_reference[] = array(
        'type' => $type, // 1 = phpBB category, 2 = phpBB forum
        'phpbb_id' => $phpbb_id,
        'label' => $label,
        'wp_id' => count($categories_cross_reference) + 1
    );
    var_dump($categories_cross_reference);
}

/* BBcode URL tag standardization */
function cleanURL($str) {
    while (preg_match('#\[url\]http://(.*?)\[/url\]#', $str, $matches)) {
        $tag = str_replace('[url]','[url=http://' . $matches[1] . ']', $matches[0]);
        $str = str_replace($matches[0], $tag, $str);
    }
    while (preg_match('#\[url\]www.(.*?)\[/url\]#', $str, $matches)) {
        $tag = str_replace('[url]','[url=http://www.' . $matches[1] . ']', $matches[0]);
        $str = str_replace($matches[0], $tag, $str);
    }
    return $str;
}

/* List BBcode */
function bbcode2Html($str, $uid = '') {
    $bbcode = array(
        "[list$uid]", "[*$uid]", "[/list$uid]",
        "[list=1$uid]", "[/list:o$uid]",
        "[img$uid]", "[/img$uid]",
        "[b$uid]", "[/b$uid]",
        "[u$uid]", "[/u$uid]",
        "[i$uid]", "[/i$uid]",
        '[color=', "[/color$uid]",
        "[size=\"", "[/size$uid]",
        "[size=", "[/size$uid]",
        '[url=', "[/url]",
        "[mail=\"", "[/mail]",
        "[code]", "[/code]",
        "[code:1$uid]", "[/code:1$uid]",
        "[quote]", "[/quote]",
    );
    $htmlcode = array(
        "<ul>", "<li>", "</ul>",
        "<ul>", "</ul>",
        "<img src=\"", "\">",
        "<strong>", "</strong>",
        "<u>", "</u>",
        "<em>", "</em>",
        "<span style=\"color:", "</span>",
        "<span style=\"font-size:", "</span>",
        "<span style=\"font-size:", "</span>",
        '<a href="', "</a>",
        "<a href=\"mailto:", "</a>",
        "<code>", "</code>",
        "<code>", "</code>",
        "<table width=100% bgcolor=lightgray><tr><td bgcolor=white>", "</td></tr></table>"
    );
    $newstr = str_replace('<', '&lt;', $str);
    $newstr = str_replace('>', '&gt;', $newstr);
    $newstr = str_replace($bbcode, $htmlcode, $newstr);
    $newstr = nl2br($newstr);
    return $newstr;
}

/* Convert BBcode to HTML */
function bbcode2Html2($str, $uid) {
    $bbcode = array(
        '"' . $uid . ']',
        '"]',
        $uid . ']',
        ']'
    );
    $htmlcode = array(
        '">',
        '">',
        '">',
        '">'
    );
    $newstr = str_replace($bbcode, $htmlcode, $str);
    return $newstr;
}

/* List smilies */
function killSmileys($str) {
    $smileys = array (
        /* Don't kill them
        ":D",
        ":)",
        ":P",
        ":-|",
        ":(",*/
        ":shock:",
        ":?",
        "8-)",
        ":lol:",
        ":oops:",
        ":cry:",
        ":evil:",
        ":twisted:",
        ":roll:",
        ":wink:",
        ":!:",
        ":?:",
        ":idea:",
        ":arrow:"
    );
    $newstr = str_replace($smileys, '', $str);
    return $newstr;
}

function cleanBracket($str) {
    while (preg_match('#\[(.*?)">#', $str, $matches)) {
        $tag = str_replace('">',']', $matches[0]);
        $str = str_replace($matches[0], $tag, $str);
    }
    return $str;
}

/* ================================================== */

/* ==================== Process ==================== */

/* Tests */
testTables();

define('WP_USE_THEMES', false);
if (file_exists('./wp-blog-header.php')) {
    require('./wp-blog-header.php');
} else {
    echo '<p class="msg warning">Wordpress seems not installed</p>';
    exit;
}

/* Datebase reading */
$sql = ' SELECT
    phpbb_posts.post_id,
    phpbb_posts.topic_id,
    phpbb_posts.post_time,
    phpbb_posts.post_edit_time,
    phpbb_posts.forum_id,
    phpbb_posts_text.bbcode_uid,
    phpbb_posts_text.post_subject,
    phpbb_posts_text.post_text
    FROM
    phpbb_posts,
    phpbb_posts_text
    WHERE
    phpbb_posts.post_id = phpbb_posts_text.post_id
    AND phpbb_posts.poster_id = 2 /* Specify an user */
    ORDER BY phpbb_posts.post_time ASC
    LIMIT 5
';
$result_posts = mysql_query($sql);

$sql_cat = 'SELECT
    *
    FROM
    phpbb_categories
    ORDER BY phpbb_categories.cat_id ASC
';
$result_posts_cat = mysql_query($sql_cat);

$sql_forum = 'SELECT
    *
    FROM
    phpbb_forums
    ORDER BY phpbb_forums.forum_id ASC
';
$result_posts_forum = mysql_query($sql_forum);

if ($result_posts) {
    echo '<p class="alert">Posts reading...</p>';
} else {
    $message = '<p class="msg warning">Invalid Request: ' . mysql_error() . "</p>";
    die($message);
}

$categories_phpbb = array();
$forums_phpbb = array();
$categories_cross_reference = array();
$posts_ids_phpbb = array();
$posts_wp = array();
$count_posts = 0;
$count_converted = 0;


/* Create categories */
while ($category_phpbb = mysql_fetch_assoc($result_posts_cat)) {
    $categories_phpbb[] = array(
        'cat_id' => $category_phpbb['cat_id'],
        'cat_title' => $category_phpbb['cat_title'],
    );

    createCategoriesCrossReference(1, $category_phpbb['cat_id'], $category_phpbb['cat_id']);
}

while ($forum_phpbb = mysql_fetch_assoc($result_posts_forum)) {
    $forums_phpbb[] = array(
        'forum_id' => $forum_phpbb['forum_id'],
        'cat_id' => $forum_phpbb['cat_id'],
        'forum_name' => $forum_phpbb['forum_name'],
    );

    createCategoriesCrossReference(2, $forum_phpbb['cat_id'], $forum_phpbb['forum_name']);
}

// var_dump($categories_cross_reference);

/* Posts conversion */
while ($post_phpbb = mysql_fetch_assoc($result_posts)) {

    // var_dump($post_phpbb);


    if (in_array($post_phpbb['topic_id'], $posts_ids_phpbb)) {
        continue;
    }

    $posts_ids_phpbb[] = $post_phpbb['topic_id'];

    // Dates - Is GMT convertion needed?
    $posts_wp[$count_posts]['post_date'] = date("Y-m-d H:i:s", $post_phpbb['post_time']); // wp_post.post_date
    $posts_wp[$count_posts]['post_date_gmt'] = date("Y-m-d H:i:s", $post_phpbb['post_time_gmt'] - (60*60)); // wp_post.post_date_gmt
    $posts_wp[$count_posts]['wp_post.post_modified'] = date("Y-m-d H:i:s", $post_phpbb['post_edit_time']); // wp_post.post_modified
    $posts_wp[$count_posts]['wp_post.post_modified_gmt'] = date("Y-m-d H:i:s", $post_phpbb['post_edit_time'] - (60*60)); // wp_post.post_modified_gmt

    // Title
    $posts_wp[$count_posts]['post_title'] = $post_phpbb['post_subject']; // wp_posts.post_title

    // Content
    $post_phpbb['post_text'] = cleanURL($post_phpbb['post_text']);
    $posts_wp[$count_posts]['post_content'] = bbcode2Html($post_phpbb['post_text'], ':' . $post_phpbb['bbcode_uid']); // post_content
    $posts_wp[$count_posts]['post_content'] = bbcode2Html2($posts_wp[$count_posts]['post_content'], ':' . $post_phpbb['bbcode_uid']); // wp_posts.post_content
    $posts_wp[$count_posts]['post_content'] = cleanBracket($posts_wp[$count_posts]['post_content']); // wp_posts.post_content
    $posts_wp[$count_posts]['post_content'] = killSmileys($posts_wp[$count_posts]['post_content']); // wp_posts.post_content

    // Category
    // $posts_wp[$count_posts]['wp_term_relationships.object_id'] = $post_phpbb['forum_id']; // wp_term_relationships.object_id
    wp_set_post_terms($label);







    /*

        @TODO créer les catégories
        @TODO récupérer l'id d'après 'cat_id' ou 'forum_id'
                $post_phpbb['cat_id'];
                $post_phpbb['forum_id'];


    */







    $posts_wp[$count_posts]['post_category'] = array(2); // Categories ids

    // Specifics Wordpress Meta
    $posts_wp[$count_posts]['comment_status'] = 'open';
    $posts_wp[$count_posts]['ping_status'] = 'open';
    $posts_wp[$count_posts]['post_author'] = 2; // Specify an user
    $posts_wp[$count_posts]['post_status'] = 'publish';
    $posts_wp[$count_posts]['post_type'] = 'post';

    /* Create Wordpress posts */
/*    if (wp_insert_post($posts_wp[$count_posts])) {
        // echo '<p><i>' .  $posts_wp[$count_posts]['post_title'] . '</i> count_converted.</p>';
        $count_converted++;
    } else {
        echo '<p class="warning">Error on writing the WP database</p>';
    }*/
    // var_dump($posts_wp[$count_posts]);
    $count_posts++;
}


echo '<p class="alert">';
echo $count_posts . ' posts found.<br>';
echo $count_converted . ' posts converted.';
echo '</p>';

/* ================================================== */

mysql_close($db_connect);
?>
