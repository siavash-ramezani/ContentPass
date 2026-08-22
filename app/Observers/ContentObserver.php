<?php

namespace App\Observers;

use App\Models\Content;
use App\Services\ContentAccessService;

class ContentObserver
{
    public function __construct(private readonly ContentAccessService $contentAccess)
    {
        //
    }

    /**
     * Handle the Content "created" event.
     */
    public function created(Content $content): void
    {
        $this->contentAccess->forgetCache();
    }

    /**
     * Handle the Content "updated" event.
     */
    public function updated(Content $content): void
    {
        $this->contentAccess->forgetCache();
    }

    /**
     * Handle the Content "deleted" event.
     */
    public function deleted(Content $content): void
    {
        $this->contentAccess->forgetCache();
    }
}
