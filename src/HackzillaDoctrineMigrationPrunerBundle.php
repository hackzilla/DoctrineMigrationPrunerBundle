<?php

declare(strict_types=1);

namespace Hackzilla\DoctrineMigrationPrunerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class HackzillaDoctrineMigrationPrunerBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
