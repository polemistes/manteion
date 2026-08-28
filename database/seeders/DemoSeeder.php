<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\AttendeeSource;
use App\Enums\ContactGroupType;
use App\Enums\ConversationType;
use App\Enums\EventVisibility;
use App\Enums\FollowStatus;
use App\Enums\FriendshipStatus;
use App\Models\ContactGroup;
use App\Models\ContactTagPreference;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventComment;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds a set of test contacts and events around a single real account, so
 * that account can exercise friends/followers/following, invitations,
 * RSVPs, tag preferences, comments, and searchable events without having
 * to create everything by hand. Safe to re-run: every record is looked up
 * by its natural key before creating it.
 */
class DemoSeeder extends Seeder
{
    private User $me;

    public function run(): void
    {
        $this->me = User::query()->where('email', 'post@robertemilberge.no')->firstOrFail();

        $tags = $this->seedTags();
        $people = $this->seedPeople();

        $this->seedFriendships($people);
        $this->seedFollows($people);
        $this->seedContactGroups($people);
        $this->seedEvents($people, $tags);
        $this->seedMyOwnEvent($people, $tags);
        $this->seedTagPreference($people, $tags);
        $this->seedConversations($people);
    }

    /**
     * Looks up specific tags from the fixed category taxonomy (seeded by
     * CategorySeeder, which always runs before this seeder). Keys here are
     * this seeder's own shorthand, not slugs — kept stable so the rest of
     * this file doesn't need to change if the taxonomy's wording does.
     *
     * @return array<string, Tag>
     */
    private function seedTags(): array
    {
        $slugsByKey = [
            'games' => 'games',
            'music' => 'concert',
            'volunteering' => 'volunteering',
            'sports' => 'sport',
            'reading' => 'literature',
        ];

        return collect($slugsByKey)
            ->map(fn (string $slug) => Tag::query()->where('slug', $slug)->firstOrFail())
            ->all();
    }

    /**
     * @return array<string, User>
     */
    private function seedPeople(): array
    {
        $people = [
            'alice' => ['name' => 'Alice Nilsen', 'username' => 'alice_nilsen', 'bio' => 'Board game enthusiast. Always up for a game night.'],
            'bjorn' => ['name' => 'Bjørn Haugen', 'username' => 'bjorn_haugen', 'bio' => null],
            'camilla' => ['name' => 'Camilla Iversen', 'username' => 'camilla_iversen', 'bio' => null],
            'erik' => ['name' => 'Erik Solberg', 'username' => 'erik_solberg', 'bio' => null],
            'frida' => ['name' => 'Frida Berg', 'username' => 'frida_berg', 'bio' => null],
            'gunnar' => ['name' => 'Gunnar Vik', 'username' => 'gunnar_vik', 'bio' => null],
            'henrik' => ['name' => 'Henrik Moen', 'username' => 'henrik_moen', 'bio' => 'Organizing neighborhood cleanups.'],
            'ingrid' => ['name' => 'Ingrid Dahl', 'username' => 'ingrid_dahl', 'bio' => 'Musician. Runs the local open mic.'],
            'johan' => ['name' => 'Johan Berge', 'username' => 'johan_berge', 'bio' => null, 'allow_public_follow' => false],
            'stranger' => ['name' => 'Sofie Kristiansen', 'username' => 'sofie_kristiansen', 'bio' => null],
        ];

        return collect($people)->mapWithKeys(function (array $attributes, string $key) {
            $user = User::query()->firstOrCreate(
                ['email' => "{$key}.demo@example.test"],
                [
                    'name' => $attributes['name'],
                    'username' => $attributes['username'],
                    'password' => 'password',
                    'allow_public_follow' => $attributes['allow_public_follow'] ?? true,
                    'bio' => $attributes['bio'],
                ]
            );

            return [$key => $user];
        })->all();
    }

    /**
     * @param  array<string, User>  $people
     */
    private function seedFriendships(array $people): void
    {
        foreach (['alice', 'bjorn', 'camilla'] as $key) {
            Friendship::query()->firstOrCreate(
                ['requester_id' => $this->me->id, 'addressee_id' => $people[$key]->id],
                ['status' => FriendshipStatus::Accepted]
            );
        }

        // A request someone else sent me, still waiting on my response.
        Friendship::query()->firstOrCreate(
            ['requester_id' => $people['stranger']->id, 'addressee_id' => $this->me->id],
            ['status' => FriendshipStatus::Pending]
        );

        // A request I sent that's still waiting on theirs.
        Friendship::query()->firstOrCreate(
            ['requester_id' => $this->me->id, 'addressee_id' => $people['gunnar']->id],
            ['status' => FriendshipStatus::Pending]
        );
    }

