<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceVisibility;
use App\Enums\AttendeeSource;
use App\Enums\ConversationType;
use App\Enums\EventVisibility;
use App\Enums\FollowStatus;
use App\Enums\FriendshipStatus;
use App\Enums\LeafletVisibility;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventComment;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\Message;
use App\Models\Reaction;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Notifications\FollowedUserPublishedEvent;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use App\Notifications\InvitedToEvent;
use App\Notifications\NewEventComment;
use App\Notifications\NewFollower;
use App\Notifications\NewLeafletResponse;
use App\Services\ImageProcessor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use ErrorException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds current, lived-in demo content around the real account (Robert)
 * and Alice Nilsen, the account screenshots log in as, so the app can be
 * judged and photographed the way people would actually use it: friends
 * and open requests, ten events from a recent hike through this evening's
 * pub quiz to a trip next month (three with a photo, two with a poster),
 * chats with unread messages and reactions, leaflets with replies, and an
 * Activity page of real notifications.
 *
 * Every date is relative to when it runs (event days and clock times in the
 * display time zone), so running it again later moves the events back
 * ahead and the chats back to yesterday and today. Safe to re-run: every
 * record is found again by its natural key (an event by host and name, a
 * message by conversation, author and text, a notification by kind, actor
 * and subject, and so on) and updated in place, read state included, and
 * nothing is ever deleted. It builds on DemoSeeder's people, creating any
 * that are missing the way DemoSeeder does, and is not part of
 * DatabaseSeeder:
 *
 *     php artisan db:seed --class=ShowcaseSeeder
 *
 * Pictures are fetched from Lorem Picsum (a drawn stand-in when it cannot
 * be reached) and stored through ImageProcessor exactly as uploads are.
 * Notifications go through their real classes, sent at once on the
 * database channel alone, so nothing is queued and no push goes out.
 */
class ShowcaseSeeder extends Seeder
{
    /**
     * The sizes pictures are fetched at. A landscape photo fills an event
     * card's 3:2 cover; a portrait poster is letterboxed, shown whole.
     *
     * @var array{int<1, max>, int<1, max>}
     */
    private const PHOTO = [1600, 1000];

    /**
     * @var array{int<1, max>, int<1, max>}
     */
    private const POSTER = [1000, 1500];

    /**
     * @var array{int<1, max>, int<1, max>}
     */
    private const LEAFLET_PICTURE = [1600, 1067];

    /**
     * Everyone involved, by short name: "robert" for the real account and
     * DemoSeeder's people by first name.
     *
     * @var array<string, User>
     */
    private array $people = [];

    private CarbonImmutable $now;

    /**
     * The start of today in the display time zone.
     */
    private CarbonImmutable $today;

    public function __construct(private ImageProcessor $imageProcessor) {}

    public function run(): void
    {
        $this->now = CarbonImmutable::now();
        $this->today = $this->now->setTimezone(config()->string('app.display_timezone'))->startOfDay();
        $this->people = $this->seedPeople();

        $friendships = $this->seedFriendships();
        $follows = $this->seedFollows();
        $events = $this->seedEvents();
        $this->seedGuestLists($events);
        $comments = $this->seedEventComments($events);
        $this->seedConversations();
        $responses = $this->seedLeaflets();

        $this->seedActivity($friendships, $follows, $events, $comments, $responses);
    }

    /**
     * The real account and DemoSeeder's people, found by username. Anyone
     * missing is created as DemoSeeder creates them (same email, password
     * "password"), so DemoSeeder later finds them too.
     *
     * @return array<string, User>
     */
    private function seedPeople(): array
    {
        $demoPeople = [
            'alice' => ['name' => 'Alice Nilsen', 'username' => 'alice_nilsen', 'email' => 'alice.demo@example.test', 'bio' => 'Board game enthusiast. Always up for a game night.'],
            'bjorn' => ['name' => 'Bjørn Haugen', 'username' => 'bjorn_haugen', 'email' => 'bjorn.demo@example.test', 'bio' => null],
            'camilla' => ['name' => 'Camilla Iversen', 'username' => 'camilla_iversen', 'email' => 'camilla.demo@example.test', 'bio' => null],
            'erik' => ['name' => 'Erik Solberg', 'username' => 'erik_solberg', 'email' => 'erik.demo@example.test', 'bio' => null],
            'frida' => ['name' => 'Frida Berg', 'username' => 'frida_berg', 'email' => 'frida.demo@example.test', 'bio' => null],
            'gunnar' => ['name' => 'Gunnar Vik', 'username' => 'gunnar_vik', 'email' => 'gunnar.demo@example.test', 'bio' => null],
            'henrik' => ['name' => 'Henrik Moen', 'username' => 'henrik_moen', 'email' => 'henrik.demo@example.test', 'bio' => 'Organizing neighborhood cleanups.'],
            'ingrid' => ['name' => 'Ingrid Dahl', 'username' => 'ingrid_dahl', 'email' => 'ingrid.demo@example.test', 'bio' => 'Musician. Runs the local open mic.'],
            'sofie' => ['name' => 'Sofie Kristiansen', 'username' => 'sofie_kristiansen', 'email' => 'stranger.demo@example.test', 'bio' => null],
        ];

        $people = collect($demoPeople)
            ->map(fn (array $person) => User::query()->firstOrCreate(
                ['username' => $person['username']],
                ['name' => $person['name'], 'email' => $person['email'], 'password' => 'password', 'bio' => $person['bio']],
            ))
            ->all();

        $people['robert'] = User::query()->where('email', 'post@robertemilberge.no')->firstOrFail();

        return $people;
    }

