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
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class HotelLatLongCommand
 * @package App\Command
 * @property EntityManagerInterface $em
 * @property GoogleApi $googleApi
 */
final class CompareDestinationsCommand extends Command
{
	private $em,$googleApi;


	// the name of the command (the part after "bin/console")
	protected static $defaultName = 'compare:hotel:destinations';

	/**
	 * HotelLatLongCommand constructor.
	 * @param EntityManagerInterface $em
	 */
	public function __construct(EntityManagerInterface $em,GoogleApi $googleApi)
	{
		$this->em = $em;
		$this->googleApi = $googleApi;

		parent::__construct();
	}

	protected function configure()
	{
		$this
			->addArgument("getDestination",InputArgument::OPTIONAL,"destination");
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{

		$getDestination =  $input->getArgument('getDestination');
		$conn = $this->em->getConnection();
		$outputFileName = 'src/Command/csvOutput/destinationComparison.csv';
		$outputFileName2 = 'src/Command/csvOutput/missingHotels.csv';
		$output->writeln([
			'Starting Upload to Hotel from csv',
			'============',
			'',
		]);
		//Fetch hotels to know if we already upload this hotel
		$sql = 'SELECT hotel_id,hotel_destination FROM hotels ORDER BY hotel_destination';
		$stmt = $conn->prepare($sql);
		$stmt->execute();
		$dataBaseHotels = $stmt->fetchAll();

		$destinations = array();
		foreach ($dataBaseHotels as $hotel) {//order by ID to make it easy after
			$destinations[strtolower($hotel['hotel_destination'])][] = $hotel['hotel_id'];
		}

		$outputFile2 = fopen($outputFileName2, 'a+');
		try {
			//open input csv
			$outputFile = fopen($outputFileName, 'r');
		} catch (\Exception $e) {
			//validate
			$outputFile = fopen($outputFileName, 'a+');
			$responseLine[0] = 'Destination';
			$responseLine[1] = 'Location';
			$responseLine[2] = 'Ids Current System';
			$responseLine[3] = 'Ids new System';
			$responseLine[4] = 'missing Hotels';
			$responseLine[5] = 'new Hotels';
			$responseLine[6] = 'count missing';
			$responseLine[7] = 'count new';
			$responseLine[8] = 'google place id';

			fputcsv($outputFile,$responseLine);
			if (!isset($outputFile)) {
				$output->writeln(['error opening output file']);
				exit;
			}
		}
		$outputFile = fopen($outputFileName, 'a+');
		$outputArray = array();
		for ($c = 0; ($line = fgetcsv($outputFile)) !== FALSE; $c++) {
			$outputArray[$line[0]] = $line;
		}
		//loop per input line
		foreach ($destinations as $destination=>$hotels) {
			if($getDestination && $getDestination != $destination){
				continue;
			}
			if((array_key_exists($destination,$outputArray) || !$destination) && !$getDestination ){
				continue;
			}
//			if($destination != "orlando"){
//				continue;
//			}

			$location = $this->googleApi->getLocation($destination,$getDestination);
			$responseLine[0] = $destination;
			$responseLine[1] = 'location not found in google';
			$responseLine[2] = '';
			$responseLine[3] = '';
			if(!$location){
				$output->writeln([$destination.' - not found in google' ]);
				continue;
			}
			$responseLine[1] = json_encode($location,true);
			$sql = "SELECT hotel_id FROM hotels WHERE hotel_lat <='".$location['north']."'".
				" AND hotel_lat >='".$location['south']."'".
				" AND hotel_lon <='".$location['east']."'".
				" AND hotel_lon >='".$location['west']."'";
//			dd($sql);
			$stmt = $conn->prepare($sql);
			$stmt->execute();
			$hotelIDs = $stmt->fetchAll();
			if($hotelIDs && is_array($hotelIDs)){
				$hotelIDs = array_column($hotelIDs ,'hotel_id');
			}else{
				$hotelIDs = array();
			}
			$responseLine[2] = json_encode($hotels);
			$responseLine[3] = json_encode($hotelIDs);
			$losing = array_diff($hotels,$hotelIDs);
			$winning = array_diff($hotelIDs,$hotels);
			$responseLine[4] = json_encode($losing);
			$responseLine[5] = json_encode($winning);
			$responseLine[6] = count($losing);
			$responseLine[7] = count($winning);
			$responseLine[8] = $location['place_id'];
//			if($responseLine[0] == "orlando"){
//				dd($location,$responseLine[1],$responseLine[6],$responseLine[7]);
//			}
			fputcsv($outputFile,$responseLine);
			if($getDestination){
				fputcsv($outputFile2,array($getDestination,implode(',',$losing)));
				fputcsv($outputFile2,$location);

			}
			if($losing || $winning){
				$output->writeln([$destination.' - losing:'. count($losing) .' - winning:'. count($winning) ]);
			}

		}
		return 0;
	}
}
