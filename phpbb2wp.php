<?php
/*
 * PHPBB2WP
 * Migrate phpBB forum to WP blog
 * Version 0.2 - No UI
 * By Colin Braly (@_awk) & Antoine Cadoret (@JackNUMBER)
 *
 * HOW TO:
 * 1. Don't be a hero and backup your database ;)
 * 2. Install Wordpress on your phpBB server.
 * 3. Download and edit the file with your db login.
 * 4. Put the file into the root folder.
 * 5. Run it.
 *
 */
?>

<h1>Migrate phpBB2 to Wordpress</h1>
<style>
	.alert{background:#ff9;}
	.warning{background:#f99;}
	.success{background:#9f9;}
</style>

<?php

$mysql_host = 'localhost';	// Edit with your db adress
$mysql_db 	= 'phpbb2wp';		// Edit with your db name
$mysql_user = 'root';				// Edit with your db username
$mysql_pwd  = '';						// Edit with your db user password

$wpPrefix		= 'wp_';				//Edit with your WP table prefix
$phpbbPrefix= 'phpbb_';			//Edit with your WP table prefix

/* Database connection */
if(($db_connect = @mysql_connect($mysql_host, $mysql_user, $mysql_pwd)) && (mysql_select_db($mysql_db))){
	echo '<p class="success">Database connection successful</p>';
}else{
	die('<p class="msg warning">Database connection failed: '.mysql_error().'</p>');
}
mysql_query("SET NAMES 'utf8'", $db_connect);


/* ==================== Global Functions ==================== */

function testTableExists($table, $db){
	$query = 'SHOW TABLES FROM '.$db.' LIKE \''.$table.'\'';
	$exec = mysql_query($query);
	return mysql_num_rows($exec);
}

function testTables(){
	global $mysql_db, $phpbbPrefix, $wpPrefix;
	if(!testTableExists($phpbbPrefix.'posts', $mysql_db)){
		echo '<p class="warning">The phpBB database seems not available ('.$phpbbPrefix.'posts)';
		exit;
	}
	if(!testTableExists($wpPrefix.'posts', $mysql_db)){
		echo '<p class="warning">The Wordpress database seems not available ('.$wpPrefix.'posts)';
		exit;
	}
}

/* BBcode URL tag standardization */
function cleanURL($str){
	while (preg_match('#\[url\]http://(.*?)\[/url\]#', $str, $matches)){
		$tag = str_replace('[url]','[url=http://'.$matches[1].']', $matches[0]);
		$str = str_replace($matches[0], $tag, $str);
	}
	while (preg_match('#\[url\]www.(.*?)\[/url\]#', $str, $matches)){
		$tag = str_replace('[url]','[url=http://www.'.$matches[1].']', $matches[0]);
		$str = str_replace($matches[0], $tag, $str);
	}
	return $str;
}

/* List BBcode */
function bbcode2Html($str, $uid = ''){
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
function bbcode2Html2($str, $uid){
  $bbcode = array(
                '"'.$uid.']',
                '"]',
                $uid.']',
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
function killSmileys($str){
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
								":arrow:");
	$newstr = str_replace($smileys, '', $str);
	return $newstr;
}
function cleanBracket($str){
	while (preg_match('#\[(.*?)">#', $str, $matches)){
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
if(file_exists('./wp-blog-header.php')){
	require('./wp-blog-header.php');
}else{
	echo '<p class="msg warning">Wordpress seems not installed</p>';
	exit;
}

/* Posts reading */
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
				 	 AND phpbb_posts.poster_id = 2
				 ORDER BY phpbb_posts.post_time ASC
				 LIMIT 10
				';

$result = mysql_query($sql);

if($result){
	echo '<p>Posts reading...</p>';
}else{
	$message  = '<p class="msg warning">Invalid Request: '.mysql_error()."</p>";
	die($message);
}


$article_wp = array();
$topic_done = array();
$cpt_article = 0;
$converted = 0;

while($post_phpbb = mysql_fetch_assoc($result)){
	if(in_array($post_phpbb['topic_id'], $topic_done)){
		continue;
	}
	$topic_done[] = $post_phpbb['topic_id'];

	/* Posts conversion */
	
	// Dates - Is GMT convertion needed?
	$article_wp[$cpt_article]['post_date'] = date("Y-m-d H:i:s", $post_phpbb['post_time']); // wp_post.post_date
	$article_wp[$cpt_article]['post_date_gmt'] = date("Y-m-d H:i:s", $post_phpbb['post_time_gmt']-(60*60)); // wp_post.post_date_gmt
	$article_wp[$cpt_article]['wp_post.post_modified'] = date("Y-m-d H:i:s", $post_phpbb['post_edit_time']); // wp_post.post_modified
	$article_wp[$cpt_article]['wp_post.post_modified_gmt'] = date("Y-m-d H:i:s", $post_phpbb['post_edit_time']-(60*60)); // wp_post.post_modified_gmt

	// Title
	$article_wp[$cpt_article]['post_title'] = $post_phpbb['post_subject']; // wp_posts.post_title

	// Content
	$post_phpbb['post_text'] = cleanURL($post_phpbb['post_text']);
	$article_wp[$cpt_article]['post_content'] = bbcode2Html($post_phpbb['post_text'], ':'.$post_phpbb['bbcode_uid']); // post_content
	$article_wp[$cpt_article]['post_content'] = bbcode2Html2($article_wp[$cpt_article]['post_content'], ':'.$post_phpbb['bbcode_uid']); // wp_posts.post_content
	$article_wp[$cpt_article]['post_content'] = cleanBracket($article_wp[$cpt_article]['post_content']); // wp_posts.post_content
	$article_wp[$cpt_article]['post_content'] = killSmileys($article_wp[$cpt_article]['post_content']); // wp_posts.post_content
	
	// Category
	$article_wp[$cpt_article]['wp_term_relationships.object_id'] = $post_phpbb['forum_id']; // wp_term_relationships.object_id

	// Specifics Wordpress Meta
	$article_wp[$cpt_article]['comment_status'] = 'open';
	$article_wp[$cpt_article]['ping_status'] = 'open';
	$article_wp[$cpt_article]['post_author'] = 2; // Antoine
	$article_wp[$cpt_article]['post_status'] = 'publish';
	$article_wp[$cpt_article]['post_type'] = 'post';

	/* Create Wordpress posts */
	/*if(wp_insert_post($article_wp[$cpt_article])){
		$converted++;
	}else{
		echo '<p class="warning">Error on writing the WP database</p>';
	}*/
	$cpt_article++;
}

echo '<p class="alert">';
	echo $cpt_article.' posts found.<br>';
	echo $converted.' posts converted.';
echo '</p>';

/* ================================================== */

mysql_close($db_connect);
?>
