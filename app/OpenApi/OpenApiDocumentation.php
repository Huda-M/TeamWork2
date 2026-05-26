<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         version="1.0.0",
 *         title="TeamWork API",
 *         description="API Documentation for Team Work System",
 *         @OA\Contact(
 *             email="support@teamwork.com",
 *             name="Support Team"
 *         ),
 *         @OA\License(
 *             name="Apache 2.0",
 *             url="https://www.apache.org/licenses/LICENSE-2.0.html"
 *         )
 *     ),
 *     @OA\Server(
 *         url="https://teamwork2-main-opmxfq.free.laravel.cloud",
 *         description="Production Server"
 *     ),
 *     @OA\Server(
 *         url="http://localhost:8000",
 *         description="Development Server"
 *     )
 * )
 * 
 * @OA\SecurityScheme(
 *     type="http",
 *     description="Login with Bearer token",
 *     name="Token",
 *     in="header",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="Bearer"
 * )
 */
class OpenApiDocumentation
{
}
