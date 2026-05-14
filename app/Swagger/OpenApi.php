<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Team Work API",
 *     version="1.0.0",
 *     description="API Documentation"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="Bearer",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token"
 * )
 */
class OpenApi
{
}
