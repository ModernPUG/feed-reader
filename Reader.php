<?php

namespace ModernPUG\FeedReader;

use Zend\Feed\Reader\Reader as ZendReader;
use Wandu\Http\Psr\Uri;

class Reader implements IReader
{
    protected $lastError;

    public function getLastError()
    {
        return $this->lastError;
    }

    public function getCreateViewName()
    {
        return 'fdrdr::blogs.create';
    }

    public function blogs()
    {
        return Blog::orderBy('title', 'asc')->get();
    }

    public function recentUpdatedArticles()
    {
        $articles = Article::with('blog');
        return $articles->orderBy('published_at', 'desc')->paginate(10);
    }

    public function viewArticle(Article $article, $ip)
    {
        return Viewcount::view($article, $ip);
    }

    public function insertFeed($args)
    {
        $url = $args['url'];

        if (empty($url)) {
            $this->lastError = '누락된 값이 있습니다.';

            return false;
        }

        try {
            $blogInfo = $this->getBlogInfo($url);
            if (!$blogInfo) {
                foreach (ZendReader::findFeedLinks($url) as $link) {
                    $url = $link['href'];
                    break;
                }
                $blogInfo = $this->getBlogInfo($url);
            }
            $blog = new Blog();
            $blog = $blog->create($blogInfo);
            $this->updateBlog($blog);
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return true;
    }

    private function getBlogInfo($url)
    {
        $blogInfo = null;

        try {
            $feed = ZendReader::import($url);

            $feedUrl = $feed->getFeedLink();
            if (!strpos($feedUrl, '://')) {
                $uri = new Uri($url);
                $feedUrl = $uri->getScheme().'://'.$uri->getHost().$feedUrl;
            }

            $uri = new Uri($feedUrl);
            $siteUrl = $uri->getScheme().'://'.$uri->getHost();

            $blogInfo = [
                'title' => $feed->getTitle(),
                'feed_url' => $feedUrl,
                'site_url' => $siteUrl,
            ];
        } catch (\Exception $e) {
            return;
        }

        return $blogInfo;
    }

    public function updateAllblogs()
    {
        $blogs = $this->blogs();

        foreach ($blogs as $blog) {
            $this->updateBlog($blog);
        }
    }

    public function updateBlog($blog)
    {
        try {
            $feed = ZendReader::import($blog->feed_url);

            foreach ($feed as $entry) {
                $blogUri = new Uri($blog->feed_url);
                $articleUri = new Uri($entry->getLink());
                $link = $blogUri->join($articleUri)->__toString();
                $description = mb_substr(strip_tags($entry->getDescription()), 0, 250);
                $published_at = $entry->getDateModified();

                $article = Article::where('blog_id', $blog->id)
                    ->where('link', $link)
                    ->first();

                if (empty($article)) {
                    $article = Article::create([
                        'title' => $entry->getTitle(),
                        'link' => $link,
                        'description' => $description,
                        'published_at' => $published_at,
                        'blog_id' => $blog->id,
                    ]);

                    foreach ($entry->getCategories() as $category) {
                        $tag = Tag::where('name', $category['label'])->first();

                        if (empty($tag)) {
                            $tag = Tag::create([
                                'name' => $category['label'],
                            ]);
                        } else {
                            $tag = Tag::where('name', $category['label'])->first();
                        }

                        $article->tags()->attach($tag['id']);
                    }
                } else {
                    if ($article->title != $entry->getTitle() || $article->description != $description) {
                        $article->title = $entry->getTitle();
                        $article->description = $description;
                        $article->save();
                    }
                }
            }
        } catch ( \Exception $e) {

        }
    }

    public function getLastBestArticles($lastDays)
    {
        return Viewcount::getLastBestArticles($lastDays);
    }

    public function getLastBestArticlesByTag($lastDays, $tagIds)
    {
        return Viewcount::getLastBestArticlesByTag($lastDays, $tagIds);
    }
}
