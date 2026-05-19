<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="TeamWork API",
 *     version="1.0.0",
 *     description="API Documentation"
 * )
 *
 * @OA\Server(
 *     url="https://YOUR-DOMAIN.com",
 *     description="Production Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer"
 * )
 */
class SwaggerInfo {}
