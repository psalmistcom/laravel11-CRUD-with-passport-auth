<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Traits\JsonResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use JsonResponseTrait;

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $createUser = User::create([
                'full_name' => $request->input('full_name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
            ]);
            $response['user'] = $createUser;
            $response['token'] = $createUser->createToken('user')->accessToken;

            DB::commit();
            return $this->successResponse($response, 'Registration Successful');
        } catch (Exception $th) {
            DB::rollBack();
            return $this->fatalErrorResponse($th);
        }
    }

    public function login()
    {
        //
    }
}
