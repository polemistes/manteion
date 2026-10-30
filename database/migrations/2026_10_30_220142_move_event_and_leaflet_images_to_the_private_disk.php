<?php

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Event and leaflet pictures move off the public disk, where anyone with a
 * link could open them, to the private one, where only EventImageController
 * and LeafletImageController hand them out after checking who is asking.
 * Every file under events/ and leaflets/ moves, including leftovers of
 * deleted events and leaflets, so none stays reachable. Paths stay the
 * same, so the database needs no change. Safe to run again: whatever has
 * already moved is skipped.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $directories = ['events', 'leaflets'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! app()->runningUnitTests()) {
            $this->moveFiles(Storage::disk('public'), Storage::disk('local'));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! app()->runningUnitTests()) {
            $this->moveFiles(Storage::disk('local'), Storage::disk('public'));
        }
    }

    /**
     * Moves everything under events/ and leaflets/ from one disk to the
     * other, keeping the paths. Not done while the tests migrate their
     * database, which would move the developer's own files; the tests call
     * this on fake disks instead.
     */
    public function moveFiles(Filesystem $from, Filesystem $to): void
    {
        foreach ($this->directories as $directory) {
            foreach ($from->allFiles($directory) as $path) {
                $stream = $from->readStream($path);

                if ($stream === null || ! $to->writeStream($path, $stream)) {
                    throw new RuntimeException("Could not move {$path}.");
                }

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $from->delete($path);
            }

            $from->deleteDirectory($directory);
        }
    }
};
