<?php

namespace Lknwoo\IntegrationRedeForWoocommerce\Includes;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Central catalog of e.Rede return codes.
 *
 * Rede returns a single flat `returnCode` in the transaction response. This
 * class maps that code to its official message (English), following the
 * official e.Rede tables at https://developer.userede.com.br/e-rede:
 *
 *  - Authorization returns (issuer/card declines): 00, 101-124, 170-175
 *  - Integration returns (request validation / processing): 1-100, 132-159, ...
 *  - 3DS returns: 200-269, 3000-3019
 *  - Cancellation / refund returns: 351-374
 *
 * Messages use the `woo-rede` text domain so they can be translated later.
 *
 * The ABECS "brand returns" (alphanumeric codes like A1, B1, FA, FD) are a
 * separate namespace returned in `brand.returnCode` (with the
 * `Transaction-Response: brand-return-opened` header) and are NOT part of this
 * catalog, because the plugin only translates the top-level numeric
 * `returnCode`.
 */
final class LknIntegrationRedeForWoocommerceAbecsCodes
{
    /**
     * Translate a return code into the official e.Rede message.
     *
     * When the code is not mapped, the original Rede message is returned.
     * When there is no message at all, a generic "Unknown error" is returned.
     *
     * @param string|int $returnCode Return code (returnCode).
     * @param string     $fallback   Original message to use when the code is not mapped.
     * @return string Official message, the original message, or "Unknown error".
     */
    public static function translate($returnCode, $fallback = '')
    {
        $code = trim((string) $returnCode);
        $codes = self::codes();

        if (isset($codes[$code])) {
            return $codes[$code];
        }

        $fallback = trim((string) $fallback);

        if ('' !== $fallback) {
            return $fallback;
        }

        return __('Unknown error', 'woo-rede');
    }

