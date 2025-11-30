<?php

namespace AppBundle\Converter;

use AppBundle\Mjml\MjmlConverter;
use Twig\Environment;

class SigloTwigTemplateConverter extends TwigTemplateConverter
{
    private readonly string $mjmlMailThemesDir;

    private string $defaultMjmlTheme = 'modern_mjml';
    private string $defaultPsMailTheme = 'modern';

    public function __construct(
        Environment $engine, MjmlConverter $mjmlConverter, string $mjmlTwigConverterDir, string $mjmlMailThemesDir
    ){
        parent::__construct($engine, $mjmlConverter, $mjmlTwigConverterDir);
        $this->mjmlMailThemesDir = $mjmlMailThemesDir;
    }

    /**
     * Components por default extienden de layout.mjml.twig. Si es siglo theme y este no tiene layout, utilizamos el default
     */
    public function convertComponentTemplate($mjmlTemplatePath, $mjmlTheme, $newTheme, bool $isWrapped): string
    {
        if ($mjmlTheme !== $this->defaultMjmlTheme) {
            $layoutTemplatePath = $this->mjmlMailThemesDir . '/'.$mjmlTheme.'/components/layout.mjml.twig';
            if (!file_exists($layoutTemplatePath)) {
                $mjmlTheme = $this->defaultMjmlTheme;
            }
        }
        return parent::convertComponentTemplate($mjmlTemplatePath, $mjmlTheme, $newTheme, $isWrapped);
    }

    /**
     * Terminan extendiendo @MailThemes/$newTheme, siendo $newTheme el nombre del directorio donde se exportan las plantillas
     * Siempre debe ser @MailThemes/modern
     */
    public function convertChildTemplate($mjmlTemplatePath, $mjmlTheme, $newTheme): string
    {
        $childTemplate = parent::convertChildTemplate($mjmlTemplatePath, $mjmlTheme, $newTheme);

        if ($newTheme !== $this->defaultPsMailTheme) {
            $childTemplate = preg_replace(
                '/(@MailThemes\/)([^\/]+)/', '$1' . $this->defaultPsMailTheme, $childTemplate, 1
            );
        }

        return $childTemplate;
    }
}