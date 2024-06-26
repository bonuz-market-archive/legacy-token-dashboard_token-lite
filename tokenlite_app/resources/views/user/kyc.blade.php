@extends('layouts.user')
@section('title', __('KYC Verification'))
@php
$has_sidebar = false;

$kyc_title = ($user_kyc !== NULL && isset($_GET['thank_you'])) ? __('Begin your ID-Verification') : __('KYC Verification');
$kyc_desc = ($user_kyc !== NULL && isset($_GET['thank_you'])) ? __('Verify your identity to participate in token sale.') : __('To comply with regulations each participant is required to go through identity verification (KYC/AML) to prevent fraud, money laundering operations, transactions banned under the sanctions regime or those which fund terrorism. Please, complete our fast and secure verification process to participate in token offerings.');
@endphp

@section('content')
<div class="page-header page-header-kyc">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7 text-center">
            <h2 class="page-title">{{ $kyc_title }}</h2>
            <p class="large">{{ $kyc_desc }}</p>
        </div>
    </div>
</div>
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="content-area card user-account-pages page-kyc">
            <div class="card-innr">
                @include('layouts.messages')
                <div class="kyc-status card mx-lg-4">
                    <div class="card-innr">
                        {{-- IF NOT SUBMITED --}}
                        @if($user_kyc == NULL)
                        <div class="status status-empty">
                            <div class="status-icon">
                                <em class="ti ti-files"></em>
                            </div>
                            <span class="status-text text-dark">{{__('You have not submitted your necessary documents to verify your identity.')}}{{ (token('before_kyc')=='1') ? __('In order to purchase our tokens, please verify your identity.') : ''}}</span>
                            <p class="px-md-5">{{__('It would great if you please submit the form. If you have any question, please feel free to contact our support team.')}}</p>
                            <!-- <a href="{{ route('user.kyc.application') }}?state=new" class="btn btn-primary">{{__('Click here to complete your KYC')}}</a> -->
                            <!-- <a href="https://verify-with.blockpass.org/?clientId=bonuz_public_kyc_67c49&serviceName=Bonuz%3A%20Public%20KYC&env=prod" class="btn btn-primary">{{__('Click here to complete your KYC')}}</a> -->
                        </div> 

                        <div class="form-step form-step-final">
                            <div class="form-step-fields card-innr">
                                <div class="input-item">
                                    <input class="input-checkbox input-checkbox-md" id="term-condition" name="condition" type="checkbox" required="required" data-msg-required="{{ __("You should read our terms and policy.") }}">
                                    <label for="term-condition">{{__('I have read the')}} {!! get_page_link('terms', ['target'=>'_blank']) !!} {{ (get_page_link('terms') && get_page_link('policy') ? __('and') : '') }} {!! get_page_link('policy', ['target'=>'_blank']) !!}.</label>
                                </div>
                                <div class="input-item">
                                    <input class="input-checkbox input-checkbox-md" id="info-currect" name="currect" type="checkbox" required="required" data-msg-required="{{ __("Confirm that all information is correct.") }}">
                                    <label for="info-currect">{{__('All the personal information I have entered is correct.')}}</label>
                                </div>
                                <div class="input-item">
                                    <input class="input-checkbox input-checkbox-md" id="certification" name="certification" type="checkbox" required="required" data-msg-required="{{ __("Certify that you are individual.") }}">
                                    <label for="certification">{{__("I certify that, I am registering to participate in the token distribution event(s) in the capacity of an individual (and beneficial owner) and not as an agent or representative of a third party corporate entity.")}}</label>
                                </div>
                                <div class="gaps-1x"></div>
                                <!-- <button class="btn btn-primary" type="submit">{{__('Proceed to Verify')}}</button> -->
                                <button id="blockpass-kyc-connect">{{__('Click here to complete your KYC')}}</button>
                                <script>
                                    const blockpass = new BlockpassKYCConnect("bonuz_public_kyc_67c49", {
                                        refId: "{{ $user->id }}",
                                    });
                                    blockpass.startKYCConnect();
                                </script>
                            </div>{{-- .step-fields --}}
                        </div>

                        @endif
                        {{-- IF SUBMITED @Thanks --}}
                        @if($user_kyc !== NULL && isset($_GET['thank_you']))
                        <div class="status status-thank px-md-5">
                            <div class="status-icon">
                                <em class="ti ti-check"></em>
                            </div>
                            <span class="status-text large text-dark">{{__('You have completed the process of KYC')}}</span>
                            <p class="px-md-5">{{__('We are still waiting for your identity verification. Once our team verified your identity, you will be notified by email. You can also check your KYC  compliance status from your profile page.')}}</p>
                            <a href="{{ route('user.account') }}" class="btn btn-primary">{{__('Back to Profile')}}</a>
                        </div>
                        @endif

                        {{-- IF PENDING --}}
                        @if($user_kyc !== NULL && $user_kyc->status == 'pending' && !isset($_GET['thank_you']))
                        <div class="status status-process">
                            <div class="status-icon">
                                <em class="ti ti-infinite"></em>
                            </div>
                            <span class="status-text text-dark">{{__('Your application verification under process.')}}</span>
                            <p class="px-md-5">{{__('We are still working on your identity verification. Once our team verified your identity, you will be notified by email.')}}</p>
                        </div>
                        @endif

                        {{-- IF REJECTED/MISSING --}}
                        @if($user_kyc !== NULL && ($user_kyc->status == 'missing' || $user_kyc->status == 'rejected') && !isset($_GET['thank_you']))
                        <div class="status status{{ ($user_kyc->status == 'missing') ? '-warnning' : '-canceled' }}">
                            <div class="status-icon">
                                <em class="ti ti-na"></em>
                            </div>
                            <span class="status-text text-dark">
                                {{ $user_kyc->status == 'missing' ? __('We found some information to be missing.') : __('Sorry! Your application was rejected.') }}
                            </span>
                            <p class="px-md-5">{{__('In our verification process, we found information that is incorrect or missing. Please resubmit the form. In case of any issues with the submission please contact our support team.')}}</p>
                            <a href="{{ route('user.kyc.application') }}?state={{ $user_kyc->status == 'missing' ? 'missing' : 'resubmit' }}" class="btn btn-primary">{{__('Submit Again')}}</a>
                        </div>
                        @endif

                        {{-- IF VERIFIED --}}
                        @if($user_kyc !== NULL && $user_kyc->status == 'approved' && !isset($_GET['thank_you']))
                        <div class="status status-verified">
                            <div class="status-icon">
                                <em class="ti ti-files"></em>
                            </div>
                            <span class="status-text text-dark">{{__('Your identity verified successfully.')}}</span>
                            <p class="px-md-5">{{__('One of our team members verified your identity. Now you can participate in our token sale. Thank you.')}}</p>
                            <div class="gaps-2x"></div>
                            <a href="{{ route('user.token') }}" class="btn btn-primary">{{__('Purchase Token')}}</a>
                        </div>
                        @endif

                    </div>
                </div>{{-- .card --}}
            </div>
        </div>
        {!! UserPanel::kyc_footer_info() !!}
    </div>
</div>
@endsection


@push('footer')
<script type="text/javascript">

(function() {

    var kycLink = 'https://verify-with.blockpass.org/?clientId=bonuz_public_kyc_67c49&serviceName=Bonuz%3A%20Public%20KYC&env=prod';

    var termsAndConditionsCheckBox = document.getElementById('term-condition');
    var $termsAndConditionsCheckBox = $(termsAndConditionsCheckBox);

    var infoCurrect = document.getElementById('info-currect');
    var $infoCurrect = $(infoCurrect);

    var certification = document.getElementById('certification');
    var $certification = $(certification);
    
    function changeBtnVisibility() {
        var allCheckboxesChecked = termsAndConditionsCheckBox.checked
            && infoCurrect.checked
            && certification.checked;

        var newKycLink = allCheckboxesChecked ? kycLink : "#";
        $('#kyc-toggle-me').attr('href', newKycLink);
    }

    $termsAndConditionsCheckBox.change(changeBtnVisibility);
    $infoCurrect.change(changeBtnVisibility);
    $certification.change(changeBtnVisibility);
})();

</script>
@endpush