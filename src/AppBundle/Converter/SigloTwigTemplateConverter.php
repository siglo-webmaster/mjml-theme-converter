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

    /**
     * Fix para product_solicitud.mjml.twig <a href="{{ 'mailto:' ~ '{customer_email}' }}" se mantenga asi
     */
    protected function extractHtml($htmlContent, $selector, $nodeIndex = null)
    {
        $hasMailTo = $nodeIndex === 0 && str_contains($htmlContent, 'mailto:');

        $extractedHtml = parent::extractHtml($htmlContent, $selector, $nodeIndex);

        if ($hasMailTo) {
            // devuelve los parentesis iniciales
            $extractedHtml = preg_replace('/href="%7B%7B%20(.*?)%20%7D%7D/', 'href="{{ \1 }}', $extractedHtml);
            // ajusta caracteres escapados adicionales
            $extractedHtml = preg_replace_callback('/<a\s+[^>]*href="([^"]+)"[^>]*>/i', function ($m) {
                $href = $m[1];

                // only decode %20, %7B, %7D — or decode all if you prefer
                $href = str_ireplace(['%20', '%7B', '%7D'], [' ', '{', '}'], $href);

                // rebuild the tag with the decoded href
                return str_replace($m[1], $href, $m[0]);
            }, $extractedHtml);
        }

        return $extractedHtml;
    }
}