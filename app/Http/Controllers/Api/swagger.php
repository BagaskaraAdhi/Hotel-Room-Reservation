<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Annotations as OA; // Jangan lupa tambahkan ini

/**
 * @OA\Info(
 * version="1.0.0",
 * title="Hotel Room Reservation API",
 * description="Dokumentasi API untuk sistem reservasi kamar hotel"
 * )
 * @OA\Server(
 * url=L5_SWAGGER_CONST_HOST,
 * description="API Server"
 * )
 * @OA\SecurityScheme(
 * securityScheme="sanctum",
 * type="http",
 * scheme="bearer",
 * bearerFormat="JWT"
 * description="Otorisasi menggunakan Bearer Token (Sanctum)"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
