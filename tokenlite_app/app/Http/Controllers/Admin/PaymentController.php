<?php

namespace App\Http\Controllers\Admin;
/**
 * Transactions Controller
 *
 * @package TokenLite
 * @author Softnio
 * @version 1.1.0
 */
use Auth;
use Validator;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Setting;
use App\Models\IcoStage;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Helpers\ReferralHelper;
use App\Notifications\TnxStatus;
use App\Notifications\Refund;
use App\Http\Controllers\Controller;
use App\Helpers\TokenCalculate as TC;

use App\Models\UserWallet;
use App\Models\Activity;
use App\Models\KYC;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     * @version 1.1
     * @since 1.0
     * @return void
     */
    public function index(Request $request, $status = '')
    {
        $paymentsInfo = [];
        
        $allUsers = User::all();
        foreach ($allUsers as $user) {
            
            // User Info
            $resultUserInfo = new \stdClass;
            $resultUserInfo->id = $user->id;
            $resultUserInfo->name = $user->name;
            $resultUserInfo->email = $user->email;
            $resultUserInfo->status = $user->status;
            
            // Build transactions
            $sumTokens = 0;
            $resultTransactions = [];
            $transactions = Transaction::where("user", "=", $user->id)->get();
            if ($transactions->count() == 0) {
                continue;
            }
            foreach ($transactions as $transaction) {
                $resultTransaction = new \stdClass;
                $resultTransaction->id = $transaction->id;
                $resultTransaction->tnx_time = $transaction->tnx_time;
                $resultTransaction->tnx_id = $transaction->tnx_id;
                $resultTransaction->tokens = $transaction->tokens;
                $resultTransaction->network = $transaction->network;
                $resultTransaction->currency = $transaction->currency;
                $resultTransaction->status = $transaction->status;
                $resultTransaction->payment_method = $transaction->payment_method;
                $resultTransaction->payment_id = $transaction->payment_id;
                $resultTransaction->payment_to = $transaction->payment_to;
                $resultTransaction->wallet_address = $transaction->wallet_address;
                
                $sumTokens = $transaction->tokens;
                
                array_push($resultTransactions, $resultTransaction);
            }
            
            // KYC status
            $kyc = KYC::where("userId", "=", $user->id)->first();
            $verified = $kyc && $kyc->status == 'approved';
            
            // Build wallets
            $resultWallets = [];
            $wallets = UserWallet::where("user_id", "=", $user->id)->get();
            foreach ($wallets as $wallet) {
                $resultWallet = new \stdClass;
                $resultWallet = $wallet->wallet;
                $resultWallet = $wallet->network;
                $resultWallet = $wallet->token;
            }
            
            // Build activities#
            $resultActivities = [];
            $activities = Activity::where("user_id", "=", $user->id)->orderBy('id', 'DESC')->get();
            foreach ($activities as $activity) {
                $resultActivity = new \stdClass;
                $resultActivity->id = $activity->id;
                $resultActivity->device = $activity->device;
                $resultActivity->browser = $activity->browser;
                $resultActivity->ip = $activity->ip;
                $resultActivity->created_at = $activity->created_at;
                
                array_push($resultActivities, $resultActivity);
            }
            
            $resultObject = new \stdClass;
            $resultObject->wallets = $wallets;
            $resultObject->transactions = $transactions;
            $resultObject->activities = $activities;
            $resultObject->tokens = $sumTokens;
            $resultObject->kyc = $verified ? "Verified" : "";
            $resultObject->lastLogin = $activities[0]->created_at;
            
            array_push($paymentsInfo, $resultObject);
        }
        
        //return view('admin.payments', compact('paymentsInfo'));
    }
}