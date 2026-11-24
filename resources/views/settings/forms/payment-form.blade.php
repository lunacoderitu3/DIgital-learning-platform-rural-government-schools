{{-- Currency Settings --}}
<div class="border border-secondary rounded-lg mb-3">
    <h3 class="col-12 page-title mt-3 ">
        {{ __('Currency Settings') }}
    </h3>
    <div class="row my-4 mx-1">

        <div class="form-group col-sm-12 col-md-6">
            <label for="currency_code">{{ __('Currency') }} <span class="text-danger">*</span></label>

            {!! Form::select(
                'currency_code',
                [
                    'USD' => 'USD',
                    'AED' => 'AED',
                    'AFN' => 'AFN',
                    'ALL' => 'ALL',
                    'AMD' => 'AMD',
                    'ANG' => 'ANG',
                    'AOA' => 'AOA',
                    'ARS' => 'ARS',
                    'AUD' => 'AUD',
                    'AWG' => 'AWG',
                    'AZN' => 'AZN',
                    'BAM' => 'BAM',
                    'BBD' => 'BBD',
                    'BDT' => 'BDT',
                    'BGN' => 'BGN',
                    'BMD' => 'BMD',
                    'BND' => 'BND',
                    'BOB' => 'BOB',
                    'BRL' => 'BRL',
                    'BSD' => 'BSD',
                    'BWP' => 'BWP',
                    'BYN' => 'BYN',
                    'BZD' => 'BZD',
                    'CAD' => 'CAD',
                    'CDF' => 'CDF',
                    'CHF' => 'CHF',
                    'CNY' => 'CNY',
                    'COP' => 'COP',
                    'CRC' => 'CRC',
                    'CVE' => 'CVE',
                    'CZK' => 'CZK',
                    'DKK' => 'DKK',
                    'DOP' => 'DOP',
                    'DZD' => 'DZD',
                    'EGP' => 'EGP',
                    'ETB' => 'ETB',
                    'EUR' => 'EUR',
                    'FJD' => 'FJD',
                    'FKP' => 'FKP',
                    'GBP' => 'GBP',
                    'GEL' => 'GEL',
                    'GHS' => 'GHS',
                    'GIP' => 'GIP',
                    'GMD' => 'GMD',
                    'GTQ' => 'GTQ',
                    'GYD' => 'GYD',
                    'HKD' => 'HKD',
                    'HNL' => 'HNL',
                    'HTG' => 'HTG',
                    'HUF' => 'HUF',
                    'IDR' => 'IDR',
                    'ILS' => 'ILS',
                    'INR' => 'INR',
                    'ISK' => 'ISK',
                    'JMD' => 'JMD',
                    'KES' => 'KES',
                    'KGS' => 'KGS',
                    'KHR' => 'KHR',
                    'KYD' => 'KYD',
                    'KZT' => 'KZT',
                    'LAK' => 'LAK',
                    'LBP' => 'LBP',
                    'LKR' => 'LKR',
                    'LRD' => 'LRD',
                    'LSL' => 'LSL',
                    'MAD' => 'MAD',
                    'MDL' => 'MDL',
                    'MKD' => 'MKD',
                    'MMK' => 'MMK',
                    'MNT' => 'MNT',
                    'MOP' => 'MOP',
                    'MUR' => 'MUR',
                    'MVR' => 'MVR',
                    'MWK' => 'MWK',
                    'MXN' => 'MXN',
                    'MYR' => 'MYR',
                    'MZN' => 'MZN',
                    'NAD' => 'NAD',
                    'NGN' => 'NGN',
                    'NIO' => 'NIO',
                    'NOK' => 'NOK',
                    'NPR' => 'NPR',
                    'NZD' => 'NZD',
                    'PAB' => 'PAB',
                    'PEN' => 'PEN',
                    'PGK' => 'PGK',
                    'PHP' => 'PHP',
                    'PKR' => 'PKR',
                    'PLN' => 'PLN',
                    'QAR' => 'QAR',
                    'RON' => 'RON',
                    'RSD' => 'RSD',
                    'RUB' => 'RUB',
                    'SAR' => 'SAR',
                    'SBD' => 'SBD',
                    'SCR' => 'SCR',
                    'SEK' => 'SEK',
                    'SGD' => 'SGD',
                    'SHP' => 'SHP',
                    'SLE' => 'SLE',
                    'SOS' => 'SOS',
                    'SRD' => 'SRD',
                    'STD' => 'STD',
                    'SZL' => 'SZL',
                    'THB' => 'THB',
                    'TJS' => 'TJS',
                    'TOP' => 'TOP',
                    'TRY' => 'TRY',
                    'TTD' => 'TTD',
                    'TWD' => 'TWD',
                    'TZS' => 'TZS',
                    'UAH' => 'UAH',
                    'UYU' => 'UYU',
                    'UZS' => 'UZS',
                    'WST' => 'WST',
                    'XCD' => 'XCD',
                    'YER' => 'YER',
                    'ZAR' => 'ZAR',
                    'ZMW' => 'ZMW',
                    'OMR' => 'OMR',
                    'XAF' => 'XAF',
                ],
                $settings['currency_code'] ?? null,
                ['id' => 'currency_code', 'class' => 'form-control select2-dropdown'],
            ) !!}


        </div>
        <div class="form-group col-md-6 col-sm-12">
            <label for="currency_symbol">{{ __('currency_symbol') }} <span class="text-danger">*</span></label>
            <input name="currency_symbol" id="currency_symbol" value="{{ $settings['currency_symbol'] ?? '' }}"
                type="text" placeholder="{{ __('currency_symbol') }}" class="form-control" required />
        </div>
    </div>
