<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Nexmo\Client;
use Nexmo\Client\Credentials\Keypair;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Aws\TranscribeService\TranscribeServiceClient;

Dotenv\Dotenv::create(__DIR__)->load();

$app = AppFactory::create();

/**
 *
 * Answer the call and provide NCCO object
 *
 */
$app->get('/webhooks/answer', function (Request $request, Response $response) {

    $uri = $request->getUri();

    $ncco = [
        [
            'action' => 'talk',
            'text' => 'Thanks for calling, we will connect you now. This conversation will be recorded.'
        ],
        [
            'action' => 'connect',
            'from' => '<callers_number>',
            'endpoint' => [
                [
                    'type' => 'phone',
                    'number' => '<recipients_number>'
                ]
            ]
        ],
        [
            'action' => 'record',
            'split' => 'conversation',
            'channels' => 2,
            'beepOnStart' => true,
            'eventUrl' => [
                'https://'.$uri->getHost().'/dev/webhooks/transcribe'
            ]
        ],
    ];

    $response->getBody()->write(json_encode($ncco));

    return $response
        ->withHeader('Content-Type', 'application/json');
});

/**
 *
 * Log call events
 *
 */
$app->post('/webhooks/event', function (Request $request, Response $response) {
    $params = $request->getParsedBody();

    error_log($params['recording_url']);

    return $response
        ->withStatus(204);
});

/**
 *
 * After recording, migrate MP3 to AWS S3 and start the Transcribe Job
 *
 */
$app->post('/webhooks/transcribe', function (Request $request, Response $response) {
    $params = json_decode($request->getBody()->getContents(), true);

    // Create Nexmo Client
    $keypair = new Keypair(
        file_get_contents($_ENV['NEXMO_APPLICATION_PRIVATE_KEY_PATH']),
        $_ENV['NEXMO_APPLICATION_ID']
    );

    $nexmoClient = new Client($keypair);

    $data = $nexmoClient->get($params['recording_url']);

    // Create AWS S3 Client
    $S3Client = new S3Client([
        'region' => $_ENV['AWS_REGION'],
        'version' => $_ENV['AWS_VERSION']
    ]);

    $adapter = new AwsS3Adapter($S3Client, $_ENV['AWS_S3_BUCKET_NAME']);

    $filesystem = new Filesystem($adapter);

    // Put the MP3 on S3
    $filesystem->put('/' . $_ENV['AWS_S3_RECORDING_FOLDER_NAME'] .'/'.$params['conversation_uuid'].'.mp3', $data->getBody());

    // Create Amazon Transcribe Client
    $awsTranscribeClient = new TranscribeServiceClient([
        'region' => $_ENV['AWS_REGION'],
        'version' => $_ENV['AWS_VERSION']
    ]);

    // Create a transcription job
    $transcriptionResult = $awsTranscribeClient->startTranscriptionJob([
        'LanguageCode' => 'en-US',
        'Media' => [
            'MediaFileUri' => 'https://' . $_ENV['AWS_S3_BUCKET_NAME'] . '.s3.amazonaws.com/' . $_ENV['AWS_S3_RECORDING_FOLDER_NAME'] . '/' . $params['conversation_uuid'] . '.mp3',
        ],
        'MediaFormat' => 'mp3',
        'Settings' => [
            'ChannelIdentification' => true,
        ],
        'TranscriptionJobName' => 'nexmo_voice_' . $params['conversation_uuid'],
    ]);

    $response->getBody()->write(json_encode($transcriptionResult->toArray()));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(204);
});
