<?php

namespace Bellows\Plugins;

use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\Contracts\ServerProviders\SiteInterface;
use Bellows\PluginSdk\Data\CreateSiteParams;
use Bellows\PluginSdk\Data\DaemonParams;
use Bellows\PluginSdk\Facades\Artisan;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Project;
use Bellows\PluginSdk\Plugin;
use Bellows\PluginSdk\PluginResults\CanBeDeployed;
use Bellows\PluginSdk\PluginResults\CanBeInstalled;
use Bellows\PluginSdk\PluginResults\DeploymentResult;
use Bellows\PluginSdk\PluginResults\InstallationResult;

class Octane extends Plugin implements Deployable, Installable
{
    use CanBeDeployed, CanBeInstalled;

    protected int $octanePort;

    protected string $octaneServer;

    public function install(): ?InstallationResult
    {
        return InstallationResult::create()->installationCommand('octane:install');
    }

    public function deploy(): ?DeploymentResult
    {
        $defaultOctanePort = 8000;

        $highestOctanePortInUse = Deployment::server()->sites()
            ->filter(fn (SiteInterface $s) => $s->data()->project_type === 'octane')
            ->map(fn (SiteInterface $s) => $s->env()->get('OCTANE_PORT'))
            ->filter()
            ->map(fn ($s) => (int) $s)
            ->max() ?: $defaultOctanePort - 1;

        $this->octanePort = $highestOctanePortInUse + 1;

        $this->octaneServer = Console::choice('Which server would you like to use for Octane?', [
            'roadrunner',
            'swoole',
        ], Project::env()->get('OCTANE_SERVER') ?? 'swoole');

        return DeploymentResult::create()
            ->createSiteParams(new CreateSiteParams(
                octanePort: $this->octanePort,
                projectType: 'octane',
            ))
            ->environmentVariables($this->environmentVariables())
            ->daemon(new DaemonParams(
                "octane:start --port={$this->octanePort} --no-interaction",
            ));
    }

    public function requiredComposerPackages(): array
    {
        return [
            'laravel/octane',
        ];
    }

    public function shouldDeploy(): bool
    {
        return !Deployment::site()->env()->hasAll('OCTANE_PORT', 'OCTANE_SERVER')
            || !Deployment::server()->getDaemons()->contains(
                fn ($daemon) => str_contains($daemon['command'], Artisan::forDaemon('octane:start'))
            );
    }

    protected function environmentVariables(): array
    {
        $vars = [
            'OCTANE_SERVER' => $this->octaneServer,
            'OCTANE_PORT'   => $this->octanePort,
        ];

        if (Project::siteIsSecure()) {
            $vars['OCTANE_HTTPS'] = true;
        }

        return $vars;
    }
}
