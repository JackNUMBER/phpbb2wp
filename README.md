#Convert phpBB to Wordpress
phpbb2wp is a free admin tool to migrate phpBB forum to Wordpress system. Created and maintained by [Colin Braly](http://twitter.com/4wk_) and [Antoine Cadoret](http://twitter.com/jacknumber).
This tool works only for phpBB3 (you can easily upgrade your phpBB2 to phpBB3 with the phpBB3 install wizard).

## Getting Started
To use this tool follow these steps:

1. Don't be a hero and backup your database ;)
2. Install Wordpress on your phpBB server.
3. Download and edit the file with your db login.
4. Put the file into the root folder.
5. Run it.

## TODO
Here our next improvements:

- Create GUI
- Use PDO interface
- Use asynchrone tasks
- Put phpBB topics answers in Wordpress posts comments
- <del>Check phpBB database</del>
- <del>Check Wordpress database</del>
- <del>Check Wordpress install</del>
- <del>Add custom prefix parameter</del>
- Add comments keep/kill option
- List phpBB categories/forum before action
- Simulate phpBB categories/forum to Wordpress categories/tags (not sure)
- Add category manager (how to use phpBB categories/forum)
- <del>Detect phpBB version</del>
- Add old database manager (keep/kill)
- Add BBcode manager (from bbcode table)
- Add link manager (list old/new post url)
- <del>Add smiley killer (from smileys table)</del>
- Add pictures manager (not sure)
