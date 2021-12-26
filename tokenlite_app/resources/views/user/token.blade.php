@extends('layouts.user')
@section('title', __('Purchase Token'))

@section('content')
@php

$user = Auth::user();

$bscWallet = $user->bscWallet;
if (!isset($bscWallet) || $bscWallet == null)
    $bscWallet = "";

echo "<script type='text/javascript'>window.bscWallet = '".$bscWallet."';</script>";

// $email=print_r($user->email, true);
// $isTestUser = $email == 'tokenlite@olimo.me' || $email == "m@bonuz.market";
// if (!$isTestUser) {
//     exit();
// }

$has_sidebar = false;
$content_class = 'col-lg-8';

$current_date = time();
$upcoming = is_upcoming();

$_b = 0;
$bc = base_currency();
$default_method = token_method();
$symbol = token_symbol();
$method = strtolower($default_method);
$min_token = ($minimum) ? $minimum : active_stage()->min_purchase;

$sold_token = (active_stage()->soldout + active_stage()->soldlock);
$have_token = (active_stage()->total_tokens - $sold_token);
$sales_ended = (($sold_token >= active_stage()->total_tokens) || ($have_token < $min_token)) ? true : false;

$is_method = is_method_valid();

$sl_01 = ($is_method) ? '01 ' : '';
$sl_02 = ($sl_01) ? '02 ' : '';
$sl_03 = ($sl_02) ? '03 ' : '';


$exc_rate = (!empty($currencies)) ? json_encode($currencies) : '{}';
$token_price = (!empty($price)) ? json_encode($price) : '{}';
$amount_bonus = (!empty($bonus_amount)) ? json_encode($bonus_amount) : '{1 : 0}';
$decimal_min = (token('decimal_min')) ? token('decimal_min') : 0;
$decimal_max = (token('decimal_max')) ? token('decimal_max') : 0;

@endphp

@include('layouts.messages')
@if ($upcoming)
<div class="alert alert-dismissible fade show alert-info" role="alert">
    <a href="javascript:void(0)" class="close" data-dismiss="alert" aria-label="close">&nbsp;</a>
    {{ __('Sales Start at') }} - {{ _date(active_stage()->start_date) }}
</div>
@endif
<div class="content-area card">

    <!-- Wallet Integration -->
    <div id="buy-token-widget"></div>

</div> {{-- .content-area --}}
@push('sidebar')
<div class="aside sidebar-right col-lg-4">
    @if(!has_wallet() && gws('token_wallet_req')==1 && !empty(token_wallet()))
    <div class="d-none d-lg-block">
        {!! UserPanel::add_wallet_alert() !!}
    </div>
    @endif
    {!! UserPanel::user_balance_card($contribution, ['vers' => 'side']) !!}
    <div class="token-sales card">
        <div class="card-innr">
            <div class="card-head">
                <h5 class="card-title card-title-sm">{{__('Token Sales')}}</h5>
            </div>
            <div class="token-rate-wrap row">
                <div class="token-rate col-md-6 col-lg-12">
                    <span class="card-sub-title">{{ $symbol }} {{__('Token Price')}}</span>
                    <h4 class="font-mid text-dark">1 {{ $symbol }} = <span>{{ to_num($token_prices->$bc, 'max', ',') .' '. base_currency(true) }}</span></h4>
                </div>
                <div class="token-rate col-md-6 col-lg-12">
                    <span class="card-sub-title">{{__('Exchange Rate')}}</span>
                    @php
                    $exrpm = collect($pm_currency);
                    $exrpm = $exrpm->forget(base_currency())->take(2);
                    $exc_rate = '<span>1 '.base_currency(true) .' ';
                    foreach ($exrpm as $cur => $name) {
                        if($cur != base_currency() && get_exc_rate($cur) != '') {
                            $exc_rate .= ' = '.to_num(get_exc_rate($cur), 'max', ',') . ' ' . strtoupper($cur);
                        }
                    }
                    $exc_rate .= '</span>';
                    @endphp
                    {!! $exc_rate !!}
                </div>
            </div>
            @if(!empty($active_bonus))
            <div class="token-bonus-current">
                <div class="fake-class">
                    <span class="card-sub-title">{{__('Current Bonus')}}</span>
                    <div class="h3 mb-0">{{ $active_bonus->amount }} %</div>
                </div>
                <div class="token-bonus-date">{{__('End at')}}<br>{{ _date($active_bonus->end_date, get_setting('site_date_format')) }}</div>
            </div>
            @endif
        </div>
    </div>
    @if(gws('user_sales_progress', 1)==1)
    {!! UserPanel::token_sales_progress('',  ['class' => 'mb-0']) !!}
    @endif
</div>{{-- .col.aside --}}
@endpush
@endsection
@section('modals')
<div class="modal fade modal-payment" id="payment-modal" tabindex="-1" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-md modal-dialog-centered">
        <div class="modal-content"></div>
    </div>
</div>
@endsection

