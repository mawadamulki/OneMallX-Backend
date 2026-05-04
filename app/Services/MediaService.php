<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    /**
     * Permanently removes every media row (including soft-deleted) and deletes matching files on the public disk when possible.
     *
     * @return array{message: string, deletedRecords: int, publicDiskFilesRemoved: int}
     */
    public function deleteAllMedia(): array
    {
        $deletedRecords = 0;
        $publicDiskFilesRemoved = 0;

        Media::query()->withTrashed()->orderBy('id')->chunkById(200, function ($medias) use (&$deletedRecords, &$publicDiskFilesRemoved) {
            foreach ($medias as $media) {
                $relative = $media->publicDiskRelativePath();
                if ($relative !== null && Storage::disk('public')->exists($relative)) {
                    Storage::disk('public')->delete($relative);
                    $publicDiskFilesRemoved++;
                }

                $media->forceDelete();
                $deletedRecords++;
            }
        });

        return [
            'message' => __('app.all_media_deleted'),
            'deletedRecords' => $deletedRecords,
            'publicDiskFilesRemoved' => $publicDiskFilesRemoved,
        ];
    }
}
