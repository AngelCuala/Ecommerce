<?php

namespace App\Services\Assistant;

use RuntimeException;

/** Google said the model is busy (or doesn't exist): the next model in the list may still answer. */
class GeminiBusy extends RuntimeException
{
}
