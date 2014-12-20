<?php
/*
 * PHPBB2WP
 * Migrate phpBB forum to WP blog
 * Version 0.3.3
 * By Colin Braly (@4wk_) & Antoine Cadoret (@JackNUMBER)
 *
 * HOW TO:
 * 1. Don't be a hero and backup your database ;)
 * 2. Install Wordpress on your phpBB server.
 * 3. Download and edit this file with your settings.
 * 4. Put this file into the root folder.
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

/* Advanced Settings */

$wp_user_id = 1; // your WP user id
$phpbb_user_id = 2; // your phpBB user id

$keep_emoticons = true;
$keep_custom_emoticons = true;

$restrict_to_one_user = true; // read phpBB post of only $phpbb_user_id


/* end:Settings */

$wp_test_table_name = $wp_prefix . 'posts';
$phpbb_test_table_name = $phpbb_prefix . 'posts';

$wp_required_files = array(
    dirname( __FILE__ ) . '/wp-blog-header.php',
    dirname( __FILE__ ) . '/wp-admin/includes/taxonomy.php'
);

// set unlimited execution time
set_time_limit(0);

// from /wp-includes/functions.php
$wp_basic_emoticons = array(
':mrgreen:',
':neutral:',
':twisted:',
  ':arrow:',
  ':shock:',
  ':smile:',
    ':???:',
   ':cool:',
   ':evil:',
   ':grin:',
   ':idea:',
   ':oops:',
   ':razz:',
   ':roll:',
   ':wink:',
    ':cry:',
    ':eek:',
    ':lol:',
    ':mad:',
    ':sad:',
      '8-)',
      '8-O',
      ':-(',
      ':-)',
      ':-?',
      ':-D',
      ':-P',
      ':-o',
      ':-x',
      ':-|',
      ';-)',
       '8O',
       ':(',
       ':)',
       ':?',
       ':D',
       ':P',
       ':o',
       ':x',
       ':|',
       ';)',
      ':!:',
      ':?:',
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
    $sql_test = 'SHOW TABLES FROM ' . $db . ' LIKE \'' . $table . '\'';
    $result_test = mysql_query($sql_test);
    return mysql_num_rows($result_test);
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

/* Sanitize string to create a string without accent or special character - source http://stackoverflow.com/questions/2103797/url-friendly-username-in-php */
function sanitize($string) {
    return strtolower(
        trim(
            preg_replace('~[^0-9a-z]+~i', '-', html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', htmlentities($string, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8')),
        '-')
    );
}

/**
 * Manage emoticons list with user params
 *
 * @param bool $keep_emoticons Define if we need to keep all emoticons
 * @param bool $keep_custom_emoticons Define if we need to keep phpBB customs emoticons
 *
 * @return string
 */
function emoticonsManager($keep_emoticons, $keep_custom_emoticons) {
    global $result_emoticons, $wp_basic_emoticons;

    $phpbb_emoticons = array();
    global $emoticoncs_to_kill;
    $emoticoncs_to_kill = array();

    while ($emoticons_phpbb = mysql_fetch_assoc($result_emoticons)) {
        $phpbb_emoticons[] = $emoticons_phpbb['code'];
    }

    $phpbb_custom_emoticons = array_diff($phpbb_emoticons, $wp_basic_emoticons);

    if (!$keep_emoticons) {
        $emoticoncs_to_kill = $phpbb_emoticons;
    } else if (!$keep_custom_emoticons) {
        $emoticoncs_to_kill = $phpbb_custom_emoticons;
    }
}

/**
 * Clean emoticons on a string
 *
 * @param bool $str Source string
 *
 * @return string $cleaned_str String with emoticons cleaned
 */
