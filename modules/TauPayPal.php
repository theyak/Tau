<?php
class TauPayPal
{
	protected $certificateUrl = 'https://api.paypal.com/nvp/';
	protected $signatureUrl = 'https://api-3t.paypal.com/nvp/';
    protected $expressCheckoutUrl = 'https://www.paypal.com/webscr?';

    protected $username = '';
    protected $password = '';
    protected $signature = '';
    protected $certificateFile = '';


    // Response returned from API call
    public $response;

    public static $credit_card_types = array(
		'V' => 'Visa',
        'M' => 'MasterCard',
        'D' => 'Discover',
        'A' => 'Amex'
	);

    private $paypalVersion = '63.0';


	public function sandbox()
	{
		$this->expressCheckoutUrl = 'https://www.sandbox.paypal.com/webscr?';
		$this->signatureUrl = 'https://api-3t.sandbox.paypal.com/nvp';
		$this->certificateUrl = 'https://api.sandbox.paypal.com/nvp';		
	}

	/**
	 * Call PayPal. Consult https://cms.paypal.com/cms_content/US/en_US/files/developer/PP_NVPAPI_DeveloperGuide.pdf
	 * for information on valid actions and parameters.
	 * 
	 * @param <type> $action - The API to call, such as DoDirectPayment or DoAuthorization 
	 * @param <type> $parameters - The parameters to the API call.
	 * @return <type> 
	 */
	public function submit($action, $parameters = array())
    {
        if (!is_array($parameters))
        {
            throw new TauPayPal_Exception('Invalid parameters. Must be array', TauPayPal_Exception::INVALID_PARAMETERS);
        }

		if (empty($this->username))
		{
            throw new TauPayPal_Exception('Invalid user name', TauPayPal_Exception::INVALID_USERNAME);
		}

		if (empty($this->password))
		{
            throw new TauPayPal_Exception('Invalid password', TauPayPal_Exception::INVALID_PASSWORD);
		}

        $curl = curl_init();

        if (!empty($this->certificateFile))
        {
	        curl_setopt($curl, CURLOPT_URL, $this->certificateUrl);
            curl_setopt($curl, CURLOPT_SSLCERT, $this->certificateFile);
        }
		else
		{
			curl_setopt($curl, CURLOPT_URL, $this->signatureUrl);
		}

        //turning off the server and peer verification(TrustManager Concept).
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        // Return output as a string instead of to output buffer
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        // POST mode
        curl_setopt($curl, CURLOPT_POST, true);

        // Create the request
        $request = array(
			'METHOD' => $action,  // e.g., DoDirectPayment
			'VERSION' => $this->paypalVersion,
			'PWD' => $this->password,
			'USER' => $this->username,
		);

        if (empty($this->certificateFile) && !empty($this->signature))
        {
            $request = array_merge($request, array('SIGNATURE' => $this->signature));
        }

        $request = array_merge($request, $parameters);
        $request = http_build_query($request, '', '&');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request);

        //getting response from server
		$start = time();
        $response = curl_exec($curl);
		file_put_contents("log", time() - $start);

        if (curl_errno($curl))
        {
            $this->response = array();
            throw new TauPayPal_Exception('cURL error: ' . curl_error($curl), TauPayPal_Exception::CURL_ERROR);
        }
        else
        {
            $this->response = $this->deformatNVP($response);
            curl_close($curl);
        }

		return $this->response;
    }

	public function setUsername($username)
	{
		$this->username = $username;
	}

	public function setPassword($password)
	{
		$this->password = $password;
	}

	public function setSignature($signature)
	{
		$this->signature = $signature;
	}

	public function setCertificateFile($file)
	{
		$this->certificateFile = $file;
	}


	public function gotoExpressCheckout($token)
	{
		header('location: ' . $this->expressCheckoutUrl . 'cmd=_express-checkout&token=' . $token);
		exit;
	}

    function deformatNVP($nvpstr)
    {
        $intial=0;
        $nvpArray = array();

        while(strlen($nvpstr))
        {
            $keypos = strpos($nvpstr, '=');
            $valuepos = strpos($nvpstr, '&', $keypos) ?
                strpos($nvpstr, '&', $keypos) :
                strlen($nvpstr);

            /*getting the Key and Value values and storing in a Associative Array*/
            $keyval = substr($nvpstr, $intial, $keypos);
            $valval = substr($nvpstr, $keypos+1, $valuepos - $keypos - 1);
            $nvpArray[urldecode($keyval)] = urldecode($valval);

            $nvpstr = substr($nvpstr, $valuepos + 1, strlen($nvpstr));
        }
        return $nvpArray;
    }
}

class TauPayPal_Exception extends Exception
{
	const INVALID_PARAMETERS = 1;
	const INVALID_USERNAME = 2;
	const INVALID_PASSWORD = 3;
	const CURL_ERROR = 4;

    public function __constuct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }
}

