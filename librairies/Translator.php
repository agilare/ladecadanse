<?php

namespace Ladecadanse;

use Symfony\Component\Yaml\Yaml;

class Translator
{
    private array $messages;

    public function __construct(string $filepath)
    {
        if (!file_exists($filepath)) {
            throw new \RuntimeException("Fichier de traduction non trouvé : $filepath");
        }

        $this->messages = Yaml::parseFile($filepath);
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->messages[$key] ?? $default ?: "[$key]";
    }
}
