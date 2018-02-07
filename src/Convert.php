<?php

namespace Samwilson\BooksForBinding;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Convert extends Command
{

	/** @var SymfonyStyle */
	protected $io;

	protected function configure()
	{
		$this->setName('convert');
		$this->setDescription('Convert HTML files to LaTeX');
		$titleDesc = "The input 'html/' directory";
		$this->addOption('indir', 'i', InputOption::VALUE_REQUIRED, $titleDesc);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->io = new SymfonyStyle($input, $output);
		$indir = $input->getOption('indir');
		if (empty($indir) || !is_dir($indir)) {
			$this->io->text("Please set --indir to the 'html/' directory of a project");
			return 1;
		}
		$indir = realpath($indir);

        $latexDir = dirname($indir) . '/latex';
        $this->io->writeln("Saving LaTeX files to $latexDir");
        if (!is_dir($latexDir)) {
            mkdir($latexDir);
        }

        $htmlFiles = glob($indir.'/*.html');
        foreach ($htmlFiles as $htmlFile) {
            $filename = pathinfo($htmlFile, PATHINFO_FILENAME);
            $latexFile = $latexDir .'/'.$filename.'.tex';
            $this->io->writeln("Converting $filename");
            system("pandoc --from html --to latex --output '$latexFile' '$htmlFile'");
        }
    }
}
