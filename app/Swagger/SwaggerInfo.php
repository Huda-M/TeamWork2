<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="TeamWork2 API",
 *     description="Documentation for TeamWork2",
 *     @OA\Contact(
 *         email="support@teamwork2.com",
 *         name="Support Team"
 *     )
 * )
 *
 * @OA\Server(
 *     url="https://teamwork2-production-ucr9dn.laravel.cloud/api",
 *     description="Production Server"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="Bearer",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token in the format: Bearer {token}"
 * )
 */
class SwaggerInfo
{
}