    /**
     * Map code => official message (evaluated at runtime to support __()).
     *
     * @return array<string, string>
     */
    private static function codes()
    {
        static $codes = null;

        if (null !== $codes) {
            return $codes;
        }

        $codes = array(
            // ===== Authorization returns (issuer / card declines) =====
            '00'  => __('Success', 'woo-rede'),
            '101' => __('Unauthorized. Problems on the card, contact the issuer.', 'woo-rede'),
            '102' => __('Unauthorized. Check the situation of the store with the issuer.', 'woo-rede'),
            '103' => __('Unauthorized. Please try again.', 'woo-rede'),
            '104' => __('Unauthorized. Please try again.', 'woo-rede'),
            '105' => __('Unauthorized. Restricted card.', 'woo-rede'),
            '106' => __('Error in issuer processing. Please try again.', 'woo-rede'),
            '107' => __('Unauthorized. Please try again.', 'woo-rede'),
            '108' => __('Unauthorized. Value not allowed for this type of card.', 'woo-rede'),
            '109' => __('Unauthorized. Nonexistent card.', 'woo-rede'),
            '110' => __('Unauthorized. Transaction type not allowed for this card.', 'woo-rede'),
            '111' => __('Unauthorized. Insufficient funds.', 'woo-rede'),
            '112' => __('Unauthorized. Expiry date expired.', 'woo-rede'),
            '113' => __('Unauthorized. Identified moderate risk by the issuer.', 'woo-rede'),
            '114' => __('Unauthorized. The card does not belong to the payment network.', 'woo-rede'),
            '115' => __('Unauthorized. Exceeded the limit of transactions allowed in the period.', 'woo-rede'),
            '116' => __('Unauthorized. Please contact the Card Issuer.', 'woo-rede'),
            '117' => __('Transaction not found.', 'woo-rede'),
            '118' => __('Unauthorized. Card locked.', 'woo-rede'),
            '119' => __('Unauthorized. Invalid security code', 'woo-rede'),
            '121' => __('Error processing. Please try again.', 'woo-rede'),
            '122' => __('Transaction previously sent', 'woo-rede'),
            '123' => __('Unauthorized. Bearer requested the end of the recurrences in the issuer.', 'woo-rede'),
            '124' => __('Unauthorized. Contact Rede', 'woo-rede'),
            '170' => __('Zero dollar transaction not allowed for this card.', 'woo-rede'),
            '172' => __('CVC2 required for Zero Dollar Transaction.', 'woo-rede'),
            '174' => __('Zero dollar transaction success.', 'woo-rede'),
            '175' => __('Zero dollar transaction denied.', 'woo-rede'),

            // ===== Integration returns (request validation / processing) =====
            '1'    => __('expirationYear: Invalid parameter size', 'woo-rede'),
            '2'    => __('expirationYear: Invalid parameter format', 'woo-rede'),
            '3'    => __('expirationYear: Required parameter missing', 'woo-rede'),
            '4'    => __('cavv: Invalid parameter size', 'woo-rede'),
            '5'    => __('cavv: Invalid parameter format', 'woo-rede'),
            '6'    => __('postalCode: Invalid parameter size', 'woo-rede'),
            '7'    => __('postalCode: Invalid parameter format', 'woo-rede'),
            '8'    => __('postalCode: Required parameter missing', 'woo-rede'),
            '9'    => __('complement: Invalid parameter size', 'woo-rede'),
            '10'   => __('complement: Invalid parameter format', 'woo-rede'),
            '11'   => __('departureTax: Invalid parameter format', 'woo-rede'),
            '12'   => __('documentNumber: Invalid parameter size', 'woo-rede'),
            '13'   => __('documentNumber: Invalid parameter format', 'woo-rede'),
            '14'   => __('documentNumber: Required parameter missing', 'woo-rede'),
            '15'   => __('securityCode: Invalid parameter size', 'woo-rede'),
            '16'   => __('securityCode: Invalid parameter format', 'woo-rede'),
            '17'   => __('distributorAffiliation: Invalid parameter size', 'woo-rede'),
            '18'   => __('distributorAffiliation: Invalid parameter format', 'woo-rede'),
            '19'   => __('xid: Invalid parameter size', 'woo-rede'),
            '20'   => __('eci: Invalid parameter format', 'woo-rede'),
            '21'   => __('xid: Required parameter for Visa card is missing', 'woo-rede'),
            '22'   => __('street: Required parameter missing', 'woo-rede'),
            '23'   => __('street: Invalid parameter format', 'woo-rede'),
            '24'   => __('affiliation: Invalid parameter size', 'woo-rede'),
            '25'   => __('affiliation: Invalid parameter format', 'woo-rede'),
            '26'   => __('affiliation: Required parameter missing', 'woo-rede'),
            '27'   => __('Parameter cavv or eci missing', 'woo-rede'),
            '28'   => __('code: Invalid parameter size', 'woo-rede'),
            '29'   => __('code: Invalid parameter format', 'woo-rede'),
            '30'   => __('code: Required parameter missing', 'woo-rede'),
            '31'   => __('softdescriptor: Invalid parameter size', 'woo-rede'),
            '32'   => __('softdescriptor: Invalid parameter format', 'woo-rede'),
            '33'   => __('expirationMonth: Invalid parameter format', 'woo-rede'),
            '34'   => __('code: Invalid parameter format', 'woo-rede'),
            '35'   => __('expirationMonth: Required parameter missing', 'woo-rede'),
            '36'   => __('cardNumber: Invalid parameter size', 'woo-rede'),
            '37'   => __('cardNumber: Invalid parameter format', 'woo-rede'),
            '38'   => __('cardNumber: Required parameter missing', 'woo-rede'),
            '39'   => __('reference: Invalid parameter size', 'woo-rede'),
            '40'   => __('reference: Invalid parameter format', 'woo-rede'),
            '41'   => __('reference: Required parameter missing', 'woo-rede'),
            '43'   => __('number: Invalid parameter size', 'woo-rede'),
            '44'   => __('number: Invalid parameter format', 'woo-rede'),
            '45'   => __('number: Required parameter missing', 'woo-rede'),
            '46'   => __('installments: Not correspond to authorization transaction', 'woo-rede'),
            '47'   => __('origin: Invalid parameter format', 'woo-rede'),
            '48'   => __('brandTid: Invalid parameter size', 'woo-rede'),
            '49'   => __('The value of the transaction exceeds the authorized', 'woo-rede'),
            '50'   => __('installments: Invalid parameter format', 'woo-rede'),
            '51'   => __('Product or service disabled for this merchant. Contact Rede', 'woo-rede'),
            '53'   => __('Transaction not allowed for the issuer. Contact Rede.', 'woo-rede'),
            '54'   => __('installments: Parameter not allowed for this transaction', 'woo-rede'),
            '55'   => __('cardHolderName: Invalid parameter size', 'woo-rede'),
            '56'   => __('Error in reported data. Try again.', 'woo-rede'),
            '57'   => __('affiliation: Invalid merchant', 'woo-rede'),
            '58'   => __('Unauthorized. Contact issuer.', 'woo-rede'),
            '59'   => __('cardHolderName: Invalid parameter format', 'woo-rede'),
            '60'   => __('street: Invalid parameter size', 'woo-rede'),
            '61'   => __('subscription: Invalid parameter format', 'woo-rede'),
            '63'   => __('softdescriptor: Not enabled for this merchant', 'woo-rede'),
            '64'   => __('Transaction not processed. Try again', 'woo-rede'),
            '65'   => __('token: Invalid token', 'woo-rede'),
            '66'   => __('departureTax: Invalid parameter size', 'woo-rede'),
            '67'   => __('departureTax: Invalid parameter format', 'woo-rede'),
            '68'   => __('departureTax: Required parameter missing', 'woo-rede'),
            '69'   => __('Transaction not allowed for this product or service.', 'woo-rede'),
            '70'   => __('amount: Invalid parameter size', 'woo-rede'),
            '71'   => __('amount: Invalid parameter format', 'woo-rede'),
            '72'   => __('Contact issuer.', 'woo-rede'),
            '73'   => __('amount: Required parameter missing', 'woo-rede'),
            '74'   => __('Communication failure. Try again', 'woo-rede'),
            '75'   => __('departureTax: Parameter should not be sent for this type of transaction', 'woo-rede'),
            '76'   => __('kind: Invalid parameter format', 'woo-rede'),
            '78'   => __('Transaction does not exist', 'woo-rede'),
            '79'   => __('Expired card. Transaction cannot be resubmitted. Contact issuer.', 'woo-rede'),
            '80'   => __('Unauthorized. Contact issuer. (Insufficient funds)', 'woo-rede'),
            '82'   => __('Unauthorized transaction for debit card.', 'woo-rede'),
            '83'   => __('Unauthorized. Contact issuer.', 'woo-rede'),
            '84'   => __('Unauthorized. Transaction cannot be resubmitted. Contact issuer.', 'woo-rede'),
            '85'   => __('complement: Invalid parameter size', 'woo-rede'),
            '86'   => __('Expired card', 'woo-rede'),
            '87'   => __('At least one of the following fields must be filled: tid or reference', 'woo-rede'),
            '88'   => __('Merchant not approved. Regulate your website and contact the Rede to return to transact.', 'woo-rede'),
            '89'   => __('token: Invalid token', 'woo-rede'),
            '97'   => __('tid: Invalid parameter size', 'woo-rede'),
            '98'   => __('tid: Invalid parameter format', 'woo-rede'),
            '99'   => __('BusinessApplicationIdentifier: Invalid parameter format.', 'woo-rede'),
            '100'  => __('WalletId: Invalid parameter format.', 'woo-rede'),
            '132'  => __('DirectoryServerTransactionId: Invalid parameter size.', 'woo-rede'),
            '133'  => __('ThreedIndicator: Invalid parameter value.', 'woo-rede'),
            '150'  => __('Timeout. Try again', 'woo-rede'),
            '151'  => __('installments: Greater than allowed', 'woo-rede'),
            '153'  => __('documentNumber: Invalid number', 'woo-rede'),
            '154'  => __('embedded: Invalid parameter format', 'woo-rede'),
            '155'  => __('eci: Required parameter missing', 'woo-rede'),
            '156'  => __('eci: Invalid parameter size', 'woo-rede'),
            '157'  => __('cavv: Required parameter missing', 'woo-rede'),
            '158'  => __('capture: Type not allowed for this transaction', 'woo-rede'),
            '159'  => __('userAgent: Invalid parameter size', 'woo-rede'),
            '160'  => __('urls: Required parameter missing (kind)', 'woo-rede'),
            '161'  => __('urls: Invalid parameter format', 'woo-rede'),
            '167'  => __('Invalid request JSON', 'woo-rede'),
            '169'  => __('Invalid Content-Type', 'woo-rede'),
            '171'  => __('Operation not allowed for this transaction', 'woo-rede'),
            '173'  => __('Authorization expired', 'woo-rede'),
            '176'  => __('urls: Required parameter missing (url)', 'woo-rede'),
            '370'  => __('Request failed. Contact Rede', 'woo-rede'),
            '898'  => __('PV with invalid ip origin', 'woo-rede'),
            '899'  => __('Unsuccessful. Please contact Rede.', 'woo-rede'),
            '1002' => __('Wallet Id: Invalid Parameter Size.', 'woo-rede'),
            '1003' => __('Wallet Id: Required parameter missing.', 'woo-rede'),
            '1018' => __('MCC Invalid Size.', 'woo-rede'),
            '1019' => __('MCC Parameter Required.', 'woo-rede'),
            '1020' => __('MCC Invalid Format.', 'woo-rede'),
            '1021' => __('PaymentFacilitatorID Invalid Size.', 'woo-rede'),
            '1023' => __('PaymentFacilitatorID Invalid Format.', 'woo-rede'),
            '1027' => __('SubMerchant: SubMerchantID Invalid Size.', 'woo-rede'),
            '1030' => __('CitySubMerchant Invalid Size.', 'woo-rede'),
            '1032' => __('SubMerchant: Estate Invalid Size.', 'woo-rede'),
            '1034' => __('CountrySubMerchant Invalid Size.', 'woo-rede'),
            '1036' => __('CepSubMerchant Invalid Size', 'woo-rede'),
            '1038' => __('CnpjSubMerchant Invalid Size', 'woo-rede'),
            '3020' => __('Cryptogram: Invalid parameter size.', 'woo-rede'),
            '3021' => __('Cryptogram: Invalid parameter format.', 'woo-rede'),
            '3028' => __('Wallet Processing Type: Invalid Parameter Missing', 'woo-rede'),
            '3029' => __('Wallet Processing Type: Invalid Parameter Size', 'woo-rede'),
            '3030' => __('Wallet Processing Type: Invalid Parameter Format', 'woo-rede'),
            '3031' => __('Wallet Sender Tax Identification: Invalid Parameter Missing', 'woo-rede'),
            '3032' => __('Wallet Sender Tax Identification: Invalid Parameter Size', 'woo-rede'),
            '3033' => __('Wallet Sender Tax Identification: Invalid Parameter Format', 'woo-rede'),
            '3034' => __('SubMerchant: Tax Identification Number Invalid Size.', 'woo-rede'),
            '3035' => __('DSubMerchant: Tax Identification Number Invalid Format.', 'woo-rede'),
            '3036' => __('QrCode Expired.', 'woo-rede'),
            '3052' => __('Wallet Code: Required parameter missing.', 'woo-rede'),
            '3053' => __('Wallet Code: Invalid Parameter format.', 'woo-rede'),
            '3054' => __('Wallet Code: Invalid Parameter size.', 'woo-rede'),
            '3055' => __('Wallet Code: Parameter not allowed.', 'woo-rede'),
            '3056' => __('Wallet Id: Parameter not allowed.', 'woo-rede'),
            '3064' => __('Sai: Invalid parameter size.', 'woo-rede'),
            '3065' => __('Sai: Invalid parameter format.', 'woo-rede'),
            '3066' => __('Sai: Required parameter missing.', 'woo-rede'),
            '3067' => __('Cryptogram: Required parameter missing.', 'woo-rede'),
            '3068' => __('Credential Id: Required parameter missing.', 'woo-rede'),
            '3069' => __('Credential Id: Invalid parameter format.', 'woo-rede'),
            '3070' => __('Credential Id: Invalid parameter size.', 'woo-rede'),
            '3076' => __('QrCode: Expiration Date parameter missing.', 'woo-rede'),
            '3077' => __('QrCode: Expiration Date Invalid parameter value.', 'woo-rede'),
            '3078' => __('QrCode: Expiration Date invalid format.', 'woo-rede'),
            '3079' => __('QrCode not processed. Try again.', 'woo-rede'),
            '3081' => __('QrCode: Expiration Date invalid size.', 'woo-rede'),
            '3084' => __('Error generating QrCode Image. Please use the GET Transaction for this operation.', 'woo-rede'),
            '3085' => __('Error generating QrCode Image. Please try again', 'woo-rede'),
            '3086' => __('OrderId: Invalid parameter size.', 'woo-rede'),
            '3087' => __('OrderId: Invalid parameter format', 'woo-rede'),
            '3089' => __('QRCode not generated, please contact Rede', 'woo-rede'),
            '3090' => __('Invalid Pix Key', 'woo-rede'),
            '3091' => __('Error, not generated. Try again', 'woo-rede'),
            '3092' => __('Fail QrCode generate, please try again;', 'woo-rede'),
            '3094' => __('Unsucessful. Please contact Rede.', 'woo-rede'),
            '3095' => __('Unknown Pix Key.', 'woo-rede'),
            '3096' => __('Unsucessful. Try again later.', 'woo-rede'),
            '3097' => __('Unavailable. Please try again later.', 'woo-rede'),
            '3098' => __('Service not authorized', 'woo-rede'),
            '3099' => __('Comunication failure. Try again later.', 'woo-rede'),
            '3100' => __('Receiver Data Last Name: Invalid parameter format.', 'woo-rede'),
            '3101' => __('Receiver Data Tax Id Number: Invalid parameter size.', 'woo-rede'),
            '3102' => __('Receiver Data Tax Id Number: Invalid parameter format.', 'woo-rede'),
            '3103' => __('Receiver Data Wallet Account Identification: Invalid parameter size.', 'woo-rede'),
            '3104' => __('Receiver Data Wallet Account Identification: Invalid parameter format.', 'woo-rede'),
            '3105' => __('Payment Destination: Invalid parameter format.', 'woo-rede'),
            '3106' => __('Payment Destination: Invalid parameter size.', 'woo-rede'),
            '3107' => __('Receiver Data: Required parameter missing.', 'woo-rede'),
            '3108' => __('Receiver Data First Name: Required parameter missing.', 'woo-rede'),
            '3109' => __('Receiver Data Last Name: Required parameter missing.', 'woo-rede'),
            '3110' => __('Receiver Data Tax Id Number: Required parameter missing.', 'woo-rede'),
            '3111' => __('Receiver Data Account Identification: Required parameter missing.', 'woo-rede'),
            '3112' => __('Payment Destination: Parameter not allowed.', 'woo-rede'),
            '3113' => __('MerchantTaxIdInvalidSize: Invalid parameter size.', 'woo-rede'),
            '3114' => __('MerchantTaxIdInvalidFormat: Invalid parameter format.', 'woo-rede'),
            '3115' => __('Receiver Data First Name: Invalid parameter size.', 'woo-rede'),
            '3116' => __('Receiver Data First Name: Invalid parameter format.', 'woo-rede'),
            '3117' => __('Receiver Data Last Name: Invalid parameter size.', 'woo-rede'),
            '3118' => __('CaptureExpirationHours: Invalid parameter format.', 'woo-rede'),
            '3119' => __('Capture: Invalid parameter format', 'woo-rede'),
            '3120' => __('CaptureExpirationHours: Invalid parameter size.', 'woo-rede'),
            '3121' => __('Invalid Amount.', 'woo-rede'),
            '3122' => __('Invalid Amount.', 'woo-rede'),
            '3123' => __('Devolution not confirmed.', 'woo-rede'),
            '3125' => __('Incorrect devolution data', 'woo-rede'),
            '3128' => __('Devolution blocked', 'woo-rede'),
            '3130' => __('Sender Data: Invalid parameter format.', 'woo-rede'),
            '3131' => __('Sender Data: Invalid parameter size.', 'woo-rede'),
            '3132' => __('SubMerchant : Merchant Tax Id Name Invalid Size.', 'woo-rede'),
            '3133' => __('SubMerchant: Merchant Tax Id Name Invalid Format.', 'woo-rede'),
            '3134' => __('marketplaceId: Invalid parameter format.', 'woo-rede'),
            '3135' => __('marketplaceId: Invalid parameter size.', 'woo-rede'),
            '3136' => __('gatewayId:Invalid parameter size.', 'woo-rede'),
            '3137' => __('gatewayId: Invalid parameter format.', 'woo-rede'),
            '3138' => __('Sender address: invalid parameter format', 'woo-rede'),
            '3139' => __('Sender address: invalid parameter size', 'woo-rede'),
            '3140' => __('Sender city: invalid parameter format', 'woo-rede'),
            '3141' => __('Sender city: invalid parameter size', 'woo-rede'),
            '3142' => __('Sender country: invalid parameter format', 'woo-rede'),
            '3143' => __('Sender country: invalid parameter size', 'woo-rede'),
            '3301' => __('PV with invalid ip origin', 'woo-rede'),
            '3302' => __('TransactionLinkId: Invalid parameter size', 'woo-rede'),
            '4005' => __('submerchant/url: invalid parameter size', 'woo-rede'),
            '4006' => __('submerchant/url: invalid parameter format', 'woo-rede'),
            '4007' => __('submerchant/telephone: invalid parameter format', 'woo-rede'),
            '4008' => __('submerchant/telephone: invalid parameter format', 'woo-rede'),
            '4009' => __('partnerCode: invalid parameter format', 'woo-rede'),
            '4010' => __('partnerCode: invalid parameter size', 'woo-rede'),
            '4011' => __('refundReasonCode: invalid parameter format', 'woo-rede'),
            '4012' => __('refundReasonCode: invalid parameter size', 'woo-rede'),
            '4030' => __('token is expired or invalid', 'woo-rede'),

            // ===== 3DS returns =====
            '200'  => __('Cardholder successfully authenticated', 'woo-rede'),
            '201'  => __('Authentication not required', 'woo-rede'),
            '202'  => __('Unauthenticated cardholder', 'woo-rede'),
            '203'  => __('Authentication service not registered for the merchant. Please contact Rede', 'woo-rede'),
            '204'  => __('Cardholder not registered in the issuer\'s authentication program', 'woo-rede'),
            '220'  => __('Transaction request with authentication received. Redirect URL sent', 'woo-rede'),
            '250'  => __('onFailure: Required parameter missing', 'woo-rede'),
            '251'  => __('onFailure: Invalid parameter format', 'woo-rede'),
            '252'  => __('urls: Required parameter missing (url/threeDSecureFailure)', 'woo-rede'),
            '253'  => __('urls: Invalid parameter size (url/threeDSecureFailure)', 'woo-rede'),
            '254'  => __('urls: Invalid parameter format (url/threeDSecureFailure)', 'woo-rede'),
            '255'  => __('urls: Required parameter missing (url/threeDSecureSuccess)', 'woo-rede'),
            '256'  => __('urls: Invalid parameter size (url/threeDSecureSuccess)', 'woo-rede'),
            '257'  => __('urls: Invalid parameter format (url/threeDSecureSuccess)', 'woo-rede'),
            '258'  => __('userAgent: Required parameter missing', 'woo-rede'),
            '259'  => __('urls: Required parameter missing', 'woo-rede'),
            '260'  => __('urls: Required parameter missing (kind/threeDSecureFailure)', 'woo-rede'),
            '261'  => __('urls: Required parameter missing (kind/threeDSecureSuccess)', 'woo-rede'),
            '269'  => __('ChallengePreference: Invalid parameter format', 'woo-rede'),
            '3000' => __('ColorDepth: Required parameter missing', 'woo-rede'),
            '3001' => __('DeviceType3ds: Required parameter missing', 'woo-rede'),
            '3002' => __('JavaEnabled: Required parameter missing', 'woo-rede'),
            '3003' => __('Language: Required parameter missing', 'woo-rede'),
            '3004' => __('TimeZoneOffset: Required parameter missing', 'woo-rede'),
            '3005' => __('ScreenHeight: Required parameter missing', 'woo-rede'),
            '3006' => __('ScreenWidth: Required parameter missing', 'woo-rede'),
            '3007' => __('ColorDepth: Invalid parameter size', 'woo-rede'),
            '3008' => __('DeviceType3ds: Invalid parameter size', 'woo-rede'),
            '3009' => __('Language: Invalid parameter size', 'woo-rede'),
            '3010' => __('TimeZoneOffset: Invalid parameter size', 'woo-rede'),
            '3011' => __('ScreenHeight: Invalid parameter size', 'woo-rede'),
            '3012' => __('ScreenWidth: Invalid parameter size', 'woo-rede'),
            '3013' => __('ColorDepth: Invalid parameter format', 'woo-rede'),
            '3014' => __('DeviceType3ds: Invalid parameter format', 'woo-rede'),
            '3015' => __('JavaEnabled: Invalid parameter format', 'woo-rede'),
            '3016' => __('Language: Invalid parameter format', 'woo-rede'),
            '3017' => __('TimeZoneOffset: Invalid parameter format', 'woo-rede'),
            '3018' => __('ScreenHeight: Invalid parameter format', 'woo-rede'),
            '3019' => __('ScreenWidth: Invalid parameter format', 'woo-rede'),

            // ===== Cancellation / refund returns =====
            '351' => __('Forbidden', 'woo-rede'),
            '353' => __('Transaction not found', 'woo-rede'),
            '354' => __('Transaction with period expired for refund', 'woo-rede'),
            '355' => __('Transaction already canceled.', 'woo-rede'),
            '357' => __('Sum of amount refunds greater than the transaction amount', 'woo-rede'),
            '358' => __('Sum of amount refunds greater than the value processed available for refund', 'woo-rede'),
            '359' => __('Refund successful', 'woo-rede'),
            '360' => __('Refund request has been successful', 'woo-rede'),
            '362' => __('RefundId not found', 'woo-rede'),
            '363' => __('Callback Url characters exceeded 500', 'woo-rede'),
            '365' => __('Partial refund not available.', 'woo-rede'),
            '368' => __('Unsuccessful. Please try again', 'woo-rede'),
            '369' => __('Refund not found', 'woo-rede'),
            '371' => __('Transaction not available for refund. Try again in a few hours', 'woo-rede'),
            '373' => __('No further Refund allowed', 'woo-rede'),
            '374' => __('Refund not allowed. Chargeback requested', 'woo-rede'),
        );

        return $codes;
    }
}
