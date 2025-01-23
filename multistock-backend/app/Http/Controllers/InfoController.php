<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

class InfoController extends Controller
{
    public function getInfo()
    {
        try {
            return response()->json([
                'status' => 'ok',
                'uptime' => $this->getUptime(),
                'database_status' => $this->checkDatabase(),
                'cache_status' => $this->checkCache(),
                'redis_status' => $this->checkRedis(),
                'queue_status' => $this->checkQueue(),
                'memory_usage' => $this->getMemoryUsage(),
                'disk_space' => $this->getDiskSpace(),
                'laravel_version' => app()->version(),
                'php_version' => phpversion(),
                'environment' => app()->environment(),
                'database_name' => $this->getDatabaseName(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to check the database status
    private function checkDatabase()
    {
        try {
            // Check if the database connection works
            DB::connection()->getPdo();
            return 'conectado'; // 'connected' in Spanish
        } catch (\Exception $e) {
            return 'desconectado'; // 'disconnected' in Spanish
        }
    }

    // Method to check the cache status
    private function checkCache()
    {
        try {
            // Try to get a cache value
            Cache::put('test', 'test', 60); // Put a test value
            if (Cache::get('test') === 'test') {
                return 'conectado'; // 'connected' in Spanish
            }
        } catch (\Exception $e) {
            return 'desconectado'; // 'disconnected' in Spanish
        }
        return 'no conectado'; // 'not connected' in Spanish
    }

    // Method to check the Redis status
    private function checkRedis()
    {
        if (!class_exists('Redis')) {
            return 'Extensión Redis no instalada'; // 'Redis extension not installed' in Spanish
        }

        try {
            Redis::ping();
            return 'conectado'; // 'connected' in Spanish
        } catch (\Exception $e) {
            return 'desconectado'; // 'disconnected' in Spanish
        }
    }

    // Method to check the queue status
    private function checkQueue()
    {
        // Try to connect and process a queue job
        try {
            Artisan::call('queue:work', ['--once' => true]); // Run a queue job once
            return 'funcionando'; // 'working' in Spanish
        } catch (\Exception $e) {
            return 'no funcionando'; // 'not working' in Spanish
        }
    }

    // Method to get memory usage
    private function getMemoryUsage()
    {
        return [
            'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB', // Memory usage in MB
            'memory_limit' => ini_get('memory_limit'), // PHP memory limit
        ];
    }

    // Method to get disk space
    private function getDiskSpace()
    {
        return [
            'total' => disk_total_space('/') / 1024 / 1024 / 1024 . ' GB', // Total space in GB
            'free' => disk_free_space('/') / 1024 / 1024 / 1024 . ' GB', // Free space in GB
        ];
    }

    // Method to get system uptime
    private function getUptime()
    {
        return shell_exec('uptime'); // Only available on Unix/Linux servers
    }

    // Method to get the database name
    private function getDatabaseName()
    {
        return DB::connection()->getDatabaseName();
    }
}
