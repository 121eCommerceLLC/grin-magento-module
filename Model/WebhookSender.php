<?php

declare(strict_types=1);

namespace Grin\GrinModule\Model;

use Grin\GrinModule\Model\SystemConfig;
use Psr\Log\LoggerInterface;
use Laminas\Http\Client\Adapter\Curl;
use Laminas\Uri\Uri;
use Magento\Framework\Serialize\Serializer\Json;

class WebhookSender
{
    private const GRIN_URL = 'https://app.grin.co/ecommerce/magento/webhook';

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var SystemConfig
     */
    private $systemConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Uri
     */
    private $uri;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param Curl $curl
     * @param SystemConfig $systemConfig
     * @param LoggerInterface $logger
     * @param Uri $uri
     * @param Json $json
     */
    public function __construct(Curl $curl, SystemConfig $systemConfig, LoggerInterface $logger, Uri $uri, Json $json)
    {
        $this->curl = $curl;
        $this->systemConfig = $systemConfig;
        $this->logger = $logger;
        $this->uri = $uri;
        $this->json = $json;
    }

    /**
     * @param string $topic
     * @param array $data
     */
    public function send(string $topic, array $data)
    {
        if (!$this->canSend()) {
            return;
        }

        $payload = $this->json->serialize($data);
        $this->logger->info(sprintf('Sending the webhook "%s" %s', $topic, $payload));

        try {
            $this->curl->setOptions([
                CURLOPT_RETURNTRANSFER => true,
                CURLINFO_HEADER_OUT => true
            ]);
            $headers = [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
                'Authorization: ' . $this->systemConfig->getWebhookToken(),
                'Magento-Webhook-Topic: ' . $topic
            ];

            $uri = $this->getUri();
            $this->curl->connect($uri->getHost(), $uri->getPort(), true);
            $this->curl->write('POST', $uri, 1.1, $headers, $payload);
            $this->curl->close();
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), $e->getTrace());
        }
    }

    /**
     * @return bool
     */
    private function canSend(): bool
    {
        if (!$this->systemConfig->isGrinWebhookActive()) {
            return false;
        }

        if (!$this->systemConfig->getWebhookToken()) {
            $this->logger->critical('Authentication token has not been set up for Grin Webhooks');

            return false;
        }

        return true;
    }

    /**
     * @return Uri
     */
    private function getUri(): Uri
    {
        $this->uri->parse(WebhookSender::GRIN_URL);
        $this->uri->setPort($this->uri->getScheme() === 'https' ? 443 : 80);

        return $this->uri;
    }
}