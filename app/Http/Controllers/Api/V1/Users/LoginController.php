<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use JustSteveKing\Tools\Http\Enums\Status;
use Treblle\ApiResponses\Data\ApiError;
use Treblle\ApiResponses\Responses\ErrorResponse;
use Treblle\ApiResponses\Responses\ExpandedResponse;
use Treblle\ErrorCodes\Enums\ErrorCode;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): ErrorResponse|ExpandedResponse
    {
        /** @var array $data */
        $data = $request->validated();

        $user = User::query()
            ->where(
                column: 'email',
                operator: '=',
                value: $data['email']
            )
            ->first();

        if ( ! $user instanceof User) {
            $error = ErrorCode::FORBIDDEN;

            return new ErrorResponse(
                data: new ApiError(
                    title: $error->getDescription()->title,
                    detail: 'The email or password were incorrect.',
                    instance: $request->path(),
                    code: $error->getDescription()->code,
                    link: $error->getDescription()->link,
                ),
                status: Status::FORBIDDEN
            );
        }

        $validPass = Hash::check(
            value: ($request->string('password')->toString()),
            hashedValue: $user->password
        );

        if ( ! $validPass) {
            $error = ErrorCode::FORBIDDEN;

            return new ErrorResponse(
                data: new ApiError(
                    title: $error->getDescription()->title,
                    detail: 'The email or password were incorrect.',
                    instance: $request->path(),
                    code: $error->getDescription()->code,
                    link: $error->getDescription()->link,
                ),
                status: Status::FORBIDDEN
            );
        }

        $token = $user->createToken(
            name: 'authToken'
        )->plainTextToken;

        return new ExpandedResponse(
            message: 'Successfully logged in.',
            data: [
                'token' => $token,
            ],
        );
    }
}
