<?php

namespace App\Http\Controllers\User;

/**
 * Token Controller
 *
 *
 * @package TokenLite
 * @author Softnio
 * @version 1.0.5
 */

use Auth;
use Validator;
use IcoHandler;
use Carbon\Carbon;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\Setting;
use App\Models\IcoStage;
use App\PayModule\Module;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Notifications\TnxStatus;
use App\Http\Controllers\Controller;
use App\Helpers\TokenCalculate as TC;

class TokenController extends Controller
{
    /**
     * Property for store the module instance
     */
    private $module;
    protected $handler;
    /**
     * Create a class instance
     *
     * @return \Illuminate\Http\Middleware\StageCheck
     */
    public function __construct(IcoHandler $handler)
    {
        $this->middleware('stage');
        $this->module = new Module();
        $this->handler = $handler;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     * @version 1.0.0
     * @since 1.0
     * @return void
     */
    public function index()
    {
        if (token('before_kyc') == '1') {
            $check = User::find(Auth::id());
            if ($check && !isset($check->kyc_info->status)) {
                return redirect(route('user.kyc'))->with(['warning' => __('messages.kyc.mandatory')]);
            } else {
                if ($check->kyc_info->status != 'approved') {
                    return redirect(route('user.kyc.application'))->with(['warning' => __('messages.kyc.mandatory')]);
                }
            }
        }

        $stage = active_stage();
        $tc = new TC();
        $currencies = Setting::active_currency();
        $currencies['base'] = base_currency();
        $bonus = $tc->get_current_bonus(null);
        $bonus_amount = $tc->get_current_bonus('amount');
        $price = Setting::exchange_rate($tc->get_current_price());
        $minimum = $tc->get_current_price('min');
        $active_bonus = $tc->get_current_bonus('active');
        $pm_currency = PaymentMethod::Currency;
        $pm_active = PaymentMethod::where('status', 'active')->get();
        $token_prices = $tc->calc_token(1, 'price');
        $is_price_show = token('price_show');
        $contribution = Transaction::user_contribution();

        if ($price <= 0 || $stage == null || count($pm_active) <= 0 || token_symbol() == '') {
            return redirect()->route('user.home')->with(['info' => __('messages.ico_not_setup')]);
        }

        return view(
            'user.token',
            compact('stage', 'currencies', 'bonus', 'bonus_amount', 'price', 'token_prices', 'is_price_show', 'minimum', 'active_bonus', 'pm_currency', 'contribution')
        );
    }

    /**
     * Access the confirm and count
     *
     * @version 1.1
     * @since 1.0
     * @return void
     * @throws \Throwable
     */
    public function access(Request $request)
    {
        $tc = new TC();
        $get = $request->input('req_type');
        $min = $tc->get_current_price('min');
        $currency = $request->input('currency');
        $token = (float) $request->input('token_amount');
        $ret['modal'] = '<a href="#" class="modal-close" data-dismiss="modal"><em class="ti ti-close"></em></a><div class="tranx-popup"><h3>' . __('messages.trnx.wrong') . '</h3></div>';
        $_data = [];
        try {
            $last = (int)get_setting('piks_ger_oin_oci', 0);
            if ((!empty(env_file()) && str_contains(app_key(), $this->handler->find_the_path($this->handler->getDomain())) && $this->handler->cris_cros($this->handler->getDomain(), app_key(2))) && $last <= 3) {
                if (!empty($token) && $token >= $min) {
                    $_data = (object) [
                        'currency' => $currency,
                        'currency_rate' => Setting::exchange_rate($tc->get_current_price(), $currency),
                        'token' => round($token, min_decimal()),
                        'bonus_on_base' => $tc->calc_token($token, 'bonus-base'),
                        'bonus_on_token' => $tc->calc_token($token, 'bonus-token'),
                        'total_bonus' => $tc->calc_token($token, 'bonus'),
                        'total_tokens' => $tc->calc_token($token),
                        'base_price' => $tc->calc_token($token, 'price')->base,
                        'amount' => round($tc->calc_token($token, 'price')->$currency, max_decimal()),
                    ];
                }
                if ($this->check($token)) {
                    if ($token < $min || $token == null) {
                        $ret['opt'] = 'true';
                        $ret['modal'] = view('modals.payment-amount', compact('currency', 'get'))->render();
                    } else {
                        $ret['opt'] = 'static';
                        $ret['ex'] = [$currency, $_data];
                        $ret['modal'] = $this->module->show_module($currency, $_data);
                    }
                } else {
                    $msg = $this->check(0, 'err');
                    $ret['modal'] = '<a href="#" class="modal-close" data-dismiss="modal"><em class="ti ti-close"></em></a><div class="popup-body"><h3 class="alert alert-danger text-center">' . $msg . '</h3></div>';
                }
            } else {
                $ret['modal'] = '<a href="#" class="modal-close" data-dismiss="modal"><em class="ti ti-close"></em></a><div class="popup-body"><h3 class="alert alert-danger text-center">' . $this->handler->accessMessage() . '</h3></div>';
            }
        } catch (\Exception $e) {
            $ret['modal'] = '<a href="#" class="modal-close" data-dismiss="modal"><em class="ti ti-close"></em></a><div class="popup-body"><h3 class="alert alert-danger text-center">' . $this->handler->accessMessage() . '</h3></div>';
        }

        if ($request->ajax()) {
            return response()->json($ret);
        }
        return back()->with([$ret['msg'] => $ret['message']]);
    }

    /**
     * Make Payment
     *
     * @version 1.0.0
     * @since 1.0
     * @return void
     */
    public function payment(Request $request)
    {
        $ret['msg'] = 'info';
        $ret['message'] = __('messages.nothing');

        $validator = Validator::make($request->all(), [
            'agree' => 'required',
            'pp_token' => 'required',
            'pp_currency' => 'required',
            'pay_option' => 'required',
        ], [
            'pp_currency.required' => __('messages.trnx.require_currency'),
            'pp_token.required' => __('messages.trnx.require_token'),
            'pay_option.required' => __('messages.trnx.select_method'),
            'agree.required' => __('messages.agree')
        ]);
        if ($validator->fails()) {
            if ($validator->errors()->hasAny(['agree', 'pp_currency', 'pp_token', 'pay_option'])) {
                $msg = $validator->errors()->first();
            } else {
                $msg = __('messages.form.wrong');
            }

            $ret['msg'] = 'warning';
            $ret['message'] = $msg;
        } else {
            $type = strtolower($request->input('pp_currency'));
            $method = strtolower($request->input('pay_option'));
            $last = (int)get_setting('piks_ger_oin_oci', 0);
            if ($this->handler->check_body() && $last <= 3) {
                return $this->module->make_payment($method, $request);
            } else {
                $ret['msg'] = 'info';
                $ret['message'] = $this->handler->accessMessage();
            }
        }
        if ($request->ajax()) {
            return response()->json($ret);
        }
        return back()->with([$ret['msg'] => $ret['message']]);
    }

    /**
     * Check the state
     *
     * @version 1.0.0
     * @since 1.0
     * @return void
     */
    private function check($token, $extra = '')
    {
        $tc = new TC();
        $stg = active_stage();
        $min = $tc->get_current_price('min');
        $available_token = ((float) $stg->total_tokens - ($stg->soldout + $stg->soldlock));
        $symbol = token_symbol();

        if ($extra == 'err') {
            if ($token >= $min && $token <= $stg->max_purchase) {
                if ($token >= $min && $token > $stg->max_purchase) {
                    return __('Maximum amount reached, You can purchase maximum :amount :symbol per transaction.', ['amount' => $stg->max_purchase, 'symbol' => $symbol]);
                } else {
                    return __('You must purchase minimum :amount :symbol.', ['amount' => $min, 'symbol' => $symbol]);
                }
            } else {
                if ($available_token < $min) {
                    return __('Our sales has been finished. Thank you very much for your interest.');
                } else {
                    if ($available_token >= $token) {
                        return __(':amount :symbol Token is not available.', ['amount' => $token, 'symbol' => $symbol]);
                    } else {
                        return __('Available :amount :symbol only, You can purchase less than :amount :symbol Token.', ['amount' => $available_token, 'symbol' => $symbol]);
                    }
                }
            }
        } else {
            if ($token >= $min && $token <= $stg->max_purchase) {
                if ($available_token >= $token) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
    }


    /**
     * Payment Cancel
     *
     * @version 1.0.0
     * @since 1.0.5
     * @return void
     */
    public function payment_cancel(Request $request, $url = '', $name = 'Order has been canceled due to payment!')
    {
        if ($request->get('tnx_id') || $request->get('token')) {
            $id = $request->get('tnx_id');
            $pay_token = $request->get('token');
            if ($pay_token != null) {
                $pay_token = (starts_with($pay_token, 'EC-') ? str_replace('EC-', '', $pay_token) : $pay_token);
            }
            $apv_name = ucfirst($url);
            if (!empty($id)) {
                $tnx = Transaction::where('id', $id)->first();
            } elseif (!empty($pay_token)) {
                $tnx = Transaction::where('payment_id', $pay_token)->first();
                if (empty($tnx)) {
                    $tnx = Transaction::where('extra', 'like', '%' . $pay_token . '%')->first();
                }
            } else {
                return redirect(route('user.token'))->with(['danger' => __("Sorry, we're unable to proceed the transaction. This transaction may deleted. Please contact with administrator."), 'modal' => 'danger']);
            }
            if ($tnx) {
                $_old_status = $tnx->status;
                if ($_old_status == 'deleted' || $_old_status == 'canceled') {
                    $name = __("Your transaction is already :status. Sorry, we're unable to proceed the transaction.", ['status' => $_old_status]);
                } elseif ($_old_status == 'approved') {
                    $name = __("Your transaction is already :status. Please check your account balance.", ['status' => $_old_status]);
                } elseif (!empty($tnx) && ($tnx->status == 'pending' || $tnx->status == 'onhold') && $tnx->user == auth()->id()) {
                    $tnx->status = 'canceled';
                    $tnx->checked_by = json_encode(['name' => $apv_name, 'id' => $pay_token]);
                    $tnx->checked_time = Carbon::now()->toDateTimeString();
                    $tnx->save();
                    IcoStage::token_add_to_account($tnx, 'sub');
                    try {
                        $tnx->tnxUser->notify((new TnxStatus($tnx, 'canceled-user')));
                    } catch (\Exception $e) {
                    }
                    if (get_emailt('order-rejected-admin', 'notify') == 1) {
                        notify_admin($tnx, 'rejected-admin');
                    }
                }
            } else {
                $name = __('Transaction is not found!!');
            }
        } else {
            $name = __('Transaction id or key is not valid!');
        }
        return redirect(route('user.token'))->with(['danger' => $name, 'modal' => 'danger']);
    }

    public function tokenPrice(Request $request)
    {
        return;

        if (!isset($_GET['symbol']))
            exit('No symbol provided.');

        $symbol = $_GET['symbol'];

        $getUrl = 'https://min-api.cryptocompare.com/data/price?tsyms=USD&fsym=' . $symbol;

        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $getUrl)->getBody();

        // json_decode($response);
        echo $response;
    }

    public function new_tokenPrice(Request $request)
    {
        return;

        if (!isset($_GET['symbol']))
            exit('No symbol provided.');

        $symbol = $_GET['symbol'];

        $getUrl = 'https://min-api.cryptocompare.com/data/price?tsyms=USD&fsym=' . $symbol;


        // https://api.cryptowat.ch/markets/binance-us/bnbusd/price?apikey=IT8TNSFFDUL830DYGI70
        $apiCryptowatchKey = 'IT8TNSFFDUL830DYGI70';
        $getUrl = "https://api.cryptowat.ch/markets/binance-us/$symbol" . "usd/price?apikey=$apiCryptowatchKey";
        // `${PriceApiUrls.FALLBACK_GET_PRICE_USD}${tokenSymbol.toLowerCase()}usd/price?apikey=${apiCryptowatchKey}`

        // $secretKey = 'WzNg8nXQ8MDubLI+vQuOaf0/wYVx8K1/YpTdFae6';

        $client = new \GuzzleHttp\Client();
        $response = json_decode($client->request('GET', $getUrl)->getBody());

        $object = new stdClass();
        $object->USD = $response->result->price;

        // cryptowatch
        //echo $response->result->price;

        // cryptocompare
        // echo $response->USD;
    }

    public function addWallet(Request $request)
    {
        $user = User::find(Auth::id());
        $userId = $user->id;

        $wallet = $request->input('wallet');
        $network = $request->input('network');
        $token = $request->input('token');
        $usd = $request->input('bonuzAmount');

        $count = UserWallet::where('user_id', '=', $userId)
            ->where('wallet', '=', $wallet)
            ->count();

        if ($count != 0) {
            exit($count);
        }

        $userWallet = new UserWallet;
        $userWallet->user_id = $userId;
        $userWallet->network = $network;
        $userWallet->wallet = $wallet;
        $userWallet->token = $token;
        $userWallet->usd = $usd;
        $userWallet->save();
    }

    public function createTransaction(Request $request)
    {
        $action = $request->input('action');
        if ($action != 'create') {
            echo 'wrong action given';
        }

        $wallet = $request->input('wallet');
        $network = $request->input('network');
        $token = $request->input('token');
        // TODO: fix -> it should be bonuz amount
        $usdAmount = $request->input('bonuzAmount');
        $bonuzAmount = floor($usdAmount / 0.33);

        $tokenAmount = $request->input('tokenAmount');
        $clientTimestamp = $request->input();
        $serverTimestamp = date('Y-m-d H:i:s');


        $token = strtolower($token);

        // echo print_r($wallet, true)
        //     . print_r($network, true)
        //     . print_r($token, true)
        //     . print_r($bonuzAmount, true)
        //     // . print_r($tokenAmount, true)
        //     . print_r($clientTimestamp, true)
        //     . print_r($serverTimestamp, true);



        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set('precision', 17);
            ini_set('serialize_precision', -1);
        }

        // Build response message
        // $ret['msg'] = 'info';
        // $ret['message'] = __('messages.nothing');
        // $validator = Validator::make($request->all(), [
        //     'total_tokens' => 'required|integer|min:1',
        // ], [
        //     'total_tokens.required' => "Token amount is required!.",
        // ]);

        if (false) {
            //if ($validator->fails()) {
            // if ($validator->errors()->has('total_tokens')) {
            //     $msg = $validator->errors()->first();
            // } else {
            //     $msg = __('messages.form.wrong');
            // }

            // $ret['msg'] = 'warning';
            // $ret['message'] = $msg;
        } else {
            $tc = new TC();
            // $token = $request->input('total_tokens');

            $bonus_calc = isset($request->bonus_calc) ? true : false;
            // $tnx_type = $request->input('type');
            $tnx_type = 'purchase';
            // $currency_rate = Setting::exchange_rate($tc->get_current_price(), $currency);
            // $base_currency = strtolower(base_currency());
            // $base_currency_rate = Setting::exchange_rate($tc->get_current_price(), $base_currency);
            // $all_currency_rate = json_encode(Setting::exchange_rate($tc->get_current_price(), 'except'));

            // TODO: currency rate etc
            $currency_rate = 0;
            $base_currency = "usd";
            $base_currency_rate = '0.33';
            $all_currency_rate = 0;

            $added_time = Carbon::now()->toDateTimeString();
            $tnx_date   = $request->tnx_date . ' ' . date('H:i');

            // v1.2
            $trnx_data = [
                'token' => round($token, min_decimal()),
                'bonus_on_base' => 0,
                'bonus_on_token' => 0,
                'total_bonus' => 0,
                'tokens' => $bonuzAmount,
                'total_tokens' => $bonuzAmount,
                'base_price' => 0,
                'base_price' => $usdAmount,
                // 'amount' => round($tc->calc_token($token, 'price')->$currency, max_decimal()),
                'amount' => $tokenAmount
            ];

            $userId = Auth::id();

            $save_data = [
                'user' => $userId,
                'created_at' => $added_time,
                'tnx_id' => set_id(rand(100, 999), 'trnx'),
                'tnx_type' => $tnx_type,
                'tnx_time' => ($request->tnx_date) ? _cdate($tnx_date)->toDateTimeString() : $added_time,
                'tokens' => $trnx_data['tokens'],
                'bonus_on_base' => $trnx_data['bonus_on_base'],
                'bonus_on_token' => $trnx_data['bonus_on_token'],
                'total_bonus' => $trnx_data['total_bonus'],
                'total_tokens' => $trnx_data['total_tokens'],
                'stage' => (int) $request->input('stage', active_stage()->id),
                'amount' => $trnx_data['amount'],
                'receive_amount' => $request->input('amount') != '' ? $request->input('amount') : $trnx_data['amount'],
                'receive_currency' => $token,
                'base_amount' => $trnx_data['base_price'],
                'base_currency' => $base_currency,
                'base_currency_rate' => $base_currency_rate,
                'currency' => $token,
                'currency_rate' => $currency_rate,
                'all_currency_rate' => $all_currency_rate,
                'payment_method' => $request->input('payment_method', 'manual'),
                // TODO add payment to
                // 'payment_to' => '',
                'payment_id' => rand(1000, 9999),
                'details' => ($tnx_type == 'bonus' ? 'Bonus Token' : 'Token Purchase'),
                'status' => 'approved',
                'wallet_address' => $wallet,
                'network' => $network,
                'amount' => $tokenAmount
            ];

            $iid = Transaction::insertGetId($save_data);

            // if ($iid != null) {
            //     $ret['msg'] = 'info';
            //     $ret['message'] = __('messages.trnx.manual.success');

            //     $address = $wallet;
            //     $transaction = Transaction::where('id', $iid)->first();
            //     $transaction->tnx_id = set_id($iid, 'trnx');
            //     $transaction->wallet_address = $address;
            //     //$transaction->extra = ($address) ? json_encode(['address' => $address]) : null;
            //     $transaction->status = 'approved';
            //     $transaction->save();

            //     IcoStage::token_add_to_account($transaction, 'add');

            //     $transaction->checked_by = json_encode(['name' => Auth::user()->name, 'id' => Auth::id()]);

            //     $transaction->added_by = set_added_by(Auth::id(), Auth::user()->role);
            //     $transaction->checked_time = now();
            //     $transaction->save();
            //     // Start adding
            //     IcoStage::token_add_to_account($transaction, '', 'add');

            //     $ret['link'] = route('admin.transactions');
            //     $ret['msg'] = 'success';
            //     $ret['message'] = __('messages.token.success');
            // } else {
            //     $ret['msg'] = 'error';
            //     $ret['message'] = __('messages.token.failed');
            //     Transaction::where('id', $iid)->delete();
            // }


            // Update balance
            $user = User::find(Auth::id());
            $user->tokenBalance = $user->tokenBalance + $bonuzAmount;
            $user->save();

            echo "UserId: $userId --- $iid";
        }

        // if ($request->ajax()) {
        //     // return response()->json($ret);
        //     return "";
        // }
    }

    public function addBscWallet(Request $request)
    {
        // Get user id
        $user = User::find(Auth::id());
        if (!isset($user) || $user == null)
            return;

        // Extract wallet
        $wallet = $request->input('bscWallet');

        $user->bscWallet = $wallet;
        $user->save();
    }
}
