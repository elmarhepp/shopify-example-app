<?php namespace App\MyClasses;

use Exception;

/**
 * Created by PhpStorm.
 * User: elmarhepp
 * Date: 23/12/14
 * Time: 11:15
 */
class ShopifyException extends Exception
{

    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        // some code

        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }


}