function cleanEmoticons($str) {
    global $emoticoncs_to_kill;

    $cleaned_str = str_replace($emoticoncs_to_kill, '', $str);
    return $cleaned_str;
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
    ' . $phpbb_prefix . 'posts.post_id,
    ' . $phpbb_prefix . 'posts.topic_id,
    ' . $phpbb_prefix . 'posts.post_time,
    ' . $phpbb_prefix . 'posts.post_edit_time,
    ' . $phpbb_prefix . 'posts.forum_id,
    ' . $phpbb_prefix . 'posts_text.bbcode_uid,
    ' . $phpbb_prefix . 'posts_text.post_subject,
    ' . $phpbb_prefix . 'posts_text.post_text
    FROM
    ' . $phpbb_prefix . 'posts,
    ' . $phpbb_prefix . 'posts_text
    WHERE
    ' . $phpbb_prefix . 'posts.post_id = ' . $phpbb_prefix . 'posts_text.post_id';

if ($restrict_read_to_one_user) {
    $sql .= ' AND ' . $phpbb_prefix . 'posts.poster_id = ' . $phpbb_user_id;
}
$sql .= ' ORDER BY ' . $phpbb_prefix . 'posts.post_time ASC';
$result_posts = mysql_query($sql);

$sql_cat = 'SELECT
    *
    FROM
    ' . $phpbb_prefix . 'categories
    ORDER BY ' . $phpbb_prefix . 'categories.cat_id ASC
';
$result_cat = mysql_query($sql_cat);

$sql_forum = 'SELECT
    *
    FROM
    ' . $phpbb_prefix . 'forums
    ORDER BY ' . $phpbb_prefix . 'forums.forum_id ASC
';
$result_forum = mysql_query($sql_forum);

$sql_emoticons = 'SELECT
    ' . $phpbb_prefix . 'smilies.code
    FROM
    ' . $phpbb_prefix . 'smilies
    ORDER BY ' . $phpbb_prefix . 'smilies.smilies_id ASC
';
$result_emoticons = mysql_query($sql_emoticons);

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

/* Define emoticons */
emoticonsManager($keep_emoticons, $keep_custom_emoticons);

/* Posts conversion */
while ($post_phpbb = mysql_fetch_assoc($result_posts)) {

    if (in_array($post_phpbb['topic_id'], $posts_ids_phpbb)) {
        continue;
    }

    // Clean content
    $cleaned_content = bbcode2Html($post_phpbb['post_text'], ':' . $post_phpbb['bbcode_uid']);
    $cleaned_content = bbcode2Html2($cleaned_content, ':' . $post_phpbb['bbcode_uid']);
    $cleaned_content = cleanBracket($cleaned_content);
    $cleaned_content = cleanEmoticons($cleaned_content);

    // Categories ids
    $categories_ids = array(
        // wordpress main category (phpbb category)
        $categories_cross_reference['main_cat'][$categories_cross_reference['sub_cat'][$post_phpbb['forum_id']]['phpbb_cat_id']]['wp_id'],
        // wordpress sub-category (phpbb forum)
        $categories_cross_reference['sub_cat'][$post_phpbb['forum_id']]['wp_id']
    );

    $posts_ids_phpbb[] = $post_phpbb['topic_id'];

    // Dates
    $posts_wp[$count_posts]['post_date'] = date("Y-m-d H:i:s", $post_phpbb['post_time']); // wp_post.post_date
    $posts_wp[$count_posts]['post_date_gmt'] = date("Y-m-d H:i:s", $post_phpbb['post_time_gmt'] - (60*60)); // wp_post.post_date_gmt
    $posts_wp[$count_posts]['post_modified'] = date("Y-m-d H:i:s", $post_phpbb['post_edit_time']); // wp_post.post_modified
    $posts_wp[$count_posts]['post_modified_gmt'] = date("Y-m-d H:i:s", $post_phpbb['post_edit_time'] - (60*60)); // wp_post.post_modified_gmt

    // Title
    $posts_wp[$count_posts]['post_title'] = $post_phpbb['post_subject']; // wp_posts.post_title

    // Content
    $post_phpbb['post_text'] = cleanURL($post_phpbb['post_text']);
    $posts_wp[$count_posts]['post_content'] = $cleaned_content; // wp_posts.post_content

    // Specifics Wordpress Meta
    $posts_wp[$count_posts]['post_author'] = $wp_user_id; // wp_posts.post_author
    $posts_wp[$count_posts]['post_status'] = 'publish'; // wp_posts.post_status
    $posts_wp[$count_posts]['post_name'] = sanitize($post_phpbb['post_subject']); // wp_posts.post_name

    $sql_insert_post = 'INSERT
    INTO ' . $wp_prefix . 'posts
    (post_author,
    post_date,
    post_date_gmt,
    post_content,
    post_title,
    post_status,
    post_name,
    post_modified,
    post_modified_gmt)
    VALUES
    ("' . $posts_wp[$count_posts]['post_author'] . '",
    "' . $posts_wp[$count_posts]['post_date'] . '",
    "' . $posts_wp[$count_posts]['post_date_gmt'] . '",
    "' . mysql_real_escape_string($posts_wp[$count_posts]['post_content']) . '",
    "' . mysql_real_escape_string($posts_wp[$count_posts]['post_title']) . '",
    "' . $posts_wp[$count_posts]['post_status'] . '",
    "' . $posts_wp[$count_posts]['post_name'] . '",
    "' . $posts_wp[$count_posts]['post_modified'] . '",
    "' . $posts_wp[$count_posts]['post_modified_gmt'] . '")
    ';

    /* Create Wordpress posts and insert in relationship table */
    /* I choose to use a sql insert instead of Wordpress wp_insert_post() function for faster results.
     * With this choice we need to insert manually in the join table (wp_term_relationships).
     * See 96e3e23023898b03de760179112f04fe5bfa4a31 for more details.
     */
    if (mysql_query($sql_insert_post)) {

        // get last post id - we suppose this is the current post id
        $current_post_id = mysql_insert_id();

        foreach ($categories_ids as $category_id) {
            $sql_insert_relationships = 'INSERT
            INTO ' . $wp_prefix . 'term_relationships
            (object_id,
            term_taxonomy_id)
            VALUES
            (' . $current_post_id . ',
            ' . $category_id . ')
            ';
            mysql_query($sql_insert_relationships);

            $sql_update_cat_count = 'UPDATE ' . $wp_prefix . 'term_taxonomy
            SET count = count+1
            WHERE term_id = ' . $category_id;
            mysql_query($sql_update_cat_count);
        }

        // @TODO: will be an option to see convert progress
        // echo '<p><i>' .  $posts_wp[$count_posts]['post_title'] . '</i> converted.</p>';

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
