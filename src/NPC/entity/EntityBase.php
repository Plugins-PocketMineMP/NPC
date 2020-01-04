<?php
declare(strict_types=1);
namespace NPC\entity;

use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use pocketmine\player\Player;
use pocketmine\Server;

abstract class EntityBase{

	public const NETWORK_ID = -1;

	/** @var string */
	protected $name;

	/** @var string */
	protected $command;

	/** @var string */
	protected $message;

	/** @var Location */
	protected $location;

	/** @var int */
	protected $id;

	/** @var float */
	protected $scale = 1.0;

	/** @var Server */
	protected $server;

	/** @var Player[] */
	protected $hasSpawned = [];

	/** @var EntityMetadataCollection */
	protected $networkProperties;

	/**
	 * EntityBase constructor.
	 * @param Location $location
	 * @param CompoundTag $nbt
	 */
	public function __construct(Location $location, CompoundTag $nbt){
		$this->location = $location;
		$this->initEntity($nbt);
		$this->id = EntityFactory::nextRuntimeId();
		$this->networkProperties = new EntityMetadataCollection();
		$this->server = Server::getInstance();
	}

	/**
	 * @param CompoundTag $nbt
	 */
	public function initEntity(CompoundTag $nbt) : void{
		if($nbt->hasTag("name", StringTag::class)){
			$this->name = $nbt->getString("name");
		}

		$this->command = $nbt->getString("command", "");
		$this->message = $nbt->getString("message", "");
	}

	public function getRealName() : string{
		return $this->name;
	}

	/**
	 * @param Vector3 $target
	 * @see Living::lookAt()
	 */
	public function lookAt(Vector3 $target){
		$horizontal = sqrt(($target->x - $this->location->x) ** 2 + ($target->z - $this->location->z) ** 2);
		$vertical = $target->y - $this->location->y;
		$this->location->pitch = -atan2($vertical, $horizontal) / M_PI * 180; //negative is up, positive is down

		$xDist = $target->x - $this->location->x;
		$zDist = $target->z - $this->location->z;
		$this->location->yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
		if($this->location->yaw < 0){
			$this->location->yaw += 360.0;
		}

		$pk = new MoveActorAbsolutePacket();
		$pk->xRot = $this->location->pitch;
		$pk->yRot = $this->location->yaw;
		$pk->zRot = $this->location->yaw;
		$pk->position = $this->location;
		$pk->entityRuntimeId = $this->id;

		foreach($this->getViewers() as $player){
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	/**
	 * @return Player|null
	 */
	public function getClosestPlayer() : ?Player{
		$arr = [];

		foreach($this->location->getWorld()->getPlayers() as $player){
			$arr[(int) floor($this->location->distance($player->getPosition()))] = $player;
		}

		arsort($arr);

		for($i = 0; $i <= 7; $i++){
			if(isset($arr[$i])){
				return $arr[$i];
			}
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getMessage() : string{
		return $this->message;
	}

	/**
	 * @return string
	 */
	public function getCommand() : string{
		return $this->command;
	}

	/**
	 * @param string $message
	 */
	public function setMessage(string $message){
		$this->message = $message;
	}

	/**
	 * @param string $command
	 */
	public function setCommand(string $command){
		$this->command = $command;
	}

	/**
	 * @return Location
	 */
	public function getLocation() : Location{
		return $this->location;
	}

	/**
	 * @return int
	 */
	public function getId() : int{
		return $this->id;
	}

	/**
	 * @return Server
	 */
	public function getServer() : Server{
		return $this->server;
	}

	/**
	 * @param $player
	 * @param array|null $data
	 * @see Entity::sendData()
	 */
	public function sendData($player, ?array $data = null) : void{
		if(!is_array($player)){
			$player = [$player];
		}

		$pk = SetActorDataPacket::create($this->getId(), $data ?? $this->getSyncedNetworkData(false));

		foreach($player as $p){
			if($p === $this){
				continue;
			}
			$p->getNetworkSession()->sendDataPacket(clone $pk);
		}

		if($this instanceof Player){
			$this->getNetworkSession()->sendDataPacket($pk);
		}
	}

	/**
	 * @see Entity::syncNetworkData()
	 */
	protected function syncNetworkData() : void{
		$this->networkProperties->setByte(EntityMetadataProperties::ALWAYS_SHOW_NAMETAG,1);
		$this->networkProperties->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, $this->height);
		$this->networkProperties->setFloat(EntityMetadataProperties::BOUNDING_BOX_WIDTH, $this->width);
		$this->networkProperties->setFloat(EntityMetadataProperties::SCALE, $this->scale);
		$this->networkProperties->setLong(EntityMetadataProperties::LEAD_HOLDER_EID, -1);
		$this->networkProperties->setLong(EntityMetadataProperties::OWNER_EID, $this->ownerId ?? -1);
		$this->networkProperties->setLong(EntityMetadataProperties::TARGET_EID, $this->targetId ?? 0);
		$this->networkProperties->setString(EntityMetadataProperties::NAMETAG, $this->name);

		$this->networkProperties->setGenericFlag(EntityMetadataFlags::AFFECTED_BY_GRAVITY, true);
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::CAN_SHOW_NAMETAG, true);
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::HAS_COLLISION, true);
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::IMMOBILE, false);
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::INVISIBLE, false);
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::ONFIRE, false);
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::SNEAKING, false);
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::WALLCLIMBING, false);
	}

	/**
	 * @param bool $dirtyOnly
	 *
	 * @return MetadataProperty[]
	 */
	final protected function getSyncedNetworkData(bool $dirtyOnly) : array{
		$this->syncNetworkData();

		return $dirtyOnly ? $this->networkProperties->getDirty() : $this->networkProperties->getAll();
	}

	abstract public static function nbtDeserialize(CompoundTag $nbt);

	public function nbtSerialize() : CompoundTag{
		$nbt = CompoundTag::create();
		$nbt->setString("name", $this->name);
		$nbt->setString("message", $this->message);
		$nbt->setString("command", $this->command);
		$nbt->setString("pos", implode(":", [$this->location->x, $this->location->y, $this->location->z, $this->location->world->getFolderName()]));
		return $nbt;
	}

	public function spawnTo(Player $player) : void{
		if(in_array($player, $this->hasSpawned, true)){
			return;
		}
		$pk = new AddActorPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->type = static::NETWORK_ID;
		$pk->position = $this->location->asVector3();
		$pk->motion = null;
		$pk->yaw = $this->location->yaw;
		$pk->headYaw = $this->location->yaw;
		$pk->pitch = $this->location->pitch;
		$pk->metadata = $this->getSyncedNetworkData(false);

		$player->getNetworkSession()->sendDataPacket($pk);
		$this->hasSpawned[] = $player;
	}

	public function despawnTo(Player $player) : void{
		if(!in_array($player, $this->hasSpawned, true)){
			return;
		}
		$player->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($this->id));
		$key = array_search($player, $this->hasSpawned, true);
		if($key !== false){
			unset($this->hasSpawned[$key]);
		}
	}

	/**
	 * @return Player[]
	 */
	public function getViewers() : array{
		return $this->hasSpawned;
	}
}