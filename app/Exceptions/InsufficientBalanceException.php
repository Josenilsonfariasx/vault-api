<?php

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    protected $message = 'Insufficient balance for this transaction.';

    public function __construct(string $message = null)
    {
        parent::__construct($message ?? $this->message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'insufficient_balance',
        ], 422);
    }
}
