<?php

namespace Ladecadanse;

class TemplateEngine
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        if (!is_dir($basePath)) {
            throw new \InvalidArgumentException("Répertoire de templates invalide : $basePath");
        }

        $this->basePath = rtrim($basePath, '/');
    }

    public function render(string $templateName, array $vars = []): string
    {
        $filepath = "{$this->basePath}/{$templateName}.txt";

        if (!file_exists($filepath)) {
            throw new \RuntimeException("Template non trouvé : $filepath");
        }

        $template = file_get_contents($filepath);

        // Remplacer les %placeholders%
        foreach ($vars as $key => $value) {
            $template = str_replace("%$key%", $value, $template);
        }

        // Détection des %non_remplacés%
        if (preg_match_all('/%([a-zA-Z0-9_.-]+)%/', $template, $matches)) {
            foreach ($matches[0] as $missing) {
                $template = str_replace($missing, "[non défini: $missing]", $template);
            }
        }

        return $template;
    }
}
