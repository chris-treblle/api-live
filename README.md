# Let's Build An API Live


## Pre-installed components
Time constraints means I can't do composer installs live on stage, as such I've preinstalled:

* treblle/api-responses
* treblle/error-codes
* treblle/security-headers
* treblle/treblle-laravel
* spatie/laravel-csp

Then configured them to be installed correctly

### treblle/api-responses

We ran `php artisan vendor:publish --tag=api-config`

Then we edited the `config\api.php` file to:

```php
<?php

declare(strict_types=1);

return [
    'headers' => [
        'default' => [
            'Content-Type' => 'application/json',
        ],
        'error' => [
            'Content-Type' => 'application/json',
        ],
    ],
];

```
### treblle/security-headers

We ran `php artisan vendor:publish --provider="Treblle\SecurityHeaders\Providers\PackageServiceProvider" --tag="security-headers"`
We then added the following to our `app\Http\Kernel.php` file:

```php
use Treblle\SecurityHeaders\Http\Middleware\CertificateTransparencyPolicy;
use Treblle\SecurityHeaders\Http\Middleware\ContentTypeOptions;
use Treblle\SecurityHeaders\Http\Middleware\PermissionsPolicy;
use Treblle\SecurityHeaders\Http\Middleware\SetReferrerPolicy;
use Treblle\SecurityHeaders\Http\Middleware\StrictTransportSecurity;

protected $middlewareGroups = [
    'api' => [
        ContentTypeOptions::class,
        CertificateTransparencyPolicy::class,
        PermissionsPolicy::class,
        SetReferrerPolicy::class,
        StrictTransportSecurity::class,
        SetReferrerPolicy::class,
    ]
```

please note, this did not replace the kernel, simply added to it.

### treblle/treblle-laravel

We ran `php artisan vendor:publish --tag=treblle-config`
We also added `TREBLLE_API_KEY` and `TREBLLE_PROJECT_ID` to our `.env` file
The keys were updated with the generated ones from the Treblle Platform

Then in the `app\Http\Kernel.php` file we added:

```php
use Treblle\Middlewares\TreblleMiddleware;

protected $middlewareGroups = [
    'api' => [
        TreblleMiddleware::class,
    ]
```

### spatie/laravel-csp

We ran `php artisan vendor:publish --tag=csp-config`

Then in the `app\Http\Kernel.php` file we added:

```php
use Spatie\Csp\AddCspHeaders;

protected $middlewareGroups = [
    'api' => [
        AddCspHeaders::class,
    ]
```
## Laravel Sail
For demo purposes, we are using Laravel Sail, and as such we have made the following changes:

Run `php artisan sail:publish`
Run `composer require ryoluo/sail-ssl --dev`
Run `php artisan sail-ssl:publish`

Modified the following lines in `nginx\templates\default.conf.template`:

```text
    - listen  80  default_server;
    + listen  80  http2;
    
    - listen 443 default_server;
    + listen 443 http2; 
```

We removed the following from `docker-compose.yml`

```yaml
    meilisearch:
        image: 'getmeili/meilisearch:latest'
        ports:
            - '${FORWARD_MEILISEARCH_PORT:-7700}:7700'
        environment:
            MEILI_NO_ANALYTICS: '${MEILISEARCH_NO_ANALYTICS:-false}'
        volumes:
            - 'sail-meilisearch:/meili_data'
        networks:
            - sail
        healthcheck:
            test:
                - CMD
                - wget
                - '--no-verbose'
                - '--spider'
                - 'http://localhost:7700/health'
            retries: 3
            timeout: 5s
```

```yaml
    selenium:
        image: seleniarm/standalone-chromium
        extra_hosts:
            - 'host.docker.internal:host-gateway'
        volumes:
            - '/dev/shm:/dev/shm'
        networks:
            - sail
```

```yaml
  sail-meilisearch:
    driver: local
```

And then the references to `meilisearch` and `selenium` in the depends_on fields in `laravel.test`

## Adding A Simple Smoke Test Route

in our `routes/api.php` file, we add the following:

```php
Route::get(
    '/v1/ping',
    function() {
        return new MessageResponse(
            data: \Carbon\Carbon::now()->format('Y/m/d H:i:s')
        );
    }
);
```

## Sanctum
To allow sanctum to protect our setup, we need to add a few things:

in `config/auth.php`

```php
'guards' => [
    // ...
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```
# Now Lets Get Started With Updating Our API:

## Security Setup

Things we need to do to ensure our security protocols are followed:

* `X-Frame-Options` header
* `Accept` header
* `Allow` header

So let's make a set of middleware to do this:

`app/Http/Kernel/XFrameOptionsMiddleware.php`

```php
    <?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Closure;

final class XFrameOptionsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * @var Response $response
         */
        $response = $next($request);

        $response->headers->set(
            key: 'X-Frame-Options',
            values: 'deny',
        );

        return $response;
    }
}
```

`app/Http/Kernel/AllowMiddleware.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use \Closure;
use Illuminate\Support\Facades\Route;

class AllowMiddleware
{
    public function handle($request, Closure $next)
    {
        $currentRoute = Route::getCurrentRoute();
        $routes = Route::getRoutes();

        $methods = [];

        foreach ($routes as $route) {
            if ($route->uri() === $currentRoute->uri()) {
                $methods = array_merge($methods, $route->methods());
            }
        }

        $response = $next($request);
        $response->header('Allow', implode(', ', $methods));

        return $response;
    }
}
```

`app/Http/Middleware/AcceptMiddleware.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;

class AcceptMiddleware
{
    public function handle($request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
```

`app/Http/Kernel.php` (adding to)

```php
use App\Http\Middleware\AcceptMiddleware;
use App\Http\Middleware\AllowMiddleware;
use App\Http\Middleware\XFrameOptionsMiddleware;

protected $middlewareGroups = [
    'api' => [
        XFrameOptionsMiddleware::class,
        AllowMiddleware::class,
        AcceptMiddleware::class,
    ],
];
```

## Let's Make Routing More Modular
We're going to use routing in multiple files to make a nicer setup:

`routes/api.php`

```php
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1/')->as('api:v1:')->group(base_path('routes/v1/routes.php'));
```

`routes/v1/routes.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Treblle\ApiResponses\Responses\MessageResponse;
use Carbon\Carbon;

Route::prefix('auth')->as('auth:')->group(base_path('routes/v1/auth.php'));

Route::get('ping', fn() => new MessageResponse(Carbon::now()->format('Y/m/d H:i:s')));
```

`routes/v1/auth.php`

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Users\LoginController;
use App\Http\Controllers\Api\V1\Users\RegisterController;
use Illuminate\Support\Facades\Route;

Route::post('login', LoginController::class)->name('login');
Route::post('register', RegisterController::class)->name('register');
```


## Authentication

Obviously, Authentication is essential for our API - so let's add our Registration and Login Routes:

`app\Http\Requests\RegistrationRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return null === auth('sanctum')->user()?->getAuthIdentifier();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email:rfc,dns|unique:users,email',
            'password' => Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
        ];
    }
}
```

`app\Http\Controllers\Api\V1\Users\RegisterController.php`

```php
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
```

## Now lets add some routes

and this is the point at which we're over to the audience - let's build some routes that do - stuff?

we want:

1) a get route
2) a post route
3) a delete route
4) a put route

