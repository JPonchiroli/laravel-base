<?php

namespace Jponchiroli\LaravelBase\Base;

/**
 * Update payload: the primary key plus the validated attributes that were sent.
 * It carries the same data as a patch; the repository decides how to apply it.
 */
class BaseUpdateDTO extends BasePatchDTO {}
