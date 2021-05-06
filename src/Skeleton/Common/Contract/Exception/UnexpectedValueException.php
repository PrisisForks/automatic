<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2021 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

namespace Narrowspark\Automatic\Skeleton\Common\Contract\Exception;

use UnexpectedValueException as BaseUnexpectedValueException;

final class UnexpectedValueException extends BaseUnexpectedValueException implements Exception
{
}