    /**
     * Alice's friends (Robert among them, as DemoSeeder made him), the
     * friendships among them that keep every invitation below between
     * friends as the app requires, Robert's DemoSeeder friendships those
     * invitations rely on, and Sofie's request to Alice, still open.
     *
     * @return array<string, Friendship> keyed "requester:addressee"
     */
    private function seedFriendships(): array
    {
        $friendships = [
            'robert:alice' => FriendshipStatus::Accepted,
            'robert:bjorn' => FriendshipStatus::Accepted,
            'robert:camilla' => FriendshipStatus::Accepted,
            'alice:bjorn' => FriendshipStatus::Accepted,
            'alice:camilla' => FriendshipStatus::Accepted,
            'erik:alice' => FriendshipStatus::Accepted,
            'alice:frida' => FriendshipStatus::Accepted,
            'ingrid:alice' => FriendshipStatus::Accepted,
            'bjorn:camilla' => FriendshipStatus::Accepted,
            'erik:bjorn' => FriendshipStatus::Accepted,
            'camilla:erik' => FriendshipStatus::Accepted,
            'frida:camilla' => FriendshipStatus::Accepted,
            'ingrid:frida' => FriendshipStatus::Accepted,
            'sofie:alice' => FriendshipStatus::Pending,
        ];

        return collect($friendships)
            ->map(function (FriendshipStatus $status, string $pair) {
                [$requester, $addressee] = explode(':', $pair);

                return $this->befriend($this->people[$requester], $this->people[$addressee], $status);
            })
            ->all();
    }

    /**
     * The one friendship between two people, whichever of them asked
     * before, made to say who asked whom here and with what answer.
     */
    private function befriend(User $requester, User $addressee, FriendshipStatus $status): Friendship
    {
        $friendship = Friendship::query()
            ->where(fn (Builder $query) => $query->where('requester_id', $requester->id)->where('addressee_id', $addressee->id))
            ->orWhere(fn (Builder $query) => $query->where('requester_id', $addressee->id)->where('addressee_id', $requester->id))
            ->orderByRaw('requester_id = ? desc', [$requester->id])
            ->first() ?? new Friendship;

        $friendship->fill(['requester_id' => $requester->id, 'addressee_id' => $addressee->id, 'status' => $status])->save();

        return $friendship;
    }

    /**
     * Alice follows Ingrid, Henrik follows Alice, and Gunnar has asked to.
     * A follow request only arises when the person followed approves their
     * followers, so Alice is set to approve hers. Robert's DemoSeeder
     * follows that the events and activity below rely on are kept too.
     *
     * @return array<string, Follow> keyed "follower:followed"
     */
    private function seedFollows(): array
    {
        $this->people['alice']->update(['allow_public_follow' => false]);

        $follows = [
            'alice:ingrid' => FollowStatus::Accepted,
            'henrik:alice' => FollowStatus::Accepted,
            'gunnar:alice' => FollowStatus::Pending,
            'robert:ingrid' => FollowStatus::Accepted,
            'robert:henrik' => FollowStatus::Accepted,
            'erik:robert' => FollowStatus::Accepted,
            'frida:robert' => FollowStatus::Accepted,
        ];

        return collect($follows)
            ->map(function (FollowStatus $status, string $pair) {
                [$follower, $followed] = explode(':', $pair);

                return Follow::query()->updateOrCreate(
                    ['follower_id' => $this->people[$follower]->id, 'followed_id' => $this->people[$followed]->id],
                    ['status' => $status],
                );
            })
            ->all();
    }

