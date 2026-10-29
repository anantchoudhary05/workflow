<?php

namespace App\Exceptions;

use RuntimeException;

/** A business rule blocked the requested action. Rendered as a validation-style error. */
class WorkflowException extends RuntimeException {}
