<?php
/**
 * Created by PhpStorm.
 * User: Ronald
 * Date: 2/18/2020
 * Time: 4:52 PM
 */

// src/Command/CreateUserCommand.php
namespace App\Command;

use App\GoogleAPI\GoogleApi;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class HotelLatLongCommand
 * @package App\Command
 */
final class SupplierRevCommand extends Command
{


	// the name of the command (the part after "bin/console")
	protected static $defaultName = 'supplierClean';

	/**
	 * HotelLatLongCommand constructor.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	protected function configure()
	{
		// ...
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$inputFileName = 'src/Command/csvInput/invoice_line_by_hotel.csv';
//		$inputFileName = 'src/Command/csvInput/invoice_line_by_supplier.csv';
		$outputFileName = 'src/Command/csvOutput/hotel.csv';
//		$outputFileName = 'src/Command/csvOutput/supplier2.csv';
		$output->writeln([
			'Starting cleaning suppliers',
			'============',
			'',
		]);

		try {
			//open input csv
			$inputFile = fopen($inputFileName, 'r');
			//open output csv
			$outputFile = fopen($outputFileName, 'a+');
		} catch (\Exception $e) {
			//validate
			if (!isset($inputFile)) {
				$output->writeln(['Input File is missing']);
				exit;
			}
			if (!isset($outputFile)) {
				$outputFile = fopen($outputFileName, 'a+');
			}
		}

		//loop per input line
		for ($c = 0; ($line = fgetcsv($inputFile)) !== FALSE; $c++) {

			$supplierId = $line[0];
			$supplierName = $line[1];
			$reservation = $line[2];
			if ($c == 0 ){
				$line[2] = 'retail_price';
				fputcsv($outputFile, $line);
				continue;

			}
			if(!$supplierId) {
				continue;
			}
			try {
				$reservationUnserialize = unserialize($reservation);
			} catch (\Exception $e) {
//				dd($line);
				continue;
			}
			$outputLine = $line;
			if(!array_key_exists('rate_retail_before_tax',$reservationUnserialize['rates_data']['total'])){
				continue;
			}
			$outputLine[2] = $reservationUnserialize['rates_data']['total']['rate_retail_before_tax'];
			$output->writeln(['new line']);

			fputcsv($outputFile, $outputLine);
			continue;


			//if query return more than one result
			// TODO output to a "multiResponseOutputFile" to do a manual check after

		}
		return 0;
	}

	private function compareLatLongIsClose($currentLat, $currentLong, $newLat, $newLong)
	{

		$radium = 1;
		$latCos = abs(cos($currentLat));

		$north = $currentLat + ($radium / 69);
		$south = $currentLat - ($radium / 69);
		$east = $currentLong + ($radium * $latCos / 69);
		$west = $currentLong - ($radium * $latCos / 69);
		if ($newLat >= $south && $newLat <= $north && $newLong >= $west && $newLong <= $east) {
			return true;
		}
		return false;
	}
}
