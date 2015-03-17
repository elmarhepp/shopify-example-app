<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;


/**
 * App\Models\Authorization
 */
class Authorization extends Eloquent
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'authorizations';

    protected $fillable = array('shop', 'token');

}
