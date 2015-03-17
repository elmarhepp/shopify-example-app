<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 */
class ApplicationCharge extends Eloquent
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'application_charge';

    protected $fillable = array('shop_id', 'charge_id', 'activated_on', 'billing_on', 'cancelled_on',
        'confirmation_url', 'name', 'price', 'return_url', 'status', 'test', 'trial_days', 'trial_ends_on');

    public function shop()
    {
        return $this->belongsTo('Authorization', 'id', 'shop_id');
    }


}
