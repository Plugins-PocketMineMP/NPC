<?php
declare(strict_types=1);

namespace alvin0319\NPC\util;

use pocketmine\entity\Skin;
use pocketmine\network\mcpe\protocol\types\SkinAdapterSingleton;
use pocketmine\network\mcpe\protocol\types\SkinData;
use pocketmine\Player;

class Image{

	protected $skinBytes;

	protected $geometryName = "";

	protected $geometryData = "";

	public function __construct(string $skinBytes, string $geometryName, string $geometryData){
		$this->skinBytes = $skinBytes;
		$this->geometryName = $geometryName;
		$this->geometryData = $geometryData;
	}

	public function getSkinBytes() : string{
		return $this->skinBytes;
	}

	public function getGeometryName() : string{
		return $this->geometryName;
	}

	public function getGeometryData() : string{
		return $this->geometryData;
	}

	public function toSkin(Player $player) : Skin{
		return new Skin($player->getSkin()->getSkinId(), $this->skinBytes, '', $this->geometryName, $this->geometryData);
	}

	public function toSkinData(Player $player) : SkinData{
		$skin = new Skin($player->getSkin()->getSkinId(), $this->skinBytes, '', $this->geometryName, $this->geometryData);

		return SkinAdapterSingleton::get()->toSkinData($skin);
	}
}