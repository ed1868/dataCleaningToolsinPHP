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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class HotelLatLongCommand
 * @package App\Command
 * @property EntityManagerInterface $em
 */
final class UploadHotelLatLongCommand extends Command
{
	private $em;


	// the name of the command (the part after "bin/console")
	protected static $defaultName = 'update:hotel:lat-long';

	/**
	 * HotelLatLongCommand constructor.
	 * @param EntityManagerInterface $em
	 */
	public function __construct(EntityManagerInterface $em)
	{
		$this->em = $em;
		parent::__construct();
	}

	protected function configure()
	{
		// ...
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$conn = $this->em->getConnection();
		$inputFileName = 'src/Command/csvInput/hotelLatLong.csv';
		$output->writeln([
			'Starting Upload to Hotel from csv',
			'============',
			'',
		]);
		//Fetch hotels to know if we already upload this hotel
		$sql = 'SELECT hotel_id,hotel_lat,hotel_lon FROM hotels ';
		$stmt = $conn->prepare($sql);
		$stmt->execute();
		$dataBaseHotels = $stmt->fetchAll();

		$hotels = array();
		foreach ($dataBaseHotels as $hotel) {//order by ID to make it easy after
			$hotels[$hotel['hotel_id']] = $hotel;
		}


		try {
			//open input csv
			$inputFile = fopen($inputFileName, 'r');
		} catch (\Exception $e) {
			//validate
			if (!isset($inputFile)) {
				$output->writeln(['Input File is missing']);
				exit;
			}
		}
		//loop per input line
		for ($c = 0; ($line = fgetcsv($inputFile)) !== FALSE; $c++) {

			$hotelId = $line[0];
			$hotelLatitude = $line[1];
			$hotelLongitude = $line[2];

			if (!$hotelId || !array_key_exists($hotelId, $hotels)) {
				$output->writeln([$hotelId . ' - Hotel id not found in database']);
				continue;

			}
			if (!$hotels[$hotelId]['hotel_lat'] || !$hotels[$hotelId]['hotel_lon']) {
				$stmt = $conn->prepare('UPDATE hotels SET hotel_lat="' . $hotelLatitude . '",hotel_lon="' . $hotelLongitude . '" WHERE hotel_id=' . $hotelId);
				$stmt->execute();
				$output->writeln([$hotelId . ' - LAT/LONG upload to database']);
				continue;

			}
			$output->writeln([$hotelId . ' - LAT/LONG already in database']);

		}
		return 0;
	}
}
