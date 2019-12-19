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
        if ($this->validateRequest($request)) {
            $this->setResponse(new HTTPResponse());
            $this->getResponse()->setStatusCode(400);
            $this->getResponse()->setBody('invalid');

            return $this->getResponse();
        }
        echo $this->getWebhookURL($request);
    }

    /**
     * @param HTTPRequest $request
     * @return bool
     */
    public function validateRequest($request)
    {
        if (!$request->getHeader('X-Salsify-Cert-Url')) {
            return false;
        }
        return true;
    }

    /**
     * @param HTTPRequest $request
     * @return string
     */
    private function getWebhookURL($request)
    {
        $host = $request->getHost();
        if ((substr($host, -strlen($host)) === '/')) {
            return $host . $path;
        }
        return $host . '/' . $this->getURLSegment();
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
     * @param HTTPRequest $request
     * @return bool
     */
    public function isValidSignature($request)
    {
        /** @var X509 $x509 */
        $x509 = new X509();

        $client = new Client([
            'timeout' => $this->config()->get('timeout'),
            'http_errors' => false,
            'verify' => true,
        ]);

        $cert = $this->getCertification($request);
        if (is_array($cert)) {
            return false;
        }
        /** @var array $cert */
        $cert = $x509->loadX509($cert);

        $response = openssl_verify(
            $this->makeHeaderString(),
            $cert['signature'], // should this be base64_decode($cert['signature'])?
            $x509->getPublicKey(),
            'sha512'
        );

        if ($response === 1) {
            return true;
        }

        return false;
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
