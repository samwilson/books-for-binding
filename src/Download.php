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
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;
use Wikisource\Api\WikisourceApi;

class Download extends Command {

	/** @var string */
	protected $outDir;

	/** @var SymfonyStyle */
	protected $io;

	/**
	 * Configure this command.
	 */
	protected function configure() {
		$this->setName( 'download' );
		$this->setDescription( 'Download all the HTML of a given book from Wikisource' );
		$langDesc = 'The language code of the Wikisource to scrape';
		$this->addOption( 'lang', 'l', InputOption::VALUE_REQUIRED, $langDesc );
		$titleDesc = 'The title of the work to download';
		$this->addOption( 'title', 't', InputOption::VALUE_REQUIRED, $titleDesc );
		$titleDesc = "The output directory\n(the HTML files will be put in this directory);\n"
			. 'defaults to the current directory';
		$this->addOption( 'outdir', 'o', InputOption::VALUE_OPTIONAL, $titleDesc );
	}

	/**
	 * @param InputInterface $input The input.
	 * @param OutputInterface $output The output.
	 * @return null|int null or 0 if everything went fine, or an error code
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->io = new SymfonyStyle( $input, $output );
		// Wikisource API.
		$wsApi = new WikisourceApi();

		// Cache.
		$cache = new Pool( new FileSystem( [ 'path' => __DIR__ . '/../cache' ] ) );
		$wsApi->setCache( $cache );

		// Check language input.
		if ( !$input->getOption( 'lang' ) ) {
			$this->io->error( 'Please specify a language' );
			return 1;
		}

		// Make sure the work exists.
		$wikisource = $wsApi->fetchWikisource( $input->getOption( 'lang' ) );
		$title = $input->getOption( 'title' );
		if ( empty( $title ) ) {
			$this->io->error( 'Please specify a title' );
			return 1;
		}
		try {
			$work = $wikisource->getWork( $title );
		} catch ( UsageException $exception ) {
			$this->io->error( $exception->getMessage() );
			return 1;
		}

		// Output directory.
		$outDirValue = $input->getOption( 'outdir' );
		if ( !is_dir( $outDirValue ) ) {
			$outDirValue = './'.$this->makeFilename( $title );
			if ( !is_dir( $outDirValue ) ) {
				mkdir( $outDirValue );
			}
		}
		$this->outDir = realpath( $outDirValue );
		$this->io->writeln( "Downloading to $this->outDir" );

		$metabook = [
			'title' => $work->getWorkTitle(),
			'author' => join( ', ', $work->getAuthors() ),
			'year' => $work->getYear(),
			'publisher' => $work->getPublisher(),
			'items' => [],
		];

		// Get the pages.
		$this->io->text( 'Getting subpages' );
		$this->getPageText( $wikisource->getMediawikiApi(), $title, '000' );
		foreach ( $work->getSubpages() as $subpageNum => $subpage ) {
			$prefix = str_pad( $subpageNum + 1, 3, '0', STR_PAD_LEFT );
			$filename = $this->getPageText( $wikisource->getMediawikiApi(), $subpage, $prefix );
			$metabook['items'][] = [
				'title' => $subpage,
				'url' => 'https://'.$wikisource->getLanguageCode().'.wikisource.org/wiki/'.$subpage,
				'content_type' => 'text/html',
				'type' => 'article',
				'pageid' => '',
				'revision' => '',
				'latest' => '',
				'currentVersion' => '',
				'timestamp' => '',
				'file' => 'html/'.basename( $filename ),
			];
		}

		// Write metabook.yaml\
		$metabookFilename = $this->outDir . '/metabook.yaml';
		file_put_contents( $metabookFilename, Yaml::dump( $metabook, 3 ) );

		return 0;
	}

	/**
	 * Fetch the text of a single page, and write it to a file.
	 * @param MediawikiApi $api The API.
	 * @param string $title Wiki page title.
	 * @param string $prefix Filename prefix.
	 * @return string The full path and filename of the written HTML file.
	 */
	protected function getPageText( MediawikiApi $api, $title, $prefix ) {
		$this->io->text( "Getting text for $title" );
		$requestParse = FluentRequest::factory()
			->setAction( 'parse' )
			->setParam( 'page', $title )
			->setParam( 'disablelimitreport', true )
			->setParam( 'prop', 'text' );
		$pageParse = $api->getRequest( $requestParse, 'parse' );
		$html = $pageParse['parse']['text']['*'];

		// Clean HTML. This is probably quite fragile and could be done in a better way.
		$htmlCrawler = new Crawler();
		// Note the slightly odd way of ensuring the HTML content is loaded as UTF8.
		$htmlCrawler->addHtmlContent( "<div>$html</div>", 'UTF-8' );
		// Remove unwanted classes.
		$xpath = '//*['
			. 'contains(@class, "noprint")'
			. ' or contains(@class, "ws-noexport")'
			. ' or contains(@class, "ws-pagenum")'
			. ']';
		$htmlCrawler->filterXPath( $xpath )->each( function ( Crawler $crawler ) {
			foreach ( $crawler as $node ) {
				$node->parentNode->removeChild( $node );
			}
		} );
		$html = $htmlCrawler->html();

		// Write file.
		$htmlDir = $this->outDir . '/html';
		if ( !is_dir( $htmlDir ) ) {
			mkdir( $htmlDir );
		}
		$filename = $htmlDir.'/'. $prefix .'_' . $this->makeFilename( $title ) . '.html';
		file_put_contents( $filename, $html );
		return $filename;
	}

	/**
	 * @param string $str The string to fix.
	 * @return string
	 */
	protected function makeFilename( $str ) {
		return str_replace( ' ', '_', str_replace( '/', '__', $str ) );
	}

}
