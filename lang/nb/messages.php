<?php

return [

    'friendship' => [
        'cannot_request_self' => 'Du kan ikke sende en venneforespørsel til deg selv.',
        'already_related' => 'Det finnes allerede en venneforespørsel med denne brukeren.',
    ],

    'follow' => [
        'cannot_follow_self' => 'Du kan ikke følge deg selv.',
        'already_following' => 'Du følger allerede denne brukeren, eller har en forespørsel som venter.',
    ],

    'event' => [
        'ends_after_start' => 'Arrangementet må slutte etter at det starter.',
        'starts_required' => 'Si når arrangementet starter.',
        'name_required' => 'Gi arrangementet et navn.',
        'image_too_large' => 'Bildet er for stort. Prøv et under 5 MB.',
    ],

    'event_template' => [
        'name_required' => 'Gi malen et navn.',
        'end_needs_start' => 'Et sluttidspunkt trenger et starttidspunkt.',
    ],

    'pictures' => [
        'too_many' => 'Du kan legge til høyst ett bilde.|Du kan legge til opptil :count bilder om gangen.',
        'not_a_picture' => 'En av filene er ikke et bilde vi kan lese (JPEG, PNG eller WebP).',
        'too_large' => 'Et av bildene er for stort. Prøv et under 5 MB.',
    ],

    'event_import' => [
        'not_a_facebook_event' => 'Det er ikke en lenke til et Facebook-arrangement.',
    ],

    'event_guest_group' => [
        'no_friends' => 'Ingen av gjestene er vennene dine, så det er ingen å legge i gruppen.',
    ],

    'event_invitation' => [
        'friends_only' => 'Du kan bare invitere vennene dine.',
    ],

    'contact_group_member' => [
        'not_a_friend' => 'Denne brukeren er ikke en av vennene dine.',
        'not_followed' => 'Du følger ikke denne brukeren.',
    ],

    'conversation' => [
        'user_missing' => 'En av de valgte personene finnes ikke lenger.',
        'friends_only' => 'Du kan bare sende meldinger til vennene dine.',
    ],

    'conversation_participant' => [
        'friends_only' => 'Du kan bare legge til dine egne venner.',
    ],

    'organizer' => [
        'friends_only' => 'Du kan bare invitere vennene dine til å tale for en arrangør.',
        'already_in' => 'De taler allerede for denne arrangøren, eller er invitert.',
        'hand_over_first' => 'Velg hvem som tar over som hovedtalsperson, eller slett arrangøren.',
        'hand_over_to_member' => 'Bare en talsperson som har takket ja, kan ta over.',
    ],

    'leaflet' => [
        'organizer_visibility' => 'En arrangør har følgere, ikke venner. Velg et annet publikum.',
        'one_attachment' => 'Et flyveblad kan ha enten bilder eller en lenke, ikke begge.',
    ],

    'reaction' => [
        'not_an_emoji' => 'Det er ikke en enkelt emoji.',
    ],

    'moderation' => [
        'deleted_placeholder' => 'Slettet av en moderator',
    ],

    'password_reset' => [
        'subject' => 'Tilbakestill passordet ditt',
        'intro' => 'Vi mottok en forespørsel om å tilbakestille passordet for kontoen som bruker denne e-postadressen.',
        'multiple_intro' => 'Vi mottok en forespørsel om å tilbakestille passordet for kontoene som bruker denne e-postadressen. Velg den du vil tilbakestille:',
        'account_line' => 'Konto: :username',
        'button' => 'Tilbakestill passord',
        'ignore' => 'Hvis du ikke ba om dette, kan du trygt ignorere denne e-posten.',
    ],

];
