<?php namespace ProcessWire;

/**
 * SquadCache - File-based response cache for Squad
 *
 * Stores AI responses as JSON files organized by page ID.
 * Supports TTL durations: D (day), W (week), M (month), Y (year), or seconds.
 * Auto-cleans expired entries on read.
 *
 * Cache structure:
 *   site/assets/cache/Squad/
 *   ├── 0/              (global, no page context)
 *   │   ├── a1b2c3.json
 *   │   └── d4e5f6.json
 *   ├── 1042/           (page ID 1042)
 *   │   └── f7a8b9.json
 *   └── 1085/
 *       └── c0d1e2.json
 *
 * @author Maxim Semenov <maxim@smnv.org> (smnv.org)
 * @license MIT
 */

class SquadCache {

    /** @var string Base cache directory */
    protected string $basePath;

    /** @var bool Logging enabled */
    protected bool $debug;

    /** TTL shorthand map */
    const TTL_MAP = [
        'D' => 86400,       // 1 day
        'W' => 604800,      // 7 days
        'M' => 2592000,     // 30 days
        'Y' => 31536000,    // 365 days
    ];

    public function __construct(?string $basePath = null, bool $debug = false) {
        $this->basePath = $basePath ?: wire('config')->paths->cache . 'Squad/';
        $this->debug = $debug;
    }

    /**
     * Get a cached response
     *
     * @param string $message Original message
     * @param array $options Request options (used for cache key)
     * @param int|null $pageId Page context (0 = global)
     * @return array|null Cached result or null if not found/expired
     */
    public function get(string $message, array $options = [], ?int $pageId = null): ?array {
        $pageId = $pageId ?? 0;
        $key = $this->buildKey($message, $options);
        $filePath = $this->getFilePath($pageId, $key);

        if (!file_exists($filePath)) return null;

        $data = json_decode(file_get_contents($filePath), true);
        if (!$data || !isset($data['expires_at'])) {
            $this->delete($pageId, $key);
            return null;
        }

        // Check expiry
        if (time() > $data['expires_at']) {
            $this->delete($pageId, $key);
            $this->debugLog("Cache expired: page={$pageId} key={$key}");
            return null;
        }

        $this->debugLog("Cache hit: page={$pageId} key={$key}");
        return $data['result'];
    }

