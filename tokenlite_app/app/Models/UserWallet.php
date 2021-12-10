<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

/**
 * KYC Model
 *
 *  Manage the User Submitted KYC
 *
 * @package TokenLite
 * @author Softnio
 * @version 1.1
 * @method static orderBy(string $string, string $string1)
 * @method static FindOrFail($id)
 */
class UserWallet extends Model
{
    /*
     * Table Name
     */
    protected $table = 'user_wallets';

    public $timestamps = false;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     *
     * Relation with user
     *
     * @version 1.0.0
     * @since 1.0
     * @return void
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }
}
