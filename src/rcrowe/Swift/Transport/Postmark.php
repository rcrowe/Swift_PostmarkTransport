<?php
namespace rcrowe\Swift\Transport;

use Swift_Transport;
use Swift_Events_EventListener;
use Swift_Mime_HeaderSet;
use Swift_Mime_Message;
use Swift_TransportException;

/**
 * A SwiftMailer transport implementation for the
 * {@link http://postmarkapp.com/ Postmark} email delivery API for transactional
 * email.
 *
 * Postmark is *not* for bulk email, but multiple recipients are still supported
 * by posting the email once for each address.
 *
 * Bcc and Cc headers are silently ignored as these are not supported by Postmark.
 *
 * Usage:
 * <code>
 *    $transport = Swift_PostmarkTransport::newInstance('YOUR-POSTMARK-API-KEY')
 *    $mailer = Swift_Mailer::newInstance($transport);
 *    $message = Swift_Message::newInstance('Wonderful Subject')
 *      ->setFrom(array('sender@mydomain.com' => 'John Doe'))
 *      ->setTo(array('receiver@otherdomain.org' => 'Jane Doe'))
 *      ->setBody('Here is the message itself');
 *    $mailer->send($message);
 * </code>
 */
class Postmark implements Swift_Transport
{
    /**
     * @var string
     */
    const POSTMARK_URI = 'http://api.postmarkapp.com/email';

    /**
     * @var string
     */
    protected $postmark_api_token = null;

    /**
     * @var array
     */
    protected $IGNORED_HEADERS = array(
        'Content-Type',
        'Date',
    );

    /**
     * @var array
     */
    protected $UNSUPPORTED_HEADERS = array(
        'Bcc',
        'Cc',
    );

    /**
     * @param string $postmark_api_token Postmark API key
     * @param string|array $from Postmark sender signature email
     * @param string $postmark_uri Postmark HTTP service URI
     */
    public function __construct($postmark_api_token, $from = null, $postmark_uri = null)
    {
        $this->postmark_api_token      = $postmark_api_token;
        $this->postmark_uri            = is_null($postmark_uri) ? self::POSTMARK_URI : $postmark_uri;
        $this->postmark_from_signature = $from;
    }

    public static function newInstance($postmark_api_token, $from = null, $postmark_uri = null)
    {
        return new self($postmark_api_token, $from, $postmark_uri);
    }

    public function isStarted()
    {
        return false;
    }
    
    public function start() { }
    public function stop() { }

    /**
     * @param Swift_Mime_Message $message
     * @param string $mime_type
     * @return \Swift_Mime_MimePart
     */
    protected function getMIMEPart(Swift_Mime_Message $message, $mime_type)
    {
        $html_part = null;

        foreach ($message->getChildren() as $part) {
            if (strpos($part->getContentType(), 'text/html') === 0) {
                $html_part = $part;
            }
        }

        return $html_part;
    }

    /**
     * @param Swift_Mime_Message $message
     * @param string $mime_type
     * @return Swift_Mime_Message
     */
    protected function processHeaders(Swift_Mime_Headerset $headers)
    {
        foreach ($this->IGNORED_HEADERS as $header_name) {
            $headers->remove($header_name);
        }

        foreach ($this->UNSUPPORTED_HEADERS as $header_name) {
            if ($headers->has($header_name)) {
                throw new Swift_TransportException(
                    "Postmark does not support the '{$header_name}' header"
                );
            }
        }

        return $headers;
    }

    /**
     * @param Swift_Mime_Message $message
     * @param string $mime_type
     * @return array
     */
    protected function buildMessageData(Swift_Mime_Message $message)
    {
        $headers = $this->processHeaders($message->getHeaders());

        $message_data = array();

        $message_data['Subject'] = $headers->get('Subject')->getFieldBody();
        $headers->remove('Subject');

        $message_data['From'] = $headers->get('From')->getFieldBody();
        $headers->remove('From');

        $message_data['TextBody'] = $message->getBody();

        // ReplyTo
        $reply_to = array();

        if (is_array($message->getReplyTo())) {
            foreach ($message->getReplyTo() as $email => $name) {
                $reply_to[] = ($name !== NULL) ? sprintf("%s <%s>", $name, $email) : $email;
            }
        }

        $message_data['ReplyTo'] = implode(',', $reply_to);

        if (!is_null($html_part = $this->getMIMEPart($message, 'text/html'))) {
            $message_data['HtmlBody'] = $html_part->getBody();
        }

        $extra_headers = array();

        foreach ($headers as $header) {
            $extra_headers[] = array(
                'Name'  => $header->getFieldName(),
                'Value' => $header->getFieldBody(),
            );
        }

        if (!empty($extra_headers)) {
            $message_data['Headers'] = $extra_headers;
        }

        return $message_data;
    }

    /**
     * @return array
     */
    protected function headers()
    {
        return array(
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Postmark-Server-Token: ' . $this->postmark_api_token,
        );
    }

    /**
     * @param array $message_data
     * @return array
     */
    protected function post(array $message_data)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => self::POSTMARK_URI,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $this->headers(),
            CURLOPT_POSTFIELDS     => json_encode($message_data),
            CURLOPT_RETURNTRANSFER => true,
        ));

        $response = curl_exec($curl);

        if ($response === false) {
            $this->fail('Postmark delivery failed: ' . curl_error($curl));
        }

        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        return array($response_code, @json_decode($response, true));
    }

    protected function fail($message)
    {
        throw new Swift_TransportException($message);
    }

    /**
     * @param Swift_Mime_Message $message
     * @param array $failed_recipients
     * @return int
     */
    public function send(Swift_Mime_Message $message, &$failed_recipients = null)
    {
        if (!is_null($this->postmark_from_signature)) {
            $message->setFrom($this->postmark_from);
        }

        $failed_recipients = (array)$failed_recipients;
        $message_data      = $this->buildMessageData($message);

        $send_count = 0;
        $recipients = $message->getHeaders()->get('To');
        $addresses  = $recipients->getAddresses();

        foreach ($recipients->getNameAddressStrings() as $i => $recipient) {
            
            $message_data['To']             = $recipient;
            list($response_code, $response) = $this->post($message_data);

            if ($response_code != 200) {
                $failed_recipients[] = $addresses[$i];

                $this->fail(
                    "Postmark delivery failed with HTTP status code {$response_code}. " .
                    "Postmark said: '{$response['Message']}'"
                );
            } else {
                $send_count++;
            }
        }

        return $send_count;
    }

    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        // TODO
    }
}