    /**
     * Store a response in cache
     *
     * @param string $message Original message
     * @param array $options Request options
     * @param array $result The AI response to cache
     * @param string|int $ttl TTL: 'D', 'W', 'M', 'Y', or seconds as int
     * @param int|null $pageId Page context (0 = global)
     * @return bool
     */
    public function set(string $message, array $options, array $result, string|int $ttl, ?int $pageId = null): bool {
        $pageId = $pageId ?? 0;
        $key = $this->buildKey($message, $options);
        $seconds = $this->parseTtl($ttl);

        $data = [
            'created_at' => time(),
            'expires_at' => time() + $seconds,
            'ttl'        => $ttl,
            'ttl_seconds'=> $seconds,
            'message'    => mb_substr($message, 0, 200), // store a preview for debugging
            'provider'   => $options['provider'] ?? '',
            'model'      => $options['model'] ?? '',
            'page_id'    => $pageId,
            'result'     => $result,
        ];

        $dir = $this->getPageDir($pageId);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return false;
            }
        }

        $filePath = $this->getFilePath($pageId, $key);
        $written = file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($written) {
            $this->debugLog("Cache set: page={$pageId} key={$key} ttl={$ttl} ({$seconds}s)");
        }

        return $written !== false;
    }

    /**
     * Delete a specific cache entry
     */
    public function delete(int $pageId, string $key): bool {
        $filePath = $this->getFilePath($pageId, $key);
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }

    /**
     * Clear all cache for a specific page
     *
     * @param int $pageId
     * @return int Number of files deleted
     */
    public function clearPage(int $pageId): int {
        $dir = $this->getPageDir($pageId);
        return $this->clearDirectory($dir);
    }

    /**
     * Clear all Squad cache
     *
     * @return int Number of files deleted
     */
    public function clearAll(): int {
        $count = 0;
        if (!is_dir($this->basePath)) return 0;

        $dirs = glob($this->basePath . '*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $count += $this->clearDirectory($dir);
            @rmdir($dir); // remove empty dir
        }

        $this->debugLog("Cache cleared: {$count} files removed");
        return $count;
    }

    /**
     * Clean up all expired cache entries across all pages
     *
     * @return int Number of expired files removed
     */
    public function cleanExpired(): int {
        $count = 0;
        if (!is_dir($this->basePath)) return 0;

        $dirs = glob($this->basePath . '*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $files = glob($dir . '/*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if (!$data || !isset($data['expires_at']) || time() > $data['expires_at']) {
                    unlink($file);
                    $count++;
                }
            }
            // Remove empty dirs
            if (count(glob($dir . '/*')) === 0) {
                @rmdir($dir);
            }
        }

        if ($count) {
            $this->debugLog("Cleaned {$count} expired cache files");
        }

        return $count;
    }

    /**
     * Get cache stats
     *
     * @return array ['total_files', 'total_size', 'pages', 'expired']
     */
    public function getStats(): array {
        $stats = [
            'total_files' => 0,
            'total_size'  => 0,
            'pages'       => 0,
            'expired'     => 0,
        ];

        if (!is_dir($this->basePath)) return $stats;

        $dirs = glob($this->basePath . '*', GLOB_ONLYDIR);
        $stats['pages'] = count($dirs);

        foreach ($dirs as $dir) {
            $files = glob($dir . '/*.json');
            foreach ($files as $file) {
                $stats['total_files']++;
                $stats['total_size'] += filesize($file);

                $data = json_decode(file_get_contents($file), true);
                if (!$data || !isset($data['expires_at']) || time() > $data['expires_at']) {
                    $stats['expired']++;
                }
            }
        }

        return $stats;
    }

    // =========================================================================
    // INTERNAL
    // =========================================================================

    /**
     * Build a cache key from message + relevant options
     */
    protected function buildKey(string $message, array $options): string {
        $keyParts = [
            'msg'      => $message,
            'provider' => $options['provider'] ?? '',
            'model'    => $options['model'] ?? '',
            'system'   => $options['systemPrompt'] ?? '',
            'temp'     => $options['temperature'] ?? '',
            'history'  => !empty($options['history']) ? md5(json_encode($options['history'])) : '',
            'web'      => !empty($options['webSearch']),
            'webMax'   => (int)($options['webSearchMaxResults'] ?? 5),
        ];

        return substr(md5(json_encode($keyParts)), 0, 12);
    }

    /**
     * Parse TTL value to seconds
     *
     * @param string|int $ttl 'D', 'W', 'M', 'Y', or integer seconds
     * @return int Seconds
     */
    protected function parseTtl(string|int $ttl): int {
        if (is_int($ttl)) return max(1, $ttl);

        $ttl = strtoupper(trim($ttl));

        // Support "2D", "3W", "6M" format
        if (preg_match('/^(\d+)([DWMY])$/', $ttl, $m)) {
            $multiplier = (int)$m[1];
            $unit = $m[2];
            return $multiplier * (self::TTL_MAP[$unit] ?? 86400);
        }

        // Single letter
        if (isset(self::TTL_MAP[$ttl])) {
            return self::TTL_MAP[$ttl];
        }

        // Numeric string
        if (is_numeric($ttl)) {
            return max(1, (int)$ttl);
        }

        return 86400; // default: 1 day
    }

    protected function getPageDir(int $pageId): string {
        return $this->basePath . $pageId . '/';
    }

    protected function getFilePath(int $pageId, string $key): string {
        return $this->getPageDir($pageId) . $key . '.json';
    }

    protected function clearDirectory(string $dir): int {
        $count = 0;
        if (!is_dir($dir)) return 0;

        $files = glob($dir . '/*.json');
        foreach ($files as $file) {
            if (unlink($file)) $count++;
        }
        return $count;
    }

    protected function debugLog(string $message): void {
        if (!$this->debug) return;
        wire('log')->save('squad-debug', "[Cache] {$message}");
    }
}
