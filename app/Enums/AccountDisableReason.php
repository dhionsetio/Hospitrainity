<?php

namespace App\Enums;

enum AccountDisableReason: string
{
    case TestFixture = 'test_fixture';
    case OwnerRequest = 'owner_request';
    case SecurityHold = 'security_hold';
    case PrivacyRestriction = 'privacy_restriction';
    case PrivacyRequest = 'privacy_request';
    case MembershipEnded = 'membership_ended';
    case Other = 'other';
}
