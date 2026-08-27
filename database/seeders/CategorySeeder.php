<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the fixed event category/subcategory taxonomy. Safe to re-run:
 * every row is upserted by its slug, so editing this array and re-running
 * the seeder is the supported way to adjust the taxonomy later. Removing a
 * category from this array does NOT delete its row — that's a deliberate
 * separate step, since it could orphan events that already use it.
 */
class CategorySeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private const TAXONOMY = [
        'Social' => ['House party', 'Dinner party', 'Celebration', 'Games', 'Bar / pub night', 'Coffee', 'Restaurant'],
        'Entertainment' => ['Concert', 'Festival', 'Film', 'Theatre', 'Performance', 'Exhibition', 'Museum', 'Literature'],
        'Recreational' => ['Sport', 'Wellness', 'Nature'],
        'Travel' => ['Day trip', 'Weekend trip', 'Road trip', 'Camping', 'Abroad'],
        'Community' => ['Volunteering', 'Fundraiser', 'Dugnad', 'Activism', 'Religious'],
        'Work' => ['Networking', 'Conference', 'Workshop', 'Meeting', 'Coworking'],
        'Studies' => ['Lecture', 'Class', 'Study group', 'Language exchange'],
        'Online' => ['Webinar', 'Livestream', 'Online game', 'Video call'],
    ];

    public function run(): void
    {
        $position = 0;

        foreach (self::TAXONOMY as $categoryName => $subcategoryNames) {
            $category = Tag::query()->updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'parent_id' => null, 'position' => $position++],
            );

            foreach ($subcategoryNames as $subcategoryName) {
                Tag::query()->updateOrCreate(
                    ['slug' => Str::slug($subcategoryName)],
                    ['name' => $subcategoryName, 'parent_id' => $category->id, 'position' => $position++],
                );
            }
        }
    }
}
