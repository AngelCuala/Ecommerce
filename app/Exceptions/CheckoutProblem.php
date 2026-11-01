<?php

namespace App\Exceptions;

/** Something the buyer must fix before the order can be placed (stock, limits, flash sale ran out). */
class CheckoutProblem extends \RuntimeException
{
}