    /**
     * Ten events: a hike on the Saturday before this week (always safely
     * past, comments and all), a pub quiz this evening, a run tomorrow,
     * Alice's taco night later this week (the coming Friday, or two days
     * ahead once Friday is that close), Robert's sauna, Ingrid's concert
     * and a cabin weekend next week, a pottery evening and a dugnad the
     * week after, and a trip to Bergen next month.
     *
     * @return array<string, Event>
     */
    private function seedEvents(): array
    {
        $thisMonday = $this->today->startOfWeek(CarbonInterface::MONDAY);
        $lastSaturday = $thisMonday->subDays(2);
        $nextMonday = $thisMonday->addWeek();
        $mondayAfterNext = $thisMonday->addWeeks(2);
        $weekday = $this->today->dayOfWeekIso;
        $laterThisWeek = $this->today->addDays($weekday <= 3 ? 5 - $weekday : 2);
        $bergenFriday = $thisMonday->addWeeks(5)->addDays(4);

        $events = [
            'hike' => $this->makeEvent('camilla', 'Hike from Sognsvann to Ullevålseter', [
                'place' => 'Sognsvann metro station',
                'latitude' => 59.9676,
                'longitude' => 10.7339,
                'description' => "A relaxed hike through Nordmarka: from Sognsvann up to Ullevålseter for waffles, then back the same way. About 12 km in all.\n\nMeet at the metro station at ten. Bring a packed lunch and good shoes, the trail can be muddy.",
                'starts_at' => $this->at($lastSaturday, '10:00'),
                'ends_at' => $this->at($lastSaturday, '16:00'),
                'visibility' => EventVisibility::Private,
            ], ['nature'], createdAt: $this->ago(days: 14)),

            'quiz' => $this->makeEvent('bjorn', 'Pub quiz at Schouskjelleren', [
                'place' => 'Schouskjelleren Mikrobryggeri, Trondheimsveien 2',
                'latitude' => 59.9183,
                'longitude' => 10.7601,
                'description' => "The quiz starts at seven sharp, so let's meet outside at half past six and grab a table before it fills up.\n\nTeams of up to six. Bring your best useless facts. The first round is on me.",
                'starts_at' => $this->at($this->today, '19:00'),
                'visibility' => EventVisibility::Private,
            ], ['bar-pub-night', 'games'], createdAt: $this->ago(days: 6)),

            'run' => $this->makeEvent('frida', 'Evening run along Akerselva', [
                'place' => 'Vulkan, by Mathallen',
                'latitude' => 59.9222,
                'longitude' => 10.7516,
                'description' => "An easy 8 km along the river, from Vulkan up to Nydalen and back. Conversational pace, and nobody gets left behind.\n\nBring a headlamp: parts of the path are dark once the sun is down.",
                'starts_at' => $this->at($this->today->addDay(), '17:30'),
                'ends_at' => $this->at($this->today->addDay(), '19:00'),
                'visibility' => EventVisibility::Public,
            ], ['sport'], createdAt: $this->ago(days: 3)),

            'tacos' => $this->makeEvent('alice', "Tacos and board games at Alice's", [
                'place' => "Alice's flat, Thorvald Meyers gate",
                'latitude' => 59.9234,
                'longitude' => 10.7588,
                'description' => "Taco night, Norwegian style: far too many toppings, and nobody leaves hungry.\n\nAfterwards the board games come out. Wingspan, Catan, or whatever you bring along. I'll have everything for the tacos; bring something to drink and your competitive spirit.",
                'starts_at' => $this->at($laterThisWeek, '18:30'),
                'ends_at' => $this->at($laterThisWeek, '23:00'),
                'visibility' => EventVisibility::Private,
            ], ['dinner-party', 'games'], createdAt: $this->ago(days: 3, hours: 1)),

            'sauna' => $this->makeEvent('robert', 'Sauna and fjord swim at Sørenga', [
                'place' => 'Sørenga sjøbad',
                'latitude' => 59.9028,
                'longitude' => 10.7520,
                'description' => "A couple of hours in the floating sauna, with as many dips in the fjord as you dare. I've booked the whole sauna, so there's room for eight.\n\nBring a towel, a warm hat and something to drink afterwards.",
                'starts_at' => $this->at($nextMonday->addDays(2), '18:00'),
                'ends_at' => $this->at($nextMonday->addDays(2), '20:00'),
                'visibility' => EventVisibility::Public,
            ], ['wellness'], createdAt: $this->ago(days: 5)),

            'concert' => $this->makeEvent('ingrid', 'Ingrid Dahl Trio: Songs from the North', [
                'place' => 'Kulturkirken Jakob, Hausmanns gate 14',
                'latitude' => 59.9170,
                'longitude' => 10.7547,
                'description' => "An evening of new songs and old favourites, with Jonas on double bass and Mari on piano. We'll also play a brand new song for the very first time.\n\nDoors open at 19:00 and the music starts at 19:30. Tickets at the door: pay what you can.",
                'starts_at' => $this->at($nextMonday->addDays(3), '19:30'),
                'ends_at' => $this->at($nextMonday->addDays(3), '21:30'),
                'visibility' => EventVisibility::Searchable,
            ], ['concert'], createdAt: $this->ago(hours: 26)),

            'cabin' => $this->makeEvent('erik', 'Cabin weekend at Norefjell', [
                'place' => 'The Solberg family cabin, Norefjell',
                'latitude' => 60.2224,
                'longitude' => 9.5486,
                'city' => 'Krødsherad',
                'description' => "Three days at our family cabin: hikes, sauna, waffles and absolutely no email. The cabin sleeps six, and the sauna is finally fixed.\n\nCamilla is driving up on Friday afternoon. Ask in the group chat if you need a lift.",
                'starts_at' => $this->at($nextMonday->addDays(4), '16:00'),
                'ends_at' => $this->at($nextMonday->addDays(6), '15:00'),
                'visibility' => EventVisibility::Private,
                'allow_invitee_invites' => true,
            ], ['weekend-trip', 'nature'], createdAt: $this->ago(days: 8)),

            'pottery' => $this->makeEvent('camilla', "Beginners' pottery evening", [
                'place' => 'Ceramics studio, Markveien 35',
                'latitude' => 59.9243,
                'longitude' => 10.7567,
                'description' => "I've booked the wheel studio for a beginners' evening. An instructor shows us the basics, and everyone goes home with a bowl (or something bowl-shaped).\n\nClay, glazes and aprons are included. Wear clothes you don't mind getting muddy.",
                'starts_at' => $this->at($mondayAfterNext->addDay(), '18:00'),
                'ends_at' => $this->at($mondayAfterNext->addDay(), '21:00'),
                'visibility' => EventVisibility::Private,
            ], ['class'], createdAt: $this->ago(hours: 7)),

            'dugnad' => $this->makeEvent('henrik', 'Dugnad in Sofienbergparken', [
                'place' => 'Sofienbergparken, by the playground',
                'latitude' => 59.9224,
                'longitude' => 10.7660,
                'description' => "Our neighbourhood dugnad: we rake leaves, fix the benches by the playground and tidy the flower beds. Rakes, bags and gloves are provided.\n\nCoffee and waffles afterwards for everyone who helped.",
                'starts_at' => $this->at($mondayAfterNext->addDays(5), '11:00'),
                'ends_at' => $this->at($mondayAfterNext->addDays(5), '14:00'),
                'visibility' => EventVisibility::Public,
            ], ['dugnad'], createdAt: $this->ago(days: 3, minutes: 10)),

            'bergen' => $this->makeEvent('frida', 'Weekend in Bergen', [
                'place' => 'Bryggen, Bergen',
                'latitude' => 60.3975,
                'longitude' => 5.3244,
                'city' => 'Bergen',
                'description' => "The train over the mountains on Friday afternoon, back on Sunday evening. Fish market, Fløyen, and with luck at least one hour without rain.\n\nI've found a flat near Bryggen that sleeps four. Let me know soon so I can book the train tickets.",
                'starts_at' => $this->at($bergenFriday, '15:00'),
                'ends_at' => $this->at($bergenFriday->addDays(2), '19:00'),
                'visibility' => EventVisibility::Private,
            ], ['weekend-trip'], createdAt: $this->ago(days: 5, hours: 2)),
        ];

        $this->attachPicture($events['quiz'], 1073, self::POSTER);
        $this->attachPicture($events['concert'], 1022, self::POSTER);
        $this->attachPicture($events['sauna'], 1051, self::PHOTO);
        $this->attachPicture($events['cabin'], 1044, self::PHOTO);
        $this->attachPicture($events['bergen'], 1015, self::PHOTO);

        return $events;
    }

