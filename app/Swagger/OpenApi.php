<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Team Work API",
 *     description="API Documentation"
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="Bearer",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token"
 * )
 *
 * @OA\Tag(
 *     name="Tasks",
 *     description="Task Management"
 * )
 *
 * @OA\Tag(
 *     name="Teams",
 *     description="Team Management"
 * )
 *
 * @OA\Tag(
 *     name="Projects",
 *     description="Project Management"
 * )
 *
 * @OA\Tag(
 *     name="Statistics",
 *     description="Statistics"
 * )
 *
 * @OA\Tag(
 *     name="Reports",
 *     description="Reports"
 * )
 */
class OpenApi
{
}
{
}
