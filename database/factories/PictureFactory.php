<?php

namespace Database\Factories;

use App\Models\EventComment;
use App\Models\Leaflet;
use App\Models\Message;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A picture on record with no files behind it: enough for shapes, counts
 * and previews. A test that reads the files sends real uploads through
 * the controllers instead. Say what it is on with onMessage(), onLeaflet()
 * or onComment().
 *
 * @extends Factory<Picture>
 */
class PictureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->uuid();

        return [
            'path' => "pictures/{$name}.jpg",
            'thumb_path' => "pictures/{$name}-small.jpg",
            'width' => 1200,
            'height' => 900,
            'position' => 0,
        ];
    }

    public function onMessage(?Message $message = null): static
    {
        return $this->state(fn () => ['message_id' => $message ?? Message::factory()]);
    }

    public function onLeaflet(?Leaflet $leaflet = null): static
    {
        return $this->state(fn () => ['leaflet_id' => $leaflet ?? Leaflet::factory()]);
    }

    public function onComment(?EventComment $comment = null): static
    {
        return $this->state(fn () => ['event_comment_id' => $comment ?? EventComment::factory()]);
    }
}
