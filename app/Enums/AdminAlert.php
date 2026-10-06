<?php

namespace App\Enums;

/**
 * What an administrator can ask to be emailed about, while the site is
 * being tested. Cases are in display order.
 */
enum AdminAlert: string
{
    case NewUser = 'new_user';
    case AccountDeleted = 'account_deleted';
    case AccountSuspended = 'account_suspended';
    case MessageSent = 'message_sent';
    case EventCreated = 'event_created';
    case LeafletPublished = 'leaflet_published';
    case ReportFiled = 'report_filed';
}
