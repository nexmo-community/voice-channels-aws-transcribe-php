<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/Config/db.global.php';

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
 * Answer the call and prompt using NCCO flow
 *
 */
$app->get('/webhooks/answer', function (Request $request, Response $response) {

    $uri = $request->getUri();

    $ncco = [
        [
            'action' => 'talk',
            'text' => 'Thanks for calling, we will connect you now.'
        ],
        [
            'action' => 'connect',
            'from' => 12018173413,
            'endpoint' => [
                [
                    'type' => 'phone',
                    'number' => 15614141389
                ]
            ]
        ],
        [
            'action' => 'record',
            'split' => 'conversation',
            'channels' => 2,
            'eventUrl' => [
//                $uri->getScheme().'://'.$uri->getHost().':'.$uri->getPort().'/dev/webhooks/migrate'
                'https://nrc6lng9t2.execute-api.us-east-1.amazonaws.com/dev/webhooks/migrate'
            ]
        ],
        [
            'action' => 'talk',
            'text' => 'This conversation is being recorded.'
        ],
        [
            'action' => 'notify',
            'payload' => ['followup'=>true],
            'eventUrl' => [
//                'https://'.$uri->getHost().'/dev/webhooks/transcribe' // @todo need to include /dev for lambda
                'https://nrc6lng9t2.execute-api.us-east-1.amazonaws.com/dev/webhooks/transcribe'
            ],
            'eventMethod' => "POST"
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

//    error_log($params['recording_url']);
    echo 'logged';

    return $response
        ->withStatus(204);
});

/**
 *
 * After recording, migrate MP3 to AWS S3
 *
 */
$app->post('/webhooks/migrate', function (Request $request, Response $response) {
    $params = json_decode($request->getBody(), true);

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
        'version' => 'latest'
    ]);

    $adapter = new AwsS3Adapter($S3Client, $_ENV['AWS_S3_BUCKET_NAME']);

    $filesystem = new Filesystem($adapter);

    $filesystem->put('/' . $_ENV['AWS_S3_RECORDING_FOLDER_NAME'] .'/'.$params['conversation_uuid'].'.mp3', $data->getBody());

    return $response
        ->withStatus(204);
});

/**
 *
 * Start Transcribe job on the call from MP3
 *
 */
$app->post('/recording/transcribe', function (Request $request, Response $response) {
    $params = json_decode($request->getBody(), true);

    // Create Amazon Transcribe Client
    $awsTranscribeClient = new TranscribeServiceClient([
        'region' => $_ENV['AWS_REGION'],
        'version' => 'latest'
    ]);

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
        // callback
    ]);

    $response->getBody()->write(json_encode($transcriptionResult->toArray()));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(204);
});

/**
 *
 * Save the contents of the transcription to the RDS MySQL database
 *
 */
$app->post('/recording/process', function (Request $request, Response $response) use ($conn) {
    $params = json_decode($request->getBody(), true);

    // Create Amazon Transcribe Client
    $awsTranscribeClient = new TranscribeServiceClient([
        'region' => 'us-east-1',
        'version' => 'latest'
    ]);

    // Retrieve the transcription job
    $transcriptionJob = $awsTranscribeClient->getTranscriptionJob([
        'TranscriptionJobName' => $params['detail']['TranscriptionJobName']
    ]);

    // parse the job to get the File U
    $transcriptionRawResult = $transcriptionJob->toArray();

    // get the result file
    $resultFile = file_get_contents($transcriptionRawResult['TranscriptionJob']['Transcript']['TranscriptFileUri']
    );

    $result = json_decode($resultFile, true);

    // Add contents to DB
    $conn->insert('transcriptions', [
        'conversation_uuid' => $result['conversation_uuid'],
        'channel' => $result['channel'],
        'message' => $result['message'],
        'created' => $result['created'],
        'modified' => $result['modified']
    ]);

//    $response->getBody()->write($resultFile);

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(204);
});

$app->run();
