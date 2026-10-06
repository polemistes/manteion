<?php

return [

    'open' => 'Open in Manteion',
    'footer' => 'You get this because you asked to, under Settings → Notifications.',

    'suspended' => 'Account suspended',
    'unsuspended' => 'Suspension lifted',
    'reason' => 'Reason: :reason',
    'deleted_by_self' => 'They deleted it themselves.',
    'deleted_by_admin' => 'Deleted by :admin.',

    'new_user' => [
        'subject' => 'New member: :name',
        'lines' => [':name (@:username) just registered with :email.'],
    ],

    'account_deleted' => [
        'subject' => 'Account deleted: :name',
        'lines' => [':name (@:username) is gone. :by'],
    ],

    'account_suspended' => [
        'subject' => ':action: :name',
        'lines' => [':action for :name (@:username), by :by.', ':reason'],
    ],

    'message_sent' => [
        'subject' => 'Message from :sender in :conversation',
        'lines' => [':sender wrote in :conversation (:type).'],
    ],

    'event_created' => [
        'subject' => 'New event: :event',
        'lines' => [':host created ":event", :visibility, starting :starts.'],
    ],

    'leaflet_published' => [
        'subject' => 'New leaflet from :author',
        'lines' => [':author published a leaflet (:visibility).'],
    ],

    'report_filed' => [
        'subject' => 'New report from :reporter',
        'lines' => [':reporter reported a :what.', 'Reason: :reason'],
    ],

];