@push('header')
<script>
    var access_url = "{{ route('user.ajax.token.access') }}";
    var minimum_token = {{ $min_token }}, maximum_token ={{ $stage->max_purchase }}, token_price = {!! $token_price !!}, token_symbol = "{{ $symbol }}",
    base_bonus = {!! $bonus !!}, amount_bonus = {!! $amount_bonus !!}, decimals = {"min":{{ $decimal_min }}, "max":{{ $decimal_max }} }, base_currency = "{{ base_currency() }}", base_method = "{{ $method }}";
    var max_token_msg = "{{ __('Maximum you can purchase :maximum_token token per contribution.', ['maximum_token' => to_num($stage->max_purchase, 'max', ',')]) }}", min_token_msg = "{{ __('Enter minimum :minimum_token token and select currency!', ['minimum_token' => to_num($min_token, 'max', ',')]) }}";
</script>

<script>

    function renderBuyTokenWidget() {
        var $area = $('.content-area > .card-innr');
        console.log('area', $area)
        $area.css('display', 'none');

        function callback(token, bonuzAmount) {

            console.log('token', token, 'bonuz token amount', bonuzAmount);

            var $buyTokenWidget = $('#buy-token-widget');
            $buyTokenWidget.css('display', 'none');

            function showBuyTokenWidget () {
                $buyTokenWidget.css('display', 'block');
            }

            document.getElementById('pay' + token.toLowerCase()).click()

            $tokenNumberInput = $('#token-number');
            $tokenNumberInput.val(bonuzAmount);

            var e = jQuery.Event( 'keyup', { which: 13 } );
            $tokenNumberInput.trigger(e);

            $("a[href='#payment-modal']").click();

            $('.modal').css('display', 'none');
            $('.modal-backdrop').css('display', 'none');

            var catchPayCoinpaymentsIntervalId = setInterval(function() {
                var $payCoinpayments = $('#pay-coinpayments');
                if ($payCoinpayments.length > 0) {
                    clearInterval(catchPayCoinpaymentsIntervalId);

                    var $modalClose = $('.modal-close');
                    $modalClose.unbind();
                    $modalClose.click(showBuyTokenWidget);

                    $payCoinpayments.click();

                    $('#agree-terms').click();

                    $('.modal .pay-list').css('display', 'none');
                    $('.modal .mgt-1-5x').css('display', 'none');
                }
            }, 50);
        }


        window.priceRequest = function(symbol) {

            console.log(symbol);
            console.log('called with ' + symbol);

            var myData;

            $.ajax({
                async: false,
                type: 'GET',
                url: '/tokenPrice?symbol=' + symbol,
                success: function(data) {
                    myData = data;
                }
            });

            console.log('end' + myData);

            return myData;

        };

        var entryPoint = document.getElementById('buy-token-widget');

        var PRICE_UPDATE_INTERVAL_SECONDS = 30;
        var BONUZ_TOKEN_TO_USD_RATIO = 0.025;
        var INITIAL_USD_TOKENS_VALUE = 500;
        
        var config = {
            demoMode: false,

            bonuzTokenToUSDRatio: BONUZ_TOKEN_TO_USD_RATIO,
            initialUSDTokensValue: INITIAL_USD_TOKENS_VALUE,
            priceUpdateIntervalSeconds: PRICE_UPDATE_INTERVAL_SECONDS,
            userId: 0,

            apiCryptowatchKey: 'IT8TNSFFDUL830DYGI70',

            // statsWalletUrl: '/stats-wallet',
            statsWalletUrl: '/addWallet',
            statsTransactionUrl: '/createTransaction',
  
            minInputUSD: 250,
            maxInputUSD: 1500,

            statsRequestIntervalSeconds: 1
        };

        document.createBlockchainPaymentsWidget(entryPoint, config);
    }

    document.addEventListener("DOMContentLoaded", renderBuyTokenWidget);
</script>
@endpush

@push('footer')
<link rel="stylesheet" href="{{ asset('assets/js/buytokenwidget/css/bundle.min.css?3') }}">
<style type="text/css">
    .select-bordered~.select2-container--flat.select2-container--open .select2-selection--single,
    .select-bordered~.select2-container--flat.select2-container--open .select2-selection--multiple {
        border-color: #43444b;
    }

    .select2-search--dropdown {
        background: #8262a3;
    }

    .search-on .select2-search--dropdown {
        border-bottom: 1px solid #202227;
    }

    .select2-search--dropdown .select2-search__field {
        background: #383a41;
        border: 1px solid #202227;
    }

    .select2-dropdown.search-on.select2-dropdown--below {
        border-color: #414249;
    }

    .select2-search__field {
        color: #ffffff;
    }

    .select2-container--flat .select2-results__option--highlighted[aria-selected],
    .select2-results__option[aria-selected] {
        background: #2a292f;
        color: #ffffff;
        border: black;
    }

    .select2-container--flat .select2-results__option[aria-selected=true] {
        background: rgb(59 61 68);
        color: #18aed2;
    }

    /* .row.guttar-15px > div {
display: none;
} */

    /* .token-currency-choose.payment-list {
display: none;
} */

    .pay-option-label {
        background: #202227;
    }

    .input-hint {
        background: transparent;
    }

    .token-pay-currency {
        border-left: 1px solid #758698;
    }

    .modal-content {
        border: 1px solid #2e3039;
        background-color: #212529;
    }

    .popup-title {
        color: #ffffff;
    }
</style>
@endpush