    /**
     * An event, found by host and name, set to these details with its tags
     * and their categories (as the event form stores them). Public and
     * searchable events count as already announced to the host's
     * followers, so a later edit does not announce them again.
     *
     * @param  array<string, mixed>  $details
     * @param  list<string>  $tagSlugs
     */
    private function makeEvent(string $hostKey, string $name, array $details, array $tagSlugs, CarbonImmutable $createdAt): Event
    {
        $visibility = $details['visibility'];

        $event = Event::query()->updateOrCreate(
            ['user_id' => $this->people[$hostKey]->id, 'name' => $name],
            [
                'city' => 'Oslo',
                'country' => 'Norway',
                'ends_at' => null,
                'allow_invitee_invites' => false,
                'allow_invitee_comments' => true,
                ...$details,
                'searchable_paid_at' => $visibility === EventVisibility::Searchable ? $createdAt : null,
                'followers_notified_at' => $visibility === EventVisibility::Private ? null : $createdAt,
                'is_deleted' => false,
            ],
        );

        $tags = collect($tagSlugs)->map(fn (string $slug) => Tag::query()->where('slug', $slug)->firstOrFail());
        $event->tags()->syncWithoutDetaching($tags->pluck('id')->merge($tags->pluck('parent_id')->filter())->unique()->values());

        $this->stamp($event, $createdAt);

        return $event;
    }

    /**
     * Who is invited to what (always by a friend of theirs) and how they
     * answered, and the followers who answered a public event themselves.
     * Alice still owes two answers, Robert one.
     *
     * @param  array<string, Event>  $events
     */
    private function seedGuestLists(array $events): void
    {
        $this->guest($events['hike'], 'alice', AttendanceStatus::Attending, invitedBy: 'camilla');
        $this->guest($events['hike'], 'robert', AttendanceStatus::Attending, invitedBy: 'camilla');
        $this->guest($events['hike'], 'bjorn', AttendanceStatus::Attending, invitedBy: 'camilla');
        $this->guest($events['hike'], 'erik', AttendanceStatus::NotAttending, invitedBy: 'camilla');

        $this->guest($events['quiz'], 'alice', AttendanceStatus::Attending, invitedBy: 'bjorn');
        $this->guest($events['quiz'], 'robert', AttendanceStatus::Attending, invitedBy: 'bjorn', visibility: AttendanceVisibility::Friends);
        $this->guest($events['quiz'], 'camilla', AttendanceStatus::Interested, invitedBy: 'bjorn');
        $this->guest($events['quiz'], 'erik', AttendanceStatus::NotAttending, invitedBy: 'bjorn');

        $this->guest($events['run'], 'alice', AttendanceStatus::NotAttending, invitedBy: 'frida');
        $this->guest($events['run'], 'camilla', AttendanceStatus::Attending, invitedBy: 'frida');
        $this->guest($events['run'], 'ingrid', AttendanceStatus::Interested, invitedBy: 'frida', visibility: AttendanceVisibility::Friends);

        $this->guest($events['tacos'], 'bjorn', AttendanceStatus::Attending, invitedBy: 'alice');
        $this->guest($events['tacos'], 'camilla', AttendanceStatus::Interested, invitedBy: 'alice');
        $this->guest($events['tacos'], 'erik', AttendanceStatus::NoResponse, invitedBy: 'alice');
        $this->guest($events['tacos'], 'frida', AttendanceStatus::Attending, invitedBy: 'alice');
        $this->guest($events['tacos'], 'ingrid', AttendanceStatus::NotAttending, invitedBy: 'alice', visibility: AttendanceVisibility::Friends);
        $this->guest($events['tacos'], 'robert', AttendanceStatus::Attending, invitedBy: 'alice');

        $this->guest($events['sauna'], 'alice', AttendanceStatus::Attending, invitedBy: 'robert');
        $this->guest($events['sauna'], 'bjorn', AttendanceStatus::Interested, invitedBy: 'robert');
        $this->guest($events['sauna'], 'camilla', AttendanceStatus::NoResponse, invitedBy: 'robert');
        $this->guest($events['sauna'], 'erik', AttendanceStatus::Attending);
        $this->guest($events['sauna'], 'frida', AttendanceStatus::Interested, visibility: AttendanceVisibility::None);

        $this->guest($events['concert'], 'frida', AttendanceStatus::Attending, invitedBy: 'ingrid');
        $this->guest($events['concert'], 'alice', AttendanceStatus::Interested);
        $this->guest($events['concert'], 'robert', AttendanceStatus::Interested);

        $this->guest($events['cabin'], 'alice', AttendanceStatus::Attending, invitedBy: 'erik');
        $this->guest($events['cabin'], 'bjorn', AttendanceStatus::Attending, invitedBy: 'erik');
        $this->guest($events['cabin'], 'camilla', AttendanceStatus::Attending, invitedBy: 'erik');
        $this->guest($events['cabin'], 'robert', AttendanceStatus::Attending, invitedBy: 'bjorn');

        $this->guest($events['pottery'], 'alice', AttendanceStatus::NoResponse, invitedBy: 'camilla');
        $this->guest($events['pottery'], 'robert', AttendanceStatus::NoResponse, invitedBy: 'camilla');
        $this->guest($events['pottery'], 'frida', AttendanceStatus::Attending, invitedBy: 'camilla');

        $this->guest($events['dugnad'], 'robert', AttendanceStatus::Attending);

        $this->guest($events['bergen'], 'alice', AttendanceStatus::NoResponse, invitedBy: 'frida');
        $this->guest($events['bergen'], 'ingrid', AttendanceStatus::Attending, invitedBy: 'frida');
        $this->guest($events['bergen'], 'camilla', AttendanceStatus::Interested, invitedBy: 'frida');
    }

