<?php

/**
 * Plugin Name: Zarinpal Gateway For Pro-VIP
 * Plugin URI: -
 * Description: This plugin lets you use Zarinpal gateway in pro-vip wp plugin.
 * Version: 1.1
 * Author: Masoud Amini
 * Author URI: masoudamini.ir
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html.
 */
defined('ABSPATH') or exit;

if (!function_exists('init_Zarinpal_gateway_pv_class')) {
    add_action('plugins_loaded', 'init_Zarinpal_gateway_pv_class');

    function init_Zarinpal_gateway_pv_class()
    {
        add_filter('pro_vip_currencies_list', 'currencies_check');

        function currencies_check($list)
        {
            if (!in_array('IRT', $list)) {
                $list['IRT'] = [
                    'name'   => 'تومان ایران',
                    'symbol' => 'تومان',
                ];
            }

            if (!in_array('IRR', $list)) {
                $list['IRR'] = [
                    'name'   => 'ریال ایران',
                    'symbol' => 'ریال',
                ];
            }

            return $list;
        }

        if (class_exists('Pro_VIP_Payment_Gateway') && !class_exists('Pro_VIP_Zarinpal_Gateway')) {
            class Pro_VIP_Zarinpal_Gateway extends Pro_VIP_Payment_Gateway
            {
                public $id = 'Zarinpal',
                        $settings = [],
                        $frontendLabel = 'زرین‌پال',
                        $adminLabel = 'زرین‌پال';

                public function __construct()
                {
                    parent::__construct();
                }

                public function beforePayment(Pro_VIP_Payment $payment)
                {
                    $MerchantID = $this->settings['api_key']; //Required
                    $Amount = intval($payment->price); // Required
                    $orderId = $payment->paymentId; // Required
                    $Description = 'پرداخت فاکتور به شماره ی'.$orderId; // Required
                    $CallbackURL = add_query_arg('order', $orderId, $this->getReturnUrl()); // $this->getReturnUrl();
                    //$currency = $order->get_order_currency();

                    if (pvGetOption('currency') === 'IRR') {
                        $amount /= 10;
                    }

                    $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);

                    $res = $client->PaymentRequest([
                        'MerchantID'  => $MerchantID,
                        'Amount'      => $Amount,
                        'Description' => $Description,
                        'Email'       => '',
                        'Mobile'      => '',
                        'CallbackURL' => $CallbackURL,
                    ]);


                    $Result = $res->Status;

                    if ($Result == 100) {
                        $payment->key = $orderId;
                        $payment->user = get_current_user_id();
                        $payment->save();

                        $payment_url = 'https://www.zarinpal.com/pg/StartPay/';

                        header("Location: $payment_url".$res->Authority);
                    } else {
                        pvAddNotice('خطا در هنگام اتصال به زرین‌پال.');

                        return;
                    }
                }

                public function afterPayment()
                {
                    if (isset($_GET['order'])) {
                        $orderId = $_GET['order'];
                    } else {
                        $orderId = 0;
                    }

                    if ($orderId) {
                        $payment = new Pro_VIP_Payment($orderId);
                        $MerchantID = $this->settings['api_key']; //Required
                        $Amount = intval($payment->price); //  - ریال به مبلغ Required
                        $Authority = $_GET['Authority'];
                        $getStatus = $_GET['Status'];

                        if (pvGetOption('currency') === 'IRR') {
                            $amount /= 10;
                        }

                        if ($getStatus == 'OK') {
                            $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
                            $result = $client->PaymentVerification([
                                'MerchantID' => $MerchantID,
                                'Authority'  => $Authority,
                                'Amount'     => $Amount,
                            ]);

                            $Result = $result->Status;

                            if ($Result == 100) {
                                pvAddNotice('پرداخت شما با موفقیت انجام شد. کد پیگیری: '.$orderId, 'success');
                                $payment->status = 'publish';
                                $payment->save();

                                $this->paymentComplete($payment);
                            } else {
                                pvAddNotice('خطایی به هنگام پرداخت پیش آمده. کد خطا عبارت است از :'.$Result.' . برای آگاهی از دلیل خطا کد آن را به زرین‌پال ارائه نمایید.');
                                $this->paymentFailed($payment);

                                return false;
                            }
                        } else {
                            pvAddNotice('به نظر می رسد عملیات پرداخت توسط شما لغو گردیده، اگر چنین نیست مجددا اقدام به پرداخت فاکتور نمایید.');
                            $this->paymentFailed($payment);

                            return false;
                        }
                    }
                }

                public function adminSettings(PV_Framework_Form_Builder $form)
                {
                    $form->textfield('api_key')->label('کلید API');
                }
            }

            Pro_VIP_Payment_Gateway::registerGateway('Pro_VIP_Zarinpal_Gateway');
        }
    }
}
