<?php

use Bellows\Plugins\Octane;
use Bellows\PluginSdk\Facades\Deployment;

it('can set the env variable if there are other ports in use', function () {
    $site1 = $this->fakeSite()->returnEnv([
        'OCTANE_PORT' => 8000,
    ])->mergeData([
        'project_type' => 'octane',
    ]);

    $site2 = $this->fakeSite()->returnEnv([
        'OCTANE_PORT' => 8001,
    ])->mergeData([
        'project_type' => 'octane',
    ]);

    Deployment::server()->shouldReceive('sites')->andReturn(collect([$site1, $site2]));

    $result = $this->plugin(Octane::class)
        ->expectsQuestion('Which server would you like to use for Octane?', 'swoole')
        ->deploy();

    $createSiteParams = $result->getCreateSiteParams();

    expect($createSiteParams['octane_port'])->toBe(8002);
    expect($createSiteParams['project_type'])->toBe('octane');

    expect($result->getEnvironmentVariables())->toBe([
        'OCTANE_SERVER' => 'swoole',
        'OCTANE_PORT'   => 8002,
        'OCTANE_HTTPS'  => true,
    ]);

    $daemons = $result->getDaemons();

    expect($daemons)->toHaveCount(1);

    expect($daemons[0]->toArray())->toBe([
        'command'   => 'octane:start --port=8002 --no-interaction',
        'user'      => null,
        'directory' => null,
    ]);
});

it('can set the env variable if there are no other ports in use', function () {
    $site1 = $this->fakeSite()->mergeData([
        'project_type' => 'octane',
    ]);

    Deployment::server()->shouldReceive('sites')->andReturn(collect([$site1]));

    $result = $this->plugin(Octane::class)
        ->expectsQuestion('Which server would you like to use for Octane?', 'swoole')
        ->deploy();

    expect($result->getEnvironmentVariables())->toBe([
        'OCTANE_SERVER' => 'swoole',
        'OCTANE_PORT'   => 8000,
        'OCTANE_HTTPS'  => true,
    ]);

    $daemons = $result->getDaemons();

    expect($daemons)->toHaveCount(1);

    expect($daemons[0]->toArray())->toBe([
        'command'   => 'octane:start --port=8000 --no-interaction',
        'user'      => null,
        'directory' => null,
    ]);
});
