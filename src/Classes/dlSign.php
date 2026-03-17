<?php
namespace Devlab\LaravelMailer\Classes;

use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class dlSign {

    /**
     * Create a sign for the value.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function sign(string $value) {

        $key = config('app.key').'usr_'.Auth::id();
        $signature = (string) hash_hmac('sha256', $value, $key);

        return $signature;
    }

    /**
     * Determine if the given value has a valid signature.
     *
     * @param  string  $value
     * @param  string  $valueSignature
     * @return bool
     */
    public static function hasValidSignature(string $value, string $valueSignature) {

        $key = config('app.key').'usr_'.Auth::id();
        $signature = hash_hmac('sha256', $value, $key);

        return hash_equals($signature, $valueSignature);
    }

    /**
     * Create a sign for SMC apps.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function smcSign(string $value) {

        $key = config('devlab.SMC_KEY').'doc_'.$value;
        $signature = (string) hash_hmac('sha256', $value, $key);

        return $signature;
    }

    /**
     * Determine if the given value has a valid SMC signature.
     *
     * @param  string  $value
     * @param  string  $valueSignature
     * @return bool
     */
    public static function smcHasValidSignature(string $value, string $valueSignature) {

        $key = config('devlab.SMC_KEY').'doc_'.$value;
        $signature = hash_hmac('sha256', $value, $key);

        return hash_equals($signature, $valueSignature);
    }

}
