<?php

namespace App\Traits;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

trait JsonResponseTrait
{
    public function successResponse($data, $message = "Operation Successful", $statusCode = Response::HTTP_OK): JsonResponse
    {
        $response = [
            "success" => true,
            "data" => $data,
            "message" => $message,
        ];

        return response()->json($response, $statusCode);
    }

    public function success($message = "Operation Successful", $statusCode = Response::HTTP_OK): JsonResponse
    {
        $response = [
            "success" => true,
            "message" => $message,
        ];

        return response()->json($response, $statusCode);
    }

    public function errorResponse($data = null, $message = null, $statusCode = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return response()->json([
            "success" => false,
            "message" => $message,
            "data" => $data
        ], $statusCode);
    }
    public function error($message = "Operation Failed", $statusCode = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return response()->json([
            "success" => false,
            "message" => $message,
        ], $statusCode);
    }

    public function fatalErrorResponse(Exception $e, $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR): JsonResponse
    {
        $line = $e->getTrace();

        $error = [
            "message" => $e->getMessage(),
            "trace" => $line[0],
            "mini_trace" => $line[1]
        ];

        if (strtoupper(config("APP_ENV")) === "PRODUCTION") {
            $error = null;
        }

        return response()->json([
            "success" => false,
            "message" => "Oops! Something went wrong on the server",
            "error" => $error,
        ], $statusCode);
    }
}
