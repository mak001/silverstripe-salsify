<?php

namespace Dynamic\Salsify\Controller;


use GuzzleHttp\Client;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Class WebhookReceiver
 * @package Dynamic\Salsify\Controller
 */
class WebhookReceiver extends Controller
{
    /**
     *
     */
    const URLSEGMENT = 'salsify-webhook';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'index',
    ];

    /**
     * @return string
     */
    public function getURLSegment()
    {
        return self::URLSEGMENT;
    }

    /**
     * @return string
     */
    public function index()
    {
        $request = $this->getRequest();
        echo $this->getWebhookURL($request);
    }

    /**
     * @param HTTPRequest $request
     * @return bool
     */
    public function validateRequest($request)
    {
        /** @var X509 $x509 */
        $x509 = new X509();

        $client = new Client([
            'timeout' => $this->config()->get('timeout'),
            'http_errors' => false,
            'verify' => true,
        ]);

        $client->request('GET', $request->getHeader('X-Salsify-Cert-Url'));

        /** @var array $cert */
        $cert = $x509->loadX509(file_get_contents($this->cert_url));

        $response = openssl_verify(
            $this->makeHeaderString(),
            $cert['signature'], // should this be base64_decode($cert['signature'])?
            $x509->getPublicKey(),
            'sha512'
        );
        if ($response === 1) {
        } elseif ($response === 0) {
            $this->valid_request = false;
        } else {
            // @TODO
            throw new \Exception(openssl_error_string());
        }
        //$x509->_validateSignature('', $x509->getPublicKey(), '', $cert['signature'], $x509->signatureSubject);
        return $this;
    }

    /**
     * @param HTTPRequest $request
     * @return string
     */
    private function getWebhookURL($request) {
        print_r($request);
        $host = $request->getHost();
        $path = $request->getURL();
        if ((substr($host, -strlen($host)) === '/')) {
            return $host . $path;
        }
        return $host . '/' . $path;
    }

    /**
     * @param string $timestamp
     * @param string $requestID
     * @param string $orgID
     * @param HTTPRequest $request
     * @param string $requestBody
     * @return string
     */
    private function makeHeaderString($timestamp, $requestID, $orgID, $request, $requestBody)
    {
        $webhookURL = $request->getURL();
        return hash('sha256', "{$timestamp}.{$requestID}.{$orgID}.{$this->webhook_url}.{$requestBody}");
    }

    /**
     * @param HTTPRequest $request
     * @return string|array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getCertification($request)
    {
        $client = new Client([
            'timeout' => $this->config()->get('timeout'),
            'http_errors' => false,
            'verify' => true,
        ]);

        $response = $client->request('GET', $request->getHeader('X-Salsify-Cert-Url'));

        if ($response->getBody() && !empty($response->getBody())) {
            return $response->getBody();
        }

        // something went wrong, just return everything
        return $response;
    }

    /**
     * @param $timestamp
     * @return bool
     */
    public function isValidTimeStamp($timestamp)
    {
        $diff = DBDatetime::now()->getTimestamp() - DBDatetime::create('Timestamp', $timestamp)->getTimestamp();
        return $this->config()->get('maxRequestAge') > $diff / 60;
    }

    /**
     * @param $url
     * @return bool
     */
    public function isValidCertificateURL($url)
    {
        $uri = parse_url($url);

        if ($uri['scheme'] !== 'htpps') {
            return false;
        }

        if ($this->config()->get('certificateHost') !== $uri['host']) {
            return false;
        }

        return true;
    }
}
