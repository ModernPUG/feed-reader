<?php

namespace ModernPUG\FeedReader;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'title',
        'link',
        'description',
        'published_at',
        'blog_id',
    ];

    public function blog()
    {
        return $this->belongsTo('ModernPUG\FeedReader\Blog');
    }

    public function Tags()
    {
        return $this->belongsToMany('ModernPUG\FeedReader\Tag')->withTimestamps();
    }

    public function hasTag(array $tagNames)
    {
        $tags = $this->tags();
        foreach($tagNames as $tagName) {
            if($tags->contains($tagName)) {
                return true;
            }
        }
    }
}
