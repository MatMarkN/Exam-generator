<?php

declare(strict_types=1);

namespace App\Application\AI;

enum AIErrorReason: string
{
    case ConfigurationMissing = 'configuration_missing';
    case BillingRequired = 'billing_required';
    case AuthenticationFailed = 'authentication_failed';
    case RateLimited = 'rate_limited';
    case Timeout = 'timeout';
    case Unavailable = 'unavailable';
    case InvalidRequest = 'invalid_request';
    case UnsupportedFeature = 'unsupported_feature';
    case Refused = 'refused';
    case IncompleteResponse = 'incomplete_response';
    case InvalidResponse = 'invalid_response';
}
