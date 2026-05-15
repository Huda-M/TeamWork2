<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Team Work API",
    description: "API Documentation"
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: "Server"
)]
#[OA\SecurityScheme(
    securityScheme: "Bearer",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
class OpenApi
{
}
