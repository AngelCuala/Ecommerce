<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Storage;

/**
 * The demo accounts' documents: a sample ID card picture and a sample business permit (database/seeders/files,
 * both marked SAMPLE ONLY). They are copied to the private disk, in the folders real uploads use, so "View" on the
 * seller's account page and on the admin's seller application opens a real file through its signed link.
 */
class DemoDocuments
{
    public const ID = 'seller-ids/sample-id.jpg';
    public const PERMIT = 'seller-permits/sample-permit.pdf';

    /** Copies the files (once) and returns their paths. */
    public static function ensure(): array
    {
        foreach ([self::ID => 'sample-id.jpg', self::PERMIT => 'sample-permit.pdf'] as $path => $file) {
            if (! Storage::disk('local')->exists($path)) {
                Storage::disk('local')->put($path, file_get_contents(__DIR__ . '/files/' . $file));
            }
        }

        return ['id' => self::ID, 'permit' => self::PERMIT];
    }
}
