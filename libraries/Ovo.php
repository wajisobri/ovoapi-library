<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ovo {

    // OVO Endpoint
    protected $API = 'https://api.ovo.id';
    protected $AWS_API = 'https://apigw01.aws.ovo.id';

    protected $apId = 'P72RVSPSF61F72ELYLZI';
    protected $appVersion = '3.6.0';
    protected $actionMark = 'OVO Cash';

    // Device Configuration
    protected $deviceId = '98c0d51e-6ebc-4d5f-b600-44a8b28b06bc';
    protected $osName = 'iOS';
    protected $osVersion = '13.3.1';
    protected $userAgent = 'OVO/3.6.0 (ovo.id; build:8139; iOS 13.3.1) Alamofire/4.7.3';

    protected $pushNotif = '7c83e48253d1cd45f84ed261a4a44a6c6b3efebc6f4510911fcb72b42dae369c';

    // Authorization Token
    private $authToken;


    public function __construct($params)
    {
        $this->authToken = $params['token'];
    }

    public function login2FA($phoneNumber)
    {
        $payload = array(
            'mobile' => $phoneNumber,
            'deviceId' => $this->deviceId
        );

        return self::Request($this->API . '/v2.0/api/auth/customer/login2FA', $payload, self::generateHeaders());
    }

    public function login2FAverify($refId, $otpCode, $phoneNumber)
    {
        $payload = array(
            'refId' => $refId,
            'verificationCode' => $otpCode,
            'mobile' => $phoneNumber,
            'osName' => $this->osName,
            'osVersion' => $this->osVersion,
            'deviceId' => $this->deviceId,
            'appVersion' => $this->appVersion,
            'pushNotificationId' => $this->pushNotif
        );

        return self::Request($this->API . '/v2.0/api/auth/customer/login2FA/verify', $payload, self::generateHeaders());
    }

    public function loginSecurityCode($securityCode, $updateAccessToken)
    {
        $payload = array(
            'deviceUnixtime' => time(),
            'securityCode' => $securityCode,
            'updateAccessToken' => $updateAccessToken
        );

        return self::Request($this->API . '/v2.0/api/auth/customer/loginSecurityCode/verify', $payload, self::generateHeaders());
    }

    public function verifyOVOMember($phoneNumber)
    {
        $payload = array(
            'mobile' => $phoneNumber,
            'amount' => '10000'
        );

        return self::Request($this->API . '/v1.1/api/auth/customer/isOVO', $payload, self::generateHeaders());
    }

    /**
     * Get OVO Account Information in Front Page
     */
    public function getAccountInfo()
    {
        return self::Request($this->API . '/v1.0/api/front/', null, self::generateHeaders());
    }
    
    /**
     * Get budget detail
     *
     * Amount, spending, total spending, and summary
     */
    public function getBudget()
    {
        return self::Request($this->API . '/v1.0/budget/detail', null, self::generateHeaders());
    }

    /**
     * get all notification
     */
    public function getAllNotification()
    {
        return self::Request($this->API . '/v1.0/notification/status/all', null, self::generateHeaders());
    }

    public function walletInquiry()
    {
        return self::parseResponse(self::Request($this->API . '/wallet/inquiry', false, self::generateHeaders()));
    }

    public function getAccountNo()
    {
        return self::walletInquiry()['data']['001']['card_no'];
    }

    public function getAccountBalance()
    {
        return self::walletInquiry()['data']['001']['card_balance'];
    }

    public function getOvoPoint()
    {
        return self::walletInquiry()['data']['600']['card_balance'];
    }

    public function getBankList()
    {
        return self::parseResponse(self::Request($this->API . '/v1.0/reference/master/ref_bank', false, self::generateHeaders()));
    }

    public function transactionHistory($page = 1, $limit = 10)
    {
        return self::parseResponse(
            self::Request($this->API . '/wallet/v2/transaction?page=' . $page . '&limit=' . $limit, false, self::generateHeaders())
        );
    }

    public function transferOvo($amount, $to, $securityCode, $message = "")
    {
        $prepare = json_decode(self::verifyOVOMember($to));

        if ($prepare->fullName) {
            $trxId   = self::parseResponse(self::generateTrxId($amount))['trxId'];
            $payload = array(
                'amount' => $amount,
                'trxId' => $trxId,
                'to' => $to,
                'message' => $message
            );

            $transfer = self::Request($this->API . '/v1.0/api/customers/transfer', $payload, self::generateHeaders());
            if (preg_match('/sorry unable to handle your request/', $transfer)) {
                $unlockTrxId = self::unlockAndValidateTrxId($amount, $trxId, $securityCode);

                if ($unlockTrxId->isAuthorized == 'true') {
                    return self::Request($this->API . '/v1.0/api/customers/transfer', $payload, self::generateHeaders());
                    exit();
                } else {
                    return $unlockTrxId;
                    exit();
                }
            } else {
                return $transfer;
                exit();
            }
        } else {
            return $prepare->message;
            exit();
        }
    }

    protected function transferBankPrepare($bankCode, $bankNumber, $amount, $message = "")
    {
        $payload = array(
            'bankCode' => $bankCode,
            'message' => $message,
            'accountNo' => $bankNumber,
            'amount' => $amount
        );

        return self::Request($this->API . '/transfer/inquiry', $payload, self::generateHeaders());
    }

    protected function transferBankExecute($amount, $bankName, $bankCode, $bankAccountNumber, $bankAccountName, $trxId, $notes = "")
    {
        $payload = array(
            'bankName' => $bankName,
            'notes' => $notes,
            'transactionId' => $trxId,
            'accountNo' => self::getAccountNo(),
            'accountName' => $bankAccountName,
            'accountNoDestination' => $bankAccountNumber,
            'bankCode' => $bankCode,
            'amount' => $amount,
        );

        return self::Request($this->API . '/transfer/direct', $payload, self::generateHeaders());
    }

    public function transferBank($bankCode, $bankNumber, $amount, $securityCode, $message = "")
    {
        $prepare = json_decode(self::transferBankPrepare($bankCode, $bankNumber, $amount));

        if ($prepare->accountName) {
            $trxId = self::parseResponse(self::generateTrxId($amount))['trxId'];
            $transfer = self::transferBankExecute(
                $prepare->baseAmount,
                $prepare->bankName,
                $prepare->bankCode,
                $prepare->accountNo,
                $prepare->accountName,
                $trxId,
                $message
            );

            if (preg_match('/sorry unable to handle your request/', $transfer)) {
                $unlockTrxId = self::unlockAndValidateTrxId($amount, $trxId, $securityCode);

                if ($unlockTrxId->isAuthorized == 'true') {
                    return self::transferBankExecute(
                        $prepare->baseAmount,
                        $prepare->bankName,
                        $prepare->bankCode,
                        $prepare->accountNo,
                        $prepare->accountName,
                        $trxId,
                        $message
                    );
                    exit();
                } else {
                    return $unlockTrxId;
                    exit();
                }
            } else {
                return $transfer;
                exit();
            }
        } else {
            return $prepare->message;
            exit();
        }
    }

    protected function Request($url, $post = false, $headers = false)
    {
        $ch = curl_init();

        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true
            )
        );

        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
        }

        if (!empty($this->authToken)) {
            array_push($headers, "authorization: " . $this->authToken);
        }

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    protected function generateHeaders()
    {
        $headers = array(
            'content-type: application/json',
            'app-id: ' . $this->appId,
            'app-version: ' . $this->appVersion,
            'os: ' . $this->osName,
            'user-agent: ' . $this->userAgent
        );

        return $headers;
    }

    protected function parseResponse($response)
    {
        return json_decode($response, true);
    }

    protected function generateTrxId($amount)
    {
        $payload = array(
            'amount' => $amount,
            'actionMark' => $this->actionMark
        );

        return self::Request($this->API . '/v1.0/api/auth/customer/genTrxId', $payload, self::generateHeaders());
    }

    public function logout()
    {
        return self::Request($this->API . '/v1.0/api/auth/customer/logout', null, self::generateHeaders());
    }
}