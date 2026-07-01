<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Bridge X API",
 *     description="API documentation for Bridge X platform",
 *     @OA\Contact(email="support@bridgex.com")
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="Bearer",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class ApiInfo
{
    // Empty class - just for annotations
}