    /**
     * @param  array<string, User>  $people
     */
    private function seedFollows(array $people): void
    {
        // People who follow me.
        Follow::query()->firstOrCreate(
            ['follower_id' => $people['erik']->id, 'followed_id' => $this->me->id],
            ['status' => FollowStatus::Accepted]
        );
        Follow::query()->firstOrCreate(
            ['follower_id' => $people['frida']->id, 'followed_id' => $this->me->id],
            ['status' => FollowStatus::Accepted]
        );
        Follow::query()->firstOrCreate(
            ['follower_id' => $people['gunnar']->id, 'followed_id' => $this->me->id],
            ['status' => FollowStatus::Pending]
        );

        // People I follow.
        Follow::query()->firstOrCreate(
            ['follower_id' => $this->me->id, 'followed_id' => $people['henrik']->id],
            ['status' => FollowStatus::Accepted]
        );
        Follow::query()->firstOrCreate(
            ['follower_id' => $this->me->id, 'followed_id' => $people['ingrid']->id],
            ['status' => FollowStatus::Accepted]
        );
        Follow::query()->firstOrCreate(
            ['follower_id' => $this->me->id, 'followed_id' => $people['johan']->id],
            ['status' => FollowStatus::Pending]
        );
    }

    /**
     * @param  array<string, User>  $people
     */
    private function seedContactGroups(array $people): void
    {
        $friendGroup = ContactGroup::query()->firstOrCreate(
            ['user_id' => $this->me->id, 'type' => ContactGroupType::Friend, 'name' => 'Game night crew'],
        );
        $friendGroup->members()->syncWithoutDetaching([$people['alice']->id, $people['bjorn']->id]);

        $followingGroup = ContactGroup::query()->firstOrCreate(
            ['user_id' => $this->me->id, 'type' => ContactGroupType::Following, 'name' => 'Local creators'],
        );
        $followingGroup->members()->syncWithoutDetaching([$people['henrik']->id, $people['ingrid']->id]);
    }

    /**
     * @param  array<string, User>  $people
     * @param  array<string, Tag>  $tags
     */
    private function seedEvents(array $people, array $tags): void
    {
        $boardGameNight = $this->makeEvent($people['alice'], [
            'name' => 'Board Game Night',
            'place' => "Alice's apartment",
            'description' => "Bring your favorite game. I'll supply snacks and drinks.",
            'starts_at' => now()->addDays(3)->setTime(18, 30),
            'visibility' => EventVisibility::Private,
            'allow_invitee_comments' => true,
        ], [$tags['games']]);
        $this->inviteMe($boardGameNight, $people['alice'], AttendanceStatus::NoResponse);
        $this->comment($boardGameNight, $people['alice'], 'Starting at 6:30 sharp, don\'t be late!');

        $cabinWeekend = $this->makeEvent($people['bjorn'], [
            'name' => 'Cabin Weekend',
            'place' => 'Haugen family cabin, Gol',
            'description' => 'A weekend away from it all. Bring warm clothes.',
            'starts_at' => now()->addDays(10)->setTime(15, 0),
            'visibility' => EventVisibility::Private,
            'allow_invitee_invites' => true,
            'allow_invitee_comments' => true,
        ], []);
        $this->inviteMe($cabinWeekend, $people['bjorn'], AttendanceStatus::Attending);
        $this->comment($cabinWeekend, $this->me, 'Counting the days! Should I bring the board games too?');
        $this->comment($cabinWeekend, $people['bjorn'], 'Good idea, always room for those.');

        $birthdayDinner = $this->makeEvent($people['camilla'], [
            'name' => 'Birthday Dinner',
            'place' => 'Restaurant Fyr, downtown',
            'description' => 'Turning 30! Come celebrate with me.',
            'starts_at' => now()->addDays(5)->setTime(19, 0),
            'visibility' => EventVisibility::Private,
            'allow_invitee_invites' => true,
            'allow_invitee_comments' => true,
        ], []);
        $this->inviteMe($birthdayDinner, $people['camilla'], AttendanceStatus::Interested);

        $communityCleanup = $this->makeEvent($people['henrik'], [
            'name' => 'Community Cleanup',
            'place' => 'Riverside Park',
            'description' => 'Bring gloves. We supply bags and tools.',
            'starts_at' => now()->addDays(7)->setTime(10, 0),
            'visibility' => EventVisibility::Public,
        ], [$tags['volunteering']]);
        $this->comment($communityCleanup, $people['erik'], "I'll bring my own gloves and an extra pair to share.");
        $this->comment($communityCleanup, $people['frida'], 'Count me in, see you all there!');

        $openMicNight = $this->makeEvent($people['ingrid'], [
            'name' => 'Open Mic Night',
            'place' => 'Cafe Sørhus',
            'description' => 'Sign up at the door. All instruments welcome.',
            'starts_at' => now()->addDays(14)->setTime(20, 0),
            'visibility' => EventVisibility::Searchable,
            'searchable_paid_at' => now(),
        ], [$tags['music']]);
        $this->comment($openMicNight, $this->me, "Wouldn't miss it, see you there!");

        $this->makeEvent($people['ingrid'], [
            'name' => 'Book Club Meetup',
            'place' => 'City Library',
            'description' => 'This month: a Norwegian crime novel.',
            'starts_at' => now()->addDays(9)->setTime(17, 0),
            'visibility' => EventVisibility::Public,
        ], [$tags['reading']]);

        $cityMarathon = $this->makeEvent($people['stranger'], [
            'name' => 'City Marathon',
            'place' => 'City center start line',
            'description' => 'Annual marathon, half marathon, and 10k routes.',
            'starts_at' => now()->addDays(20)->setTime(9, 0),
            'visibility' => EventVisibility::Searchable,
            'searchable_paid_at' => now(),
        ], [$tags['sports']]);
        $this->comment($cityMarathon, $people['bjorn'], 'Signed up for the half marathon, who else is running?');
    }

