<?php

namespace Tobi1craft\Sso\Commands;

use Illuminate\Console\Command;
use App\Traits\EnvironmentWriterTrait;
use Illuminate\Support\Str;

class GenerateSecretKey extends Command
{
    use EnvironmentWriterTrait;

    protected $description = 'Generate new SSO secret key';

    protected $signature = 'sso:generate';

    /**
     * GenerateSecretKey constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Handle command execution.
     *
     * @throws \App\Exceptions\PanelException
     */
    public function handle(): int
    {
        $secret_key = $this->generate();
        $this->writeToEnvironment(['SSO_SECRET' => $secret_key]);

        $this->info("Generated new secret key: $secret_key");
        return 0;
    }

    /**
     * Generate random secret key
     *
     * @return mixed
     */
    protected function generate()
    {
      return Str::random(48);
    }
}
