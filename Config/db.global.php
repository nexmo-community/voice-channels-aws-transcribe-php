<?php
//..
$connectionParams = array(
    'dbname' => getenv('AWS_RDS_DATABASE_NAME'),
    'user' => getenv('AWS_RDS_USER'),
    'password' => getenv('AWS_RDS_PASSWORD'),
    'host' => getenv('AWS_RDS_URL'),
    'driver' => 'pdo_mysql',
);

$conn = Doctrine\DBAL\DriverManager::getConnection($connectionParams);
