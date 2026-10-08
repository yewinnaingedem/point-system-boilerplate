<?php

namespace Modules\Customer\Exceptions;

use DomainException;

/**
 * A partner sign-in token we won't accept. The message is for logs; the client always gets
 * the same generic answer, so a forger learns nothing about which check failed.
 */
class SsoTokenRejected extends DomainException {}
