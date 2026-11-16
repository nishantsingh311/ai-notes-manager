<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NoteCacheService
{
    private const VERSION_KEY = 'notes:cache:version:v2';

    public function rememberList(int $page, int $limit, Closure $callback): mixed
    {
        return Cache::remember(
            $this->key("list:page:{$page}:limit:{$limit}"),
            now()->addSeconds(60),
            $callback
        );
    }

    public function rememberStats(Closure $callback): mixed
    {
        return Cache::remember($this->key('stats'), now()->addSeconds(30), $callback);
    }

    public function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }

    private function key(string $suffix): string
    {
        $version = Cache::rememberForever(self::VERSION_KEY, fn () => (string) Str::uuid());

        return "notes:{$version}:{$suffix}";
    }
}
