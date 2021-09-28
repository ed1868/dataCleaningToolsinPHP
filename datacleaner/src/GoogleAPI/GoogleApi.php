<?php
/**
 * Created by PhpStorm.
 * User: Ronald
 * Date: 8/26/2019
 * Time: 2:28 PM
 */

namespace App\GoogleAPI;

use Doctrine\ORM\EntityManagerInterface;
use Unirest;

class GoogleApi
{
	private $url = 'https://maps.googleapis.com/maps/api';
	private $apiKey = '';

	/**
	 * @var array
	 */
	private $defaultHeaders;
	/**
	 * @var array
	 */
	private $error;
	/**
	 * @var array
	 */
	private $goodResponseCode = array('200', '201', '204');
	private $em;


	public function __construct($apiKey,EntityManagerInterface $em)
	{
		$this->defaultHeaders = array();
		$this->em = $em;
		$this->apiKey = $apiKey;
	}


	public function getLocation($location,$getDestination = false)
	{
		$conn = $this->em->getConnection();
		$endpoint = "place/findplacefromtext/json?input=" . $location . "&inputtype=textquery&fields=name,formatted_address,rating,geometry,types,place_id&key=" . $this->apiKey;
		if (!$result = $this->getRequest($endpoint)) {
			return false;
		}


		if (!array_key_exists('status', $result) || $result['status'] != 'OK' || !array_key_exists('candidates', $result) ) {
			return false;
		}
		$sql = 'SELECT * FROM google_destinations WHERE place_id="'.$result['candidates'][0]['place_id'].'"';
		$stmt = $conn->prepare($sql);
		$stmt->execute();
		if($overwriteDestination = $stmt->fetch()){
//			dd($overwriteDestination,$result['candidates'][0]['geometry']['viewport']);
			return [
				'north' => $overwriteDestination['north'],
				'east'  => $overwriteDestination['east'],
				'south' => $overwriteDestination['south'],
				'west'  => $overwriteDestination['west'],
				'place_id'  => $result['candidates'][0]['place_id']
			];
		}
//dd($result);

		$location = array(
			'latitude' => $result['candidates'][0]['geometry']['location']['lat'],
			'longitude' => $result['candidates'][0]['geometry']['location']['lng']
		);
		return $location;

		$useDefault = true;
		if(array_key_exists('viewport',$result['candidates'][0]['geometry'])
		) {
			$viewport = $result['candidates'][0]['geometry']['viewport'];
			$locationDetail = array(
				'north' => $viewport['northeast']['lat'],
				'east' => $viewport['northeast']['lng'],
				'south' => $viewport['southwest']['lat'],
				'west' => $viewport['southwest']['lng'],
				'place_id'  => $result['candidates'][0]['place_id']
			);
			if(!count(array_intersect(array('route','street_address'),$result['candidates'][0]['types'])) ){
				$useDefault = false;
			}

		}
		if($useDefault){
			$radium = 3;
			$latCos   = abs(cos($location['latitude']));
			$locationDetail = [
				'north' => $location['latitude'] + ($radium / 69),
				'south' => $location['latitude'] - ($radium / 69),
				'east'  => $location['longitude'] + ($radium * $latCos / 69),
				'west'  => $location['longitude'] - ($radium * $latCos / 69),
				'place_id'  => $result['candidates'][0]['place_id']
			];
		}

//		dd($location,$result,$locationDetail);

		if($getDestination){
			$sql = 'INSERT INTO google_destinations (place_id,name,north,east,south,west) VALUES ("'.$locationDetail['place_id'].'","'.$result['candidates'][0]['name'].'","'.$locationDetail['north'].'","'.$locationDetail['east'].'","'.$locationDetail['south'].'","'.$locationDetail['west'].'")';
			$stmt = $conn->prepare($sql);
			$stmt->execute();
		}
		return $locationDetail;
	}


// API Generic methods


	private function getRequest($endpoint, $body = array(), $header = array(), $toArray = true, $toBody = true)
	{

		return $this->request("get", $endpoint, $body, $header, $toArray, $toBody);
	}

	private function request($method, $endpoint, $body, $header, $toArray, $toBody)
	{
		$header = array_merge($header, $this->defaultHeaders);
		$url = $this->url . "/" . $endpoint;
//        var_dump($header);
//        var_dump($url);
		Unirest\Request::verifyPeer(false);
		$response = Unirest\Request::$method($url, $header, $body);
		if ($toArray) {
			$response = json_decode(json_encode($response), true);
			if (array_key_exists('code', $response))
				if (in_array($response['code'], $this->goodResponseCode) && $toBody)
					return $response['body'];
		}
//        var_dump($response);
		return $response;
	}

	/**
	 * @return string
	 */
	public function getUrl()
	{
		return $this->url;
	}

	/**
	 * @param string $url
	 * @return CoreApi
	 */
	public function setUrl($url)
	{
		$this->url = $url;
		return $this;
	}


	/**
	 * @return array
	 */
	public function getError()
	{
		return $this->error;
	}

	/**
	 * @param $msg
	 * @param $code
	 * @param $errorCode
	 * @return CoreApi
	 * @internal param array $error
	 */
	public function setError($msg = 'Something is not Right ', $code = 'no error code', $errorCode = 'X', $fullErrorResponse = null)
	{
		$this->error['msg'] = $msg;
		$this->error['errorCode'] = $errorCode;
		$this->error['code'] = $code;
		$this->error['fullErrorResponse'] = $fullErrorResponse;
		return $this;
	}
}