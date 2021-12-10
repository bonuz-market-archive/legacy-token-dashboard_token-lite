<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exceptions\APIException;
use App\Models\KYC;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Models\IcoStage;
use App\PayModule\Module;
use App\Models\Transaction;
use App\Notifications\TnxStatus;

class APIController extends Controller
{
    public function __construct()
    {
        if( $this->hasKey() === false && !app()->runningInConsole()){
            throw new APIException("Provide valid access key", 401);
        }
    }
    /**
     * Check the API key 
     */
    protected function hasKey() : bool
    {
        $api_key = request()->secret;
        return (get_setting('site_api_key', null) == $api_key);
    }

    /**
     * return the specified resource.
     */
    protected function bonus_data()
    {
        $stage = active_stage();
        $base = (get_base_bonus($stage->id)) ? get_base_bonus($stage->id) : 0;
        $amount = (get_base_bonus($stage->id, 'amount')) ? get_base_bonus($stage->id, 'amount') : 0;
        $base_dt = ($base > 0) ? get_base_bonus($stage->id, 'base') : [];
        
        $bonus_data = ['base' => $base];
        if ($base > 0) {
            $bonus_data['start'] = isset($base_dt->start_date) ? $base_dt->start_date : $stage->start_date;
            $bonus_data['end'] = isset($base_dt->end_date) ? $base_dt->end_date : $stage->end_date;
        }
        $bonus_data['amount'] = $amount;

        return $bonus_data;
    }
    /**
     * return the specified resource.
     */
    protected function stage_data($type='')
    {
        $stage = active_stage();
        $in_caps = (token('sales_cap')) ? token('sales_cap') : 'token';
        $in_total = (token('sales_total')) ? token('sales_total') : 'token';
        $in_raised = (token('sales_raised')) ? token('sales_raised') : 'token';

        $in_caps_cur = ($in_caps=='token') ? base_currency() : $in_caps;
        $in_total_cur = ($in_total=='token') ? base_currency() : $in_total;
        $in_raised_cur = ($in_raised=='token') ? base_currency() : $in_raised;

        $token = ($stage->total_tokens) ? $stage->total_tokens : 0;
        $token_cur = to_num_token($token). ' '.token_symbol();
        $token_amt = to_num(token_price($token, $in_total_cur), 'auto', ',') . ' ' . strtoupper($in_total_cur);

        $sold = ($stage->soldout) ? $stage->soldout : 0; //@v1.1.2 @old sales_token
        $sold_cur = to_num_token($sold). ' '.token_symbol();
        $sold_amt = to_num(token_price($sold, $in_raised_cur), 'auto', ',') . ' ' . strtoupper($in_raised_cur);

        $soft = ($stage->soft_cap) ? $stage->soft_cap : 0;
        $soft_amt = to_num(token_price($soft, $in_caps_cur), 'auto', ',') . ' ' . strtoupper($in_caps_cur);
        $hard = ($stage->hard_cap) ? $stage->hard_cap : 0;
        $hard_amt = to_num(token_price($hard, $in_caps_cur), 'auto', ',') . ' ' . strtoupper($in_caps_cur);

        $bonus_data = $this->bonus_data();

        $response_minimal = array(
            'ico' => active_stage_status($stage),
            'total' => $token_cur,
            'total_amount' => $token_amt,
            'sold' => $sold_cur,
            'sold_amount' => $sold_amt,
            'progress' => sale_percent($stage),
            'price' => current_price(),
            'start' => $stage->start_date,
            'end' => $stage->end_date,
            'min' => $stage->min_purchase,
            'max' => $stage->max_purchase,
            'soft' => $soft,
            'soft_amount' => $soft_amt,
            'hard' => $hard,
            'hard_amount' => $hard_amt,
        );
        
        $response_full = array(
            'ico' => active_stage_status($stage),
            'total' => $token_cur,
            'total_amount' => $token_amt,
            'total_token' => $token, 
            'sold' => $sold_cur,
            'sold_amount' => $sold_amt,
            'sold_token' => $sold, 
            'progress' => sale_percent($stage),
            'price' => current_price(),
            'bonus' => $bonus_data,
            'start' => $stage->start_date,
            'end' => $stage->end_date,
            'min' => $stage->min_purchase,
            'max' => $stage->max_purchase,
            'soft' => ['cap' => $soft, 'amount' => $soft_amt, 'percent' => round(ico_stage_progress('soft'), 2) ],
            'hard' => ['cap' => $hard, 'amount' => $hard_amt, 'percent' => round(ico_stage_progress('hard'), 2) ],
        );
        return ($type=='full') ? $response_full : $response_minimal;
    }

    /**
     * Display the specified resource.
     * @return \Illuminate\Http\Response
     */
    public function stage()
    {
        $response = $this->stage_data();
        $data = [
            'success' => true,
            'response' => $response
        ];
        
        return response()->json($data, 200, [], JSON_PRETTY_PRINT);
    }

    /**
     * Display the specified resource.
     * @return \Illuminate\Http\Response
     */
    public function stage_full()
    {
        $response = $this->stage_data('full');
        $data = [
            'success' => true,
            'response' => $response
        ];

        return response()->json($data, 200, [], JSON_PRETTY_PRINT);
    }

    /**
     * Display the specified resource.
     * @return \Illuminate\Http\Response
     */
    public function bonuses()
    {
        
        $bonus_data = $this->bonus_data();

        $data = [
            'success' => true, 
            'response' => $bonus_data
        ];
        
        return response()->json($data, 200, [], JSON_PRETTY_PRINT);
    }

