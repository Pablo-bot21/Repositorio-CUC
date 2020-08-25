<?php

require './vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

// define constants to call API (get them from Backstage > Admin > Reporting API)
define('AUTH_API_URL', 'https://api.inbenta.io/v1/auth');
define('REPORTING_API_BASE_URL', 'https://api-reporting-us.inbenta.io/prod/');
define('DEBUG', false);


class LogAPI {

    public $auth;
    public $accessToken;
    public $reponseBody;
    public $signatureVersion;
    public $timestamp;
    public $signature;
    public $signedHeader;

    protected $apiKey, $apiSecret, $signatureKey;

    public function __construct($apiKey, $apiSecret, $signatureKey)
    {
        $this->auth = new Client();
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->signatureKey = $signatureKey;
    }

    public function authorization()
    {
        $headers = [
            'x-inbenta-key' => $this->apiKey
        ];
        $body = json_encode([
            'secret' => $this->apiSecret
        ]);

        $response = $this->auth->request('POST', AUTH_API_URL, ['headers' => $headers, 'body' => $body]);
        $responseBody = json_decode($response->getBody(true)->getContents(), true);
        $this->accessToken = $responseBody['accessToken'];
    }


    public function getData($urlPath, $urlParams)
    {
        
        $url = REPORTING_API_BASE_URL.$urlPath.$urlParams;
        
        $parsedUrl = parse_url($url);
        
        parse_str($parsedUrl['query'], $queryParams);

        $request = new Request(
            'GET',
            $url,
            [
                'x-inbenta-key' => $this->apiKey,
                'Authorization' => 'Bearer '.$this->accessToken
            ]
        );
        
        // Get request method
        $method = $request->getMethod();  // 'GET'
        // Get request path (from api version, v1, onward)
        $urlPath = urlencode($urlPath);
        // Join all given query string params in a single string
        $queryStringEncodedElements = [];
        ksort($queryParams);
        foreach ($queryParams as $key => $value) {
            $value = urldecode(json_encode($value));
            $queryStringEncodedElements[$key] = "{$key}={$value}";
        }

        $queryString = rawurlencode(implode('&', $queryStringEncodedElements));

        // Get request body
        $body = urlencode($request->getBody()->__toString());

        
        // Generate unix timestamp
        $timestamp = time();
        
        // Fill signature version
        $signatureVersion = 'v1';

        // Now that we have all the parts, let's join them if not empty
        $signatureElements = [$method, $urlPath, $queryString, $body, $timestamp, $signatureVersion];
        foreach ($signatureElements as $key => $elem) {
            if (empty($elem)) {
                unset($signatureElements[$key]);  // Skip it
            }
        }

        // Finally, join together all the parts
        $myBaseString = implode('&', $signatureElements);

        // 4. Sign the request
        $signature = hash_hmac('sha256', $myBaseString, $this->signatureKey);

        // 5. Add signature headers to request
        $request = $request->withHeader('x-inbenta-signature-version', $signatureVersion);
        $request = $request->withHeader('x-inbenta-timestamp', $timestamp);
        $request = $request->withHeader('x-inbenta-signature', $signature);

        // 6. Make the request to the API
        $client = new Client();
        $response = $client->send($request);
        // Get back the signed header
        $signedHeader = $response->getHeaderLine('x-inbenta-signature');
        // get response body
        
        return $response->getBody()->__toString();
    }
}

// Los datos están en la sección de Adminsitración > Reporting API
$apiKey = "BYUTOQ6RSqymSgqGkcFZRGOgZf/tWPAOsVQsA/MOiuM=";
$apiSecret = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJwcm9qZWN0IjoiYmJ2YW14X2N1Y19rbV9lcyIsInNhbHQiOiJCWVVUT1E2QWhBMUZpQjd5cFwvSklQdz09In0.NNrB0BOmwwXvkc1f5Ce-JI1GE4R7nKBRDbYFRoFxrLE_iJjpt93qX8OLofVVp1fo8VYW4HELpdUsbbA1rGTv1w";
$signatureKey = "BYUTOQ6RZfkPlFmM6ZmqwzXcAeEYl5lex4UU0VGCkU5vYSPKuI07Ve57txV86jPv+DqB6Mw5ptHEpmU8D9BrOA==";

$logClient = new LogAPI($apiKey, $apiSecret, $signatureKey);
$logClient->authorization();

$urlPath = "v1/aggregates/session_details";
$urlParams = "?env=production&has_matching=1&date_format=iso&date_from=2020-08-19&date_to=2020-08-19&data_keys=SEARCH";
$allSessionsWithSearch = $logClient->getData($urlPath, $urlParams);


$urlPath = "v1/events/user_questions";
$urlParams = "?env=production&date_format=iso&date_from=2020-08-19&date_to=2020-08-19&length=1000";
$allUserQuestions = $logClient->getData($urlPath, $urlParams);

// Cruzar los datos entre LogID de $allSessionsWithSearch y $allUserQuestions
echo "<pre>";print_r($allUserQuestions);die;