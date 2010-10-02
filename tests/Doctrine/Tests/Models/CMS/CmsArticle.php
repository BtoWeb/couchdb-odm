<?php

namespace Doctrine\Tests\Models\CMS;

/**
 * @Document
 */
class CmsArticle
{
    /** @Id */
    public $id;
    /** @String */
    public $topic;
    /** @String */
    public $text;
    /** @ReferenceOne(targetDocument="CmsUser") */
    public $user;
    public $comments;
    /** @Version */
    public $version;
    
    public function setAuthor(CmsUser $author) {
        $this->user = $author;
    }

    public function addComment(CmsComment $comment) {
        $this->comments[] = $comment;
        $comment->setArticle($this);
    }
}
