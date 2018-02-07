<?php

namespace Samwilson\BooksForBinding;

use Mediawiki\Api\FluentRequest;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\UsageException;
use Stash\Driver\FileSystem;
use Stash\Pool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wikisource\Api\WikisourceApi;

class Download extends Command {

	/** @var string */
	protected $outDir;

	/** @var SymfonyStyle */
	protected $io;

	protected function configure()
	{
		$this->setName('download');
		$this->setDescription('Download all the HTML of a given book from Wikisource');
		$langDesc = 'The language code of the Wikisource to scrape';
		$this->addOption( 'lang', 'l', InputOption::VALUE_REQUIRED, $langDesc );
		$titleDesc = 'The title of the work to download';
		$this->addOption( 'title', 't', InputOption::VALUE_REQUIRED, $titleDesc );
		$titleDesc = 'The output directory (the HTML files will be put in this directory); defaults to the current directory';
		$this->addOption( 'outdir', 'o', InputOption::VALUE_OPTIONAL, $titleDesc );
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->io = new SymfonyStyle($input, $output);
		// Wikisource API.
		$wsApi = new WikisourceApi();

		// Cache.
		$cache = new Pool( new FileSystem( [ 'path' => __DIR__.'/../cache' ] ) );
		$wsApi->setCache( $cache );

		// Make sure the work exists.
		$wikisource = $wsApi->fetchWikisource( $input->getOption( 'lang' ) );
		$title = $input->getOption( 'title' );
		if ( empty( $title ) ) {
			$this->io->error( 'Please specify a title.' );
			return 1;
		}
		try {
			$work = $wikisource->getWork($title);
		} catch (UsageException $exception ) {
			$this->io->error($exception->getMessage());
			return 1;
		}

		// Output directory.
		$outDirValue = $input->getOption('outdir');
		if (!is_dir($outDirValue)) {
			$outDirValue = './'.$this->makeFilename($title);
			if (!is_dir($outDirValue)) {
				mkdir($outDirValue);
			}
		}
		$this->outDir = realpath($outDirValue);
		$this->io->writeln("Downloading to $this->outDir");

		// Get the pages.
		$this->io->text( 'Getting subpages' );
		$this->getPageText( $wikisource->getMediawikiApi(), $title, '__' );
		foreach ( $work->getSubpages() as $subpageNum => $subpage ) {
			$prefix = str_pad( $subpageNum + 1, 2, '0', STR_PAD_LEFT );
			$this->getPageText( $wikisource->getMediawikiApi(), $subpage, $prefix );
		}
		
	}

	protected function getPageText( MediawikiApi $api, $title, $prefix ) {
		$this->io->text("Getting text for $title");
		$requestParse = FluentRequest::factory()
			->setAction( 'parse' )
			->setParam( 'page', $title )
			->setParam( 'prop', 'text' );
		$pageParse = $api->getRequest( $requestParse, 'parse' );
		$html = $pageParse['parse']['text']['*'];
		$htmlDir = $this->outDir . '/html';
		if (!is_dir($htmlDir)) {
			mkdir($htmlDir);
		}
		$filename = $htmlDir.'/'. $prefix .'_' . $this->makeFilename($title) . '.html';
		file_put_contents( $filename, $html );
	}

	protected function makeFilename($str)
	{
		return str_replace(' ', '_', str_replace('/', '__', $str));
	}

}
