<?php

namespace Modules\Partner\Exceptions;

use DomainException;

/** The reference was already used for a different customer or number of points. */
class PointAwardConflict extends DomainException {}
