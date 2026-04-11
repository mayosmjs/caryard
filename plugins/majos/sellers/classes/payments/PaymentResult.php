<?php namespace Majos\Sellers\Classes\Payments;

/**
 * Payment Result
 * Represents the result of a payment operation
 */
class PaymentResult
{
    public $success;
    public $transactionId;
    public $message;
    public $data;
    public $redirectUrl;

    public function __construct($success = false, $message = '', $transactionId = null, $data = [], $redirectUrl = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->transactionId = $transactionId;
        $this->data = $data;
        $this->redirectUrl = $redirectUrl;
    }

    public static function success($message = '', $transactionId = null, $data = [], $redirectUrl = null)
    {
        return new self(true, $message, $transactionId, $data, $redirectUrl);
    }

    public static function failure($message, $data = [])
    {
        return new self(false, $message, null, $data);
    }
}
