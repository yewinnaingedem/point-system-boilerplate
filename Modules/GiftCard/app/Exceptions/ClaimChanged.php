<?php

namespace Modules\GiftCard\Exceptions;

use DomainException;

/** The claim is not what the admin looked at (a card was used or settled meanwhile). */
class ClaimChanged extends DomainException {}
