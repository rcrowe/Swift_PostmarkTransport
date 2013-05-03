# SwiftMailer Postmark transport

Send emails with [SwiftMailer](http://) using [Postmark](http://postmarkapp.com) as the transport.

Original author: Øystein Riiser Gundersen <oysteinrg@gmail.com>

Forked to github by: Rob Crowe <hello@vivalacrowe.com>

Made PSR-0 compatible by: Karsten Dambekalns <karsten@dambekalns.de>

## Package installation

The Postmark transport is provided as Composer package which can be installed by adding the package to your composer.json file:

```javascript
{
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/kdambekalns/swift-postmark-transport.git"
        }
    ],
    "require": {
        "rcrowe\Swift_PostmarkTransport": "dev-psr0"
    }
}
```

## Usage

```php
$transport = \rcrowe\Swift\Transport\Postmark::newInstance('POSTMARK_API_KEY');
$mailer    = Swift_Mailer::newInstance($transport);

$message = Swift_Message::newInstance('Subjebt')
                ->setFrom('hello@vivalacrowe.com')
                ->setTo('')
                ->setBody('This can be any text from the web app');

$mailer->send($message);
```