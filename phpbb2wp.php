<?php
/*
 * PHPBB2WP
 * Migrate phpBB forum to WP blog
 * Version 0.3.1
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

/* Settings - Edit theses parameters */

$mysql_host = 'localhost'; // your db adress
$mysql_db = 'phpbb2wp';    // your db name
$mysql_user = 'root';      // your db username
$mysql_pwd = '';           // your db user password

$wp_prefix = 'wp_';        // your WP table name prefix
$phpbb_prefix = 'phpbb_';  // your phpBB table name prefix

$wp_user_id = 1; // your WP user id
$phpBB_user_id = 2; // your phpBB user id

/* end:Settings */

$wp_test_table_name = $wp_prefix . 'posts';
$phpbb_test_table_name = $phpbb_prefix . 'posts';

$wp_required_files = array(
    dirname( __FILE__ ) . '/wp-blog-header.php',
    dirname( __FILE__ ) . '/wp-admin/includes/taxonomy.php'
);

?>
<style>
    .notice{background:#ff9;}
    .warning{background:#f99;}
    .success{background:#9f9;}
</style>

<h1>Migrate phpBB2 to Wordpress</h1>
<?php

/* Database connection */
if (($db_connect = @mysql_connect($mysql_host, $mysql_user, $mysql_pwd)) && (mysql_select_db($mysql_db))) {
    echo '<p class="success">Database connection successful</p>';
} else {
    echo '<p class="msg warning">Database connection failed: ' . mysql_error() . '</p>';
    exit;
}
mysql_query("SET NAMES 'utf8'", $db_connect);


/* ==================== Global Functions ==================== */

/**
 * Test if a table exists in the database
 *
 * @param string $table Targeted table
 * @param string $db Targeted database
 *
 * @return bool True if table exists
 */
function testTableExists($table, $db) {
    $query = 'SHOW TABLES FROM ' . $db . ' LIKE \'' . $table . '\'';
    $exec = mysql_query($query);
    return mysql_num_rows($exec);
}

/**
 * Create an associative array between phpBB categories/forum and new Wordpress categories
 *
 * @param int $phpbb_id The old phpBB id
 * @param string $label The category title
 * @param int $parent_id The Wordpress parent id
 */
function createCategoriesCrossReference($phpbb_id, $label, $parent_id = null) {
    global $categories_cross_reference;

    if ($parent_id) {
        // phpbb forum > wordpress sub-category
        $categories_cross_reference['sub_cat'][$phpbb_id] = array(
            'label' => $label,
            'wp_id' => wp_create_category($label, $categories_cross_reference['main_cat'][$parent_id]['wp_id']),
            'wp_parent_id' => $categories_cross_reference[$parent_id]['wp_id'],
            'phpbb_cat_id' => $parent_id
        );
    } else {
        // phpbb category > wordpress main category
        $categories_cross_reference['main_cat'][$phpbb_id] = array(
            'label' => $label,
            'wp_id' => wp_create_category($label)
        );
    }
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
if (!testTableExists($wp_test_table_name, $mysql_db)) {
    exit('p class="warning">The Wordpress database seems not available (<code>' . $wp_test_table_name . '</code> not found)');
}

if (!testTableExists($phpbb_test_table_name, $mysql_db)) {
    exit('<p class="warning">The phpBB database seems not available (<code>' . $phpbb_test_table_name . '</code> not found)');
}

// define('WP_USE_THEMES', false);
foreach ($wp_required_files as $file) {
    if (file_exists($file)) {
        require($file);
    } else {
        exit('<p class="msg warning">Wordpress seems not installed (<code>' . $file . '</code> not found)</p>');
    }
}

/* Database reading */
$sql = 'SELECT
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
    AND phpbb_posts.poster_id = ' . $phpBB_user_id . '
    ORDER BY phpbb_posts.post_time ASC
';
$result_posts = mysql_query($sql);

$sql_cat = 'SELECT
    *
    FROM
    phpbb_categories
    ORDER BY phpbb_categories.cat_id ASC
';
$result_cat = mysql_query($sql_cat);

$sql_forum = 'SELECT
    *
    FROM
    phpbb_forums
    ORDER BY phpbb_forums.forum_id ASC
';
$result_forum = mysql_query($sql_forum);

if ($result_posts) {
    echo '<p class="notice">Posts reading...</p>';
} else {
    exit('<p class="msg warning">Invalid Request: ' . mysql_error() . "</p>");
}

if ($result_forum) {
    echo '<p class="notice">Forums reading...</p>';
} else {
    exit('<p class="msg warning">Invalid Request: ' . mysql_error() . "</p>");
}

$categories_cross_reference = array();
$posts_ids_phpbb = array();
$posts_wp = array();
$count_posts = 0;
$count_converted = 0;
$count_categories = 0;
$count_categories_created = 0;


/* Create categories */
while ($category_phpbb = mysql_fetch_assoc($result_cat)) {
    createCategoriesCrossReference($category_phpbb['cat_id'], $category_phpbb['cat_title']);
}

while ($forum_phpbb = mysql_fetch_assoc($result_forum)) {
    createCategoriesCrossReference($forum_phpbb['forum_id'], $forum_phpbb['forum_name'], $forum_phpbb['cat_id']);
}

/* Posts conversion */
while ($post_phpbb = mysql_fetch_assoc($result_posts)) {

    if (in_array($post_phpbb['topic_id'], $posts_ids_phpbb)) {
        continue;
    }

    // Clean content
    $cleaned_content = bbcode2Html($post_phpbb['post_text'], ':' . $post_phpbb['bbcode_uid']);
    $cleaned_content = bbcode2Html2($cleaned_content, ':' . $post_phpbb['bbcode_uid']);
    $cleaned_content = cleanBracket($cleaned_content);
    $cleaned_content = killSmileys($cleaned_content);

    // Categories ids
    $categories_ids = array(
        // wordpress main category (phpbb category)
        $categories_cross_reference['main_cat'][$categories_cross_reference['sub_cat'][$post_phpbb['forum_id']]['phpbb_cat_id']]['wp_id'],
        // wordpress sub-category (phpbb forum)
        $categories_cross_reference['sub_cat'][$post_phpbb['forum_id']]['wp_id']
    );

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
    $posts_wp[$count_posts]['post_content'] = $cleaned_content; // wp_posts.post_content

    // Category
    $posts_wp[$count_posts]['post_category'] = $categories_ids; // Categories ids

    // Specifics Wordpress Meta
    $posts_wp[$count_posts]['comment_status'] = 'open';
    $posts_wp[$count_posts]['ping_status'] = 'open';
    $posts_wp[$count_posts]['post_author'] = $wp_user_id; // Specify an user
    $posts_wp[$count_posts]['post_status'] = 'publish';
    $posts_wp[$count_posts]['post_type'] = 'post';

    /* Create Wordpress posts */
    if (wp_insert_post($posts_wp[$count_posts])) {
        $count_converted++;
    } else {
        echo '<p class="warning">Error on writing the WP database</p>';
    }
    $count_posts++;
}


echo '<p class="notice">';
echo $count_posts . ' posts found.<br>';
echo $count_converted . ' posts converted.';
echo '</p>';

/* ================================================== */

mysql_close($db_connect);
?>