</div>
{{-- End Currency Settings --}}

{{-- Payment Gateway Tabs --}}
<div class="border border-secondary rounded-lg mb-3">
    {{-- Tab Nav --}}
    <ul class="nav nav-tabs px-3 pt-3" id="paymentGatewayTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="tab-stripe" data-toggle="tab" href="#pane-stripe" role="tab"
                aria-controls="pane-stripe" aria-selected="true">
                {{ __('Stripe') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-razorpay" data-toggle="tab" href="#pane-razorpay" role="tab"
                aria-controls="pane-razorpay" aria-selected="false">
                {{ __('Razorpay') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-paystack" data-toggle="tab" href="#pane-paystack" role="tab"
                aria-controls="pane-paystack" aria-selected="false">
                {{ __('Paystack') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-flutterwave" data-toggle="tab" href="#pane-flutterwave" role="tab"
                aria-controls="pane-flutterwave" aria-selected="false">
                {{ __('Flutterwave') }}
            </a>
        </li>
        @if (Auth::user()->school_id)
            <li class="nav-item">
                <a class="nav-link" id="tab-manual" data-toggle="tab" href="#pane-manual" role="tab"
                    aria-controls="pane-manual" aria-selected="false">
                    {{ __('manual_payment') }}
                </a>
            </li>
        @endif
    </ul>

    {{-- Tab Panes --}}
    <div class="tab-content p-3" id="paymentGatewayTabContent">

        {{-- Stripe Pane --}}
        <div class="tab-pane fade show active" id="pane-stripe" role="tabpanel" aria-labelledby="tab-stripe">
            <div class="row my-3 mx-1">
                <div class="form-group col-sm-12 col-md-6">
                    <label for="stripe_status">{{ __('status') }} <span class="text-danger">*</span></label>
                    <select name="gateway[Stripe][status]" id="stripe_status" class="form-control">
                        <option value="1"
                            {{ isset($paymentGateway['Stripe']['status']) && $paymentGateway['Stripe']['status'] == 1 ? 'selected' : '' }}>
                            {{ __('Enable') }}
                        </option>
                        <option value="0"
                            {{ isset($paymentGateway['Stripe']['status']) && $paymentGateway['Stripe']['status'] == 0 ? 'selected' : '' }}>
                            {{ __('Disable') }}
                        </option>
                    </select>
                </div>
                <input type="hidden" name="gateway[Stripe][currency_code]" id="stripe_currency"
                    value="{{ $paymentGateway['Stripe']['currency_code'] ?? '' }}">

                <div class="form-group col-sm-12 col-md-6">
                    <label for="stripe_publishable_key">{{ __('Stripe Publishable Key') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Stripe][api_key]" id="stripe_publishable_key"
                        class="form-control" placeholder="Stripe Publishable Key"
                        value="{{ $paymentGateway['Stripe']['api_key'] ?? '' }}">
                </div>

                <div class="form-group col-sm-12 col-md-6">
                    <label for="stripe_secret_key">{{ __('Stripe Secret Key') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Stripe][secret_key]" id="stripe_secret_key" class="form-control"
                        placeholder="Stripe Secret Key" value="{{ $paymentGateway['Stripe']['secret_key'] ?? '' }}">
                </div>

                <div class="form-group col-sm-12 col-md-6">
                    <label for="stripe_webhook_secret">{{ __('Stripe Webhook Secret') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Stripe][webhook_secret_key]" id="stripe_webhook_secret"
                        class="form-control" placeholder="Stripe Webhook Secret"
                        value="{{ $paymentGateway['Stripe']['webhook_secret_key'] ?? '' }}">
                </div>

                @if (Auth::user()->school_id)
                    <div class="form-group col-sm-12 col-md-6">
                        <label for="stripe_webhook_url">{{ __('Stripe Webhook URL') }}</label>
                        <input type="text" name="gateway[Stripe][webhook_url]" id="stripe_webhook_url"
                            class="form-control" placeholder="Stripe Webhook URL" disabled
                            value="{{ url('webhook/stripe') }}">
                    </div>
                @else
                    <div class="form-group col-sm-12 col-md-6">
                        <label for="stripe_webhook_url">{{ __('Stripe Webhook URL') }}</label>
                        <input type="text" name="gateway[Stripe][webhook_url]" id="stripe_webhook_url"
                            class="form-control" placeholder="Stripe Webhook URL" disabled
                            value="{{ url('subscription/webhook/stripe') }}">
                    </div>
                @endif
            </div>
        </div>{{-- /.tab-pane stripe --}}

        {{-- Razorpay Pane --}}
        <div class="tab-pane fade" id="pane-razorpay" role="tabpanel" aria-labelledby="tab-razorpay">
            <div class="row my-3 mx-1">
                <div class="form-group col-sm-12 col-md-6">
                    <label for="razorpay_status">{{ __('status') }} <span class="text-danger">*</span></label>
                    <select name="gateway[Razorpay][status]" id="razorpay_status" class="form-control">
                        <option value="0"
                            {{ isset($paymentGateway['Razorpay']['status']) && $paymentGateway['Razorpay']['status'] == 0 ? 'selected' : '' }}>
                            {{ __('Disable') }}
                        </option>
                        <option value="1"
                            {{ isset($paymentGateway['Razorpay']['status']) && $paymentGateway['Razorpay']['status'] == 1 ? 'selected' : '' }}>
                            {{ __('Enable') }}
                        </option>
                    </select>
                </div>
                <input type="hidden" name="gateway[Razorpay][currency_code]" id="razorpay_currency"
                    value="{{ $paymentGateway['Razorpay']['currency_code'] ?? '' }}">

                <div class="form-group col-sm-12 col-md-6">
                    <label for="razorpay_api_key">{{ __('Razorpay Api Key') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Razorpay][api_key]" id="razorpay_api_key"
                        class="form-control" placeholder="Razorpay Api Key"
                        value="{{ $paymentGateway['Razorpay']['api_key'] ?? '' }}">
                </div>

                <div class="form-group col-sm-12 col-md-6">
                    <label for="razorpay_secret_key">{{ __('Razorpay Secret Key') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Razorpay][secret_key]" id="razorpay_secret_key"
                        class="form-control" placeholder="Razorpay Secret Key"
                        value="{{ $paymentGateway['Razorpay']['secret_key'] ?? '' }}">
                </div>

                <div class="form-group col-sm-12 col-md-6">
                    <label for="razorpay_webhook_secret">{{ __('Razorpay Webhook Secret') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Razorpay][webhook_secret_key]" id="razorpay_webhook_secret"
                        class="form-control" placeholder="Razorpay Webhook Secret"
                        value="{{ $paymentGateway['Razorpay']['webhook_secret_key'] ?? '' }}">
                </div>

                @if (Auth::user()->school_id)
                    <div class="form-group col-sm-12 col-md-6">
                        <label for="razorpay_webhook_url">{{ __('Razorpay Webhook URL') }}</label>
                        <input type="text" name="gateway[Razorpay][webhook_url]" id="razorpay_webhook_url"
                            class="form-control" placeholder="Razorpay Webhook URL" readonly
                            value="{{ url('webhook/razorpay') }}">
                    </div>
                @else
                    <div class="form-group col-sm-12 col-md-6">
                        <label for="razorpay_webhook_url">{{ __('Razorpay Webhook URL') }}</label>
                        <input type="text" name="gateway[Razorpay][webhook_url]" id="razorpay_webhook_url"
                            class="form-control" placeholder="Razorpay Webhook URL" readonly
                            value="{{ url('subscription/webhook/razorpay') }}">
                    </div>
                @endif
            </div>
        </div>{{-- /.tab-pane razorpay --}}

        {{-- Paystack Pane --}}
        <div class="tab-pane fade" id="pane-paystack" role="tabpanel" aria-labelledby="tab-paystack">
            <div class="row my-3 mx-1">
                <div class="form-group col-sm-12 col-md-6">
                    <label for="paystack_status">{{ __('status') }} <span class="text-danger">*</span></label>
                    <select name="gateway[Paystack][status]" id="paystack_status" class="form-control">
                        <option value="0"
                            {{ isset($paymentGateway['Paystack']['status']) && $paymentGateway['Paystack']['status'] == 0 ? 'selected' : '' }}>
                            {{ __('Disable') }}
                        </option>
                        <option value="1"
                            {{ isset($paymentGateway['Paystack']['status']) && $paymentGateway['Paystack']['status'] == 1 ? 'selected' : '' }}>
                            {{ __('Enable') }}
                        </option>
                    </select>
                </div>
                <input type="hidden" name="gateway[Paystack][currency_code]" id="paystack_currency"
                    value="{{ $paymentGateway['Paystack']['currency_code'] ?? '' }}">

                <div class="form-group col-sm-12 col-md-6">
                    <label for="paystack_api_key">{{ __('Paystack Api Key') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Paystack][api_key]" id="paystack_api_key"
                        class="form-control" placeholder="Paystack Publishable Key"
                        value="{{ $paymentGateway['Paystack']['api_key'] ?? '' }}">
                </div>

                <div class="form-group col-sm-12 col-md-6">
                    <label for="paystack_secret_key">{{ __('Paystack Secret Key') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Paystack][secret_key]" id="paystack_secret_key"
                        class="form-control" placeholder="Paystack Secret Key"
                        value="{{ $paymentGateway['Paystack']['secret_key'] ?? '' }}">
                </div>

                @if (Auth::user()->school_id)
                    <div class="form-group col-sm-12 col-md-6">
                        <label for="paystack_webhook_url">{{ __('Paystack Webhook URL') }}</label>
                        <input type="text" name="gateway[Paystack][webhook_url]" id="paystack_webhook_url"
                            class="form-control" placeholder="Paystack Webhook URL" disabled
                            value="{{ url('webhook/paystack') }}">
                    </div>
                @else
                    <div class="form-group col-sm-12 col-md-6">
                        <label for="paystack_webhook_url">{{ __('Paystack Webhook URL') }}</label>
                        <input type="text" name="gateway[Paystack][webhook_url]" id="paystack_webhook_url"
                            class="form-control" placeholder="Paystack Webhook URL" disabled
                            value="{{ url('subscription/webhook/paystack') }}">
                    </div>
                @endif
            </div>
        </div>{{-- /.tab-pane paystack --}}

        {{-- Flutterwave Pane --}}
        <div class="tab-pane fade" id="pane-flutterwave" role="tabpanel" aria-labelledby="tab-flutterwave">
            <div class="row my-3 mx-1">
                <div class="form-group col-sm-12 col-md-6">
                    <label for="flutterwave_status">{{ __('status') }} <span class="text-danger">*</span></label>
                    <select name="gateway[Flutterwave][status]" id="flutterwave_status" class="form-control">
                        <option value="0"
                            {{ isset($paymentGateway['Flutterwave']['status']) && $paymentGateway['Flutterwave']['status'] == 0 ? 'selected' : '' }}>
                            {{ __('Disable') }}
                        </option>
                        <option value="1"
                            {{ isset($paymentGateway['Flutterwave']['status']) && $paymentGateway['Flutterwave']['status'] == 1 ? 'selected' : '' }}>
                            {{ __('Enable') }}
                        </option>
                    </select>
                </div>
                <input type="hidden" name="gateway[Flutterwave][currency_code]" id="flutterwave_currency"
                    value="{{ $paymentGateway['Flutterwave']['currency_code'] ?? '' }}">

                <div class="form-group col-sm-12 col-md-6">
                    <label for="flutterwave_publishable_key">{{ __('Flutterwave Api Key') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Flutterwave][api_key]" id="flutterwave_publishable_key"
                        class="form-control" placeholder="Flutterwave Publishable Key"
                        value="{{ $paymentGateway['Flutterwave']['api_key'] ?? '' }}">
                </div>

                <div class="form-group col-sm-12 col-md-6">
                    <label for="flutterwave_secret_key">{{ __('Flutterwave Secret Key') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Flutterwave][secret_key]" id="flutterwave_secret_key"
                        class="form-control" placeholder="Flutterwave Secret Key"
                        value="{{ $paymentGateway['Flutterwave']['secret_key'] ?? '' }}">
                </div>

                <div class="form-group col-sm-12 col-md-6">
                    <label for="flutterwave_webhook_secret">{{ __('Flutterwave Webhook Secret') }} <span
                            class="text-danger">*</span></label>
                    <input type="text" name="gateway[Flutterwave][webhook_secret_key]"
                        id="flutterwave_webhook_secret" class="form-control" placeholder="Flutterwave Webhook Secret"
                        value="{{ $paymentGateway['Flutterwave']['webhook_secret_key'] ?? '' }}">
                </div>

                @if (Auth::user()->school_id)
                    <div class="form-group col-sm-12 col-md-6">
                        <label for="flutterwave_webhook_url">{{ __('Flutterwave Webhook URL') }}</label>
                        <input type="text" name="gateway[Flutterwave][webhook_url]" id="flutterwave_webhook_url"
                            class="form-control" placeholder="Flutterwave Webhook URL" readonly
                            value="{{ url('webhook/flutterwave') }}">
                    </div>
                @else
                    <div class="form-group col-sm-12 col-md-6">
                        <label for="flutterwave_webhook_url">{{ __('Flutterwave Webhook URL') }}</label>
                        <input type="text" name="gateway[Flutterwave][webhook_url]" id="flutterwave_webhook_url"
                            class="form-control" placeholder="Flutterwave Webhook URL" readonly
                            value="{{ url('subscription/webhook/flutterwave') }}">
                    </div>
                @endif
            </div>
        </div>{{-- /.tab-pane flutterwave --}}
        <div class="tab-pane fade" id="pane-manual" role="tabpanel" aria-labelledby="tab-manual">
            <div class="row my-3 mx-1">
                {{-- Status (Enable/Disable) --}}
                <div class="form-group col-sm-12 col-md-6">
                    <label for="manual_status">{{ __('status') }} <span class="text-danger">*</span></label>
                    <select name="gateway[Manual][status]" id="manual_status" class="form-control">
                        <option value="0"
                            {{ isset($paymentGateway['Manual']['status']) && $paymentGateway['Manual']['status'] == 0 ? 'selected' : '' }}>
                            {{ __('Disable') }}</option>
                        <option value="1"
                            {{ isset($paymentGateway['Manual']['status']) && $paymentGateway['Manual']['status'] == 1 ? 'selected' : '' }}>
                            {{ __('Enable') }}</option>
                    </select>
                </div>

                <input type="hidden" name="gateway[Manual][currency_code]" id="manual_currency"
                    value="{{ $paymentGateway['Manual']['currency_code'] ?? '' }}">

                {{-- Bank Details / Instructions (Rich Text Editor) --}}
                <div class="form-group col-sm-12">
                    <label for="manual_payment_details">{{ __('Bank Details & Payment Instructions') }}</label>

                    <textarea name="gateway[Manual][details]" id="manual_payment_details" class="form-control tinymce-editor"
                        rows="6"
                        placeholder="{{ __('Enter bank name, account number, IFSC code, UPI ID, or any payment instructions...') }}">{{ $paymentGateway['Manual']['details'] ?? '' }}</textarea>
                    @if (!empty($paymentGateway['Manual']['file']['file_url']))
                        <div class="mt-2">
                            <label>{{ __('Current QR Code') }}:</label><br>
                            <img src="{{ $paymentGateway['Manual']['file']['file_url'] }}" alt="QR Code"
                                style="max-height: 120px;" class="rounded border p-1">
                        </div>
                    @endif
                </div>

            </div>
        </div>

    </div>{{-- /.tab-content --}}





</div>{{-- /.payment gateway tabs card --}}

{{-- Manual Payment Pane --}}

{{-- /.tab-pane manual --}}

{{-- Bank transfer --}}
{{-- <div class="border border-secondary rounded-lg mb-3">


    <h3 class="col-12 page-title mt-3 ">
        {{ __('bank_transfer') }}
</h3>
<div class="row my-4 mx-1">
    <div class="form-group col-sm-12 col-md-6">
        <label for="bank_transfer_status">{{__("status")}} <span class="text-danger">*</span></label>
        <select name="gateway[bank_transfer][status]" id="bank_transfer_status" class="form-control">
            <option value="0" {{(isset($paymentGateway["bank_transfer"]["status"]) && $paymentGateway["bank_transfer"]["status"]==0) ? 'selected' : ''}}>{{__("Disable")}}</option>
            <option value="1" {{(isset($paymentGateway["bank_transfer"]["status"]) && $paymentGateway["bank_transfer"]["status"]==1) ? 'selected' : ''}}>{{__("Enable")}}</option>
        </select>
    </div>
    <input type="hidden" name="gateway[bank_transfer][currency_code]" id="bank_transfer_currency" value="{{$paymentGateway["bank_transfer"]['currency_code'] ?? ''}}">

    <div class="form-group col-sm-12 col-md-6">
        <label for="bank_name">{{__("bank_name")}} <span class="text-danger">*</span></label>
        <input type="text" name="gateway[bank_transfer][bank_name]" id="bank_name" class="form-control" placeholder="{{ __('bank_name') }}" required value="{{$paymentGateway["bank_transfer"]['bank_name'] ?? ''}}">
    </div>

    <div class="form-group col-sm-12 col-md-6">
        <label for="account_name">{{__("account_name")}} <span class="text-danger">*</span></label>
        <input type="text" name="gateway[bank_transfer][account_name]" id="razorpay_secret_key" class="form-control" placeholder="{{ __('account_name') }}" required value="{{$paymentGateway["bank_transfer"]['account_name'] ??''}}">
    </div>

    <div class="form-group col-sm-12 col-md-6">
        <label for="account_no">{{__("account_no")}} <span class="text-danger">*</span></label>
        <input type="text" name="gateway[bank_transfer][account_no]" id="account_no" class="form-control" placeholder="{{ __('account_no') }}" required value="{{$paymentGateway["bank_transfer"]['account_no'] ?? ''}}">
    </div>


</div>

</div> --}}
