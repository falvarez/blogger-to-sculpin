<?php

namespace Falvarez\Sculpin\Utils;

use Cocur\Slugify\Slugify;
use DateTime;
use DOMDocument;
use DOMElement;
use League\HTMLToMarkdown\HtmlConverter;

class Post
{
    /** @var string */
    protected $title;
    /** @var string */
    protected $layout = 'post';
    /** @var string[] */
    protected $tags = [];
    /** @var string[] */
    protected $categories = [];
    /** @var string */
    protected $content;
    /** @var string */
    protected $permalink;
    /** @var DateTime */
    protected $dateTime;
    /** @var bool */
    protected $draft;
    /** @var bool */
    protected $markdownOutput = true;
    /** @var bool */
    protected $customizedOutput = true;

    /** @var string  */
    protected $headerImage = null;

    /**
     * @return DateTime
     */
    public function getDateTime() {
        return $this->dateTime;
    }

    /**
     * @return string
     */
    public function getTitle() {
        return $this->title;
    }

    /**
     * @param $title
     */
    public function setTitle($title) {
        $this->title = $title;
    }

    /**
     *
     */
    private function setHeaderImageFromBodyImages() {
        $doc = new DOMDocument();
        $doc->loadHTML($this->content);
        $imageTags = $doc->getElementsByTagName('img');
        if ($imageTags->length > 0) {
            /** @var DOMElement $firstImage */
            $firstImage = $imageTags->item(0);
            $this->headerImage = $firstImage->getAttribute('src');
        }
    }

    /**
     * @param $content
     */
    public function setContent($content) {
        $this->content = $content;
        $this->setHeaderImageFromBodyImages();
    }

    /**
     * @param $markdownOutput
     */
    public function setMarkdownOutput($markdownOutput) {
        $this->markdownOutput = $markdownOutput;
    }

    /**
     * @return bool
     */
    public function getDraft() {
        return $this->draft;
    }

    /**
     * @param $draft
     */
    public function setDraft($draft) {
        $this->draft = $draft;
    }

    /**
     * @param $tag
     */
    public function addTag($tag) {
        $this->tags[] = $tag;
    }

    /**
     * @param $category
     */
    public function addCategory($category) {
        $this->categories[] = $category;
    }

    /**
     * @param $timestamp
     */
    public function setDateTimeFromTimestamp($timestamp) {
        $this->dateTime = (new DateTime)->setTimestamp($timestamp);
    }

    /**
     * @param $permalink
     */
    public function setPermalink($permalink) {
        $this->permalink = $permalink;
    }

    /**
     * @return string
     */
    private function getCustomizedContent() {
        $content = str_replace('<img ', '<img class="center-block img-responsive" ', $this->content);
        return $content;
    }

    /**
     * @return string
     */
    public function getContent() {
        return !$this->customizedOutput ? $this->content : $this->getCustomizedContent();
    }

    /**
     * @return string
     */
    private function getMarkdownContent() {
        $converter = new HtmlConverter();
        return $converter->convert($this->content);
    }

    /**
     * @return string
     */
    private function getFormattedContent() {
        return $this->markdownOutput ?
            $this->getMarkdownContent() :
            $this->getContent();
    }

    /**
     * @return string
     */
    public function generateFilename() {
        $parsed = parse_url($this->permalink);
        $path = substr($parsed['path'], 9);
        $path = $this->dateTime->format('Y-m-d') . '-' . $path;
        if ($this->draft) {
            $path = str_replace('.html', '.draft.html', $path);
        }
        if ($this->markdownOutput) {
            $path = str_replace('.html', '.md', $path);
        }
        return $path;
    }

    /**
     * @param bool $includeDisqusData @optional
     * @return string
     */
    public function toSculpin($includeDisqusData = true) {
        $lines = [];
        $lines[] = '---';
        $lines[] = 'layout: ' . $this->layout;
        $lines[] = 'title: "' . addslashes($this->title) . '"';
        if ($this->headerImage !== null) {
            $lines[] = 'header_image: ' . $this->headerImage;
        }
        $lines[] = 'text_date: ' . ucfirst(strftime("%A, %e de %B de %Y", $this->dateTime->getTimestamp()));
        $lines[] = 'tags: [' . join(',' , $this->tags) . ']';
        if ($this->draft) {
            $lines[] = 'draft: true';
        }
        if ($includeDisqusData) {
            $lines[] = 'disqus_identifier: ' . (new Slugify)->slugify($this->title);
            $lines[] = 'disqus_url: ' . $this->permalink;
        }
        $lines[] = '';
        $lines[] = '---';
        $lines[] = $this->getFormattedContent();
        return join("\n", $lines);
    }
}
