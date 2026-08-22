<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Services\ContentAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContentCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_content_invalidates_the_published_content_cache(): void
    {
        Content::factory()->create();

        app(ContentAccessService::class)->publishedContent();
        $this->assertTrue(Cache::has(ContentAccessService::CACHE_KEY));

        Content::factory()->create();

        $this->assertFalse(Cache::has(ContentAccessService::CACHE_KEY));
    }

    public function test_updating_content_invalidates_the_published_content_cache(): void
    {
        $content = Content::factory()->create();

        app(ContentAccessService::class)->publishedContent();
        $this->assertTrue(Cache::has(ContentAccessService::CACHE_KEY));

        $content->update(['title' => 'Updated Title']);

        $this->assertFalse(Cache::has(ContentAccessService::CACHE_KEY));
    }

    public function test_deleting_content_invalidates_the_published_content_cache(): void
    {
        $content = Content::factory()->create();

        app(ContentAccessService::class)->publishedContent();
        $this->assertTrue(Cache::has(ContentAccessService::CACHE_KEY));

        $content->delete();

        $this->assertFalse(Cache::has(ContentAccessService::CACHE_KEY));
    }
}
