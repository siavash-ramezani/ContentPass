<?php

namespace Database\Seeders;

use App\Models\Content;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'title' => 'Getting Started with PHP 8',
                'type' => 'article',
                'required_plan_level' => 0,
                'body' => "PHP 8 brought major improvements like the JIT compiler, union types, and named arguments. In this guide we'll walk through setting up a fresh PHP 8 environment and writing your first modern PHP script.",
                'published_at' => now()->subMonths(4),
            ],
            [
                'title' => 'Understanding Git Branching Strategies',
                'type' => 'article',
                'required_plan_level' => 0,
                'body' => 'Trunk-based development, GitFlow, and GitHub Flow all solve the same problem differently. We compare the trade-offs so you can pick the right strategy for your team.',
                'published_at' => now()->subMonths(3),
            ],
            [
                'title' => 'Intro to Docker for Developers',
                'type' => 'video',
                'required_plan_level' => 0,
                'video_url' => 'https://videos.contentpass.test/intro-to-docker',
                'published_at' => now()->subMonths(2),
            ],
            [
                'title' => 'Building REST APIs with Laravel',
                'type' => 'article',
                'required_plan_level' => 1,
                'body' => 'Learn how to design clean, versioned REST APIs in Laravel using Form Requests, API Resources, and consistent JSON response envelopes.',
                'published_at' => now()->subMonths(2),
            ],
            [
                'title' => 'Advanced Eloquent Query Techniques',
                'type' => 'video',
                'required_plan_level' => 1,
                'video_url' => 'https://videos.contentpass.test/advanced-eloquent',
                'published_at' => now()->subMonth(),
            ],
            [
                'title' => 'Mastering JWT Authentication',
                'type' => 'article',
                'required_plan_level' => 1,
                'body' => 'A deep dive into how JSON Web Tokens work, how to issue and refresh them safely, and common pitfalls when securing an API with JWT.',
                'published_at' => now()->subWeeks(3),
            ],
            [
                'title' => 'Designing Scalable Microservices',
                'type' => 'article',
                'required_plan_level' => 2,
                'body' => "Splitting a monolith into microservices introduces new challenges around data consistency, service discovery, and observability. Here's how experienced teams approach it.",
                'published_at' => now()->subWeeks(1),
            ],
            [
                'title' => 'Kubernetes Deep Dive for Teams',
                'type' => 'video',
                'required_plan_level' => 2,
                'video_url' => 'https://videos.contentpass.test/kubernetes-deep-dive',
                'published_at' => null,
            ],
        ];

        foreach ($items as $item) {
            Content::updateOrCreate(
                ['slug' => Str::slug($item['title'])],
                [
                    'title' => $item['title'],
                    'body' => $item['body'] ?? null,
                    'video_url' => $item['video_url'] ?? null,
                    'type' => $item['type'],
                    'required_plan_level' => $item['required_plan_level'],
                    'published_at' => $item['published_at'],
                ]
            );
        }
    }
}
