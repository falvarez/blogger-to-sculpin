<?php

namespace Falvarez\Sculpin\Utils;

use Cocur\Slugify\Slugify;
use DOMDocument;
use DOMElement;
use Psr\Log\LoggerInterface;
use SimplePie;
use SimplePie_Category;
use SimplePie_Item;

class MigrateScript
{
    /** @var string */
    private $rssFilename;
    /** @var array */
    private $configuration;
    /** @var LoggerInterface $logger */
    private $logger;

    /**
     * MigrateScript constructor.
     * @param string $inputFile
     * @param mixed[] $configuration
     * @param LoggerInterface $logger
     */
    public function __construct($inputFile, $configuration, LoggerInterface $logger)
    {
        $this->rssFilename = $inputFile;
        $this->configuration = $configuration;
        $this->logger = $logger;
    }

    /**
     *
     */
    private function checkParameters()
    {
        if (empty($this->rssFilename)) {
            die("Usage: migrate.php exportFile.xml\n");
        }
    }

    /**
     * @param Post $post
     */
    private function writeConvertedFileContent($post)
    {
        if (!is_dir($this->configuration['exportFolder'] . $this->configuration['postsFolder'])) {
            mkdir($this->configuration['exportFolder'] . $this->configuration['postsFolder']);
        }
        $filename = $this->configuration['exportFolder'] . $this->configuration['postsFolder'] . $post->generateFilename();
        file_put_contents($filename, $post->toSculpin($this->configuration['includeDisqusData']));
    }

    /**
     * @param string $content
     * @param string $folderPrefix
     * @return string
     */
    private function downloadImagesToLocal($content, $folderPrefix)
    {
        $doc = new DOMDocument();
        $doc->loadHTML($content);
        $images = [];
        $imageTags = $doc->getElementsByTagName('img');
        foreach ($imageTags as $imageTag) {
            /** @var DOMElement $imageTag */
            $src = $imageTag->getAttribute('src');

            // Querystring must be removed from image URL
            $imageBasename = explode('?', basename($src));
            $imageBasename = $imageBasename[0];

            $images[] = [
                'source' => $src,
                'destinationPath' => $this->configuration['exportFolder'] . $this->configuration['imagesRootFolder'] . $folderPrefix,
                'destinationFile' => $imageBasename,
                'destinationUrl' => $this->configuration['blogUrl'] . $this->configuration['imagesRootFolder'] . $folderPrefix . $imageBasename
            ];
        }
        foreach ($images as $image) {
            $this->downloadImage($image);
            $content = str_replace($image['source'], $image['destinationUrl'],
                $content);
        }
        return $content;
    }

    /**
     * @param array $imageData
     */
    private function downloadImage($imageData)
    {
        $this->logger->info('Downloading image from ' . $imageData['source']);
        $this->logger->info('Writing image to ' . $imageData['destinationPath'] . $imageData['destinationFile']);

        if (!is_dir($imageData['destinationPath'])) {
            mkdir($imageData['destinationPath'], 0755, true);
        }

        $ch = curl_init($imageData['source']);
        $fp = fopen($imageData['destinationPath'] . $imageData['destinationFile'], 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    /**
     * @param string $html
     * @return string
     */
    private function parseHtml($html)
    {
        return htmlspecialchars_decode(html_entity_decode($html));
    }

    /**
     * @param string $html
     * @return string
     */
    private function convertBrsToParagraphs($html)
    {
        $paragraphs = '';
        foreach (explode("\n", str_replace('<br>', "\n", $html)) as $line) {
            if (!preg_match('#^<.+$#', $line)) {
                $paragraphs .= '<p>' . $line . '</p>';
            } else {
                $paragraphs .= $line . "\n";
            }
        }
        return str_replace('<p></p>', '', $paragraphs);
    }

    /**
     * @param SimplePie_Item $item
     * @return bool
     */
    private function isPost($item)
    {
        foreach ($item->get_categories() as $category) {
            if ($category->scheme == 'http://schemas.google.com/g/2005#kind' &&
                $category->term == 'http://schemas.google.com/blogger/2008/kind#post'
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     *
     */
    public function run()
    {
        $this->checkParameters();

        // Erase destination folders
        system('rm -rf ' . $this->configuration['exportFolder'] . $this->configuration['postsFolder']);
        system('rm -rf ' . $this->configuration['exportFolder'] . $this->configuration['imagesRootFolder']);

        $feed = new SimplePie();
        $feed->strip_htmltags(false);
        $feed->set_feed_url($this->rssFilename);
        $feed->enable_cache(false);
        $feed->init();

        $publishedPostCount = 0;
        $draftPostCount = 0;

        foreach ($feed->get_items() as $item) {
            /* @var $item SimplePie_Item */
            if (!$this->isPost($item)) {
                continue;
            }

            $this->logger->info('Parsing post ' . $item->get_title());

            $post = new Post();

            // Check draft status
            $control = $item->get_item_tags('http://purl.org/atom/app#', 'control');
            $post->setDraft($control[0]['child']['http://purl.org/atom/app#']['draft'][0]['data'] === 'yes');

            $post->setMarkdownOutput($this->configuration['enableMarkdownOutput']);
            $post->setTitle($this->parseHtml($item->get_title()));
            $post->setDateTimeFromTimestamp($item->get_date('U'));

            foreach ($item->get_categories() as $category) {
                /* @var $category SimplePie_Category */
                if ($category->get_label() !== 'http://schemas.google.com/blogger/2008/kind#post') {
                    $label = $category->get_label();
                    if (preg_match('/[a-z]/', $label{0})) {
                        $post->addTag($label);
                    } else {
                        $post->addCategory($label);
                    }
                }
            }

            $permalink = !empty($item->get_permalink()) ?
                $item->get_permalink() :
                $this->configuration['blogUrl'] . $post->getDateTime()->format('Y/m/') .
                (new Slugify)->slugify($post->getTitle()) . '.html';
            $post->setPermalink($permalink);

            $content = $this->parseHtml($item->get_content());
            if ($this->configuration['downloadRemoteImages']) {
                $content = $this->downloadImagesToLocal($content, $post->getDateTime()->format('Y/m/'));
            }
            if ($this->configuration['convertBrToParagraphs']) {
                $content = $this->convertBrsToParagraphs($content);
            }
            $post->setContent($content);

            $this->writeConvertedFileContent($post);

            if ($post->getDraft()) {
                $draftPostCount++;
            } else {
                $publishedPostCount++;
            }

        }

        $totalPostCount = $draftPostCount + $publishedPostCount;
        $this->logger->info('Draft posts processed: ' . $draftPostCount);
        $this->logger->info('Published posts processed: ' . $publishedPostCount);
        $this->logger->info('Total posts processed: ' . $totalPostCount);
    }
}
