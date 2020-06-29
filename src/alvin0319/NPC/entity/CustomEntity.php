<?php
declare(strict_types=1);
namespace alvin0319\NPC\entity;

use alvin0319\NPC\config\EntityConfig;
use pocketmine\entity\Entity;
use pocketmine\level\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\Player;
use pocketmine\Server;
use function explode;
use function in_array;

class CustomEntity extends EntityBase{

	public const NETWORK_ID = 0xf;

	/** @var int */
	protected $id;

	protected $width;

	protected $height;

	/**
	 * CustomEntity constructor.
	 * @param int $networkId
	 * @param Location $location
	 * @param CompoundTag $nbt
	 */
	public function __construct(int $networkId, Location $location, CompoundTag $nbt){
		parent::__construct($location, $nbt);
		$this->id = $networkId;

		$this->width = EntityConfig::WIDTHS[$this->id];
		$this->height = EntityConfig::HEIGHTS[$this->id];

		if($nbt->hasTag("width", FloatTag::class) && $nbt->hasTag("height", FloatTag::class)){
			$this->width = $nbt->getFloat("width");
			$this->height = $nbt->getFloat("height");
		}

		$this->scale = $nbt->getFloat("scale", 1.0);
	}

	public function getNetworkId() : int{
		return $this->id;
	}

	public function spawnTo(Player $player) : void{
		if(in_array($player, $this->hasSpawned, true)){
			return;
		}
		$pk = new AddActorPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->type = AddActorPacket::LEGACY_ID_MAP_BC[$this->id];
		$pk->position = $this->location->asVector3();
		$pk->motion = null;
		$pk->yaw = $this->location->yaw;
		$pk->headYaw = $this->location->yaw;
		$pk->pitch = $this->location->pitch;
		$pk->metadata = $data = $this->getSyncedNetworkData(false);

		$player->sendDataPacket($pk);
		$this->hasSpawned[] = $player;
	}

	public static function nbtDeserialize(CompoundTag $nbt){
		[$x, $y, $z, $world] = explode(":", $nbt->getString("pos"));
		return new CustomEntity(
			$nbt->getInt("networkId"),
			new Location((float) $x, (float) $y, (float) $z, 0.0, 0.0, Server::getInstance()->getLevelByName($world)),
			$nbt
		);
	}

	public function nbtSerialize() : CompoundTag{
		$nbt = parent::nbtSerialize();
		$nbt->setInt("networkId", $this->id);
		$nbt->setInt("type", self::NETWORK_ID);
		return $nbt;
	}
}