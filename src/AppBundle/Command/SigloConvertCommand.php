<?php

namespace AppBundle\Command;

use AppBundle\Converter\SigloTwigTemplateConverter;
use AppBundle\Exception\FileNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

#[AsCommand(
    name: 'siglo:convert',
    description: 'Convert an MJML theme to a twig theme. (Siglo customization)'
)]
class SigloConvertCommand extends Command
{
    public function __construct(
        private readonly SigloTwigTemplateConverter $converter,
        private readonly Filesystem                 $filesystem,
        private readonly string                     $mjmlMailThemesDir
    ){
        parent::__construct();
    }


    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Filter MJML file', '*')
            ->addOption('theme', 't', InputOption::VALUE_REQUIRED, 'MJML theme to convert.', 'modern_mjml')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Target path where files are converted. <comment>[default: var/themes/modern]</comment>')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mjmlFilePattern = $input->getArgument('file');
        $mjmlTheme = $input->getOption('theme');
        $twigThemePath = $input->getOption('path');
        if (!$twigThemePath) {
            $twigThemePath = 'var/themes/' . preg_replace('/_mjml$/', '', $mjmlTheme);
            $this->filesystem->mkdir($twigThemePath);
        }

        $mjmlThemeFolder = $this->mjmlMailThemesDir.'/'.$mjmlTheme;
        if (!is_dir($mjmlThemeFolder)) {
            throw new FileNotFoundException(sprintf('Could not find mjml theme folder %s', $mjmlThemeFolder));
        }
        if (!is_dir($twigThemePath)) {
            throw new FileNotFoundException(sprintf('Could not find twig theme folder %s', $twigThemePath));
        }
        $twigThemePath = realpath($twigThemePath);
        $twigTheme = basename($twigThemePath);

        $finder = (new Finder())->files()->sortByModifiedTime()->name("$mjmlFilePattern.mjml.twig")->in($mjmlThemeFolder);

        /** @var SplFileInfo $mjmlFile */
        foreach ($finder as $mjmlFile) {
            //Ignore components file for now
            $path_separator = preg_match('/\//', $mjmlFile->getRelativePathname()) ? '/' : '\\';
            if (str_starts_with($mjmlFile->getRelativePathname(), 'components')) {
                if ('components' . $path_separator . 'layout.mjml.twig' == $mjmlFile->getRelativePathname() ||
                    'components' . $path_separator . 'order_layout.mjml.twig' == $mjmlFile->getRelativePathname()) {
                    $output->writeln('Converting layout '.$mjmlFile->getRelativePathname());
                    $twigTemplate = $this->converter->convertLayoutTemplate($mjmlFile->getRealPath(), $mjmlTheme, $twigTheme);
                } else {
                    $isWrapped = 'components' . $path_separator . 'footer.mjml.twig' !== $mjmlFile->getRelativePathname();
                    $output->writeln('Converting component '.$mjmlFile->getRelativePathname());
                    $twigTemplate = $this->converter->convertComponentTemplate($mjmlFile->getRealPath(), $mjmlTheme, $twigTheme, $isWrapped);
                }
            } else {
                $output->writeln('Converting template '.$mjmlFile->getRelativePathname());
                $twigTemplate = $this->converter->convertChildTemplate($mjmlFile->getRealPath(), $mjmlTheme, $twigTheme);
            }

            $twigTemplatePath = $twigThemePath.'/'.$mjmlFile->getRelativePathname();
            $twigTemplatePath = preg_replace('/mjml\.twig/', 'html.twig', $twigTemplatePath);
            $twigTemplateFolder = dirname($twigTemplatePath);
            $this->filesystem->mkdir($twigTemplateFolder);

            $twigTemplate = $this->converter->convertRTL($twigTemplate);

            file_put_contents($twigTemplatePath, $twigTemplate);
        }

        $assetsFolder = $mjmlThemeFolder . '/assets';
        if (($mjmlFilePattern === '*' || $mjmlFilePattern === 'assets') && $this->filesystem->exists($assetsFolder)) {
            $output->writeln('Copying assets');
            $twigAssetsFolder = $twigThemePath . '/assets';
            $this->filesystem->mkdir($twigAssetsFolder);

            $finder = new Finder();
            $finder->files()->in($assetsFolder);
            /** @var SplFileInfo $assetFile */
            foreach ($finder as $assetFile) {
                $twigAssetPath = $twigAssetsFolder . '/' . $assetFile->getRelativePathname();
                $output->writeln('Copying asset ' . $twigAssetPath);
                $this->filesystem->copy($assetFile->getRealPath(), $twigAssetPath);
            }
        }

        return Command::SUCCESS;
    }
}