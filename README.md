# SwiftMailer Postmark transport

Send emails with [SwiftMailer](http://) using [Postmark](http://postmarkapp.com) as the transport.

## Package installation

The Postmark transport is provided as Composer package which can be installed by adding the package to your composer.json file:

```javascript
{
    "require": {
        "rcrowe\Swift_PostmarkTransport": "*"
    }
}
```

## Usage

```php
$transport = Swift_PostmarkTransport::newInstance('POSTMARK_API_KEY');
$mailer    = Swift_Mailer::newInstance($transport);

$message = Swift_Message::newInstance('Subjebt')
                ->setFrom('hello@vivalacrowe.com')
                ->setTo('')
                ->setBody('This can be any text from the web app');

$mailer->send($message);
```