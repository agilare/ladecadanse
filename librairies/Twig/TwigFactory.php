<?php

declare(strict_types=1);

namespace Ladecadanse\Twig;

use Ladecadanse\Security\Authorization;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

final class TwigFactory
{
    public static function create(Authorization $authorization, array $session): Environment
    {
        $isDev = ENV === 'dev';

        $twig = new Environment(new FilesystemLoader(__ROOT__ . '/templates'), [
            'cache' => __ROOT__ . '/var/cache/twig',
            'debug' => $isDev,
            'auto_reload' => true,
            'strict_variables' => $isDev,
            'autoescape' => 'html',
        ]);
        $twig->addExtension(new LadecadanseExtension($authorization, $session));

        if ($isDev)
        {
            $twig->addExtension(new DebugExtension());
        }

        return $twig;
    }
}
