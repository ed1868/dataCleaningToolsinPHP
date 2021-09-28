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
 * @property GoogleApi $googleApi
 */
final class HotelLatLongCommand extends Command
{
	private $googleApi;


	// the name of the command (the part after "bin/console")
	protected static $defaultName = 'hotel:lat-long';

	/**
	 * HotelLatLongCommand constructor.
	 * @param GoogleApi $googleApi
	 */
	public function __construct(GoogleApi $googleApi)
	{
		$this->googleApi = $googleApi;
		parent::__construct();
	}

	protected function configure()
	{
		// ...
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$inputFileName = 'src/Command/csvInput/hotels.csv';
		$outputFileName = 'src/Command/csvOutput/hotelLatLong.csv';
		$output->writeln([
			'Starting Hotel cleaning from csv',
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
		//output file to array.
		$outputArray = array();
		for ($c = 0; ($line = fgetcsv($outputFile)) !== FALSE; $c++) {
			$outputArray[$line[0]] = $line;
		}
		//loop per input line
		for ($c = 0; ($line = fgetcsv($inputFile)) !== FALSE; $c++) {
			if ($c == 0) {
				continue;
			}
			$hotelId = $line[0];
			$hotelSameAs = $line[3];
			$hotelName = str_replace("&", "and", $line[4]);
			$hotelAddress = str_replace("&", "and", $line[5]);
			$hotelZipCode = $line[6];
			$hotelCity = $line[7];
			$hotelDestination = $line[8];
			$hotelState = $line[9];
			$hotelCountry = $line[10];
			$hotelRegion = $line[11];
			$hotelLatitude = $line[21];
			$hotelLongitude = $line[22];
			$hotelGooglePlaceId = $line[36];

			if (array_key_exists($hotelId, $outputArray)) {
				$hotelLatitude = $outputArray[$hotelId][1];
				$hotelLongitude = $outputArray[$hotelId][2];
			}
			//check if lat and long in any of the files

			if ($hotelLatitude && $hotelLongitude) {
				//if lat/long
				//TODO we may verify if they values are right?? -- add an option for this

				$output->writeln([$hotelId . ' - LAT/LONG already in the files']);
				continue;
			}
			//if no lat/long
			// query google using hotel name and other location information

			if ($location = $this->googleApi->getLocation($hotelName . " " . $hotelAddress)) {
				//if query return one result
				if ($hotelId == 63) {

					dd($location);
				}
				$output->writeln([$hotelId . ' - ADDING LAT/LONG to Output File']);

				if(!$this->compareLatLongIsClose($hotelLatitude,$hotelLongitude,$location['latitude'], $location['longitude'])){
					$outputLine = array(
						$hotelId,
						$location['latitude'],
						$location['longitude']
					);
				}
				// add it to output file

				fputcsv($outputFile, $outputLine);
				continue;

			}
			//if query return more than one result
			// TODO output to a "multiResponseOutputFile" to do a manual check after


			$output->writeln([$hotelId . ' - LAT/LONG missing and we didn\'t find a match']);

		}
		return 0;
	}
	private function compareLatLongIsClose($currentLat, $currentLong, $newLat, $newLong){

		$radium = 1;
		$latCos = abs(cos($currentLat));

		$north = $currentLat + ($radium / 69);
		$south = $currentLat - ($radium / 69);
		$east = $currentLong + ($radium * $latCos / 69);
		$west = $currentLong - ($radium * $latCos / 69);
		if($newLat >= $south && $newLat <= $north && $newLong >= $west && $newLong <= $east){
			return true;
		}
		return false;
	}
}
