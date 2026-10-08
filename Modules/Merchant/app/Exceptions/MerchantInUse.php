<?php

namespace Modules\Merchant\Exceptions;

use DomainException;

/** A merchant or branch with redemptions can't be deleted (they are owed money); deactivate it. */
class MerchantInUse extends DomainException {}
