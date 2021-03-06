<?php

namespace Samwilson\BooksForBinding;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Convert extends Command {

	/** @var SymfonyStyle */
	protected $io;

	/**
	 * Configure this command.
	 */
	protected function configure() {
		$this->setName( 'convert' );
		$this->setDescription( 'Convert HTML files to LaTeX' );
		$titleDesc = "The input 'html/' directory";
		$this->addOption( 'indir', 'i', InputOption::VALUE_REQUIRED, $titleDesc );
	}

	/**
	 * @param InputInterface $input The input.
	 * @param OutputInterface $output The output.
	 * @return null|int null or 0 if everything went fine, or an error code
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->io = new SymfonyStyle( $input, $output );
		$indir = $input->getOption( 'indir' );
		if ( empty( $indir ) || !is_dir( $indir ) ) {
			$this->io->text( "Please set --indir to the 'html/' directory of a project" );
			return 1;
		}
		$indir = realpath( $indir );

		$latexDir = dirname( $indir ) . '/latex';
		$this->io->writeln( "Saving LaTeX files to $latexDir" );
		if ( !is_dir( $latexDir ) ) {
			mkdir( $latexDir );
		}

		$latexFiles = [];
		$htmlFiles = glob( $indir.'/*.html' );
		foreach ( $htmlFiles as $htmlFile ) {
			$filename = pathinfo( $htmlFile, PATHINFO_FILENAME );
			$latexFile = $latexDir .'/'.$filename.'.tex';
			$this->io->writeln( "Converting $filename" );
			system( "pandoc --from html --to latex --output '$latexFile' '$htmlFile'" );
			$latexFiles[] = $filename;
		}

		// Create the main.tex file if needed, which includes all pages.
		$mainTexFile = dirname( $latexDir ) . '/main.tex';
		if ( !file_exists( $mainTexFile ) ) {
			$latexSource = '\\documentclass{book}'."\n"
				.'\\usepackage{hyperref, booktabs, longtable, graphicx}'."\n"
				.'\\begin{document}'."\n";
			foreach ( $latexFiles as $file ) {
				$latexSource .= '\\include{./latex/' . $file . '}' . "\n";
			}
			$latexSource .= '\\end{document}'."\n";
			$this->io->writeln( "Writing $mainTexFile" );
			file_put_contents( $mainTexFile, $latexSource );
		}
	}
}
