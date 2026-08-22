<?php

namespace App\Services;

use App\Models\Content;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ContentAccessService
{
    public const CACHE_KEY = 'content:published';

    public const CACHE_TTL_SECONDS = 300;

    /**
     * Get all published content (not future-dated), cached for all users alike.
     * Per-user accessibility is deliberately NOT part of this cached data —
     * compute it afterward with isAccessibleTo().
     *
     * @return Collection<int, Content>
     */
    public function publishedContent(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return Content::query()
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderByDesc('published_at')
                ->get();
        });
    }

    /**
     * Whether the given content is accessible to the given user, based on
     * the user's plan level (no plan == level 0) vs. the content's required level.
     */
    public function isAccessibleTo(Content $content, ?User $user): bool
    {
        $userLevel = $user?->plan?->level ?? 0;

        return $userLevel >= $content->required_plan_level;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