    /**
     * Someone's answer to an event: invited by a friend, or, with nobody
     * inviting them, a follower answering a public event on their own.
     */
    private function guest(Event $event, string $guestKey, AttendanceStatus $status, ?string $invitedBy = null, AttendanceVisibility $visibility = AttendanceVisibility::Everyone): void
    {
        EventAttendee::query()->updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $this->people[$guestKey]->id],
            [
                'invited_by_user_id' => $invitedBy === null ? null : $this->people[$invitedBy]->id,
                'status' => $status,
                'attendance_visibility' => $visibility,
                'source' => $invitedBy === null ? AttendeeSource::Follower : AttendeeSource::Invited,
            ],
        );
    }

    /**
     * Comments on four events: thanks after the hike, and planning for the
     * taco night, the cabin weekend and the sauna.
     *
     * @param  array<string, Event>  $events
     * @return array<string, EventComment> keyed "event:author"
     */
    private function seedEventComments(array $events): array
    {
        $hikeStart = CarbonImmutable::instance($events['hike']->starts_at);

        return [
            'hike:alice' => $this->comment($events['hike'], 'alice', 'Thank you for a lovely day! The waffles at Ullevålseter were worth every step.', $hikeStart->addHours(10)),
            'hike:robert' => $this->comment($events['hike'], 'robert', 'My legs disagree, but what a trip. Same again soon?', $hikeStart->addHours(11)),
            'hike:camilla' => $this->comment($events['hike'], 'camilla', 'Photos are in the shared album. Next time we go all the way to Kikut!', $hikeStart->addHours(26)),

            'tacos:bjorn' => $this->comment($events['tacos'], 'bjorn', "I'll bring Wingspan, plus the expansion if anyone's feeling brave.", $this->ago(days: 2)),
            'tacos:camilla' => $this->comment($events['tacos'], 'camilla', "Might be half an hour late, I'm at work until seven. Save me some guacamole!", $this->ago(hours: 18)),
            'tacos:alice' => $this->comment($events['tacos'], 'alice', "No stress, we'll keep a plate warm for you.", $this->ago(hours: 17, minutes: 30)),

            'cabin:camilla' => $this->comment($events['cabin'], 'camilla', "Who's bringing the brunost? I refuse to eat waffles without it.", $this->ago(days: 3)),
            'cabin:erik' => $this->comment($events['cabin'], 'erik', 'Already in the cabin pantry. Bring wool socks, the floors are cold in the mornings.', $this->ago(days: 2, hours: 23, minutes: 40)),
            'cabin:robert' => $this->comment($events['cabin'], 'robert', "Weekend off confirmed! I'll bring a couple of games for the evenings.", $this->ago(hours: 2, minutes: 20)),

            'sauna:alice' => $this->comment($events['sauna'], 'alice', 'Is there a cold plunge, or is the fjord the cold plunge?', $this->ago(hours: 20)),
            'sauna:robert' => $this->comment($events['sauna'], 'robert', "The fjord is the cold plunge. That's the whole point 😄", $this->ago(hours: 19, minutes: 30)),
            'sauna:bjorn' => $this->comment($events['sauna'], 'bjorn', 'Bringing my own towel this time, promise.', $this->ago(hours: 2)),
        ];
    }

    private function comment(Event $event, string $authorKey, string $body, CarbonImmutable $at): EventComment
    {
        $comment = EventComment::query()->firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $this->people[$authorKey]->id, 'body' => $body],
        );

        $this->stamp($comment, $at);

        return $comment;
    }

    /**
     * Alice's chats with Bjørn and with Robert, and the "Cabin Weekend"
     * group, over the last day or two. Alice has unread messages in two of
     * them (Bjørn's and the group), Robert in one (the group); everything
     * else is read.
     */
    private function seedConversations(): void
    {
        $withBjorn = Conversation::findOrCreateDirect($this->people['alice'], $this->people['bjorn']);
        $this->chat($withBjorn, [
            ['bjorn', 1505, 'Are you still up for the quiz at Schouskjelleren tomorrow?'],
            ['alice', 1501, "Wouldn't miss it! Is Robert coming too?"],
            ['bjorn', 1497, 'He\'s in. We just need a team name that isn\'t "The Quizzly Bears" again.'],
            ['alice', 1490, "Brunost Brains? Fjord Focus? I'll keep thinking."],
            ['bjorn', 1486, 'Brunost Brains, final answer 😂', ['alice' => '😂']],
            ['alice', 1330, 'Also, I found my old Catan box, but half the boats are missing. Do you still have Seafarers?'],
            ['bjorn', 1322, "I do! I'll bring it along to your taco night.", ['alice' => '🙏']],
            ['alice', 178, 'What time should we meet before the quiz?'],
            ['bjorn', 171, 'Half six outside? Then we get a table before it fills up.'],
            ['alice', 166, 'Perfect. Should I book one just in case?'],
            ['bjorn', 41, 'Already done: a table for four under Haugen. Camilla might come too.'],
            ['bjorn', 39, 'See you at half six 🍻'],
        ]);
        $this->markRead($withBjorn, 'alice', $this->ago(minutes: 166));
        $this->markRead($withBjorn, 'bjorn', $this->now);

        $withRobert = Conversation::findOrCreateDirect($this->people['alice'], $this->people['robert']);
        $this->chat($withRobert, [
            ['robert', 1440, 'Thanks for the taco invite! Can I bring anything?'],
            ['alice', 1436, "Just yourself. Maybe a dip if you're feeling fancy."],
            ['robert', 1431, 'Guacamole it is 🥑', ['alice' => '❤️']],
            ['alice', 230, "Did you see Ingrid's trio is playing at Kulturkirken Jakob next week? Want to go together?"],
            ['robert', 221, 'Already marked myself as interested! Dinner at Mathallen first?'],
            ['alice', 214, 'Deal 👍'],
        ]);
        $this->markRead($withRobert, 'alice', $this->now);
        $this->markRead($withRobert, 'robert', $this->now);

        $cabinGroup = Conversation::query()->firstOrCreate(
            ['type' => ConversationType::Group, 'name' => 'Cabin Weekend', 'creator_id' => $this->people['alice']->id],
        );
        foreach (['alice', 'bjorn', 'camilla', 'erik', 'robert'] as $member) {
            $cabinGroup->participants()->firstOrCreate(['user_id' => $this->people[$member]->id]);
        }
        $this->stamp($cabinGroup, $this->ago(minutes: 2770));
        $this->chat($cabinGroup, [
            ['alice', 2765, 'Made a group for the cabin weekend so we stop losing things across five different chats 🏔️', ['bjorn' => '👍', 'camilla' => '❤️']],
            ['erik', 2758, 'Good call. The cabin sleeps six, so there\'s room for everyone.'],
            ['camilla', 2751, 'I can drive! Room for three plus luggage.'],
            ['bjorn', 2747, 'Dibs on the front seat.'],
            ['robert', 2702, "Still waiting to hear about work, but I'm hoping to join."],
            ['erik', 1562, 'Shopping list so far: brunost, waffle mix, sausages and far too much coffee.'],
            ['camilla', 1556, 'Adding marshmallows. Non-negotiable.', ['alice' => '😂', 'bjorn' => '😂', 'erik' => '👍']],
            ['bjorn', 1551, 'Is the sauna working this year?'],
            ['erik', 1547, "Fixed it last time I was up there. It's ready 🔥"],
            ['alice', 1205, 'Should we plan a hike on the Saturday? The ridge walk from the cabin is supposed to be gorgeous.'],
            ['erik', 1196, 'Yes! About four hours there and back, with a waffle break at the top.'],
            ['robert', 143, 'Good news: I got the weekend off. Count me in!', ['alice' => '🎉', 'camilla' => '🎉', 'erik' => '❤️']],
            ['alice', 138, 'Yay! 🎉'],
            ['camilla', 62, "So that's five of us. I'm leaving Grünerløkka at four on Friday. Who needs a lift?"],
            ['bjorn', 57, 'Me please!'],
            ['erik', 21, '🙌'],
        ]);
        $this->markRead($cabinGroup, 'alice', $this->ago(minutes: 138));
        $this->markRead($cabinGroup, 'robert', $this->ago(minutes: 143));
        $this->markRead($cabinGroup, 'bjorn', $this->ago(minutes: 57));
        $this->markRead($cabinGroup, 'camilla', $this->ago(minutes: 62));
        $this->markRead($cabinGroup, 'erik', $this->now);
    }

    /**
     * Writes a conversation's messages in order, each sent the given number
     * of minutes ago (so their times strictly increase), with the reactions
     * each one got: one emoji per person, as the app allows.
     *
     * @param  list<array{0: string, 1: int, 2: string, 3?: array<string, string>}>  $lines  author, minutes ago, text, reactions by person
     */
    private function chat(Conversation $conversation, array $lines): void
    {
        foreach ($lines as $line) {
            [$authorKey, $minutesAgo, $body] = $line;

            $message = $conversation->messages()->firstOrCreate([
                'user_id' => $this->people[$authorKey]->id,
                'body' => $body,
            ]);
            $this->stamp($message, $this->ago(minutes: $minutesAgo));

            foreach ($line[3] ?? [] as $reactorKey => $emoji) {
                $this->react($message, $reactorKey, $emoji);
            }
        }
    }

    /**
     * Sets how far the person has read the conversation (and that they get
     * its notifications), which is what its unread count goes by.
     */
    private function markRead(Conversation $conversation, string $personKey, CarbonImmutable $at): void
    {
        $conversation->participants()
            ->where('user_id', $this->people[$personKey]->id)
            ->update(['last_read_at' => $at, 'muted_at' => null]);
    }

    /**
     * Five leaflets in every visibility: Henrik's official dugnad notice
     * with a link, Ingrid's new song, Alice's Catan question and her
     * private shopping list, and Robert's coffee with a picture. Alice's
     * question and Robert's coffee have replies.
     *
     * @return array<string, LeafletResponse> keyed "leaflet:author"
     */
    private function seedLeaflets(): array
    {
        $dugnad = $this->leaflet('henrik', "Dugnad in Sofienbergparken in a couple of weeks! We'll rake leaves, fix the benches by the playground and finish with coffee and waffles. Bring gloves if you have some.\n\nNew to dugnads? This explains the idea better than I can.", LeafletVisibility::Official, $this->ago(days: 3), link: [
            'link_url' => 'https://en.wikipedia.org/wiki/Dugnad',
            'link_title' => 'Dugnad - Wikipedia',
            'link_description' => 'Dugnad is a Norwegian term for voluntary work done together with other people, such as neighbours tidying a shared courtyard or park.',
            'link_image_url' => null,
            'link_fetched_at' => $this->ago(days: 3),
        ]);
        $this->react($dugnad, 'robert', '👍');
        $this->react($dugnad, 'alice', '🙌');
        $this->react($dugnad, 'frida', '💪');

        $newSong = $this->leaflet('ingrid', "Finished a new song this week, and we'll play it for the very first time at Kulturkirken Jakob. Come and tell me honestly whether the bridge works 🎶", LeafletVisibility::FriendsAndFollowers, $this->ago(days: 2, hours: 1));
        $this->react($newSong, 'alice', '❤️');
        $this->react($newSong, 'frida', '🎶');
        $this->react($newSong, 'robert', '🔥');

        $catan = $this->leaflet('alice', 'Does anyone have a copy of Catan: Seafarers I could borrow for game night? Ours is missing half the boats.', LeafletVisibility::Friends, $this->ago(hours: 26, minutes: 30));
        $this->react($catan, 'bjorn', '👍');
        $this->react($catan, 'camilla', '😂');

        $coffee = $this->leaflet('robert', "Finally got the hang of pour-over coffee. The kettle's on if anyone wants to stop by this week ☕", LeafletVisibility::FriendsAndFollowers, $this->ago(hours: 6), picsumId: 1060);
        $this->react($coffee, 'alice', '😍');
        $this->react($coffee, 'camilla', '☕');
        $this->react($coffee, 'erik', '👍');

        $this->leaflet('alice', "Taco shopping list: tortillas, mince, sour cream, sweetcorn, salsa, grated cheese and limes. Don't forget the limes this time.", LeafletVisibility::Private, $this->ago(hours: 2, minutes: 5));

        $responses = [
            'catan:bjorn' => $this->respond($catan, 'bjorn', "Got it on my shelf. It's yours for the evening!", $this->ago(hours: 21, minutes: 40)),
            'catan:robert' => $this->respond($catan, 'robert', "Ours lost the pirate ship years ago, so I'm no help there. I'll bring snacks instead!", $this->ago(minutes: 50)),
            'coffee:alice' => $this->respond($coffee, 'alice', "Save me a cup! I'll bring kanelboller from the bakery on the corner.", $this->ago(hours: 5)),
        ];
        $this->react($responses['catan:bjorn'], 'alice', '❤️');

        return $responses;
    }

    /**
     * A leaflet, found by author and text, posted at the given time and not
     * edited since, with its link preview as if already fetched, or a
     * picture stored the way the leaflet form stores one.
     *
     * @param  array<string, mixed>  $link
     */
    private function leaflet(string $authorKey, string $body, LeafletVisibility $visibility, CarbonImmutable $at, array $link = [], ?int $picsumId = null): Leaflet
    {
        $leaflet = Leaflet::query()->updateOrCreate(
            ['user_id' => $this->people[$authorKey]->id, 'body' => $body],
            ['visibility' => $visibility, ...$link],
        );

        if ($picsumId !== null && ($leaflet->image_path === null || ! Storage::disk('public')->exists($leaflet->image_path))) {
            $leaflet->image_path = $this->storePicture($picsumId, self::LEAFLET_PICTURE, "leaflets/{$leaflet->id}")['path'];
        }

        $this->stamp($leaflet, $at);

        return $leaflet;
    }

    private function respond(Leaflet $leaflet, string $authorKey, string $body, CarbonImmutable $at): LeafletResponse
    {
        $response = $leaflet->responses()->firstOrCreate([
            'user_id' => $this->people[$authorKey]->id,
            'body' => $body,
        ]);

        $this->stamp($response, $at);

        return $response;
    }

    /**
     * Someone's one reaction to a message, leaflet or reply, replacing any
     * other emoji they had used on it, as the app does.
     */
    private function react(Message|Leaflet|LeafletResponse $reactable, string $personKey, string $emoji): void
    {
        Reaction::query()->updateOrCreate(
            [
                'user_id' => $this->people[$personKey]->id,
                'reactable_type' => $reactable->getMorphClass(),
                'reactable_id' => $reactable->id,
            ],
            ['emoji' => $emoji],
        );
    }

    /**
     * What the app would have told Alice and Robert about all of the above,
     * on their Activity pages. The two oldest of each are read.
     *
     * @param  array<string, Friendship>  $friendships
     * @param  array<string, Follow>  $follows
     * @param  array<string, Event>  $events
     * @param  array<string, EventComment>  $comments
     * @param  array<string, LeafletResponse>  $responses
     */
    private function seedActivity(array $friendships, array $follows, array $events, array $comments, array $responses): void
    {
        $alice = $this->people['alice'];
        $this->notify($alice, new InvitedToEvent($events['bergen'], $this->people['frida']), $this->ago(days: 4), read: true);
        $this->notify($alice, new FriendRequestAccepted($friendships['alice:frida']), $this->ago(days: 3, hours: 3), read: true);
        $this->notify($alice, new NewFollower($follows['henrik:alice']), $this->ago(days: 2, hours: 20));
        $this->notify($alice, new NewEventComment($comments['tacos:bjorn']), $this->happenedAt($comments['tacos:bjorn']));
        $this->notify($alice, new FollowedUserPublishedEvent($events['concert']), $this->happenedAt($events['concert']));
        $this->notify($alice, new NewLeafletResponse($responses['catan:bjorn']), $this->happenedAt($responses['catan:bjorn']));
        $this->notify($alice, new NewEventComment($comments['tacos:camilla']), $this->happenedAt($comments['tacos:camilla']));
        $this->notify($alice, new InvitedToEvent($events['pottery'], $this->people['camilla']), $this->ago(hours: 6, minutes: 55));
        $this->notify($alice, new FriendRequestReceived($friendships['sofie:alice']), $this->ago(hours: 3, minutes: 10));
        $this->notify($alice, new NewFollower($follows['gunnar:alice']), $this->ago(hours: 1, minutes: 35));
        $this->notify($alice, new NewLeafletResponse($responses['catan:robert']), $this->happenedAt($responses['catan:robert']));

        $robert = $this->people['robert'];
        $this->notify($robert, new FriendRequestAccepted($friendships['robert:camilla']), $this->ago(days: 5), read: true);
        $this->notify($robert, new NewFollower($follows['erik:robert']), $this->ago(days: 4, hours: 2), read: true);
        $this->notify($robert, new FollowedUserPublishedEvent($events['dugnad']), $this->happenedAt($events['dugnad']));
        $this->notify($robert, new InvitedToEvent($events['cabin'], $this->people['bjorn']), $this->ago(days: 2, hours: 1));
        $this->notify($robert, new FollowedUserPublishedEvent($events['concert']), $this->happenedAt($events['concert']));
        $this->notify($robert, new NewEventComment($comments['sauna:alice']), $this->happenedAt($comments['sauna:alice']));
        $this->notify($robert, new InvitedToEvent($events['pottery'], $this->people['camilla']), $this->ago(hours: 6, minutes: 55));
        $this->notify($robert, new NewLeafletResponse($responses['coffee:alice']), $this->happenedAt($responses['coffee:alice']));
        $this->notify($robert, new NewEventComment($comments['sauna:bjorn']), $this->happenedAt($comments['sauna:bjorn']));
    }

    /**
     * Stores one notification as the app would, through its real class but
     * sent at once on the database channel alone: no push, no broadcast of
     * the item, nothing queued. One seeded before (the same kind, actor and
     * subject, and for a comment, reply or request the same one) is updated
     * instead, back to the given time and read state.
     */
    private function notify(User $recipient, ActivityNotification $notification, CarbonImmutable $at, bool $read = false): void
    {
        $data = $notification->toArray($recipient);

        $stored = $recipient->notifications()
            ->where('type', $notification::class)
            ->where(function (Builder $query) use ($data) {
                foreach (Arr::except($data, ['subject_title', 'url', 'excerpt', 'pending']) as $key => $value) {
                    $query->where("data->{$key}", $value);
                }
            })
            ->first();

        if ($stored === null) {
            $notification->id = (string) Str::uuid();

            Notification::sendNow($recipient, $notification->locale($recipient->locale ?? 'en'), ['database']);

            $stored = $recipient->notifications()->findOrFail($notification->id);
        }

        $stored->forceFill([
            'data' => $data,
            'read_at' => $read ? $at->addHour() : null,
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();
    }

    /**
     * Gives the event its cover picture, stored the way the event form
     * stores an upload, unless it already has one on disk.
     *
     * @param  array{int<1, max>, int<1, max>}  $size
     */
    private function attachPicture(Event $event, int $picsumId, array $size): void
    {
        $image = $event->images()->first();
        $disk = Storage::disk('public');

        if ($image !== null && $disk->exists($image->path)) {
            // Pictures stored before images remembered their size.
            if ($image->width === null || $image->height === null) {
                $dimensions = @getimagesize($disk->path($image->path));

                if ($dimensions !== false) {
                    $image->update(['width' => $dimensions[0], 'height' => $dimensions[1]]);
                }
            }

            return;
        }

        $stored = $this->storePicture($picsumId, $size, "events/{$event->id}");
        $attributes = ['path' => $stored['path'], 'width' => $stored['width'], 'height' => $stored['height']];

        if ($image === null) {
            $event->images()->create([...$attributes, 'position' => 0]);
        } else {
            $image->update($attributes);
        }
    }

    /**
     * A picture from Lorem Picsum, or a drawn stand-in when it cannot be
     * had, put through ImageProcessor exactly as an upload is, so it lands
     * re-encoded on the public disk. Returns its path there and its size.
     *
     * @param  array{int<1, max>, int<1, max>}  $size
     * @return array{path: string, width: int, height: int}
     */
    private function storePicture(int $picsumId, array $size, string $directory): array
    {
        [$width, $height] = $size;
        $downloaded = $this->downloadPicture("https://picsum.photos/id/{$picsumId}/{$width}/{$height}");

        try {
            return $this->storeUpload($downloaded ?? throw new RuntimeException('Lorem Picsum could not be reached.'), $directory);
        } catch (ErrorException|RuntimeException) {
            return $this->storeUpload($this->drawPicture($width, $height), $directory);
        }
    }

    private function downloadPicture(string $url): ?string
    {
        try {
            $response = Http::timeout(20)->connectTimeout(5)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() && Str::startsWith($response->header('Content-Type'), 'image/')
            ? $response->body()
            : null;
    }

    /**
     * Hands a picture to ImageProcessor as the uploaded file it expects.
     *
     * @return array{path: string, width: int, height: int}
     */
    private function storeUpload(string $picture, string $directory): array
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'showcase-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Could not create a temporary file for a picture.');
        }

        try {
            file_put_contents($temporaryPath, $picture);

            return $this->imageProcessor->storeWithSize(new UploadedFile($temporaryPath, 'picture.jpg', test: true), $directory, ImageProcessor::EVENT_MAX_EDGE);
        } finally {
            unlink($temporaryPath);
        }
    }

    /**
     * The stand-in for when Lorem Picsum cannot be reached: an evening sky
     * fading from dusk blue to terracotta, with a low, pale sun, as a JPEG.
     *
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private function drawPicture(int $width, int $height): string
    {
        $picture = imagecreatetruecolor($width, $height);

        if ($picture === false) {
            throw new RuntimeException('Could not draw a stand-in picture.');
        }

        for ($y = 0; $y < $height; $y++) {
            $mix = $y / max(1, $height - 1);
            $color = imagecolorallocate($picture, $this->blend(52, 214, $mix), $this->blend(74, 146, $mix), $this->blend(106, 108, $mix));
            imageline($picture, 0, $y, $width - 1, $y, $color ?: 0);
        }

        $sun = imagecolorallocatealpha($picture, 255, 236, 204, 24);
        $diameter = (int) round(min($width, $height) * 0.28);
        imagefilledellipse($picture, (int) round($width * 0.68), (int) round($height * 0.62), $diameter, $diameter, $sun ?: 0);

        ob_start();
        imagejpeg($picture, null, 90);

        return (string) ob_get_clean();
    }

    /**
     * One colour channel, the given part of the way from one value to
     * another.
     *
     * @return int<0, 255>
     */
    private function blend(int $from, int $to, float $mix): int
    {
        return max(0, min(255, (int) round($from + ($to - $from) * $mix)));
    }

    /**
     * Dates a record to when it happened, with no edit since.
     */
    private function stamp(Model $record, CarbonInterface $at): void
    {
        $record->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    private function happenedAt(Event|EventComment|LeafletResponse $record): CarbonImmutable
    {
        return CarbonImmutable::instance($record->created_at ?? $this->now);
    }

    /**
     * The moment the given time before now.
     */
    private function ago(int $days = 0, int $hours = 0, int $minutes = 0): CarbonImmutable
    {
        return $this->now->subDays($days)->subHours($hours)->subMinutes($minutes);
    }

    /**
     * A clock time in the display time zone on the given day, as the
     * instant the database stores (in the app's time zone).
     */
    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        return $day->setTimeFromTimeString($time)->setTimezone(config()->string('app.timezone'));
    }
}
