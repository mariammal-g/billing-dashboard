<?php
namespace App\Services;
use App\Models\Plan;
use Illuminate\Support\Facades\Cache;
class PlanCache
{
    public function get(int $id): Plan { return Cache::remember("plan:{$id}", 3600, fn() => Plan::findOrFail($id)); }
    public function forget(int $id): void { Cache::forget("plan:{$id}"); }
}
