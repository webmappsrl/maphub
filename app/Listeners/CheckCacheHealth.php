<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;

class CheckCacheHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        Cache::put('health_check', true, 10);

        if (Cache::get('health_check') !== true) {
            throw new \RuntimeException('Cache read-back failed after successful write.');
        }
    }
}
