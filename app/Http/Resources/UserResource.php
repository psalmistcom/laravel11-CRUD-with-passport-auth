<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array|JsonSerializable|Arrayable
    {
        return [
            'id' => $this['id'],
            'full_name' => $this['full_name'],
            'email' => $this['email'],
            'status' => $this['status'],
            'created_at' => $this['created_at'],
            'email_verified_at' => $this['email_verified_at'],
        ];
    }
}
