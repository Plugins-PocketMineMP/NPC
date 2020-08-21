<?php
declare(strict_types=1);

namespace alvin0319\NPC\config;

use alvin0319\NPC\NPCPlugin;
use alvin0319\NPC\util\Image;
use pocketmine\utils\Config;

/**
 * Class ImageConfig
 *
 * @package alvin0319\NPC\config
 * @deprecated
 */
class ImageConfig{

	/** @var Image[] */
	protected $images = [];

	protected $plugin;

	public function __construct(NPCPlugin $plugin){
		$this->plugin = $plugin;

		$data = (new Config($plugin->getDataFolder() . "ImageData.yml", Config::YAML))->getAll();

		foreach($data as $name => $datum){
			$image = new Image($datum["skinBytes"], $datum["geometryName"], $datum["geometryData"]);
			$this->images[$name] = $image;
		}
	}

	/**
	 * @param string $key
	 *
	 * @return Image|null
	 */
	public function getImageData(string $key) : ?Image{
		return $this->images[$key] ?? null;
	}

	/**
	 * @return Image[]
	 */
	public function getAllImages() : array{
		return $this->images;
	}

	public function addImage(string $name, string $skinBytes, string $geometryName, string $geometryData){
		$this->images[$name] = new Image($skinBytes, $geometryName, $geometryData);
	}

	public function removeImage(string $name){
		unset($this->images[$name]);
	}

	public function save(){
		$config = new Config($this->plugin->getDataFolder() . "ImageData.yml", Config::YAML);
		$arr = [];

		foreach($this->images as $name => $image){
			$arr[$name] = [
				"skinBytes" => $image->getSkinBytes(),
				"geometryName" => $image->getGeometryName(),
				"geometryData" => $image->getGeometryData()
			];
		}

		$config->setAll($arr);
		$config->save();
	}
}