    /**
     * Display the specified resource.
     * @return \Illuminate\Http\Response
     */
    public function prices()
    {
        $get_price = current_price('base');
        $prices_data = ['price' => $get_price->price, 'min' => $get_price->min_purchase, 'end' => $get_price->end_date];
        $data = [
            'success' => true, 
            'response' => $prices_data
        ];
        
        return response()->json($data, 200, [], JSON_PRETTY_PRINT);
    }

    // {
    //     "guid": "5ffffc46baaaaf001236b209",
    //     "status": "approved",
    //     "clientId": "client_id",
    //     "event": "review.approved",
    //     "recordId": "5ffffb44baaaaf001236b1d1",
    //     "refId": "rdm-1610611387861",
    //     "submitCount": 1,
    //     "blockPassID": "5ffffaeaaaaaaaa0182f387c",
    //     "isArchived": false,
    //     "inreviewDate": "2021-01-14T08:09:39.320Z",
    //     "waitingDate": "2021-01-14T08:09:16.803Z",
    //     "approvedDate": "2021-01-14T08:09:42.508Z",
    //     "isPing": false,
    //     "env": "prod",
    //     "webhookId": null
    // }

    public function kyc(Request $request)
    {        

        $recordId = $request->input('recordId');

        // Get record id
        if ($recordId == NULL) {
            throw new \Exception(print_r($request->all(), true));
        }
        
        $clientId = 'bonuz_public_kyc_67c49';
        $blockpassApiKey = '3931e01d6a05f12ba47f90a17c3abdfb';

        $getUrl = "https://kyc.blockpass.org/kyc/1.0/connect/$clientId/recordId/$recordId";

        // Call blockpass API
        // $response = \Illuminate\Support\Facades\Http::withHeaders([
        //     'Authorization' => $blockpassApiKey,
        //     'cache-control' => 'no-cache'
        // ])->get($getUrl);

        $client = new \GuzzleHttp\Client();
        $response = json_decode($client->request('GET', $getUrl, [
            'headers' => [
                'Authorization' => $blockpassApiKey,
                'cache-control' => 'no-cache'
            ]
        ])->getBody());

        // Get email of approved user
        $identity = $response->data->identities;
        $givenName = $identity->given_name->value;
        $familyName = $identity->family_name->value;
        $name = "$givenName $familyName";
        
        
        $user = User::where('name', $name)->first();
        if ($user == NULL) {
            
            $email = $response->data->identities->email->value;
            
            // Get user by email
            $user = User::where('email', $email)->first();
        }
        
        
        // Get user by name
        // $user = User::whereLike('name', '%' . $name . '%')->first();

                // throw new \Exception($name);

        $dt = new \DateTime();
        $formattedDateTime = $dt->format('Y-m-d H:i:s');

        // Get user by email
        $kyc = KYC::where('email', $user->email)->first();
        if ($kyc == null) {
            $kyc = new KYC;
        }

        $kyc->userId = $user->id;
        $kyc->email = $user->email;
        $kyc->firstName = $givenName;
        $kyc->lastName = $familyName;
        $kyc->record_id = $recordId;
        $kyc->status = 'approved';
        $kyc->reviewedBy = 1;
        $kyc->reviewedAt = $formattedDateTime;

        $kyc->save();

    }

    // public function createTransaction(Request $request)
    // {
    //     $action = $request->input('action');
    //     if ($action != 'create') {
    //         echo 'wrong action given';
    //     }
    //         $wallet = $request->input('wallet');
    //         $network = $request->input('network');
    //         $token = $request->input('token');
    //         $bonuzAmount = $request->input('bonuzAmount');
    //         $tokenAmount = $request->input('tokenAmount');
    //         $wallet = $request->input('wallet');
    //         $clientTimestamp = $request->input();
    //         $serverTimestamp = date('Y-m-d H:i:s');

    //         echo print_r($wallet, true)
    //         . print_r($network, true)
    //         . print_r($token, true)
    //         . print_r($bonuzAmount, true)
    //         . print_r($tokenAmount, true)
    //         . print_r($clientTimestamp, true)
    //         . print_r($serverTimestamp, true);
    // }

    // public function updateTransaction(Request $request)
    // {
    //     $wallet = $request->input('wallet');
    //     $amount = $request->input('amount');

    //     echo print_r($wallet, true)
    //     . print_r($amount, true);
    // }
    
    public function backup(Request $request)
    {
        $dbhost = "127.0.0.1";
        // DB_PORT=3306
        $dbname = "bonuzzzc_enterbonuzmarket";
        $dbuser = "bonuzzzc_enterbonuzmarket";
        $dbpassword = "nbzu/%&&(Z)U\"IUBG(Z/§)$";

        include_once(dirname(__FILE__) . '/Mysqldump.php');
        $dump = new \Ifsnop\Mysqldump\Mysqldump('mysql:host=localhost;dbname=' . $dbname, $dbuser, $dbpassword);
        $dump->start('php://output');







        return;


        // $lastTime = $request['lastTime'];

         
        $dumpfile = $dbname . "_" . date("Y-m-d_H-i-s") . ".sql";
         
        echo "Start dump\n";
        $code = exec("mysqldump --user=$dbuser --password=$dbpassword --host=$dbhost $dbname > $dumpfile");
        echo "-- Dump completed -- ";
        echo $code;
        echo $dumpfile;
    }
}