    /**
     * An event I organize myself, so other people's RSVPs and comments show
     * up on my own content too (useful for exercising moderation flows
     * against content I own, not just content I'm invited to).
     *
     * @param  array<string, User>  $people
     * @param  array<string, Tag>  $tags
     */
    private function seedMyOwnEvent(array $people, array $tags): void
    {
        $housewarming = Event::query()->firstOrCreate(
            ['user_id' => $this->me->id, 'name' => 'Housewarming Party'],
            [
                'place' => 'My place',
                'description' => 'Finally settled in, come see the new apartment. Drinks and snacks provided.',
                'starts_at' => now()->addDays(6)->setTime(18, 0),
                'visibility' => EventVisibility::Public,
                'allow_invitee_invites' => false,
                'allow_invitee_comments' => true,
            ]
        );
        $housewarming->tags()->syncWithoutDetaching([$tags['games']->id]);

        $this->attend($housewarming, $people['alice'], AttendanceStatus::Attending, AttendeeSource::Follower);
        $this->attend($housewarming, $people['bjorn'], AttendanceStatus::Attending, AttendeeSource::Follower);
        $this->attend($housewarming, $people['frida'], AttendanceStatus::Interested, AttendeeSource::Follower);
        $this->attend($housewarming, $people['gunnar'], AttendanceStatus::NotAttending, AttendeeSource::Follower);

        $this->comment($housewarming, $people['alice'], "Can't wait to see the place!");
        $this->comment($housewarming, $people['frida'], 'Should I bring anything?');
        $this->comment($housewarming, $this->me, "Just yourselves, I've got it covered!");
    }

