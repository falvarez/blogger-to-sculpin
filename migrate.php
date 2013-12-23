#!/usr/bin/php
<?php
include_once(__DIR__ . '/vendor/autoload.php');
include __DIR__ . '/Post.php';
// Parse it
$feed = new SimplePie();
if (isset($argv[1]) && $argv[1] !== '') {
    $feed->set_feed_url($argv[1]);
    $feed->enable_cache(false);
    $feed->init();

    $markdown = new HTML_To_Markdown();

    foreach ($feed->get_items() as $item) {
        /* @var $item SimplePie_Item */

        // Filter settings (from the export file)
        if ($item->get_category()->term !== 'http://schemas.google.com/blogger/2008/kind#post') {
            continue;
        }

        $post = new \Post();
        $post->title = $item->get_title();
        $post->content = $markdown->convert($item->get_content());
        foreach ($item->get_categories() as $category) {
            /* @var $category SimplePie_Category */
            if ($category->get_label() !== 'http://schemas.google.com/blogger/2008/kind#post') {
                $label = $category->get_label();
                if (preg_match('/[a-z]/', $label{0})) {
                    $post->tags[] = $label;
                }
                else {
                    $post->categories[] = $label;
                }
            }
            $post->permalink = $item->get_permalink();
            $post->date = $item->get_date('Y-m-d');

            $post->getConvertedFileContent();
        }

        var_dump($post);
    }
}