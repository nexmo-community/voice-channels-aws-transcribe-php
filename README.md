# voice-channels-aws-transcribe-php
Transcribe a voice conversation separate channels using Amazon Transcribe. This is done using PHP and Amazon Transcribe with an AWS Lambda function and AWS S3.

## Prerequisites

* PHP 7.4 (update `serverless.yml` for other versions)
* Composer installed [globally](https://getcomposer.org/doc/00-intro.md#globally)
* [Node.js](https://nodejs.org/en/) and npm
* [Serverless Framework](https://serverless.com/framework/docs/getting-started/)
* [AWS account](https://aws.amazon.com/)
* [Vonage account](https://vonage.com)

## Setup Instructions

Clone this repo from GitHub, and navigate into the newly created directory to proceed.

### Use Composer to install dependencies

This example requires the use of Composer to install dependencies and set up the autoloader.

Assuming a Composer global installation. [https://getcomposer.org/doc/00-intro.md#globally](https://getcomposer.org/doc/00-intro.md#globally)

```
composer install
```

### AWS Setup

You will need to create [AWS credentials](https://www.serverless.com/framework/docs/providers/aws/guide/credentials/) as indicated by `Serverless`.

Also, create a new [AWS S3 bucket](https://aws.amazon.com/s3/) and make note of the URL for later use.

### Linking the app to Vonage

Create a new Vonage Voice application for this app, and associated it with a Vonage number.

#### Create a Vonage Application Using the Command Line Interface

Install the CLI by following [these instructions](https://github.com/Nexmo/nexmo-cli#installation). Then create a new Vonage Voice application that also sets up an `answer_url` and `event_url` for the app running in AWS Lambda.

Ensure to append `/webhooks/answer` or `/webhooks/event` to the end of the URL provided later by AWS Lambda, to coincide with the routes in `index.php`.

```
nexmo app:create aws-transcribe https://<your_hostname>/webhooks/answer https://<your_hostname>/webhooks/event
```

> NOTE: You will need to return to these settings to update after you know the URLs provided by deploying to AWS Lambda

IMPORTANT: This will return an application ID, and a private key. The application ID will be needed for the nexmo link:app as well as the .env file later, and create a file named private.key in the same location/level as server.js, by default, containing the private key.

### Obtain a New Virtual Number
If you don't have a number already in place, obtain one from Vonage. This can also be achieved using the CLI by running this command:

```
nexmo number:buy
```

### Link the Virtual Number to the Application
Finally, link the new number to the created application by running:

```
nexmo link:app YOUR_NUMBER YOUR_APPLICATION_ID
```

### Update Environment

Rename the provided `.env.dist` file to `.env` and update the values as needed from `AWS` and `Vonage`.

```env
APP_ID=voice-aws-transcribe-php
LANG_CODE=en-US
SAMPLE_RATE=8000
AWS_VERSION=latest
AWS_S3_ARN=<aws_s3_arn>
AWS_S3_BUCKET_NAME='<bucket_name>'
AWS_S3_RECORDING_FOLDER_NAME='<aws_s3_bucket_folder_name>'
NEXMO_APPLICATION_PRIVATE_KEY_PATH='./private.key'
NEXMO_APPLICATION_ID=<nexmo_application_id>
```

> NOTE: All placeholders `<>` need to be updated.

### Serverless Plugin

Install the [serverless-dotenv-plugin](https://www.serverless.com/plugins/serverless-dotenv-plugin/) with the following command.

```bash
npm i -D serverless-dotenv-plugin
```

### Deploy to Lambda

With all the above updated successfully, you can now use `Serverless` to deploy the app to [AWS Lambda](https://aws.amazon.com/lambda/).

```bash
serverless deploy
```

> Note: Return to Nexmo and update the `answer` and `event` URLs with what is provided by the deployment.

### Usage

With the deployment completed, you should be able to place a call to the `Nexmo number` from any phone. You will hear a message about being connected, and the `recipient` number will be called.

After you hang up, the `MP3 file` will be retrieved from `Nexmo` and uploaded to `AWS S3`. Following that, a transcription job will be started. The job can be monitored in the AWS Console website after login.

## Next Steps

As a follow-up, you may want to automate adding the results to a database. See this [nexmo-community/aws-voice-transcription-rds-callback-php](https://github.com/nexmo-community/aws-voice-transcription-rds-callback-php) for more info on how to accomplish that.

## Contributing

We love questions, comments, issues - and especially pull requests. Either open an issue to talk to us, or reach us on twitter: <https://twitter.com/VonageDev>.
