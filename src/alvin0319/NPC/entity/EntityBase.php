<?php
declare(strict_types=1);
namespace alvin0319\NPC\entity;

use pocketmine\entity\DataPropertyManager;
use pocketmine\entity\Entity;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\Player;
use pocketmine\Server;
use function array_search;
use function atan2;
use function implode;
use function in_array;
use function is_array;
use function sqrt;
use function trim;
use const M_PI;

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

	/** @var DataPropertyManager */
	protected $networkProperties;

	/**
	 * EntityBase constructor.
	 * @param Location $location
	 * @param CompoundTag $nbt
	 */
	public function __construct(Location $location, CompoundTag $nbt){
		$this->location = $location;
		$this->initEntity($nbt);
		$this->id = Entity::$entityCount++;
		$this->networkProperties = new DataPropertyManager();
		$this->server = Server::getInstance();
	}

	/**
	 * @param CompoundTag $nbt
	 */
	public function initEntity(CompoundTag $nbt) : void{
		if($nbt->hasTag("name", StringTag::class)){
			$this->name = $nbt->getString("name");
		}

		if($nbt->hasTag("scale", FloatTag::class)){
			$this->scale = $nbt->getFloat("scale");
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
			$player->sendDataPacket($pk);
		}
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

		$pk = new SetActorDataPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->metadata = $data ?? $this->getSyncedNetworkData(false);

		foreach($player as $p){
			if($p === $this){
				continue;
			}
			$p->sendDataPacket(clone $pk);
		}
	}

	/**
	 * @see Entity::syncNetworkData()
	 */
	protected function syncNetworkData() : void{
		$this->networkProperties->setByte(Entity::DATA_ALWAYS_SHOW_NAMETAG,1);
		$this->networkProperties->setFloat(Entity::DATA_BOUNDING_BOX_HEIGHT, $this->height);
		$this->networkProperties->setFloat(Entity::DATA_BOUNDING_BOX_WIDTH, $this->width);
		$this->networkProperties->setFloat(Entity::DATA_SCALE, $this->scale);
		$this->networkProperties->setLong(Entity::DATA_LEAD_HOLDER_EID, -1);
		$this->networkProperties->setLong(Entity::DATA_OWNER_EID, -1);
		$this->networkProperties->setLong(Entity::DATA_TARGET_EID, 0);
		$this->networkProperties->setString(Entity::DATA_NAMETAG, $this->name);

		$this->setGenericFlag(Entity::DATA_FLAG_AFFECTED_BY_GRAVITY, true);
		$this->setGenericFlag(Entity::DATA_FLAG_CAN_SHOW_NAMETAG, true);
		$this->setGenericFlag(Entity::DATA_FLAG_HAS_COLLISION, true);
		$this->setGenericFlag(Entity::DATA_FLAG_IMMOBILE, false);
		$this->setGenericFlag(Entity::DATA_FLAG_INVISIBLE, false);
		$this->setGenericFlag(Entity::DATA_FLAG_ONFIRE, false);
		$this->setGenericFlag(Entity::DATA_FLAG_SNEAKING, false);
		$this->setGenericFlag(Entity::DATA_FLAG_WALLCLIMBING, false);
		$this->setGenericFlag(Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG, true);
	}

	/**
	 * @param bool $dirtyOnly
	 *
	 * @return array
	 */
	final protected function getSyncedNetworkData(bool $dirtyOnly) : array{
		$this->syncNetworkData();

		return $dirtyOnly ? $this->networkProperties->getDirty() : $this->networkProperties->getAll();
	}

	abstract public static function nbtDeserialize(CompoundTag $nbt);

	public function nbtSerialize() : CompoundTag{
		$nbt = new CompoundTag();
		$nbt->setString("name", $this->name);
		$nbt->setString("message", $this->message);
		$nbt->setString("command", $this->command);
		$nbt->setString("pos", implode(":", [$this->location->x, $this->location->y, $this->location->z, $this->location->level->getFolderName()]));
		$nbt->setFloat("scale", $this->scale);
		$nbt->setFloat("width", $this->width);
		$nbt->setFloat("height", $this->height);
		return $nbt;
	}

	public function spawnTo(Player $player) : void{
		if(in_array($player, $this->hasSpawned, true)){
			return;
		}
		$pk = new AddActorPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->type = AddActorPacket::LEGACY_ID_MAP_BC[static::NETWORK_ID];
		$pk->position = $this->location->asVector3();
		$pk->motion = null;
		$pk->yaw = $this->location->yaw;
		$pk->headYaw = $this->location->yaw;
		$pk->pitch = $this->location->pitch;
		$pk->metadata = $this->getSyncedNetworkData(false);

		$player->sendDataPacket($pk);
		$this->hasSpawned[] = $player;
	}

	public function despawnTo(Player $player) : void{
		if(!in_array($player, $this->hasSpawned, true)){
			return;
		}

		$pk = new RemoveActorPacket();
		$pk->entityUniqueId = $this->id;
		$player->sendDataPacket($pk);

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

	public function setScale(float $scale){
		$this->scale = $scale;

		$multiplier = $scale / $this->scale;

		$this->width *= $multiplier;
		$this->height *= $multiplier;
	}

	/**
	 * @param int  $propertyId
	 * @param int  $flagId
	 * @param bool $value
	 * @param int  $propertyType
	 */
	public function setDataFlag(int $propertyId, int $flagId, bool $value = true, int $propertyType = Entity::DATA_TYPE_LONG) : void{
		if($this->getDataFlag($propertyId, $flagId) !== $value){
			$flags = (int) $this->networkProperties->getPropertyValue($propertyId, $propertyType);
			$flags ^= 1 << $flagId;
			$this->networkProperties->setPropertyValue($propertyId, $propertyType, $flags);
		}
	}

	/**
	 * @param int $propertyId
	 * @param int $flagId
	 *
	 * @return bool
	 */
	public function getDataFlag(int $propertyId, int $flagId) : bool{
		return (((int) $this->networkProperties->getPropertyValue($propertyId, -1)) & (1 << $flagId)) > 0;
	}

	/**
	 * Wrapper around {@link Entity#getDataFlag} for generic data flag reading.
	 *
	 * @param int $flagId
	 *
	 * @return bool
	 */
	public function getGenericFlag(int $flagId) : bool{
		return $this->getDataFlag($flagId >= 64 ? Entity::DATA_FLAGS2 : Entity::DATA_FLAGS, $flagId % 64);
	}

	/**
	 * Wrapper around {@link Entity#setDataFlag} for generic data flag setting.
	 *
	 * @param int  $flagId
	 * @param bool $value
	 */
	public function setGenericFlag(int $flagId, bool $value = true) : void{
		$this->setDataFlag($flagId >= 64 ? Entity::DATA_FLAGS2 : Entity::DATA_FLAGS, $flagId % 64, $value, Entity::DATA_TYPE_LONG);
	}

	public function interact(Player $player){
		if(trim($this->getMessage()) !== ""){
			$player->sendMessage($this->getMessage());
		}

		if(trim($this->getCommand()) !== ""){
			$player->getServer()->dispatchCommand($player, $this->getCommand());
		}
	}
}