    private function attend(Event $event, User $user, AttendanceStatus $status, AttendeeSource $source): void
    {
        EventAttendee::query()->firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['status' => $status, 'source' => $source]
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, Tag>  $tags
     */
    private function makeEvent(User $owner, array $attributes, array $tags): Event
    {
        $event = Event::query()->firstOrCreate(
            ['user_id' => $owner->id, 'name' => $attributes['name']],
            [
                'place' => $attributes['place'],
                'description' => $attributes['description'],
                'starts_at' => $attributes['starts_at'],
                'ends_at' => $attributes['ends_at'] ?? null,
                'visibility' => $attributes['visibility'],
                'allow_invitee_invites' => $attributes['allow_invitee_invites'] ?? false,
                'allow_invitee_comments' => $attributes['allow_invitee_comments'] ?? true,
                'searchable_paid_at' => $attributes['searchable_paid_at'] ?? null,
            ]
        );

        if ($tags !== []) {
            $event->tags()->syncWithoutDetaching(collect($tags)->pluck('id'));
        }

        return $event;
    }

    private function inviteMe(Event $event, User $invitedBy, AttendanceStatus $status): void
    {
        EventAttendee::query()->firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $this->me->id],
            ['invited_by_user_id' => $invitedBy->id, 'status' => $status, 'source' => AttendeeSource::Invited]
        );
    }

    private function comment(Event $event, User $author, string $body): void
    {
        EventComment::query()->firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $author->id, 'body' => $body],
        );
    }

    /**
     * @param  array<string, User>  $people
     * @param  array<string, Tag>  $tags
     */
    private function seedTagPreference(array $people, array $tags): void
    {
        // I only want to hear about Ingrid's music events, not her book club.
        ContactTagPreference::query()->firstOrCreate([
            'user_id' => $this->me->id,
            'contact_user_id' => $people['ingrid']->id,
            'tag_id' => $tags['music']->id,
        ]);
    }

    /**
     * @param  array<string, User>  $people
     */
    private function seedConversations(array $people): void
    {
        // Messages are given explicit, strictly increasing timestamps
        // (rather than relying on wall-clock time during seeding) so the
        // read/unread state below is deterministic.
        $ago = fn (int $minutes) => Carbon::now()->subMinutes($minutes);

        $withAlice = Conversation::findOrCreateDirect($this->me, $people['alice']);
        $this->message($withAlice, $this->me, 'Hey, are you still hosting board game night this week?', $ago(90));
        $this->message($withAlice, $people['alice'], 'Yes! Same time as always. Bring Catan if you still have it.', $ago(85));
        $this->message($withAlice, $this->me, 'Will do, see you then.', $ago(80));
        // Alice's last reply is left unread on my side, so the inbox badge
        // has something to show.
        $this->message($withAlice, $people['alice'], 'Actually, could you also bring some snacks?', $ago(10));
        $this->markRead($withAlice, $this->me, $ago(80));
        $this->markRead($withAlice, $people['alice'], $ago(10));

        $withBjorn = Conversation::findOrCreateDirect($this->me, $people['bjorn']);
        $this->message($withBjorn, $people['bjorn'], "Cabin's booked for the weekend. Need anything from the shop on the way up?", $ago(120));
        $this->message($withBjorn, $this->me, 'Just firewood if the cabin\'s low. Otherwise we\'re set.', $ago(115));
        $this->message($withBjorn, $people['bjorn'], 'Noted, see you Friday.', $ago(110));
        $this->markRead($withBjorn, $this->me, $ago(110));
        $this->markRead($withBjorn, $people['bjorn'], $ago(110));

        $withErik = Conversation::findOrCreateDirect($this->me, $people['erik']);
        $this->message($withErik, $people['erik'], 'Hey, I started following your events. Nice picnic idea last month!', $ago(200));
        $this->message($withErik, $this->me, 'Thanks! More coming up soon.', $ago(195));
        $this->markRead($withErik, $this->me, $ago(195));
        $this->markRead($withErik, $people['erik'], $ago(195));

        $cabinCrew = Conversation::query()->firstOrCreate(
            ['type' => ConversationType::Group, 'creator_id' => $people['bjorn']->id, 'name' => 'Cabin Weekend Crew'],
        );
        foreach ([$this->me, $people['bjorn'], $people['camilla']] as $participant) {
            $cabinCrew->participants()->firstOrCreate(['user_id' => $participant->id]);
        }
        $this->message($cabinCrew, $people['bjorn'], "Group chat for cabin weekend logistics. Camilla, you're driving?", $ago(130));
        $this->message($cabinCrew, $people['camilla'], 'Yep, I can take two more in the car.', $ago(125));
        $this->message($cabinCrew, $this->me, "I'll grab a seat, thanks Camilla!", $ago(120));
        $this->markRead($cabinCrew, $this->me, $ago(120));
        $this->markRead($cabinCrew, $people['bjorn'], $ago(120));
        $this->markRead($cabinCrew, $people['camilla'], $ago(120));
    }

    private function message(Conversation $conversation, User $author, string $body, Carbon $at): void
    {
        $message = $conversation->messages()->firstOrCreate([
            'user_id' => $author->id,
            'body' => $body,
        ]);

        $message->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    private function markRead(Conversation $conversation, User $user, Carbon $at): void
    {
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['last_read_at' => $at]);
    }
}
