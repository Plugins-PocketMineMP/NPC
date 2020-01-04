<?php
declare(strict_types=1);
namespace NPC\entity;

use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityLegacyIds;
use pocketmine\Server;

class ChickenEntity extends EntityBase{

	public const NETWORK_ID = EntityLegacyIds::CHICKEN;

	public $width = 1;
	public $height = 0.8;
	public $eyeHeight = 0.6;

	public function getName() : string{
		return (new \ReflectionClass($this))->getShortName();
	}

	public static function nbtDeserialize(CompoundTag $nbt) : ChickenEntity{
		[$x, $y, $z, $world] = explode(":", $nbt->getString("pos"));
		return new ChickenEntity(
			new Location((float) $x, (float) $y, (float) $z, 0.0, 0.0, Server::getInstance()->getWorldManager()->getWorldByName($world)),
			$nbt
		);
	}

	public function nbtSerialize() : CompoundTag{
		$nbt = parent::nbtSerialize();
		$nbt->setInt("type", self::NETWORK_ID);
		return $nbt;
	}
}