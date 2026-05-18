<?php

namespace App\Swagger;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="My API",
 *     version="1.0.0",
 *     description="This is my Laravel API",
 *     @OA\Contact(
 *         email="admin@example.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 */
class OpenApiSpec
{
    // هذا الكلاس قد يكون فارغًا، المهم وجود التعليقات أعلاه
}
