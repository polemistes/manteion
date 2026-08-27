<?php

use App\Models\Tag;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the old free-form, user-typed tags with the fixed
     * category/subcategory taxonomy. This app is still in alpha
     * ("data may be deleted at any time"), so existing tags and their
     * event/contact-preference associations are wiped rather than
     * preserved — cascades clean up `event_tag` and
     * `contact_tag_preferences` via their existing foreign keys.
     */
    public function up(): void
    {
        Tag::query()->delete();

        (new CategorySeeder)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Tag::query()->delete();
    }
};
