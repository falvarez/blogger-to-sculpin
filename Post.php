<?php
class Post {

    public $title;
    public $layout = 'post';
    public $tags = [];
    public $categories = [];
    public $content;
    public $permalink;
    public $date;

    private function generateFilename() {
        $parsed = parse_url($this->permalink);
        $path = substr($parsed['path'], 9);
        return str_replace('.html' , '.md', $this->date . '-' . $path);
    }

    public function getConvertedFileContent() {
        $filename = $this->generateFilename();
        $tags = implode(',' , $this->tags);
        $cats = implode(',' , $this->categories);
        $lines = [
            '---',
            'layout: post',
            "title: $this->title",
            "tags: [$tags]",
            "categories: [$cats]",
            '',
            '---',
            $this->content
        ];
        file_put_contents($filename, implode("\n", $lines));
    }
} 