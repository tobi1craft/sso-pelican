# Pelican SSO

## 🚧 *The goal is to switch to JWTs, it is not ready yet.* 🚧

<br>

Pelican SSO is a package for implementing Single Sign-On (SSO) in [Pelican Panel](https://github.com/pelican-dev/panel/). It allows you to authorize users on a Pelican panel instance from another website.

## Installation

#### To install the package, use Composer:

1. Add the GitHub repo as a Composer repository by running the following command:
```bash
composer config repositories.sso-pelican vcs https://github.com/tobi1craft/sso-pelican.git
```

<br>

2. Install the package:
```bash
composer require tobi1craft/sso-pelican:dev-main
```

<br>

3. (Optional) Reoptimize the autoloader:
```bash
composer dump-autoload --optimize
```

<br>

<details>

<summary>Example when using Docker for the Panel</summary>

```Dockerfile
# change version here:
FROM ghcr.io/pelican-dev/panel:latest


# 1) Install OS deps (git, unzip, curl) and Composer
USER root

RUN apk add --no-cache \
    curl \
    git \
    unzip

RUN curl -sS https://getcomposer.org/installer \
    | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version


# 2) Install the SSO package
WORKDIR /var/www/html

RUN composer config repositories.sso-pelican vcs https://github.com/tobi1craft/sso-pelican.git \
    && composer require tobi1craft/sso-pelican:dev-main


# 3) regenerate optimized autoloader
RUN composer dump-autoload --optimize
```

</details>

## Configuration
1. Generate new SSO key. A method for this is provided by the package, but you can also generate it manually. <br>The key should be a random string of at least **32** characters.

```shell
php artisan sso:generate
```

<br>

2. Set the SSO key as an environment variable named `SSO_SECRET`.

## Usage

1. Generate a access token for using a GET request from your application
2. Redirect the user to the SSO redirect with their token

<br>

<details>

<summary>Example in PHP</summary>

```php
public function loginPanel()
{
    $response = Http::get("https://panel.example.com/generate-token/", [
        'sso_secret' => "xxxxxxx",
        'user_id' => 1
    ]);

    if (!$response->successful()) {
        $message = $response['success'] && !$response['success']
            ? $response['message']
            : 'Something went wrong, please contact an administrator.';

        return redirect()->back()->withError($message);
    }

    return redirect()->intended($response['redirect']);
}
```

</details>

<br>

After being redirected to the /sso route, the user will be automatically authorized on the Pelican Panel.

## Support

If you have any questions or issues, please create a new issue in the project repository on GitHub.

## Contributing

Just clone and install everything using composer. Optionally leave out PHP-Extensions that are not installed.

```bash
composer install --ignore-platform-req=ext-intl --ignore-platform-req=ext-zip --ignore-platform-req=ext-bcmath
```

## License

This project is licensed under the MIT License.
