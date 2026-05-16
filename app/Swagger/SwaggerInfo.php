<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'API KosKuy PAA',
    version: '1.0.0',
    description: 'Dokumentasi Swagger UI untuk testing endpoint API Laravel 12'
)]
#[OA\Server(
    url: '/api'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Masukkan token JWT hasil login'
)]
class SwaggerInfo
{
    // Placeholder class so swagger-php has a concrete PHP file to scan.
}
