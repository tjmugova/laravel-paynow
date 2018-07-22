<?php
/**
 * Created by PhpStorm.
 * User: Tj
 * Date: 2018-07-21
 * Time: 3:09 PM
 */

namespace Webstudio\Paynow;


class Paynow
{
    private $integration_key;
    private $integration_id;
    private $initiate_transaction_url = "https://www.paynow.co.zw/Interface/InitiateTransaction";
    private $result_url;
    private $return_url;
    private $hash;
    const STATUS_OK = "Ok";
    const STATUS_ERROR = "Error";
    const STATUS_PAID = "Paid";
    const STATUS_AWAITING_DELIVERY = "Awaiting Delivery";
    const STATUS_DELIVERED = "Delivered";
    const STATUS_CANCELED = "Canceled";
    const STATUS_DISPUTED = "Disputed";
    const STATUS_CREATED = "Created";
    const STATUS_SENT = "Sent";
    const STATUS_REFUNDED = "Refunded";


    /**
     * Paynow constructor.
     * @param $id
     * @param $key
     */
    public function __construct($id, $key)
    {
        $this->integration_id = $id;
        $this->integration_key = $key;
    }

    public function createPayment($reference, $amount, $additional_info = '', $auth_email = '')
    {
        $values = [
            "id" => $this->integration_id,
            "reference" => $reference,
            "amount" => $amount,
            "returnurl" => $this->return_url,
            "resulturl" => $this->result_url,
            "auth_email" => $auth_email,
            "additionalinfo" => $additional_info,
            "status" => "Message"
        ];
        $hash = $this->createHash($values);
        $values["hash"] = $hash;
        $this->hash = $hash;
        return $values;
    }

    /**
     * @param $values
     * @throws \Exception
     * @return array
     */
    public function sendPayment($values)
    {
        if (empty($this->return_url)) {
            throw new \Exception("Return url should not be empty");
        }
        $ch = curl_init();
        $url = $this->initiate_transaction_url;
        $post_data = $this->createMsg($values);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $output = curl_exec($ch);
        if (!$output) {
            throw new \Exception("Curl Request failed:" . curl_error($ch));
        }
        curl_close($ch);
        $result = $this->parseMsg($output);
        if ($result["status"] == Paynow::STATUS_OK) {
            return $result;
        } else {
            throw new \Exception("Paynow returned an error:" . $result["error"]);
        }
    }

    public function pollTransaction($poll_url)
    {
        if (empty($this->return_url)) {
            throw new \Exception("Return url should not be empty");
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $poll_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $output = curl_exec($ch);
        if (!$output) {
            throw new \Exception("Curl Request failed:" . curl_error($ch));
        }
        curl_close($ch);
        $result = $this->parseMsg($output);
        return $result;
    }

    /**
     * @return mixed
     */
    public function getPollUrl($response)
    {
        return $response["pollurl"];
    }

    /**
     * @return mixed
     */
    public function getBrowserUrl($response)
    {
        return $response["browserurl"];
    }

    /**
     * @return mixed
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param array $response
     * @return boolean
     */
    public function isValidInitResponse($response)
    {
        if ($this->createHash($response) != $response["hash"]) {
            return false;
        } else {
            return true;
        }
    }

    public function isValidPollResponse($response)
    {
        if ($this->createHash($response) != $response["hash"]) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param mixed $result_url
     */
    public function setResultUrl($result_url)
    {
        $this->result_url = $result_url;
    }

    /**
     * @param mixed $return_url
     */
    public function setReturnUrl($return_url)
    {
        $this->return_url = $return_url;
    }

    function createHash($values)
    {
        $string = "";
        foreach ($values as $key => $value) {
            if (strtoupper($key) != "HASH") {
                $string .= $value;
            }
        }
        $string .= $this->integration_key;
        $hash = hash("sha512", $string);
        return strtoupper($hash);
    }

    function urlIfy($fields)
    {
        //url-ify the data for the POST
        $delim = "";
        $fields_string = "";
        foreach ($fields as $key => $value) {
            $fields_string .= $delim . $key . '=' . $value;
            $delim = "&";
        }

        return $fields_string;
    }

    function createMsg($values)
    {
        $fields = array();
        foreach ($values as $key => $value) {
            $fields[$key] = urlencode($value);
        }
        $fields["hash"] = urlencode($this->CreateHash($values));
        $fields_string = $this->urlIfy($fields);
        return $fields_string;
    }

    function parseMsg($msg)
    {
        //convert to array data
        $parts = explode("&", $msg);
        $result = array();
        foreach ($parts as $i => $value) {
            $bits = explode("=", $value, 2);
            $result[$bits[0]] = urldecode($bits[1]);
        }
        return $result;
    }
}