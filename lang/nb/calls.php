<?php

return [

    'push' => [
        'call' => [
            'title' => 'Innkommende videosamtale',
            'body' => ':name ringer',
        ],
        'missed_call' => [
            'title' => 'Tapt videosamtale',
            'body' => ':name ringte',
        ],
        'call_over' => [
            'title' => 'Samtalen er over',
            'body' => 'Samtalen fra :name er over.',
        ],
    ],

];
