<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

/**
 * NFT Model
 *
 *  NFT Data
 */
class NFT extends Model
{
    /*
     * Table Name
     */
    protected $table = 'nfts';

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
}
