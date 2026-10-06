<?php

return [

    'open' => 'Åpne i Manteion',
    'footer' => 'Du får dette fordi du ba om det, under Innstillinger → Varsler.',

    'suspended' => 'Konto stengt',
    'unsuspended' => 'Stenging opphevet',
    'reason' => 'Grunn: :reason',
    'deleted_by_self' => 'De slettet den selv.',
    'deleted_by_admin' => 'Slettet av :admin.',

    'new_user' => [
        'subject' => 'Nytt medlem: :name',
        'lines' => [':name (@:username) registrerte seg nettopp med :email.'],
    ],

    'account_deleted' => [
        'subject' => 'Konto slettet: :name',
        'lines' => [':name (@:username) er borte. :by'],
    ],

    'account_suspended' => [
        'subject' => ':action: :name',
        'lines' => [':action for :name (@:username), av :by.', ':reason'],
    ],

    'message_sent' => [
        'subject' => 'Melding fra :sender i :conversation',
        'lines' => [':sender skrev i :conversation (:type).'],
    ],

    'event_created' => [
        'subject' => 'Nytt arrangement: :event',
        'lines' => [':host opprettet «:event», :visibility, som starter :starts.'],
    ],

    'leaflet_published' => [
        'subject' => 'Nytt flyveblad fra :author',
        'lines' => [':author publiserte et flyveblad (:visibility).'],
    ],

    'report_filed' => [
        'subject' => 'Ny rapport fra :reporter',
        'lines' => [':reporter rapporterte :what.', 'Grunn: :reason'],
    ],

];
