<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrationRequest;
use App\Models\User;
use JustSteveKing\Tools\Http\Enums\Status;
use Treblle\ApiResponses\Responses\MessageResponse;

class RegisterController extends Controller
{
    public function __invoke(RegistrationRequest $request): MessageResponse
    {
        $user = new User($request->validated());

        $user->save();

        return new MessageResponse(
            data: "You are successfully registered",
            status: Status::CREATED,
        );
    }
}
