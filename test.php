<?php

include __DIR__.'/vendor/autoload.php';

$transport = Swift_PostmarkTransport::newInstance('7cff563d-9f06-4f37-8fc5-13a2fd22d5f1');
$mailer    = Swift_Mailer::newInstance($transport);

try
{
    $message = Swift_Message::newInstance('Hello world')
                ->setReplyTo(array(
                        '427c8b455e988d3170f7b0d53068ab70+12345678@inbound.postmarkapp.com' => 'Julain Gill',
                        '2@inbound.postmarkapp.com' => 'Rob Gill',
                    ))
                ->setFrom('support@bigblast.co')
                ->setTo(array(
                        'rob@vocabexpress.com' => 'Rob Crowe',
                    ))
                ->setBody('This can be any text from the web app');

    $result = $mailer->send($message);

    var_dump($result);
}
catch(Exception $ex)
{
    echo $ex->getMessage()."\n";
    die();
}

// $message = Swift_Message::newInstance('Hello world')
//                 ->setFrom('support@bigblast.co')
//                 ->setReplyTo('427c8b455e988d3170f7b0d53068ab70+12345678@inbound.postmarkapp.com', 'Julain Gill');

// var_dump($message);