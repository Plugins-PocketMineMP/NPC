<?php
declare(strict_types=1);
namespace alvin0319\NPC\entity;

use alvin0319\NPC\NPCPlugin;
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\SkinAdapterSingleton;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\UUID;

class NPCHuman extends EntityBase{

	public const NETWORK_ID = 0;

	public $width = 0.6;
	public $height = 1.8;
	public $eyeHeight = 1.62;

	protected $isCustomSkin = false;

	/** @var UUID */
	protected $uuid;

	/** @var Skin */
	protected $skin;

	/** @var Item */
	protected $item;

	/** @var Item */
	protected $offHandItem;

	public function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->uuid = UUID::fromRandom();

		$skinTag = $nbt->getCompoundTag("Skin");

		if($skinTag === null){
			throw new \InvalidStateException((new \ReflectionClass($this))->getShortName() . " must have a valid skin set");
		}

		$this->skin = new Skin(
			$skinTag->getString("Name"),
			$skinTag->hasTag("Data", StringTag::class) ? $skinTag->getString("Data") : $skinTag->getByteArray("Data"),
			$skinTag->getByteArray("CapeData", ""),
			$skinTag->getString("GeometryName", ""),
			$skinTag->getByteArray("GeometryData", "")
		);

		$this->isCustomSkin = $nbt->getByte("isCustomSkin", 0) === 1 ? true : false;

		if($nbt->hasTag("width", FloatTag::class) && $nbt->hasTag("height", FloatTag::class)){
			$this->width = $nbt->getFloat("width");
			$this->height = $nbt->getFloat("height");
		}

		$this->scale = $nbt->getFloat("scale", 1.0);

		if($nbt->hasTag("item", CompoundTag::class)){
			$this->item = Item::nbtDeserialize($nbt->getCompoundTag("item"));
		}else{
			$this->item = ItemFactory::get(0);
		}
		if($nbt->hasTag("offHand", CompoundTag::class)){
			$this->offHandItem = Item::nbtDeserialize($nbt->getCompoundTag("offHand"));
		}else{
			$this->offHandItem = ItemFactory::get(0);
		}
	}

	public function getName() : string{
		return (new \ReflectionClass($this))->getShortName();
	}

	public function spawnTo(Player $player) : void{
		if(in_array($player, $this->hasSpawned, true)){
			return;
		}

		$pk = new PlayerListPacket();
		$pk->entries = [PlayerListEntry::createAdditionEntry($this->uuid, $this->id, $this->getName(), SkinAdapterSingleton::get()->toSkinData($this->skin))];
		$pk->type = PlayerListPacket::TYPE_ADD;
		$player->sendDataPacket($pk);

		$pk = new AddPlayerPacket();
		$pk->uuid = $this->uuid;
		$pk->username = $this->getRealName();
		$pk->entityRuntimeId = $this->getId();
		$pk->position = $this->location->asVector3();
		$pk->motion = null;
		$pk->yaw = $this->location->yaw;
		$pk->pitch = $this->location->pitch;
		$pk->item = $this->item;
		$pk->metadata = $this->getSyncedNetworkData(false);
		$player->sendDataPacket($pk);

		$this->sendData($player, [Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $this->getRealName()]]);

		$pk = new PlayerListPacket();
		$pk->entries = [PlayerListEntry::createRemovalEntry($this->uuid)];
		$pk->type = PlayerListPacket::TYPE_REMOVE;
		$player->sendDataPacket($pk);

		$this->hasSpawned[] = $player;

		if($this->offHandItem->getId() !== 0){
			$pk = new MobEquipmentPacket();
			$pk->windowId = ContainerIds::OFFHAND;
			$pk->entityRuntimeId = $this->getId();
			$pk->inventorySlot = $pk->hotbarSlot = 0;
			$pk->item = $this->offHandItem;
			$this->server->broadcastPacket($this->hasSpawned, $pk);
		}
	}

	public function despawnTo(Player $player) : void{
		parent::despawnTo($player);
		$pk = new PlayerListPacket();
		$pk->entries = [PlayerListEntry::createAdditionEntry($this->uuid, $this->id, $this->getName(), SkinAdapterSingleton::get()->toSkinData($this->skin))];
		$pk->type = PlayerListPacket::TYPE_REMOVE;
		$player->sendDataPacket($pk);
	}

	public static function nbtDeserialize(CompoundTag $nbt) : NPCHuman{
		[$x, $y, $z, $world] = explode(":", $nbt->getString("pos"));
		return new NPCHuman(
			new Location((float) $x, (float) $y, (float) $z, 0.0, 0.0, Server::getInstance()->getLevelByName($world)),
			$nbt
		);
	}

	public function nbtSerialize() : CompoundTag{
		$nbt = parent::nbtSerialize();
		$nbt->setInt("type", self::NETWORK_ID);

		$nbt->setString("command", $this->command);
		$nbt->setString("message", $this->message);

		$nbt->setTag(NPCPlugin::getInstance()->getSkinCompound($this->skin));
		$nbt->setByte("isCustomSkin", $this->isCustomSkin ? 1 : 0);
		$nbt->setTag($this->item->nbtSerialize(-1, "item"));
		$nbt->setTag($this->offHandItem->nbtSerialize(-1, "offHand"));
		return $nbt;
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

		$pk = new MovePlayerPacket();
		$pk->position = $this->location->add(0, 1.62);
		$pk->yaw = $this->location->yaw;
		$pk->pitch = $this->location->pitch;
		$pk->entityRuntimeId = $this->id;
		$pk->headYaw = $this->location->yaw;

		foreach($this->getViewers() as $player){
			$player->sendDataPacket($pk);
		}
	}

	public function getItem() : Item{
		return $this->item;
	}

	public function setItem(?Item $item, bool $offHand = false){
		if($item === null){
			$item = ItemFactory::get(0);
		}

		if($offHand)
			$this->offHandItem = $item;
		else
			$this->item = $item;
	}
}