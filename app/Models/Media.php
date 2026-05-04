<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mediableType',
        'mediableID',
        'fileType',
        'url',
    ];

    /**
     * The `url` column holds the path on the public disk (e.g. floors/1/abc.png), or a legacy full URL.
     * During HTTP, links use the request host (Postman/browser) so they work even when CI/CD leaves APP_URL as localhost.
     * In console/queue, falls back to the `public` disk URL from config.
     */
    public function getUrlAttribute(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $path = $this->publicStoragePathFromStoredValue($value);
        if ($path !== null) {
            return $this->absolutePublicStorageUrl($path);
        }

        return $value;
    }

    /**
     * @return non-empty-string|null Relative path under storage/app/public (no "/storage" prefix)
     */
    private function publicStoragePathFromStoredValue(string $stored): ?string
    {
        if (! preg_match('#^https?://#i', $stored)) {
            return ltrim(str_replace('\\', '/', $stored), '/');
        }

        if (preg_match('#/storage/(.+)$#', $stored, $m)) {
            return $m[1];
        }

        return null;
    }

    private function absolutePublicStorageUrl(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (! app()->runningInConsole()) {
            $request = request();
            $host = $request?->getHttpHost();
            if ($host !== null && $host !== '') {
                $root = $request->getScheme().'://'.$host;

                return rtrim($root, '/').'/storage/'.$path;
            }
        }

        return Storage::disk('public')->url($path);
    }

    public function mediable()
    {
        return $this->morphTo(__FUNCTION__, 'mediableType', 'mediableID');
    }

    /**
     * Path relative to storage/app/public when the row points at the public disk (bypasses url accessor).
     */
    public function publicDiskRelativePath(): ?string
    {
        $stored = $this->getRawOriginal('url');
        if ($stored === null || $stored === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $stored)) {
            return ltrim(str_replace('\\', '/', $stored), '/');
        }

        if (preg_match('#/storage/(.+)$#', $stored, $m)) {
            return $m[1];
        }

        return null;
    }
}

